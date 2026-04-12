<?php

namespace App\Http\Controllers\Api\Sales;

use App\Helpers\FinancialMath;
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
                    $returnedQty = (string) $item['returned_qty'];

                    $maxReturnable = FinancialMath::sub((string) $soLine->shipped_qty, (string) $soLine->returned_qty);
                    if (FinancialMath::gt($returnedQty, $maxReturnable)) {
                        abort(422, "Cannot return more than what was shipped for product: {$soLine->product->name}");
                    }

                    $transactionData['lines'][] = [
                        'product_id' => $soLine->product_id,
                        'location_id' => $request->location_id,
                        'quantity' => $returnedQty, // Receipt is positive string
                        'uom_id' => $soLine->uom_id,
                    ];

                    $soLine->returned_qty = FinancialMath::add((string) $soLine->returned_qty, $returnedQty);
                    $soLine->shipped_qty = FinancialMath::sub((string) $soLine->shipped_qty, $returnedQty);
                    $newPacked = FinancialMath::sub((string) $soLine->packed_qty, $returnedQty);
                    $soLine->packed_qty = FinancialMath::round(
                        FinancialMath::isNegative($newPacked) ? '0' : $newPacked,
                        FinancialMath::LINE_SCALE
                    );

                    $newPicked = FinancialMath::sub((string) $soLine->picked_qty, $returnedQty);
                    $soLine->picked_qty = FinancialMath::round(
                        FinancialMath::isNegative($newPicked) ? '0' : $newPicked,
                        FinancialMath::LINE_SCALE
                    );

                    if ($item['resolution'] === 'refund') {
                        $newOrdered = FinancialMath::sub((string) $soLine->ordered_qty, $returnedQty);
                        $soLine->ordered_qty = FinancialMath::round(
                            FinancialMath::isNegative($newOrdered) ? '0' : $newOrdered,
                            FinancialMath::LINE_SCALE
                        );

                        // Recalculate line totals using BCMath — no floats.
                        $soLine->discount_amount = FinancialMath::soLineDiscount(
                            $soLine->ordered_qty, $soLine->unit_price, $soLine->discount_rate ?? 0
                        );
                        $soLine->tax_amount = FinancialMath::soLineTax(
                            $soLine->ordered_qty, $soLine->unit_price, $soLine->discount_rate ?? 0, $soLine->tax_rate ?? 0
                        );
                        $soLine->subtotal = FinancialMath::soLineSubtotal(
                            $soLine->ordered_qty, $soLine->unit_price, $soLine->discount_rate ?? 0, $soLine->tax_rate ?? 0
                        );

                        // Credit Note line values (Capture tax/discount from original line)
                        $cnLineSubtotal = FinancialMath::soLineSubtotal(
                            $returnedQty,
                            (string) $soLine->unit_price,
                            (string) $soLine->discount_rate,
                            (string) $soLine->tax_rate
                        );

                        $creditNoteLines[] = [
                            'product_id' => $soLine->product_id,
                            'sales_order_line_id' => $soLine->id,
                            'quantity' => $returnedQty,
                            'unit_price' => (string) $soLine->unit_price,
                            'tax_rate' => $soLine->tax_rate,
                            'tax_amount' => FinancialMath::soLineTax($returnedQty, (string) $soLine->unit_price, (string) $soLine->discount_rate, (string) $soLine->tax_rate),
                            'discount_rate' => $soLine->discount_rate,
                            'discount_amount' => FinancialMath::soLineDiscount($returnedQty, (string) $soLine->unit_price, (string) $soLine->discount_rate),
                            'subtotal' => $cnLineSubtotal,
                        ];

                        $lineTotals[] = $cnLineSubtotal;
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
                        'total_amount' => FinancialMath::headerTotal($lineTotals),
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
