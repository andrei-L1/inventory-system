<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\UomConversionException;
use App\Helpers\FinancialMath;
use App\Helpers\UomHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\PurchaseOrderStoreRequest;
use App\Http\Requests\Procurement\PurchaseOrderUpdateRequest;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\InventoryCostLayer;
use App\Models\DebitNote;
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

        // M-5: Full-text search across PO number and vendor name
        if ($request->filled('query')) {
            $term = $request->query('query');
            $query->where(function ($q) use ($term) {
                $q->where('po_number', 'like', "%{$term}%")
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$term}%"));
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
            'transactions.type',       // M-6: required by PurchaseOrderResource receipt/return filter
            'transactions.createdBy',
            'transactions.toLocation',
            'transactions.fromLocation',
            'transactions.lines.product.uom',
            'transactions.lines.billLines', // New: load billing history for each receipt line
        ]);

        return new PurchaseOrderResource($purchaseOrder);
    }

    public function store(PurchaseOrderStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $statusId = PurchaseOrderStatus::where('name', 'draft')->value('id');

        $po = DB::transaction(function () use ($data, $statusId, $request) {
            $po = PurchaseOrder::create([
                'po_number' => 'PO-'.now()->format('Ymd-His').'-'.substr(uniqid(), -4),
                'vendor_id' => $data['vendor_id'],
                'status_id' => $statusId,
                'order_date' => now(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'currency' => $data['currency'] ?? 'PHP',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
                'total_amount' => 0,
            ]);

            $lineTotals = [];
            foreach ($data['lines'] as $lineData) {
                $qty  = (string) $lineData['ordered_qty'];
                $cost = (string) $lineData['unit_cost'];
                $discountRate = (string) ($lineData['discount_rate'] ?? 0);
                $taxRate = (string) ($lineData['tax_rate'] ?? 0);

                // Gross line cost before discount
                $grossCost = FinancialMath::poLineCost($qty, $cost);

                // Discount amount = gross * rate / 100
                $discountAmount = FinancialMath::round(
                    FinancialMath::mul($grossCost, FinancialMath::div($discountRate, '100')),
                    FinancialMath::LINE_SCALE
                );

                // Tax amount = (gross - discount) * taxRate / 100
                $taxableAmount = FinancialMath::sub($grossCost, $discountAmount);
                $taxAmount = FinancialMath::round(
                    FinancialMath::mul($taxableAmount, FinancialMath::div($taxRate, '100')),
                    FinancialMath::LINE_SCALE
                );

                // Net line cost = gross - discount + tax
                $netCost = FinancialMath::add(FinancialMath::sub($grossCost, $discountAmount), $taxAmount);
                $lineTotals[] = $netCost;

                $po->lines()->create([
                    'product_id'      => $lineData['product_id'],
                    'uom_id'          => $lineData['uom_id'],
                    'ordered_qty'     => FinancialMath::round($qty, FinancialMath::LINE_SCALE),
                    'received_qty'    => 0,
                    'unit_cost'       => FinancialMath::round($cost, FinancialMath::LINE_SCALE),
                    'discount_rate'   => FinancialMath::round($discountRate, 2),
                    'discount_amount' => $discountAmount,
                    'tax_rate'        => FinancialMath::round($taxRate, 2),
                    'tax_amount'      => $taxAmount,
                ]);
            }

            $po->update(['total_amount' => FinancialMath::headerTotal($lineTotals)]);

            return $po;
        });

        return (new PurchaseOrderResource($po->refresh()->load('lines.product.uom')))->response()->setStatusCode(201);
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

            // H-2: Guard against editing a draft that already has received stock.
            // This prevents wiping GRN-linked received_qty back to 0.
            $hasReceipts = $purchaseOrder->lines()->where('received_qty', '>', 0)->exists();
            if ($hasReceipts) {
                abort(403, 'This purchase order has received stock. Lines can no longer be modified — use the Return (RTV) flow to adjust quantities.');
            }

            $purchaseOrder->update([
                'vendor_id' => $data['vendor_id'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'currency' => $data['currency'] ?? 'PHP',
                'notes' => $data['notes'] ?? null,
            ]);

            // For simplicity, recreate lines
            $purchaseOrder->lines()->delete();

            $lineTotals = [];
            foreach ($data['lines'] as $lineData) {
                $qty  = (string) $lineData['ordered_qty'];
                $cost = (string) $lineData['unit_cost'];
                $discountRate = (string) ($lineData['discount_rate'] ?? 0);
                $taxRate = (string) ($lineData['tax_rate'] ?? 0);

                $grossCost    = FinancialMath::poLineCost($qty, $cost);
                $discountAmount = FinancialMath::round(
                    FinancialMath::mul($grossCost, FinancialMath::div($discountRate, '100')),
                    FinancialMath::LINE_SCALE
                );

                $taxableAmount = FinancialMath::sub($grossCost, $discountAmount);
                $taxAmount = FinancialMath::round(
                    FinancialMath::mul($taxableAmount, FinancialMath::div($taxRate, '100')),
                    FinancialMath::LINE_SCALE
                );

                $netCost = FinancialMath::add(FinancialMath::sub($grossCost, $discountAmount), $taxAmount);
                $lineTotals[] = $netCost;

                $purchaseOrder->lines()->create([
                    'product_id'      => $lineData['product_id'],
                    'uom_id'          => $lineData['uom_id'],
                    'ordered_qty'     => FinancialMath::round($qty, FinancialMath::LINE_SCALE),
                    'received_qty'    => 0,
                    'unit_cost'       => FinancialMath::round($cost, FinancialMath::LINE_SCALE),
                    'discount_rate'   => FinancialMath::round($discountRate, 2),
                    'discount_amount' => $discountAmount,
                    'tax_rate'        => FinancialMath::round($taxRate, 2),
                    'tax_amount'      => $taxAmount,
                ]);
            }

            $purchaseOrder->update(['total_amount' => FinancialMath::headerTotal($lineTotals)]);

            return $purchaseOrder;
        });

        return new PurchaseOrderResource($po->refresh()->load('lines.product.uom'));
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        DB::transaction(function () use ($purchaseOrder) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            $po->loadMissing('status');

            // L-4: Physical deletion is only permitted for purely local/editable drafts.
            // Once a PO is in 'open' or beyond, it should be formally CANCELLED.
            if (! $po->status->is_editable) {
                abort(403, 'Purchase order cannot be deleted. Try cancelling it instead.');
            }

            $po->delete();
        });

        return response()->json(null, 204);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return DB::transaction(function () use ($purchaseOrder) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            $po->loadMissing(['status', 'lines']);

            if (! $po->can_be_cancelled) {
                if (in_array($po->status->name, ['closed', 'cancelled'])) {
                    abort(400, "Purchase order is already in a terminal '{$po->status->name}' state.");
                }
                abort(400, 'Purchase order cannot be cancelled because it has already started receiving goods. Close it instead to truncate remaining items.');
            }

            $cancelledStatus = PurchaseOrderStatus::where('name', 'cancelled')->firstOrFail();
            $po->update(['status_id' => $cancelledStatus->id]);

            return new PurchaseOrderResource($po->load('lines', 'status'));
        });
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

            // H-4: Prevent closing a draft (must go through approval workflow) or already terminal states.
            if (in_array($po->status->name, ['draft', 'closed', 'cancelled'])) {
                abort(400, "Purchase order cannot be manually closed from '{$po->status->name}' status.");
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
                        'reference_number' => 'GRN-'.now()->format('YmdHis').'-'.mt_rand(1000, 9999),
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

                    $receivedQtyRaw = (string) $item['received_qty'];
                    $receivedUomId = $item['uom_id'] ?? $poLine->uom_id ?? $poLine->product->uom_id;
                    $receivedUom = UnitOfMeasure::find($receivedUomId);
                    $productUom = $poLine->product->uom;

                    // LOCK 1: Discrete units must be whole numbers
                    if ($receivedUom && UomHelper::isDiscrete($receivedUom->abbreviation)) {
                        // isZero checks if the fractional part is zero (BCMath-safe).
                        // Instead of float floor(), use BCMath truncation (scale 0) for positive numbers.
                        $truncated = bcadd($receivedQtyRaw, '0', 0);
                        $fractional = FinancialMath::sub($receivedQtyRaw, $truncated);
                        if (! FinancialMath::isZero($fractional)) {
                            $formattedQty = UomHelper::format($receivedQtyRaw, (int) $receivedUomId, $poLine->product_id);
                            abort(422, "Inventory Integrity Error: Units of type '{$receivedUom->abbreviation}' are discrete and cannot be split into fractions (Attempted: {$formattedQty}). Please enter a whole number.");
                        }
                        $receivedQtyRaw = $truncated;
                    }

                    // LOCK 2: Discrete products cannot be received in continuous units (No KG for Pieces)
                    if ($productUom && UomHelper::isDiscrete($productUom->abbreviation)) {
                        if ($receivedUom && ! UomHelper::isDiscrete($receivedUom->abbreviation)) {
                            abort(422, "This product is discrete ({$productUom->abbreviation}). You cannot receive it in continuous units like {$receivedUom->abbreviation} to prevent piece integrity errors.");
                        }
                    }

                    $receivedQty = $receivedQtyRaw;
                    $lineUomId = $poLine->uom_id ?? $poLine->product->uom_id;

                    // Convert received quantity to PO line UOM for validation and tracking
                    $qtyToUpdatePO = $receivedQty;
                    if ((int) $receivedUomId !== (int) $lineUomId) {
                        $factor = $this->getUomConversionFactor($receivedUomId, $lineUomId, $poLine->product_id);
                        $qtyToUpdatePO = FinancialMath::round(FinancialMath::mul($receivedQty, $factor), FinancialMath::LINE_SCALE);
                    }

                    // VALIDATION: Prevent over-receipt (measured in PO Line UOM)
                    $newReceived = FinancialMath::add((string) $poLine->received_qty, $qtyToUpdatePO);
                    if (FinancialMath::gt($newReceived, (string) $poLine->ordered_qty)) {
                        $remaining = FinancialMath::sub((string) $poLine->ordered_qty, (string) $poLine->received_qty);
                        $formattedReceived = UomHelper::format($receivedQty, (int) $receivedUomId, $poLine->product_id);
                        $formattedMax = UomHelper::format($remaining, (int) $lineUomId, $poLine->product_id);

                        abort(422, "Cannot process receipt: The entered quantity ({$formattedReceived}) is greater than the pending quantity ({$formattedMax}) remaining on this line for SKU {$poLine->product->sku}.");
                    }

                    $discountPct = FinancialMath::div((string) ($poLine->discount_rate ?? '0'), '100');
                    $discountAmountPerUnit = FinancialMath::mul((string) $poLine->unit_cost, $discountPct);
                    $netUnitCostRaw = FinancialMath::sub((string) $poLine->unit_cost, $discountAmountPerUnit);

                    $unitCost = $netUnitCostRaw;
                    if ((int) $receivedUomId !== (int) $lineUomId) {
                        // Factor ($receivedUom to $lineUom) was calculated at line 373.
                        // Cost per $receivedUom = Cost per $lineUom * Factor.
                        // e.g. Box cost P100 * (1 Piece = 0.25 Box) = P25 Piece cost.
                        $unitCost = FinancialMath::round(FinancialMath::mul($netUnitCostRaw, $factor), FinancialMath::LINE_SCALE);
                    }

                    $transactionData['lines'][] = [
                        'product_id' => $poLine->product_id,
                        'location_id' => $request->location_id,
                        'quantity' => $receivedQty,
                        'unit_cost' => (string) $unitCost,
                        'uom_id' => $receivedUomId,
                    ];

                    $poLine->received_qty = FinancialMath::add((string) $poLine->received_qty, $qtyToUpdatePO);
                    $poLine->save();
                }

                // Record movement in Stock Engine
                $transaction = $stockService->recordMovement($transactionData);

                // Update PO Status
                $purchaseOrder->recalculateStatus();

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
                $po = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
                $po->loadMissing('status', 'lines');

                // R-H2: Detect if the PO was manually closed (closed despite not being fully received)
                $wasManuallyClosed = $po->status->name === 'closed' && ! $po->isCompleted();

                $pretType = TransactionType::where('code', 'PRET')->firstOrFail();
                $postedStatus = TransactionStatus::where('name', 'posted')->firstOrFail();

                $transactionData = [
                    'header' => [
                        'transaction_type_id' => $pretType->id,
                        'transaction_status_id' => $postedStatus->id,
                        'transaction_date' => now()->toDateString(),
                        'reference_number' => 'RTV-'.now()->format('YmdHis').'-'.mt_rand(1000, 9999),
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

                $totalCreditAmount = '0';
                foreach ($request->lines as $item) {
                    $poLine = $poLines->get($item['po_line_id']);

                    if (!$poLine || $poLine->purchase_order_id !== $purchaseOrder->id) {
                        abort(400, 'Invalid PO line ID.');
                    }

                    $returnQtyRaw = (string)$item['return_qty'];
                    $returnUomId = $item['uom_id'] ?? $poLine->uom_id ?? $poLine->product->uom_id;
                    $lineUomId = $poLine->uom_id ?? $poLine->product->uom_id;

                    // Convert return qty to PO line UOM for validation and tracking
                    $qtyToUpdatePO = $returnQtyRaw;
                    if ((int)$returnUomId !== (int)$lineUomId) {
                        $factor = $this->getUomConversionFactor($returnUomId, $lineUomId, $poLine->product_id);
                        $qtyToUpdatePO = FinancialMath::round(FinancialMath::mul($returnQtyRaw, $factor), FinancialMath::LINE_SCALE);
                    }

                    // Guard: cannot return more than received
                    if (FinancialMath::gt($qtyToUpdatePO, (string)$poLine->received_qty)) {
                        $receivedInReturnUnit = (string)$poLine->received_qty;
                        if ((int)$returnUomId !== (int)$lineUomId) {
                            $revFactor = $this->getUomConversionFactor($lineUomId, $returnUomId, $poLine->product_id);
                            $receivedInReturnUnit = FinancialMath::round(FinancialMath::mul((string)$poLine->received_qty, $revFactor), FinancialMath::LINE_SCALE);
                        }

                        $formattedReturn = UomHelper::format($returnQtyRaw, (int)$returnUomId, $poLine->product_id);
                        $formattedMax = UomHelper::format($receivedInReturnUnit, (int)$returnUomId, $poLine->product_id);

                        abort(422, "Cannot process return: The entered quantity ({$formattedReturn}) is greater than the quantity currently received ({$formattedMax}) for SKU {$poLine->product->sku}.");
                    }

                    $discountPct = FinancialMath::div((string) ($poLine->discount_rate ?? '0'), '100');
                    $discountAmountPerUnit = FinancialMath::mul((string) $poLine->unit_cost, $discountPct);
                    $netUnitCostRaw = FinancialMath::sub((string) $poLine->unit_cost, $discountAmountPerUnit);

                    $unitCost = $netUnitCostRaw;
                    if ((int)$returnUomId !== (int)$lineUomId) {
                        $unitCost = FinancialMath::round(FinancialMath::mul($netUnitCostRaw, $factor), FinancialMath::LINE_SCALE);
                    }
 
                    // Negative quantity → issue path (consumes layers and reduces QOH).
                    $transactionData['lines'][] = [
                        'product_id' => $poLine->product_id,
                        'location_id' => $request->location_id,
                        'quantity' => -abs($returnQtyRaw),
                        'unit_cost' => (string)$unitCost,
                        'uom_id' => $returnUomId,
                        'notes' => 'Resolution: ' . ucfirst($item['resolution']),
                    ];
 
                    // [INDUSTRY STANDARD]: We maintain historical 'returned' count
                    // but we MUST decrement 'received_qty' to allow "re-receipt" of replacements.
                    $poLine->returned_qty = FinancialMath::add((string)$poLine->returned_qty, (string)$qtyToUpdatePO);
                    $poLine->received_qty = FinancialMath::max('0', FinancialMath::sub((string)$poLine->received_qty, (string)$qtyToUpdatePO));
 
                    if ($item['resolution'] === 'credit') {
                        // Financial Credit Accumulation
                        $lineCredit = FinancialMath::round(FinancialMath::mul((string)$qtyToUpdatePO, (string)$poLine->unit_cost), FinancialMath::LINE_SCALE);
                        $totalCreditAmount = FinancialMath::add($totalCreditAmount, $lineCredit);
                    }
 
                    $poLine->notes = trim(($poLine->notes ?? '') . ' | Return Reason: ' . ($item['reason'] ?? 'N/A') . ' (' . $item['resolution'] . ')');
                    $poLine->save();
                }
 
                // H-7: Record movement FIRST, then recalculate total.
                $transaction = $stockService->recordMovement($transactionData);
 
                // ─── Phase 5.7: Generate Debit Note (Consolidated in Bills) ───
                if (FinancialMath::gt($totalCreditAmount, '0')) {
                    $debitNote = \App\Models\Bill::create([
                        'bill_number' => 'DN-' . now()->format('YmdHi') . '-' . mt_rand(1001, 9999),
                        'type' => \App\Models\Bill::TYPE_DEBIT_NOTE,
                        'vendor_id' => $purchaseOrder->vendor_id,
                        'purchase_order_id' => $purchaseOrder->id,
                        'ref_transaction_id' => $transaction->id ?? null,
                        'bill_date' => now()->toDateString(),
                        'total_amount' => $totalCreditAmount,
                        'paid_amount' => 0,
                        'status' => \App\Models\Bill::STATUS_POSTED,
                        'notes' => 'Generated from Purchase Return: ' . ($transaction->reference_number ?? 'N/A'),
                        'reason' => $request->lines[0]['reason'] ?? 'Purchase Return',
                    ]);

                    // Generate Line Items for the Debit Note to support Dynamic Quantity Release
                    foreach ($request->lines as $item) {
                        // IMPORTANT: Only generate lines for items that opted for CREDIT resolution
                        if (($item['resolution'] ?? '') !== 'credit') {
                            continue;
                        }

                        $poLine = $purchaseOrder->lines()->where('id', $item['po_line_id'])->first();
                        if ($poLine) {
                            $returnQtyRaw = (string) $item['return_qty'];
                            $returnUomId = $item['uom_id'] ?? $poLine->uom_id ?? $poLine->product->uom_id;
                            
                            // Convert return qty to ATOMIC PIECES for BillLine storage
                            $piecesMultiplier = (string) \App\Helpers\UomHelper::getMultiplierToSmallest($returnUomId, $poLine->product_id);
                            $qtyPieces = FinancialMath::mul($returnQtyRaw, $piecesMultiplier);

                            // Calculate line credit amount
                            $multiplier = (string) \App\Helpers\UomHelper::getMultiplierToSmallest($poLine->uom_id, $poLine->product_id);
                            $unitPricePieces = FinancialMath::div((string)$poLine->unit_cost, $multiplier);
                            $lineCreditRaw = FinancialMath::mul($qtyPieces, $unitPricePieces);

                            // Note: We store the returned quantity as NEGATIVE in the BillLine 
                            // to correctly re-balance the net billed quantity in the dynamic accessor.
                            $debitNote->lines()->create([
                                'purchase_order_line_id' => $poLine->id,
                                'quantity' => '-' . $qtyPieces,
                                'unit_price' => $unitPricePieces,
                                'subtotal' => '-' . FinancialMath::round($lineCreditRaw, FinancialMath::LINE_SCALE),
                                'notes' => 'Credit for Return: ' . ($item['reason'] ?? 'N/A'),
                            ]);
                        }
                    }
                }

                $purchaseOrder->recalculateStatus();

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
        // Collect all line costs as BCMath strings, sum in 8dp, round to 2dp at the end.
        $lineTotals = PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)
            ->get()
            ->map(fn ($line) => FinancialMath::poLineCost($line->ordered_qty, $line->unit_cost))
            ->all();

        $purchaseOrder->update(['total_amount' => FinancialMath::headerTotal($lineTotals)]);
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
        // L-5: Track suggestions that were skipped due to missing vendor
        $skippedSkus = [];

        DB::transaction(function () use ($grouped, &$posCreated, &$skippedSkus) {
            $statusId = PurchaseOrderStatus::where('name', 'draft')->value('id');

            foreach ($grouped as $vendorId => $vendorSuggestions) {
                if ($vendorId == 0) {
                    // L-5: Collect skipped items so the caller can inform the user
                    foreach ($vendorSuggestions as $s) {
                        $skippedSkus[] = $s->product->sku ?? "Product #{$s->product_id}";
                    }

                    continue;
                }

                // H-5: Use microsecond-based uniqid to prevent PO number collision during bulk creation
                $po = PurchaseOrder::create([
                    'po_number' => 'PO-'.now()->format('Ymd-His').'-'.substr(uniqid(), -4),
                    'vendor_id' => $vendorId,
                    'status_id' => $statusId,
                    'order_date' => now(),
                    'expected_delivery_date' => now()->addDays(7),
                    'currency' => 'PHP',
                    'notes' => 'Auto-generated from replenishment suggestions.',
                    'created_by' => auth()->id(),
                    'total_amount' => 0,
                ]);

                $totalAmount = '0';
                foreach ($vendorSuggestions as $suggestion) {
                    /** @var ReplenishmentSuggestion $suggestion */
                    // Try to get the last unit cost from cost layers (actual procurement cost)
                    $lastCost = InventoryCostLayer::where('product_id', $suggestion->product_id)
                        ->latest('id')
                        ->value('unit_cost');

                    $unitCost = $lastCost
                        ?? (FinancialMath::isPositive((string) $suggestion->product->average_cost) ? (string) $suggestion->product->average_cost : null)
                        ?? FinancialMath::mul((string) $suggestion->product->selling_price, '0.6'); // Fallback estimate

                    $suggestedQtyStr = (string) $suggestion->suggested_qty;
                    $lineCost = FinancialMath::round(FinancialMath::mul($suggestedQtyStr, (string) $unitCost), FinancialMath::LINE_SCALE);
                    $totalAmount = FinancialMath::add($totalAmount, $lineCost);

                    $po->lines()->create([
                        'product_id' => $suggestion->product_id,
                        'uom_id' => $suggestion->product->uom_id,
                        'ordered_qty' => FinancialMath::round($suggestedQtyStr, FinancialMath::LINE_SCALE),
                        'received_qty' => 0,
                        'unit_cost' => FinancialMath::round((string) $unitCost, FinancialMath::LINE_SCALE),
                    ]);

                    // Link and update suggestion
                    $suggestion->update([
                        'status' => 'ordered',
                        'purchase_order_id' => $po->id,
                    ]);
                }

                $po->update(['total_amount' => FinancialMath::headerTotal([$totalAmount])]);
                $posCreated[] = $po->po_number;
            }
        });

        $response = [
            'message' => 'Successfully generated '.count($posCreated).' Purchase Order(s).',
            'po_numbers' => $posCreated,
        ];

        // L-5: Inform caller about skipped items so the UI can surface a warning
        if (! empty($skippedSkus)) {
            $response['skipped_skus'] = $skippedSkus;
            $response['skipped_message'] = count($skippedSkus).' item(s) were skipped because their products have no preferred vendor assigned: '.implode(', ', $skippedSkus);
        }

        return response()->json($response);
    }

    /**
     * Helper to find a conversion factor between two UOMs.
     */
    private function getUomConversionFactor(int $fromId, int $toId, ?int $productId = null): string
    {
        try {
            return (string) UomHelper::getConversionFactor($fromId, $toId, $productId);
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
