<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\UomConversionException;
use App\Helpers\UomHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\PurchaseOrderStoreRequest;
use App\Http\Requests\Procurement\PurchaseOrderUpdateRequest;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\InventoryCostLayer;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderStatus;
use App\Models\ReplenishmentSuggestion;
use App\Models\TransactionStatus;
use App\Models\TransactionType;
use App\Models\UnitOfMeasure;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PurchaseOrder::with(['vendor', 'status', 'creator', 'approver'])
            ->latest('id');

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->has('status')) {
            $query->whereHas('status', function ($q) use ($request) {
                $q->where('name', $request->status);
            });
        }

        return PurchaseOrderResource::collection($query->paginate($request->get('limit', 15)));
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $purchaseOrder->load([
            'lines.product.uom',
            'vendor',
            'status',
            'creator',
            'approver',
            'transactions.createdBy',
            'transactions.toLocation',
            'transactions.lines.product.uom',
        ]);

        return new PurchaseOrderResource($purchaseOrder);
    }

    public function store(PurchaseOrderStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $statusId = PurchaseOrderStatus::where('name', 'draft')->value('id');

        $po = DB::transaction(function () use ($data, $statusId, $request) {
            $po = PurchaseOrder::create([
                'po_number' => 'PO-'.now()->format('Ymd-Hi').'-'.rand(10, 99),
                'vendor_id' => $data['vendor_id'],
                'status_id' => $statusId,
                'order_date' => now(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
                'total_amount' => 0,
            ]);

            $totalAmount = 0.0;
            foreach ($data['lines'] as $lineData) {
                $lineCost = round($lineData['ordered_qty'] * $lineData['unit_cost'], 8);
                $totalAmount += $lineCost;

                $po->lines()->create([
                    'product_id' => $lineData['product_id'],
                    'uom_id' => $lineData['uom_id'],
                    'ordered_qty' => round($lineData['ordered_qty'], 8),
                    'received_qty' => 0,
                    'unit_cost' => round($lineData['unit_cost'], 8),
                ]);
            }

            $po->update(['total_amount' => round($totalAmount, 8)]);

            return $po;
        });

        return (new PurchaseOrderResource($po->load('lines.product.uom')))->response()->setStatusCode(201);
    }

    public function update(PurchaseOrderUpdateRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $data = $request->validated();

        $po = DB::transaction(function () use ($data, $purchaseOrder) {
            // If the PO is already not editable (Sent/Closed), we allow updating ONLY notes and delivery dates.
            if (! $purchaseOrder->status->is_editable) {
                $purchaseOrder->update([
                    'expected_delivery_date' => $data['expected_delivery_date'] ?? $purchaseOrder->expected_delivery_date,
                    'notes' => $data['notes'] ?? $purchaseOrder->notes,
                ]);

                return $purchaseOrder;
            }

            $purchaseOrder->update([
                'vendor_id' => $data['vendor_id'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'notes' => $data['notes'] ?? null,
            ]);

            // For simplicity, recreate lines
            $purchaseOrder->lines()->delete();

            $totalAmount = 0.0;
            foreach ($data['lines'] as $lineData) {
                $lineCost = round($lineData['ordered_qty'] * $lineData['unit_cost'], 8);
                $totalAmount += $lineCost;

                $purchaseOrder->lines()->create([
                    'product_id' => $lineData['product_id'],
                    'uom_id' => $lineData['uom_id'],
                    'ordered_qty' => round($lineData['ordered_qty'], 8),
                    'received_qty' => 0,
                    'unit_cost' => round($lineData['unit_cost'], 8),
                ]);
            }

            $purchaseOrder->update(['total_amount' => round($totalAmount, 8)]);

            return $purchaseOrder;
        });

        return new PurchaseOrderResource($po->load('lines.product.uom'));
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (! $purchaseOrder->status->is_editable) {
            abort(403, 'Purchase order cannot be deleted in its current status.');
        }

        $purchaseOrder->delete();

        return response()->json(null, 204);
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        // FIX [GAP 3]: Wrap in a transaction and re-fetch the PO row with a lock so
        // the status check and the write are atomic. Without this, two concurrent
        // approve requests can both pass the 'draft' guard and double-approve.
        $purchaseOrder = DB::transaction(function () use ($purchaseOrder, $request) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            $po->loadMissing('status');

            if ($po->status->name !== 'draft') {
                abort(400, 'Only draft purchase orders can be approved.');
            }

            $openStatus = PurchaseOrderStatus::where('name', 'open')->firstOrFail();

            $po->update([
                'status_id' => $openStatus->id,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            return $po;
        });

        return new PurchaseOrderResource($purchaseOrder->load('lines', 'status'));
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        // FIX [GAP 3]: Same atomic lock pattern — prevents double-sending.
        $purchaseOrder = DB::transaction(function () use ($purchaseOrder) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            $po->loadMissing('status');

            if ($po->status->name !== 'open') {
                abort(400, 'Only approved (open) purchase orders can be sent to vendors.');
            }

            $sentStatus = PurchaseOrderStatus::where('name', 'sent')->firstOrFail();

            $po->update([
                'status_id' => $sentStatus->id,
                'sent_at' => now(),
            ]);

            return $po;
        });

        return new PurchaseOrderResource($purchaseOrder->load('lines', 'status'));
    }

    public function markAsShipped(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $request->validate([
            'carrier' => 'required|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
        ]);

        // FIX [GAP 3]: Same atomic lock pattern — prevents concurrent ship requests.
        $purchaseOrder = DB::transaction(function () use ($purchaseOrder, $request) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            $po->loadMissing('status');

            if (! in_array($po->status->name, ['open', 'sent'])) {
                abort(400, 'Purchase order must be in open or sent status to be marked as shipped.');
            }

            $transitStatus = PurchaseOrderStatus::where('name', 'in_transit')->firstOrFail();

            $po->update([
                'status_id' => $transitStatus->id,
                'shipped_at' => now(),
                'carrier' => $request->carrier,
                'tracking_number' => $request->tracking_number,
            ]);

            return $po;
        });

        return new PurchaseOrderResource($purchaseOrder->load('lines', 'status'));
    }

    public function close(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        // FIX [GAP 3]: Same atomic lock pattern — prevents concurrent close requests.
        $purchaseOrder = DB::transaction(function () use ($purchaseOrder, $request) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            $po->loadMissing('status');

            if (in_array($po->status->name, ['closed', 'cancelled'])) {
                abort(400, "Purchase order is already {$po->status->name}.");
            }

            $closedStatus = PurchaseOrderStatus::where('name', 'closed')->firstOrFail();

            $po->update([
                'status_id' => $closedStatus->id,
                'notes' => trim(($po->notes ?? '').' | Manually closed by '.$request->user()->name.' on '.now()->toDateString()),
            ]);

            return $po;
        });

        return new PurchaseOrderResource($purchaseOrder->load('lines', 'status'));
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, StockService $stockService): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'lines' => 'required|array',
            'lines.*.po_line_id' => 'required|exists:purchase_order_lines,id',
            'lines.*.received_qty' => 'required|numeric|min:0.01',
            'lines.*.uom_id' => 'nullable|exists:units_of_measure,id',
        ]);

        if (in_array($purchaseOrder->status->name, ['draft', 'closed', 'cancelled'])) {
            abort(400, "Purchase order cannot be received while in {$purchaseOrder->status->name} status.");
        }

        try {
            $transaction = DB::transaction(function () use ($request, $purchaseOrder, $stockService) {
                $receiptType = TransactionType::where('name', 'receipt')->firstOrFail();
                $postedStatus = TransactionStatus::where('name', 'posted')->firstOrFail();

                $transactionData = [
                    'header' => [
                        'transaction_type_id' => $receiptType->id,
                        'transaction_status_id' => $postedStatus->id,
                        'transaction_date' => now()->toDateString(),
                        'reference_number' => 'GRN-'.$purchaseOrder->po_number.'-'.substr(uniqid(), -4),
                        'vendor_id' => $purchaseOrder->vendor_id,
                        'purchase_order_id' => $purchaseOrder->id,
                        'reference_doc' => $purchaseOrder->po_number,
                        'notes' => 'Goods Receipt Note for PO: '.$purchaseOrder->po_number,
                        'created_by' => $request->user()->id,
                        'to_location_id' => $request->location_id,
                    ],
                    'lines' => [],
                ];

                // FIX [GAP 1]: Lock all PO lines for this order before reading
                // received_qty so the over-receipt guard and the increment are atomic.
                // Without this, two concurrent GRN requests can both read received_qty=0,
                // both pass the guard, and both increment — exceeding ordered_qty.
                $poLines = $purchaseOrder->lines()->lockForUpdate()->get()->keyBy('id');

                foreach ($request->lines as $item) {
                    $poLine = $poLines->get($item['po_line_id']);

                    if (! $poLine) {
                        abort(400, 'Invalid PO line ID.');
                    }

                    if ($poLine->purchase_order_id !== $purchaseOrder->id) {
                        abort(400, 'PO line does not belong to this purchase order.');
                    }

                    $receivedQtyRaw = (float) $item['received_qty'];
                    $receivedUomId = $item['uom_id'] ?? $poLine->uom_id ?? $poLine->product->uom_id;
                    $receivedUom = UnitOfMeasure::find($receivedUomId);
                    $productUom = $poLine->product->uom;

                    // LOCK 1: Discrete units must be whole numbers
                    // Threshold matches the 8-decimal DB standard (1e-8)
                    if ($receivedUom && UomHelper::isDiscrete($receivedUom->abbreviation)) {
                        if (abs($receivedQtyRaw - round($receivedQtyRaw)) > 0.00000001) {
                            abort(422, "Discrete units ({$receivedUom->abbreviation}) must be received in whole numbers. Fractional inputs are not allowed for this unit type.");
                        }
                        $receivedQtyRaw = round($receivedQtyRaw);
                    }

                    // LOCK 2: Discrete products cannot be received in continuous units (No KG for Pieces)
                    if ($productUom && UomHelper::isDiscrete($productUom->abbreviation)) {
                        if ($receivedUom && ! UomHelper::isDiscrete($receivedUom->abbreviation)) {
                            abort(422, "This product is discrete ({$productUom->abbreviation}). You cannot receive it in continuous units like {$receivedUom->abbreviation} to prevent piece integrity errors.");
                        }
                    }

                    $receivedQty = $receivedQtyRaw;
                    $lineUomId = $poLine->uom_id ?? $poLine->product->uom_id;

                    // 1. Convert received quantity to the PO line's UOM for validation and tracking
                    $qtyToUpdatePO = $receivedQty;
                    if ((int) $receivedUomId !== (int) $lineUomId) {
                        $factor = $this->getUomConversionFactor($receivedUomId, $lineUomId, $poLine->product_id);
                        $qtyToUpdatePO = round($receivedQty * $factor, 8);
                    }

                    // VALIDATION: Prevent over-receipt (measured in PO Line UOM)
                    // Threshold matches the 8-decimal DB standard (1e-8)
                    if (($poLine->received_qty + $qtyToUpdatePO) > ($poLine->ordered_qty + 0.00000001)) {
                        $remaining = max(0, $poLine->ordered_qty - $poLine->received_qty);
                        abort(422, "Cannot receive {$receivedQty} units (equiv. to {$qtyToUpdatePO} in PO unit) for SKU {$poLine->product->sku}. Only {$remaining} remains on this order line.");
                    }

                    $transactionData['lines'][] = [
                        'product_id' => $poLine->product_id,
                        'location_id' => $request->location_id,
                        'quantity' => $receivedQty,
                        'unit_cost' => $poLine->unit_cost,
                        'uom_id' => $receivedUomId,
                    ];

                    // Update PO line received quantity (normalized to PO unit)
                    $poLine->received_qty += $qtyToUpdatePO;
                    $poLine->save();
                }

                // Record movement in Stock Engine
                $transaction = $stockService->recordMovement($transactionData);

                // Update PO Status
                $purchaseOrder->refresh();
                $newStatusName = $purchaseOrder->isCompleted() ? 'closed' : 'partially_received';
                $poStatus = PurchaseOrderStatus::where('name', $newStatusName)->firstOrFail();

                $purchaseOrder->status_id = $poStatus->id;
                $purchaseOrder->save();

                return $transaction;
            });
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (UomConversionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Goods Receipt Note posted successfully.',
            'purchase_order' => new PurchaseOrderResource($purchaseOrder->fresh('lines', 'status')),
            'transaction_id' => $transaction->id,
        ]);
    }

    public function processReturn(Request $request, PurchaseOrder $purchaseOrder, StockService $stockService): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'lines' => 'required|array',
            'lines.*.po_line_id' => 'required|exists:purchase_order_lines,id',
            'lines.*.return_qty' => 'required|numeric|min:0.01',
            'lines.*.uom_id' => 'nullable|exists:units_of_measure,id',
            'lines.*.resolution' => 'required|in:replacement,credit',
            'lines.*.reason' => 'nullable|string',
        ]);

        if (in_array($purchaseOrder->status->name, ['draft', 'cancelled'])) {
            abort(400, "Cannot process return for PO in {$purchaseOrder->status->name} status.");
        }

        try {
            $transaction = DB::transaction(function () use ($request, $purchaseOrder, $stockService) {
                $pretType = TransactionType::where('code', 'PRET')->firstOrFail();
                $postedStatus = TransactionStatus::where('name', 'posted')->firstOrFail();

                $transactionData = [
                    'header' => [
                        'transaction_type_id' => $pretType->id,
                        'transaction_status_id' => $postedStatus->id,
                        'transaction_date' => now()->toDateString(),
                        'reference_number' => 'RTV-'.$purchaseOrder->po_number.'-'.substr(uniqid(), -4),
                        'vendor_id' => $purchaseOrder->vendor_id,
                        'purchase_order_id' => $purchaseOrder->id,
                        'reference_doc' => $purchaseOrder->po_number,
                        'notes' => 'Purchase Return for PO: '.$purchaseOrder->po_number,
                        'created_by' => $request->user()->id,
                        'from_location_id' => $request->location_id,
                    ],
                    'lines' => [],
                ];

                // FIX [GAP 2]: Lock all PO lines before reading received_qty so the
                // "cannot exceed received stock" guard and the decrement are atomic.
                // Without this, two concurrent return requests can both pass the guard
                // and both decrement — causing received_qty to go negative.
                $poLines = $purchaseOrder->lines()->lockForUpdate()->get()->keyBy('id');

                foreach ($request->lines as $item) {
                    $poLine = $poLines->get($item['po_line_id']);

                    if (! $poLine || $poLine->purchase_order_id !== $purchaseOrder->id) {
                        abort(400, 'Invalid PO line ID.');
                    }

                    $returnQtyRaw = (float) $item['return_qty'];
                    $returnUomId = $item['uom_id'] ?? $poLine->uom_id ?? $poLine->product->uom_id;
                    $lineUomId = $poLine->uom_id ?? $poLine->product->uom_id;

                    // 1. Convert return quantity to the PO line's UOM for validation and tracking
                    $qtyToUpdatePO = $returnQtyRaw;
                    if ((int) $returnUomId !== (int) $lineUomId) {
                        $factor = $this->getUomConversionFactor($returnUomId, $lineUomId, $poLine->product_id);
                        $qtyToUpdatePO = round($returnQtyRaw * $factor, 8);
                    }

                    // Threshold matches the 8-decimal DB standard (1e-8)
                    if ($qtyToUpdatePO > ((float) $poLine->received_qty + 0.00000001)) {
                        $receivedInReturnUnit = $poLine->received_qty;
                        if ((int) $returnUomId !== (int) $lineUomId) {
                            $revFactor = $this->getUomConversionFactor($lineUomId, $returnUomId, $poLine->product_id);
                            $receivedInReturnUnit = round($poLine->received_qty * $revFactor, 4);
                        }
                        abort(422, "Return quantity ({$returnQtyRaw} units) exceeds available received stock for SKU {$poLine->product->sku}. Max returnable: {$receivedInReturnUnit} units.");
                    }

                    // Negative quantity → issue path (consumes layers and reduces QOH).
                    $transactionData['lines'][] = [
                        'product_id' => $poLine->product_id,
                        'location_id' => $request->location_id,
                        'quantity' => -abs($returnQtyRaw),
                        'unit_cost' => $poLine->unit_cost,
                        'uom_id' => $returnUomId,
                        'notes' => 'Resolution: '.ucfirst($item['resolution']),
                    ];

                    $poLine->received_qty = max(0, (float) $poLine->received_qty - $qtyToUpdatePO);
                    $poLine->returned_qty = (float) $poLine->returned_qty + $qtyToUpdatePO;

                    if ($item['resolution'] === 'credit') {
                        $poLine->ordered_qty = max(0, (float) $poLine->ordered_qty - $qtyToUpdatePO);
                    }

                    $poLine->notes = trim(($poLine->notes ?? '').' | Return Reason: '.($item['reason'] ?? 'N/A').' ('.$item['resolution'].')');
                    $poLine->save();
                }

                $this->recalculatePurchaseOrderTotal($purchaseOrder);

                $transaction = $stockService->recordMovement($transactionData);

                $purchaseOrder->refresh();
                $purchaseOrder->load('lines');
                $newStatusName = $purchaseOrder->isCompleted() ? 'closed' : 'partially_received';

                $poStatus = PurchaseOrderStatus::where('name', $newStatusName)->firstOrFail();
                $purchaseOrder->status_id = $poStatus->id;
                $purchaseOrder->save();

                return $transaction;
            });
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (UomConversionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Purchase Return posted successfully.',
            'purchase_order' => new PurchaseOrderResource($purchaseOrder->fresh('lines', 'status')),
            'transaction_id' => $transaction->id,
        ]);
    }

    /**
     * PO header total = sum of (ordered_qty × unit_cost) per line.
     */
    private function recalculatePurchaseOrderTotal(PurchaseOrder $purchaseOrder): void
    {
        // Aggregate line costs at full 8-decimal precision first to prevent
        // "penny bleeding" when summing many lines. The final total_amount
        // (shown to users on headers/invoices) is then rounded to 2 decimals.
        $total = PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)
            ->get()
            ->sum(fn ($line) => round((float) $line->ordered_qty * (float) $line->unit_cost, 8));

        $purchaseOrder->update(['total_amount' => round($total, 8)]);
    }

    /**
     * Get pending replenishment suggestions.
     */
    public function getSuggestions(): JsonResponse
    {
        $suggestions = ReplenishmentSuggestion::with(['product.uom', 'product.preferredVendor', 'location'])
            ->where('status', 'pending')
            ->get();

        return response()->json($suggestions);
    }

    /**
     * Create draft POs from a selection of suggestions.
     */
    public function bulkCreateFromSuggestions(Request $request): JsonResponse
    {
        $request->validate([
            'suggestion_ids' => 'required|array',
            'suggestion_ids.*' => 'exists:replenishment_suggestions,id',
        ]);

        $suggestions = ReplenishmentSuggestion::with('product')
            ->whereIn('id', $request->suggestion_ids)
            ->where('status', 'pending')
            ->get();

        if ($suggestions->isEmpty()) {
            return response()->json(['message' => 'No valid pending suggestions found.'], 400);
        }

        // Group by vendor - use product preferred vendor if available, else group 0 (No Vendor)
        $grouped = $suggestions->groupBy(function ($s) {
            return $s->product->preferred_vendor_id ?? 0;
        });

        $posCreated = [];

        DB::transaction(function () use ($grouped, &$posCreated) {
            $statusId = PurchaseOrderStatus::where('name', 'draft')->value('id');

            foreach ($grouped as $vendorId => $vendorSuggestions) {
                if ($vendorId == 0) {
                    continue; // Skip items without a vendor for now or handle specifically
                }

                $po = PurchaseOrder::create([
                    'po_number' => 'PO-'.now()->format('Ymd-Hi').'-'.rand(10, 99),
                    'vendor_id' => $vendorId,
                    'status_id' => $statusId,
                    'order_date' => now(),
                    'expected_delivery_date' => now()->addDays(7),
                    'currency' => 'USD',
                    'notes' => 'Auto-generated from replenishment suggestions.',
                    'created_by' => auth()->id(),
                    'total_amount' => 0,
                ]);

                $totalAmount = 0.0;
                foreach ($vendorSuggestions as $suggestion) {
                    /** @var ReplenishmentSuggestion $suggestion */
                    // Try to get the last unit cost from cost layers (actual procurement cost)
                    $lastCost = InventoryCostLayer::where('product_id', $suggestion->product_id)
                        ->latest('id')
                        ->value('unit_cost');

                    $unitCost = $lastCost
                        ?? ($suggestion->product->average_cost > 0 ? $suggestion->product->average_cost : null)
                        ?? ($suggestion->product->selling_price * 0.6); // Fallback estimate

                    $lineCost = round($suggestion->suggested_qty * $unitCost, 8);
                    $totalAmount += $lineCost;

                    $po->lines()->create([
                        'product_id' => $suggestion->product_id,
                        'uom_id' => $suggestion->product->uom_id,
                        'ordered_qty' => round($suggestion->suggested_qty, 8),
                        'received_qty' => 0,
                        'unit_cost' => round($unitCost, 8),
                    ]);

                    // Link and update suggestion
                    $suggestion->update([
                        'status' => 'ordered',
                        'purchase_order_id' => $po->id,
                    ]);
                }

                $po->update(['total_amount' => round($totalAmount, 8)]);
                $posCreated[] = $po->po_number;
            }
        });

        return response()->json([
            'message' => 'Successfully generated '.count($posCreated).' Purchase Orders.',
            'po_numbers' => $posCreated,
        ]);
    }

    /**
     * Helper to find a conversion factor between two UOMs.
     */
    private function getUomConversionFactor(int $fromId, int $toId, ?int $productId = null): float
    {
        try {
            return UomHelper::getConversionFactor($fromId, $toId, $productId);
        } catch (\Exception $e) {
            throw new UomConversionException($e->getMessage());
        }
    }

    /**
     * Generate a printable view for the Purchase Order.
     */
    public function print(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load([
            'vendor',
            'status',
            'lines.product.uom',
            'creator',
            'approver',
        ]);

        return view('procurement.purchase-order-print', [
            'po' => $purchaseOrder,
            'company' => [
                'name' => 'Nexus Logistics',
                'address' => '123 Logistics Way, Suite 100, Tech City, TC 54321',
                'phone' => '+1 (555) 123-4567',
                'email' => 'procurement@nexus.com',
                'website' => 'www.nexus.com',
            ],
        ]);
    }
}
