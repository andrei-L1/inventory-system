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
            'lines.*.uom_id' => 'nullable|exists:units_of_measure,id',
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
                $lineTotals = [];

                foreach ($request->lines as $item) {
                    /** @var SalesOrderLine $soLine */
                    $soLine = $soLines->get($item['so_line_id']);
                    $returnedQtyRaw = (string) $item['returned_qty'];
                    $returnUomId = $item['uom_id'] ?? $soLine->uom_id;
                    $lineUomId = $soLine->uom_id;

                    // Convert return qty to SO line UOM for validation and tracking
                    $qtyToUpdateSO = $returnedQtyRaw;
                    if ((int)$returnUomId !== (int)$lineUomId) {
                        $factor = $this->getUomConversionFactor($returnUomId, $lineUomId, $soLine->product_id);
                        $qtyToUpdateSO = FinancialMath::round(FinancialMath::mul($returnedQtyRaw, $factor), FinancialMath::LINE_SCALE);
                    }

                    // [Net-Aware Check]: Since shipped_qty is now a net counter (decremented on return),
                    // it represents the absolute maximum that can be returned from the warehouse.
                    $maxReturnable = (string) $soLine->shipped_qty;
                    if (FinancialMath::gt($qtyToUpdateSO, $maxReturnable)) {
                        $uomName = $soLine->uom->abbreviation ?? 'units';
                        abort(422, "Cannot return more than what is currently fulfilled for {$soLine->product->name}. Max available to return: {$maxReturnable} {$uomName}.");
                    }

                    $transactionData['lines'][] = [
                        'product_id' => $soLine->product_id,
                        'location_id' => $request->location_id,
                        'quantity' => $returnedQtyRaw, // Receipt is positive string in its provided UOM
                        'uom_id' => $returnUomId,
                    ];
 
                    // [INDUSTRY STANDARD]: We maintain cumulative historical values for returns
                    // but we MUST decrement fulfillment counters to allow "re-dispatch" of replacements.
                    $soLine->returned_qty = FinancialMath::add((string) $soLine->returned_qty, $qtyToUpdateSO);
                    
                    // Rebalance fulfillment counters
                    $soLine->shipped_qty = FinancialMath::max('0', FinancialMath::sub((string) $soLine->shipped_qty, $qtyToUpdateSO));
                    $soLine->packed_qty = FinancialMath::max('0', FinancialMath::sub((string) $soLine->packed_qty, $qtyToUpdateSO));
                    $soLine->picked_qty = FinancialMath::max('0', FinancialMath::sub((string) $soLine->picked_qty, $qtyToUpdateSO));
 
                    if ($item['resolution'] === 'refund') {
                        // Credit Note line values (Capture tax/discount from original line)
                        // Use base-unit cost (soLine->unit_price) and the scaled quantity (qtyToUpdateSO)
                        $cnLineSubtotal = FinancialMath::soLineSubtotal(
                            $qtyToUpdateSO,
                            (string) $soLine->unit_price,
                            (string) $soLine->discount_rate,
                            (string) $soLine->tax_rate
                        );

                        $creditNoteLines[] = [
                            'product_id' => $soLine->product_id,
                            'sales_order_line_id' => $soLine->id,
                            'quantity' => $qtyToUpdateSO, // We store credit in base-unit terms
                            'unit_price' => (string) $soLine->unit_price,
                            'tax_rate' => $soLine->tax_rate,
                            'tax_amount' => FinancialMath::soLineTax($qtyToUpdateSO, (string) $soLine->unit_price, (string) $soLine->discount_rate, (string) $soLine->tax_rate),
                            'discount_rate' => $soLine->discount_rate,
                            'discount_amount' => FinancialMath::soLineDiscount($qtyToUpdateSO, (string) $soLine->unit_price, (string) $soLine->discount_rate),
                            'subtotal' => $cnLineSubtotal,
                        ];

                        $lineTotals[] = $cnLineSubtotal;
                    }

                    $soLine->notes = trim(($soLine->notes ?? '').' | Return Reason: '.($item['reason'] ?? 'N/A').' ('.$item['resolution'].')');
                    $soLine->save();
                }

                // Record stock movement
                $transaction = $stockService->recordMovement($transactionData);

                // --- STATUS RECALCULATION ---
                $so->unsetRelation('lines'); // force a fresh load
                $so->recalculateStatus();

                // Automatically generate a Posted Credit Note if there are refunds
                $creditNote = null;
                if (! empty($creditNoteLines)) {
                    $creditNote = Invoice::create([
                        'invoice_number' => 'CN-'.now()->format('Ymd-Hi').'-'.rand(10, 99),
                        'customer_id' => $salesOrder->customer_id,
                        'sales_order_id' => $salesOrder->id,
                        'invoice_date' => now()->toDateString(),
                        'total_amount' => FinancialMath::headerTotal($lineTotals),
                        'status' => Invoice::STATUS_OPEN,
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

    /**
     * Helper to find a conversion factor between two UOMs.
     */
    private function getUomConversionFactor(int $fromId, int $toId, ?int $productId = null): string
    {
        try {
            return (string) \App\Helpers\UomHelper::getConversionFactor($fromId, $toId, $productId);
        } catch (\Exception $e) {
            abort(422, $e->getMessage());
        }
    }
}
