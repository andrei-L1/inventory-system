<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\UomConversionException;
use App\Helpers\UomHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesOrderStoreRequest;
use App\Http\Requests\Sales\SalesOrderUpdateRequest;
use App\Http\Resources\Sales\SalesOrderResource;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SalesOrderStatus;
use App\Models\TransactionStatus;
use App\Models\TransactionType;
use App\Models\UnitOfMeasure;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SalesOrder::with(['customer', 'status', 'creator', 'approver'])
            ->latest('id');

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('status')) {
            $query->whereHas('status', function ($q) use ($request) {
                $q->where('name', $request->status);
            });
        }

        return SalesOrderResource::collection($query->paginate($request->get('limit', 15)));
    }

    public function show(SalesOrder $salesOrder): SalesOrderResource
    {
        $salesOrder->load([
            'lines.product.uom',
            'lines.location',
            'customer',
            'status',
            'creator',
            'approver',
            'transactions.createdBy',
            'transactions.lines.product.uom',
        ]);

        return new SalesOrderResource($salesOrder);
    }

    public function store(SalesOrderStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $statusId = SalesOrderStatus::where('name', SalesOrderStatus::QUOTATION)->value('id');

        $so = DB::transaction(function () use ($data, $statusId, $request) {
            $so = SalesOrder::create([
                'so_number' => 'SO-'.now()->format('Ymd-Hi').'-'.rand(10, 99),
                'customer_id' => $data['customer_id'],
                'status_id' => $statusId,
                'order_date' => $data['order_date'],
                'expected_shipping_date' => $data['expected_shipping_date'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
                'total_amount' => 0,
            ]);

            $totalAmount = 0.0;
            foreach ($data['lines'] as $lineData) {
                $lineTotal = $this->calculateLineSubtotal($lineData);
                $totalAmount += $lineTotal;

                $so->lines()->create([
                    'product_id' => $lineData['product_id'],
                    'location_id' => $lineData['location_id'],
                    'uom_id' => $lineData['uom_id'],
                    'ordered_qty' => $lineData['ordered_qty'],
                    'unit_price' => $lineData['unit_price'],
                    'tax_rate' => $lineData['tax_rate'] ?? 0,
                    'tax_amount' => $this->calculateTaxAmount($lineData),
                    'discount_rate' => $lineData['discount_rate'] ?? 0,
                    'discount_amount' => $this->calculateDiscountAmount($lineData),
                    'subtotal' => $lineTotal,
                ]);
            }

            $so->update(['total_amount' => $totalAmount]);

            return $so;
        });

        return (new SalesOrderResource($so->load('lines.product.uom')))->response()->setStatusCode(201);
    }

    public function update(SalesOrderUpdateRequest $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $data = $request->validated();

        $so = DB::transaction(function () use ($data, $salesOrder) {
            if (! $salesOrder->status->is_editable) {
                $salesOrder->update([
                    'expected_shipping_date' => $data['expected_shipping_date'] ?? $salesOrder->expected_shipping_date,
                    'notes' => $data['notes'] ?? $salesOrder->notes,
                ]);
                return $salesOrder;
            }

            $salesOrder->update([
                'customer_id' => $data['customer_id'],
                'order_date' => $data['order_date'],
                'expected_shipping_date' => $data['expected_shipping_date'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'notes' => $data['notes'] ?? null,
            ]);

            $salesOrder->lines()->delete();

            $totalAmount = 0.0;
            foreach ($data['lines'] as $lineData) {
                $lineTotal = $this->calculateLineSubtotal($lineData);
                $totalAmount += $lineTotal;

                $salesOrder->lines()->create([
                    'product_id' => $lineData['product_id'],
                    'location_id' => $lineData['location_id'],
                    'uom_id' => $lineData['uom_id'],
                    'ordered_qty' => $lineData['ordered_qty'],
                    'unit_price' => $lineData['unit_price'],
                    'tax_rate' => $lineData['tax_rate'] ?? 0,
                    'tax_amount' => $this->calculateTaxAmount($lineData),
                    'discount_rate' => $lineData['discount_rate'] ?? 0,
                    'discount_amount' => $this->calculateDiscountAmount($lineData),
                    'subtotal' => $lineTotal,
                ]);
            }

            $salesOrder->update(['total_amount' => $totalAmount]);

            return $salesOrder;
        });

        return new SalesOrderResource($so->load('lines.product.uom'));
    }

    public function destroy(SalesOrder $salesOrder): JsonResponse
    {
        if (! $salesOrder->status->is_editable) {
            abort(403, 'Sales order cannot be deleted in its current status.');
        }

        $salesOrder->delete();

        return response()->json(null, 204);
    }

    public function approve(Request $request, SalesOrder $salesOrder, StockService $stockService): SalesOrderResource
    {
        $salesOrder = DB::transaction(function () use ($salesOrder, $request, $stockService) {
            $so = SalesOrder::lockForUpdate()->findOrFail($salesOrder->id);
            $so->loadMissing(['status', 'lines.product', 'lines.location']);

            if (! $so->isDraft()) {
                abort(400, 'Only draft sales orders can be approved.');
            }

            $confirmedStatus = SalesOrderStatus::where('name', SalesOrderStatus::CONFIRMED)->firstOrFail();

            // RESERVE STOCK
            foreach ($so->lines as $line) {
                $product = $line->product;
                $location = $line->location;
                
                // Convert ordered qty to base UOM for reservation
                $baseQty = $line->ordered_qty;
                if ($line->uom_id !== $product->uom_id) {
                    $factor = UomHelper::getConversionFactor($line->uom_id, $product->uom_id);
                    $baseQty *= $factor;
                }

                $stockService->reserveStock($product, $location, $baseQty);
            }

            $so->update([
                'status_id' => $confirmedStatus->id,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            return $so;
        });

        return new SalesOrderResource($salesOrder->load('lines', 'status'));
    }

    public function pick(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $salesOrder = DB::transaction(function () use ($salesOrder, $request) {
            $so = SalesOrder::lockForUpdate()->findOrFail($salesOrder->id);
            if ($so->status->name !== SalesOrderStatus::CONFIRMED) {
                abort(400, 'Only confirmed sales orders can be picked.');
            }

            $pickStatus = SalesOrderStatus::where('name', SalesOrderStatus::PICKED)->firstOrFail();
            $so->update(['status_id' => $pickStatus->id]);

            return $so;
        });

        return new SalesOrderResource($salesOrder->load('lines', 'status'));
    }

    public function pack(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $salesOrder = DB::transaction(function () use ($salesOrder, $request) {
            $so = SalesOrder::lockForUpdate()->findOrFail($salesOrder->id);
            if ($so->status->name !== SalesOrderStatus::PICKED) {
                abort(400, 'Only picked sales orders can be packed.');
            }

            $packStatus = SalesOrderStatus::where('name', SalesOrderStatus::PACKED)->firstOrFail();
            $so->update(['status_id' => $packStatus->id]);

            return $so;
        });

        return new SalesOrderResource($salesOrder->load('lines', 'status'));
    }

    public function ship(Request $request, SalesOrder $salesOrder, StockService $stockService): JsonResponse
    {
        $request->validate([
            'lines' => 'required|array',
            'lines.*.so_line_id' => 'required|exists:sales_order_lines,id',
            'lines.*.shipped_qty' => 'required|numeric|min:0.0001',
            'carrier' => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
        ]);

        if (! $salesOrder->canBeShipped()) {
            abort(400, 'Sales order must be confirmed to be fulfilled.');
        }

        try {
            $transaction = DB::transaction(function () use ($request, $salesOrder, $stockService) {
                $issueType = TransactionType::where('name', 'issue')->firstOrFail();
                $postedStatus = TransactionStatus::where('name', 'posted')->firstOrFail();

                $transactionData = [
                    'header' => [
                        'transaction_type_id' => $issueType->id,
                        'transaction_status_id' => $postedStatus->id,
                        'transaction_date' => now()->toDateString(),
                        'reference_number' => 'SHP-'.$salesOrder->so_number.'-'.substr(uniqid(), -4),
                        'customer_id' => $salesOrder->customer_id,
                        'sales_order_id' => $salesOrder->id,
                        'reference_doc' => $salesOrder->so_number,
                        'notes' => 'Shipment for SO: '.$salesOrder->so_number,
                        'created_by' => $request->user()->id,
                        'from_location_id' => null, // Will use line location
                    ],
                    'lines' => [],
                ];

                $soLines = $salesOrder->lines()->lockForUpdate()->get()->keyBy('id');

                foreach ($request->lines as $item) {
                    $soLine = $soLines->get($item['so_line_id']);
                    $shippedQtyRaw = (float) $item['shipped_qty'];
                    $product = $soLine->product;

                    // Convert to base UOM for physical issue
                    $factor = 1.0;
                    if ($soLine->uom_id !== $product->uom_id) {
                        $factor = UomHelper::getConversionFactor($soLine->uom_id, $product->uom_id);
                    }
                    $baseShippedQty = $shippedQtyRaw * $factor;

                    // 1. Release Reservation
                    $stockService->releaseReservation($product, $soLine->location, $baseShippedQty);

                    // 2. Prepare Issue Line
                    $transactionData['lines'][] = [
                        'product_id' => $soLine->product_id,
                        'location_id' => $soLine->location_id,
                        'quantity' => -abs($shippedQtyRaw), // Negative for ISSUE
                        'uom_id' => $soLine->uom_id,
                    ];

                    // 3. Update SO line
                    $soLine->shipped_qty += $shippedQtyRaw;
                    $soLine->save();
                }

                // 4. Record Movement (Decrements QOH, consumes layers, records COGS)
                $transaction = $stockService->recordMovement($transactionData);

                // 5. Update SO Status
                $salesOrder->refresh();
                $shippedStatus = SalesOrderStatus::where('name', SalesOrderStatus::SHIPPED)->firstOrFail();
                
                $salesOrder->update([
                    'status_id' => $shippedStatus->id,
                    'shipped_at' => now(),
                    'carrier' => $request->carrier,
                    'tracking_number' => $request->tracking_number,
                ]);

                return $transaction;
            });
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (UomConversionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sales Order shipped successfully.',
            'sales_order' => new SalesOrderResource($salesOrder->fresh('lines', 'status')),
            'transaction_id' => $transaction->id,
        ]);
    }

    public function cancel(Request $request, SalesOrder $salesOrder, StockService $stockService): SalesOrderResource
    {
        $salesOrder = DB::transaction(function () use ($salesOrder, $stockService) {
            $so = SalesOrder::lockForUpdate()->findOrFail($salesOrder->id);
            
            if (in_array($so->status->name, [SalesOrderStatus::SHIPPED, SalesOrderStatus::CANCELLED, SalesOrderStatus::CLOSED])) {
                abort(400, "Cannot cancel sales order in {$so->status->name} status.");
            }

            // If confirmed/picked/packed, release reservations
            if (in_array($so->status->name, [SalesOrderStatus::CONFIRMED, SalesOrderStatus::PICKED, SalesOrderStatus::PACKED])) {
                foreach ($so->lines as $line) {
                    $product = $line->product;
                    $location = $line->location;
                    
                    $baseQty = $line->ordered_qty;
                    if ($line->uom_id !== $product->uom_id) {
                        $factor = UomHelper::getConversionFactor($line->uom_id, $product->uom_id);
                        $baseQty *= $factor;
                    }

                    $stockService->releaseReservation($product, $location, $baseQty);
                }
            }

            $cancelStatus = SalesOrderStatus::where('name', SalesOrderStatus::CANCELLED)->firstOrFail();
            $so->update(['status_id' => $cancelStatus->id]);

            return $so;
        });

        return new SalesOrderResource($salesOrder->load('lines', 'status'));
    }

    private function calculateLineSubtotal(array $data): float
    {
        $qty = (float) $data['ordered_qty'];
        $price = (float) $data['unit_price'];
        $taxRate = (float) ($data['tax_rate'] ?? 0);
        $discountRate = (float) ($data['discount_rate'] ?? 0);

        $base = $qty * $price;
        $discount = $base * ($discountRate / 100);
        $taxable = $base - $discount;
        $tax = $taxable * ($taxRate / 100);

        return round($taxable + $tax, 6);
    }

    private function calculateTaxAmount(array $data): float
    {
        $qty = (float) $data['ordered_qty'];
        $price = (float) $data['unit_price'];
        $taxRate = (float) ($data['tax_rate'] ?? 0);
        $discountRate = (float) ($data['discount_rate'] ?? 0);

        $base = $qty * $price;
        $discount = $base * ($discountRate / 100);
        $taxable = $base - $discount;

        return round($taxable * ($taxRate / 100), 6);
    }

    private function calculateDiscountAmount(array $data): float
    {
        $qty = (float) $data['ordered_qty'];
        $price = (float) $data['unit_price'];
        $discountRate = (float) ($data['discount_rate'] ?? 0);

        return round(($qty * $price) * ($discountRate / 100), 6);
    }
}
