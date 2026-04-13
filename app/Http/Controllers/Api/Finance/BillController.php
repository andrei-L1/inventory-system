<?php

namespace App\Http\Controllers\Api\Finance;

use App\Helpers\FinancialMath;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\TransactionLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Bill::with(['vendor', 'purchaseOrder'])->latest();

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate($request->get('limit', 15)));
    }

    public function show(Bill $bill): JsonResponse
    {
        $bill->load(['vendor', 'purchaseOrder', 'lines.product', 'lines.transactionLine']);
        return response()->json($bill);
    }

    /**
     * Create a Bill from a Purchase Order.
     * Strict "Match-to-GRN" (Receipt) logic.
     */
    public function storeFromPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $request->validate([
            'bill_number' => 'required|string|max:50',
            'bill_date' => 'required|date',
            'due_date' => 'nullable|date',
            'lines' => 'required|array',
            'lines.*.po_line_id' => 'required|exists:purchase_order_lines,id',
            'lines.*.transaction_line_id' => 'required|exists:transaction_lines,id',
            'lines.*.quantity' => 'required|numeric|min:0.00000001',
            'lines.*.unit_price' => 'nullable|numeric|min:0', // Explicit pricing override support
        ]);

        try {
            $bill = DB::transaction(function () use ($request, $purchaseOrder) {
                $bill = Bill::create([
                    'bill_number' => $request->bill_number,
                    'vendor_id' => $purchaseOrder->vendor_id,
                    'purchase_order_id' => $purchaseOrder->id,
                    'bill_date' => $request->bill_date,
                    'due_date' => $request->due_date,
                    'status' => Bill::STATUS_DRAFT,
                    'total_amount' => 0, // Explicit default for strict mode safety
                ]);

                $lineTotals = [];
                foreach ($request->lines as $item) {
                    // AUDIT PHASE II: Lock PO Line and GRN Line for update to prevent concurrent over-billing
                    $poLine = PurchaseOrderLine::lockForUpdate()->findOrFail($item['po_line_id']);
                    $grnLine = TransactionLine::lockForUpdate()->findOrFail($item['transaction_line_id']);

                    // Incoming quantity is ATOMIC (Pieces)
                    $qtyPieces = (string) $item['quantity'];

                    // Incoming price is ATOMIC (Price per Piece)
                    $unitPrice = $item['unit_price'] ?? ($poLine->unit_cost / \App\Helpers\UomHelper::getMultiplierToSmallest($poLine->uom_id, $poLine->product_id));

                    // Scale the quantity back to the PO Line's UOM for validation and tracking
                    // (Pieces / Factor) = PO Unit Qty
                    $factor = (string) \App\Helpers\UomHelper::getMultiplierToSmallest($poLine->uom_id, $poLine->product_id);
                    $qtyPoUom = FinancialMath::div($qtyPieces, $factor);

                    // Validation: Transaction line must be part of this PO
                    if ($grnLine->transaction->purchase_order_id !== $purchaseOrder->id) {
                        abort(422, "Inventory Error: Receipt Line {$grnLine->id} does not belong to Purchase Order {$purchaseOrder->po_number}.");
                    }

                    // Validation: PO line must match the receipt line's product
                    if ($poLine->product_id !== $grnLine->product_id) {
                        abort(422, "Product Mismatch: Line SKU DOES NOT MATCH receipt SKU.");
                    }

                    // Hard Validation: Compare SCALED quantity against PO limit
                    $billableQty = $poLine->billable_qty;
                    $uomName = $poLine->uom->abbreviation ?? 'items';

                    if (FinancialMath::gt($qtyPoUom, $billableQty)) {
                        if (FinancialMath::isZero($billableQty)) {
                            abort(422, "Product '{$poLine->product->name}' is already fully billed for all received quantities.");
                        }
                        abort(422, "Cannot bill {$qtyPieces} pieces for '{$poLine->product->name}'. Only {$billableQty} {$uomName} rem. billable.");
                    }

                    // Calculate subtotal using ATOMIC values (Pieces * Price-per-Piece)
                    $lineSubtotal = FinancialMath::round(FinancialMath::mul($qtyPieces, (string) $unitPrice), FinancialMath::LINE_SCALE);

                    $bill->lines()->create([
                        'purchase_order_line_id' => $poLine->id,
                        'transaction_line_id' => $grnLine->id,
                        'quantity' => $qtyPieces, // Stored as pieces
                        'unit_price' => $unitPrice,
                        'subtotal' => $lineSubtotal,
                    ]);

                    $lineTotals[] = $lineSubtotal;
                }

                $bill->update(['total_amount' => FinancialMath::headerTotal($lineTotals)]);

                // Sync the PO Billing Status
                $purchaseOrder->syncBillingStatus();

                return $bill;
            });

            return response()->json([
                'message' => 'Vendor Bill (Draft) created successfully.',
                'bill' => $bill->load('lines'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Create a generic Bill (Manual/Non-PO).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'bill_number' => 'required|string|max:50|unique:bills,bill_number',
            'bill_date' => 'required|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string|max:255',
            'lines.*.quantity' => 'required|numeric|min:0.00000001',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $bill = DB::transaction(function () use ($request) {
                $bill = Bill::create([
                    'vendor_id' => $request->vendor_id,
                    'bill_number' => $request->bill_number,
                    'bill_date' => $request->bill_date,
                    'due_date' => $request->due_date,
                    'notes' => $request->notes,
                    'status' => Bill::STATUS_DRAFT,
                    'total_amount' => 0, // Will update below
                ]);

                $lineTotals = [];
                foreach ($request->lines as $item) {
                    $qty = (string) $item['quantity'];
                    $price = (string) $item['unit_price'];
                    $subtotal = FinancialMath::round(FinancialMath::mul($qty, $price), FinancialMath::LINE_SCALE);

                    $bill->lines()->create([
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'subtotal' => $subtotal,
                        'notes' => $item['description'],
                    ]);

                    $lineTotals[] = $subtotal;
                }

                $bill->update(['total_amount' => FinancialMath::headerTotal($lineTotals)]);
                return $bill;
            });

            return response()->json([
                'message' => 'Manual Bill created successfully.',
                'bill' => $bill->load('lines'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function post(Bill $bill): JsonResponse
    {
        if (!$bill->isDraft()) {
            return response()->json(['message' => 'Only draft bills can be posted.'], 400);
        }

        $bill->update(['status' => Bill::STATUS_POSTED]);

        return response()->json([
            'message' => 'Bill posted successfully. Liability is now recognized in A/P.',
            'bill' => $bill,
        ]);
    }

    /**
     * Void a POSTED or DRAFT bill.
     */
    public function void(Bill $bill): JsonResponse
    {
        if ($bill->status === Bill::STATUS_VOID) {
            return response()->json(['message' => 'Bill is already voided.'], 200);
        }

        if ($bill->status === Bill::STATUS_PAID) {
            return response()->json(['message' => 'Cannot void a PAID bill. Remove payment allocations first.'], 422);
        }

        $bill->void();

        return response()->json([
            'message' => 'Bill voided successfully. Quantities released for re-billing.',
            'bill' => $bill,
        ]);
    }
}
