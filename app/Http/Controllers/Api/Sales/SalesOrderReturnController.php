<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Resources\Sales\SalesOrderResource;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\TransactionStatus;
use App\Models\TransactionType;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOrderReturnController extends Controller
{
    /**
     * Post a Sales Return (RMA).
     *
     * Logic:
     * 1. Validate returns don't exceed shipped qty.
     * 2. Create SRET transaction (Receipt) to increase stock.
     * 3. Update Sales Order Line (returned_qty++, shipped_qty--).
     * 4. Automatically generate a Draft Credit Note.
     */
    public function store(Request $request, SalesOrder $salesOrder, StockService $stockService): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'lines' => 'required|array',
            'lines.*.so_line_id' => 'required|exists:sales_order_lines,id',
            'lines.*.returned_qty' => 'required|numeric|min:0.0001',
            'lines.*.resolution' => 'required|in:replacement,refund',
            'lines.*.reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        try {
            $result = DB::transaction(function () use ($request, $salesOrder, $stockService) {
                // S-H1: Lock the SO header row first to prevent concurrent status/total conflicts.
                $so = SalesOrder::lockForUpdate()->findOrFail($salesOrder->id);

                $sretType = TransactionType::where('code', StockService::TYPE_SALES_RETURN)->firstOrFail();
                $postedStatus = TransactionStatus::where('name', 'posted')->firstOrFail();

                $transactionData = [
                    'header' => [
                        'transaction_type_id' => $sretType->id,
                        'transaction_status_id' => $postedStatus->id,
                        'transaction_date' => now()->toDateString(),
                        'reference_number' => 'RET-'.$so->so_number.'-'.substr(uniqid(), -4),
                        'customer_id' => $so->customer_id,
                        'sales_order_id' => $so->id,
                        'reference_doc' => $so->so_number,
                        'notes' => 'Return for SO: '.$so->so_number.'. '.($request->notes ?? ''),
                        'created_by' => $request->user()->id,
                        'return_reason' => $request->lines[0]['reason'] ?? null, // Primary reason
                    ],
                    'lines' => [],
                ];

                $soLines = $so->lines()->lockForUpdate()->get()->keyBy('id');
                $creditNoteLines = [];

                foreach ($request->lines as $item) {
                    /** @var SalesOrderLine $soLine */
                    $soLine = $soLines->get($item['so_line_id']);
                    $returnedQty = (float) $item['returned_qty'];

                    if ($returnedQty > ($soLine->shipped_qty - $soLine->returned_qty + 0.00000001)) {
                        abort(422, "Cannot return more than what was shipped for product: {$soLine->product->name}");
                    }

                    // Add to inventory transaction (Receipt)
                    // We use the location specified by the user (e.g. a Quarantine or Returns bin)
                    // instead of the original picking bin.
                    $transactionData['lines'][] = [
                        'product_id' => $soLine->product_id,
                        'location_id' => $request->location_id,
                        'quantity' => abs($returnedQty), // Receipt is positive
                        'uom_id' => $soLine->uom_id,
                    ];

                    // Update SO Line pipeline quantities. The item physically returned,
                    // so it must be picked/packed/shipped again if it's a replacement.
                    $soLine->returned_qty += $returnedQty;
                    $soLine->shipped_qty -= $returnedQty;
                    $soLine->packed_qty = max(0, $soLine->packed_qty - $returnedQty);
                    $soLine->picked_qty = max(0, $soLine->picked_qty - $returnedQty);

                    // If refund, we cancel this portion of the order and refund them
                    if ($item['resolution'] === 'refund') {
                        $soLine->ordered_qty = max(0, (float) $soLine->ordered_qty - $returnedQty);

                        // Recalculate line totals
                        $qty = (float) $soLine->ordered_qty;
                        $price = (float) $soLine->unit_price;
                        $taxRate = (float) ($soLine->tax_rate ?? 0);
                        $discountRate = (float) ($soLine->discount_rate ?? 0);

                        $base = $qty * $price;
                        $discount = $base * ($discountRate / 100);
                        $taxable = $base - $discount;
                        $tax = $taxable * ($taxRate / 100);

                        $soLine->discount_amount = round($discount, 8);
                        $soLine->tax_amount = round($tax, 8);
                        $soLine->subtotal = round($taxable + $tax, 8);

                        // Prepare Credit Note Line for refunds
                        $creditNoteLines[] = [
                            'product_id' => $soLine->product_id,
                            'sales_order_line_id' => $soLine->id,
                            'quantity' => round($returnedQty, 8),
                            'unit_price' => round((float) $soLine->unit_price, 8),
                            'subtotal' => round($returnedQty * (float) $soLine->unit_price, 8),
                        ];
                    }

                    $soLine->notes = trim(($soLine->notes ?? '').' | Return Reason: '.($item['reason'] ?? 'N/A').' ('.$item['resolution'].')');
                    $soLine->save();
                }

                // S-M1: The SO total_amount is intentionally NOT recalculated here.
                // Once an SO is approved, its total_amount is immutable (like an original contract).
                // The generated Credit Note (below) solely represents the financial adjustment.
                // Mutating the SO total AND issuing a credit note would double-count the loss.

                // Record stock movement
                $transaction = $stockService->recordMovement($transactionData);

                // --- STATUS RECALCULATION ---
                // After modifying shipped_qty, the SO-level status must be
                // re-evaluated from the actual line quantities. This allows the
                // status to move backwards (e.g. SHIPPED → PARTIALLY_SHIPPED)
                // so that warehouse staff can continue fulfilling the remainder.
                $so->unsetRelation('lines'); // force a fresh load
                $so->recalculateStatus();

                // Automatically generate a Draft Credit Note if there are refunds
                $creditNote = null;
                if (! empty($creditNoteLines)) {
                    $creditNote = Invoice::create([
                        'invoice_number' => 'CN-'.now()->format('Ymd-Hi').'-'.rand(10, 99),
                        'customer_id' => $salesOrder->customer_id,
                        'sales_order_id' => $salesOrder->id,
                        'invoice_date' => now()->toDateString(),
                        'total_amount' => round(collect($creditNoteLines)->sum('subtotal'), 8),
                        'status' => Invoice::STATUS_DRAFT,
                        'type' => Invoice::TYPE_CREDIT_NOTE,
                        'notes' => 'Generated from Return: '.$transaction->reference_number,
                    ]);

                    foreach ($creditNoteLines as $lineData) {
                        $creditNote->lines()->create($lineData);
                    }
                }

                return [
                    'transaction' => $transaction,
                    'credit_note' => $creditNote,
                ];
            });

            $response = [
                'message' => 'Sales Return processed successfully.',
                'transaction_id' => $result['transaction']->id,
                'sales_order' => new SalesOrderResource(
                    $salesOrder->fresh(['lines.product.uom', 'lines.location', 'status'])
                ),
            ];

            if ($result['credit_note']) {
                $response['credit_note_id'] = $result['credit_note']->id;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
