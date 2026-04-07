<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Resources\Sales\SalesOrderResource;
use App\Models\Invoice;
use App\Models\SalesOrder;
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
            'lines' => 'required|array',
            'lines.*.so_line_id' => 'required|exists:sales_order_lines,id',
            'lines.*.returned_qty' => 'required|numeric|min:0.0001',
            'lines.*.reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        try {
            $result = DB::transaction(function () use ($request, $salesOrder, $stockService) {
                $sretType = TransactionType::where('code', StockService::TYPE_SALES_RETURN)->firstOrFail();
                $postedStatus = TransactionStatus::where('name', 'posted')->firstOrFail();

                $transactionData = [
                    'header' => [
                        'transaction_type_id' => $sretType->id,
                        'transaction_status_id' => $postedStatus->id,
                        'transaction_date' => now()->toDateString(),
                        'reference_number' => 'RET-'.$salesOrder->so_number.'-'.substr(uniqid(), -4),
                        'customer_id' => $salesOrder->customer_id,
                        'sales_order_id' => $salesOrder->id,
                        'reference_doc' => $salesOrder->so_number,
                        'notes' => 'Return for SO: '.$salesOrder->so_number.'. '.($request->notes ?? ''),
                        'created_by' => $request->user()->id,
                        'return_reason' => $request->lines[0]['reason'] ?? null, // Primary reason
                    ],
                    'lines' => [],
                ];

                $soLines = $salesOrder->lines()->lockForUpdate()->get()->keyBy('id');
                $creditNoteLines = [];

                foreach ($request->lines as $item) {
                    $soLine = $soLines->get($item['so_line_id']);
                    $returnedQty = (float) $item['returned_qty'];

                    if ($returnedQty > ($soLine->shipped_qty - $soLine->returned_qty + 0.000001)) {
                        abort(422, "Cannot return more than what was shipped for product: {$soLine->product->name}");
                    }

                    // Add to inventory transaction (Receipt)
                    $transactionData['lines'][] = [
                        'product_id' => $soLine->product_id,
                        'location_id' => $soLine->location_id,
                        'quantity' => abs($returnedQty), // Receipt is positive
                        'uom_id' => $soLine->uom_id,
                    ];

                    // Update SO Line
                    $soLine->returned_qty += $returnedQty;
                    $soLine->shipped_qty -= $returnedQty; // Decrement shipped_qty as per roadmap
                    $soLine->save();

                    // Prepare Credit Note Line
                    $creditNoteLines[] = [
                        'product_id' => $soLine->product_id,
                        'sales_order_line_id' => $soLine->id,
                        'quantity' => $returnedQty,
                        'unit_price' => $soLine->unit_price,
                        'subtotal' => $returnedQty * $soLine->unit_price,
                    ];
                }

                // Record stock movement
                $transaction = $stockService->recordMovement($transactionData);

                // Automatically generate a Draft Credit Note
                $creditNote = Invoice::create([
                    'invoice_number' => 'CN-'.now()->format('Ymd-Hi').'-'.rand(10, 99),
                    'customer_id' => $salesOrder->customer_id,
                    'sales_order_id' => $salesOrder->id,
                    'invoice_date' => now()->toDateString(),
                    'total_amount' => collect($creditNoteLines)->sum('subtotal'),
                    'status' => Invoice::STATUS_DRAFT,
                    'type' => Invoice::TYPE_CREDIT_NOTE,
                    'notes' => 'Generated from Return: '.$transaction->reference_number,
                ]);

                foreach ($creditNoteLines as $lineData) {
                    $creditNote->lines()->create($lineData);
                }

                return [
                    'transaction' => $transaction,
                    'credit_note' => $creditNote,
                ];
            });

            return response()->json([
                'message' => 'Sales Return processed successfully.',
                'transaction_id' => $result['transaction']->id,
                'credit_note_id' => $result['credit_note']->id,
                'sales_order' => new SalesOrderResource($salesOrder->fresh('lines', 'status')),
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
