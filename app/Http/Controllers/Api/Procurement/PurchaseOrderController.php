<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\PurchaseOrderStoreRequest;
use App\Http\Requests\Procurement\PurchaseOrderUpdateRequest;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatus;
use App\Models\TransactionStatus;
use App\Models\TransactionType;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        $purchaseOrder->load(['lines.product.uom', 'vendor', 'status', 'creator', 'approver']);
        return new PurchaseOrderResource($purchaseOrder);
    }

    public function store(PurchaseOrderStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $statusId = PurchaseOrderStatus::where('name', 'draft')->value('id');

        $po = DB::transaction(function () use ($data, $statusId, $request) {
            $po = PurchaseOrder::create([
                'po_number' => 'PO-' . now()->format('Ymd-Hi') . '-' . rand(10, 99),
                'vendor_id' => $data['vendor_id'],
                'status_id' => $statusId,
                'order_date' => now(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id ?? 1,
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
                    'total_line_cost' => $lineCost,
                ]);
            }

            $po->update(['total_amount' => $totalAmount]);

            return $po;
        });

        return response()->json(new PurchaseOrderResource($po->load('lines.product.uom')), 201);
    }

    public function update(PurchaseOrderUpdateRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (!$purchaseOrder->status->is_editable) {
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
                    'total_line_cost' => $lineCost,
                ]);
            }

            $purchaseOrder->update(['total_amount' => $totalAmount]);

            return $purchaseOrder;
        });

        return response()->json(new PurchaseOrderResource($po->load('lines.product.uom')));
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (!$purchaseOrder->status->is_editable) {
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
            'approved_by' => $request->user()->id ?? 1,
            'approved_at' => now(),
        ]);

        return response()->json(new PurchaseOrderResource($purchaseOrder->load('lines')));
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
                    'reference_number' => 'GRN-' . $purchaseOrder->po_number . '-' . substr(uniqid(), -4),
                    'vendor_id' => $purchaseOrder->vendor_id,
                    'reference_doc' => $purchaseOrder->po_number,
                    'notes' => 'Goods Receipt Note for PO: ' . $purchaseOrder->po_number,
                ],
                'lines' => [],
            ];

            $poLines = $purchaseOrder->lines()->get()->keyBy('id');

            foreach ($request->lines as $item) {
                $poLine = $poLines->get($item['po_line_id']);
                
                if (!$poLine) {
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
}
