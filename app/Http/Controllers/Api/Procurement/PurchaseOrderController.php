<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\PurchaseOrderStoreRequest;
use App\Http\Requests\Procurement\PurchaseOrderUpdateRequest;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatus;
use App\Models\ReplenishmentSuggestion;
use App\Models\TransactionStatus;
use App\Models\TransactionType;
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
                $lineCost = $lineData['ordered_qty'] * $lineData['unit_cost'];
                $totalAmount += $lineCost;

                $po->lines()->create([
                    'product_id' => $lineData['product_id'],
                    'ordered_qty' => $lineData['ordered_qty'],
                    'received_qty' => 0,
                    'unit_cost' => $lineData['unit_cost'],
                ]);
            }

            $po->update(['total_amount' => $totalAmount]);

            return $po;
        });

        return response()->json(new PurchaseOrderResource($po->load('lines.product.uom')), 201);
    }

    public function update(PurchaseOrderUpdateRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (! $purchaseOrder->status->is_editable) {
            abort(403, 'Purchase order cannot be edited in its current status.');
        }

        $data = $request->validated();

        $po = DB::transaction(function () use ($data, $purchaseOrder) {
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
                $lineCost = $lineData['ordered_qty'] * $lineData['unit_cost'];
                $totalAmount += $lineCost;

                $purchaseOrder->lines()->create([
                    'product_id' => $lineData['product_id'],
                    'ordered_qty' => $lineData['ordered_qty'],
                    'received_qty' => 0,
                    'unit_cost' => $lineData['unit_cost'],
                ]);
            }

            $purchaseOrder->update(['total_amount' => $totalAmount]);

            return $purchaseOrder;
        });

        return response()->json(new PurchaseOrderResource($po->load('lines.product.uom')));
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (! $purchaseOrder->status->is_editable) {
            abort(403, 'Purchase order cannot be deleted in its current status.');
        }

        $purchaseOrder->delete();

        return response()->json(null, 204);
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status->name !== 'draft') {
            abort(400, 'Only draft purchase orders can be approved.');
        }

        $openStatus = PurchaseOrderStatus::where('name', 'open')->firstOrFail();

        $purchaseOrder->update([
            'status_id' => $openStatus->id,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json(new PurchaseOrderResource($purchaseOrder->load('lines', 'status')));
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status->name !== 'open') {
            abort(400, 'Only approved (open) purchase orders can be sent to vendors.');
        }

        $sentStatus = PurchaseOrderStatus::where('name', 'sent')->firstOrFail();

        $purchaseOrder->update([
            'status_id' => $sentStatus->id,
            'sent_at' => now(),
        ]);

        return response()->json(new PurchaseOrderResource($purchaseOrder->load('lines', 'status')));
    }

    public function markAsShipped(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $request->validate([
            'carrier' => 'required|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
        ]);

        if (! in_array($purchaseOrder->status->name, ['open', 'sent'])) {
            abort(400, 'Purchase order must be in open or sent status to be marked as shipped.');
        }

        $transitStatus = PurchaseOrderStatus::where('name', 'in_transit')->firstOrFail();

        $purchaseOrder->update([
            'status_id' => $transitStatus->id,
            'shipped_at' => now(),
            'carrier' => $request->carrier,
            'tracking_number' => $request->tracking_number,
        ]);

        return response()->json(new PurchaseOrderResource($purchaseOrder->load('lines', 'status')));
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, StockService $stockService): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'lines' => 'required|array',
            'lines.*.po_line_id' => 'required|exists:purchase_order_lines,id',
            'lines.*.received_qty' => 'required|numeric|min:0.01',
        ]);

        if (in_array($purchaseOrder->status->name, ['draft', 'closed', 'cancelled'])) {
            abort(400, "Purchase order cannot be received while in {$purchaseOrder->status->name} status.");
        }

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
                ],
                'lines' => [],
            ];

            $poLines = $purchaseOrder->lines()->get()->keyBy('id');

            foreach ($request->lines as $item) {
                $poLine = $poLines->get($item['po_line_id']);

                if (! $poLine) {
                    abort(400, 'Invalid PO line ID.');
                }

                if ($poLine->purchase_order_id !== $purchaseOrder->id) {
                    abort(400, 'PO line does not belong to this purchase order.');
                }

                $transactionData['lines'][] = [
                    'product_id' => $poLine->product_id,
                    'location_id' => $request->location_id,
                    'quantity' => $item['received_qty'],
                    'unit_cost' => $poLine->unit_cost,
                    'uom_id' => $poLine->product->uom_id,
                ];

                // Update PO line received quantity
                $poLine->received_qty += $item['received_qty'];
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

        return response()->json([
            'message' => 'Goods Receipt Note posted successfully.',
            'purchase_order' => new PurchaseOrderResource($purchaseOrder->fresh('lines', 'status')),
            'transaction_id' => $transaction->id,
        ]);
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
                    $unitCost = $suggestion->product->average_cost > 0
                        ? $suggestion->product->average_cost
                        : $suggestion->product->selling_price * 0.6; // Fallback estimate

                    $lineCost = $suggestion->suggested_qty * $unitCost;
                    $totalAmount += $lineCost;

                    $po->lines()->create([
                        'product_id' => $suggestion->product_id,
                        'ordered_qty' => $suggestion->suggested_qty,
                        'received_qty' => 0,
                        'unit_cost' => $unitCost,
                    ]);

                    // Link and update suggestion
                    $suggestion->update([
                        'status' => 'ordered',
                        'purchase_order_id' => $po->id,
                    ]);
                }

                $po->update(['total_amount' => $totalAmount]);
                $posCreated[] = $po->po_number;
            }
        });

        return response()->json([
            'message' => 'Successfully generated '.count($posCreated).' Purchase Orders.',
            'po_numbers' => $posCreated,
        ]);
    }
}
