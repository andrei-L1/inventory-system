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
use App\Models\SalesOrderStatus;
use App\Models\TransactionStatus;
use App\Models\TransactionType;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
                'currency' => $data['currency'] ?? 'PHP',
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
                    'ordered_qty' => round($lineData['ordered_qty'], 8),
                    'unit_price' => round($lineData['unit_price'], 8),
                    'tax_rate' => $lineData['tax_rate'] ?? 0,
                    'tax_amount' => $this->calculateTaxAmount($lineData),
                    'discount_rate' => $lineData['discount_rate'] ?? 0,
                    'discount_amount' => $this->calculateDiscountAmount($lineData),
                    'subtotal' => $lineTotal,
                ]);
            }

            $so->update(['total_amount' => round($totalAmount, 2)]);

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
                'currency' => $data['currency'] ?? 'PHP',
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
                    'ordered_qty' => round($lineData['ordered_qty'], 8),
                    'unit_price' => round($lineData['unit_price'], 8),
                    'tax_rate' => $lineData['tax_rate'] ?? 0,
                    'tax_amount' => $this->calculateTaxAmount($lineData),
                    'discount_rate' => $lineData['discount_rate'] ?? 0,
                    'discount_amount' => $this->calculateDiscountAmount($lineData),
                    'subtotal' => $lineTotal,
                ]);
            }

            $salesOrder->update(['total_amount' => round($totalAmount, 2)]);

            return $salesOrder;
        });

        return new SalesOrderResource($so->load('lines.product.uom'));
    }

    public function destroy(SalesOrder $salesOrder): JsonResponse
    {
        DB::transaction(function () use ($salesOrder) {
            $so = SalesOrder::lockForUpdate()->findOrFail($salesOrder->id);

            if (! $so->status->is_editable) {
                abort(403, 'Sales order cannot be deleted in its current status.');
            }

            $so->delete();
        });

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

            // CREDIT LIMIT ENFORCEMENT
            $customer = $so->customer;
            if ($customer->credit_limit > 0) {
                $exposure = (float) $customer->exposure;
                $newTotal = round($exposure + (float) $so->total_amount, 8);

                if ($newTotal > ((float) $customer->credit_limit + 0.00000001)) {
                    abort(422, "Credit Limit Exceeded. Customer Limit: {$customer->credit_limit}, Current Exposure: {$exposure}, New Order: {$so->total_amount}.");
                }
            }

            $confirmedStatus = SalesOrderStatus::where('name', SalesOrderStatus::CONFIRMED)->firstOrFail();

            // RESERVE STOCK
            foreach ($so->lines as $line) {
                $product = $line->product;
                $location = $line->location;

                // Convert ordered qty to base UOM for reservation
                // round(..., 8) prevents floating-point dust from UOM chain multiplication
                $baseQty = (float) $line->ordered_qty;
                if ($line->uom_id !== $product->uom_id) {
                    $factor = UomHelper::getConversionFactor($line->uom_id, $product->uom_id, $product->id);
                    $baseQty = round($baseQty * $factor, 8);
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

    /**
     * S-M2: Send a quotation to the customer (quotation → quotation_sent).
     * This was a registered route with no implementation — now resolved.
     */
    public function send(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $salesOrder = DB::transaction(function () use ($salesOrder, $request) {
            $so = SalesOrder::lockForUpdate()->findOrFail($salesOrder->id);
            $so->loadMissing('status');

            if ($so->status->name !== SalesOrderStatus::QUOTATION) {
                abort(400, 'Only quotations can be sent. Current status: '.$so->status->name);
            }

            $sentStatus = SalesOrderStatus::where('name', SalesOrderStatus::QUOTATION_SENT)->firstOrFail();

            $so->update([
                'status_id' => $sentStatus->id,
                'sent_at' => now(),
            ]);

            return $so;
        });

        return new SalesOrderResource($salesOrder->load('lines', 'status'));
    }

    public function pick(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $request->validate([
            'lines' => 'required|array',
            'lines.*.so_line_id' => 'required|exists:sales_order_lines,id',
            'lines.*.picked_qty' => 'required|numeric|min:0',
        ]);

        $salesOrder = DB::transaction(function () use ($salesOrder, $request) {
            $so = SalesOrder::lockForUpdate()->findOrFail($salesOrder->id);
            if (! $so->canBePicked()) {
                abort(400, "Sales order cannot be picked in its current status: {$so->status->name}");
            }

            $allPicked = true;
            foreach ($request->lines as $item) {
                $line = $so->lines()->findOrFail($item['so_line_id']);
                $newPicked = (float) $item['picked_qty'];

                // Enforce cap: cannot pick more than ordered
                $remainingToPick = (float) $line->ordered_qty - (float) $line->picked_qty;
                if ($newPicked > ($remainingToPick + 0.00000001)) {
                    abort(422, "Cannot pick more than ordered for product: {$line->product->name}. Remaining to pick: {$remainingToPick}");
                }

                $line->picked_qty += $newPicked;
                $line->save();

                if ($line->picked_qty < $line->ordered_qty) {
                    $allPicked = false;
                }
            }

            // Global check across all lines
            foreach ($so->lines as $line) {
                if ($line->picked_qty < $line->ordered_qty) {
                    $allPicked = false;
                    break;
                }
            }

            // FORWARD-ONLY STATUS UPDATE:
            // Only update status if current state is CONFIRMED or a PICK state.
            // If we are already in PACKED or SHIPPED states, keep the 'higher' status.
            $pickStatuses = [SalesOrderStatus::CONFIRMED, SalesOrderStatus::PARTIALLY_PICKED, SalesOrderStatus::PICKED];
            if (in_array($so->status->name, $pickStatuses)) {
                $statusName = $allPicked ? SalesOrderStatus::PICKED : SalesOrderStatus::PARTIALLY_PICKED;
                $status = SalesOrderStatus::where('name', $statusName)->firstOrFail();
                $so->update(['status_id' => $status->id]);
            }

            return $so;
        });

        return new SalesOrderResource($salesOrder->load('lines', 'status'));
    }

    public function pack(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $request->validate([
            'lines' => 'required|array',
            'lines.*.so_line_id' => 'required|exists:sales_order_lines,id',
            'lines.*.packed_qty' => 'required|numeric|min:0',
        ]);

        $salesOrder = DB::transaction(function () use ($salesOrder, $request) {
            $so = SalesOrder::lockForUpdate()->findOrFail($salesOrder->id);
            if (! $so->canBePacked()) {
                abort(400, "Sales order cannot be packed in its current status: {$so->status->name}");
            }

            $allPacked = true;
            foreach ($request->lines as $item) {
                $line = $so->lines()->findOrFail($item['so_line_id']);
                $newPacked = (float) $item['packed_qty'];

                // Enforce cap: cannot pack more than picked
                $remainingToPack = (float) $line->picked_qty - (float) $line->packed_qty;
                if ($newPacked > ($remainingToPack + 0.00000001)) {
                    abort(422, "Cannot pack more than picked for product: {$line->product->name}. Remaining to pack: {$remainingToPack}");
                }

                $line->packed_qty += $newPacked;
                $line->save();

                if ($line->packed_qty < $line->ordered_qty) {
                    $allPacked = false;
                }
            }

            // Global check
            foreach ($so->lines as $line) {
                if ($line->packed_qty < $line->ordered_qty) {
                    $allPacked = false;
                    break;
                }
            }

            // FORWARD-ONLY STATUS UPDATE:
            // Only update status if current state is a PICK or PACK state.
            // If already in SHIPPED states, keep the 'higher' status.
            $packStatuses = [
                SalesOrderStatus::PARTIALLY_PICKED,
                SalesOrderStatus::PICKED,
                SalesOrderStatus::PARTIALLY_PACKED,
                SalesOrderStatus::PACKED,
            ];
            if (in_array($so->status->name, $packStatuses)) {
                $statusName = $allPacked ? SalesOrderStatus::PACKED : SalesOrderStatus::PARTIALLY_PACKED;
                $status = SalesOrderStatus::where('name', $statusName)->firstOrFail();
                $so->update(['status_id' => $status->id]);
            }

            return $so;
        });

        return new SalesOrderResource($salesOrder->load('lines', 'status'));
    }

    public function ship(Request $request, SalesOrder $salesOrder, StockService $stockService): JsonResponse
    {
        $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.so_line_id' => 'required|exists:sales_order_lines,id',
            'lines.*.shipped_qty' => 'required|numeric|min:0.0001',
            'carrier' => 'required|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
        ], [
            'lines.min' => 'Please select at least one item to ship.',
            'carrier.required' => 'A carrier service is required for fulfillment.',
        ]);

        if (! $salesOrder->canBeShipped()) {
            abort(400, "Sales order cannot be shipped in its current status: {$salesOrder->status->name}");
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
                        'from_location_id' => null,
                    ],
                    'lines' => [],
                ];

                $soLines = $salesOrder->lines()->with(['product', 'location'])->lockForUpdate()->get()->keyBy('id');

                foreach ($request->lines as $item) {
                    $soLine = $soLines->get($item['so_line_id']);
                    $shippedQtyRaw = (float) $item['shipped_qty'];
                    $product = $soLine->product;

                    if ($shippedQtyRaw > ($soLine->packed_qty - $soLine->shipped_qty + 0.00000001)) {
                        $remaining = $soLine->packed_qty - $soLine->shipped_qty;
                        abort(422, "Cannot ship more than what was packed for line: {$product->name}. Remaining: {$remaining}");
                    }

                    // Convert to base UOM
                    $factor = 1.0;
                    if ($soLine->uom_id !== $product->uom_id) {
                        $factor = UomHelper::getConversionFactor($soLine->uom_id, $product->uom_id, $product->id);
                    }
                    $baseShippedQty = round($shippedQtyRaw * $factor, 8);

                    $stockService->releaseReservation($product, $soLine->location, $baseShippedQty);

                    $transactionData['lines'][] = [
                        'product_id' => $soLine->product_id,
                        'location_id' => $soLine->location_id,
                        'quantity' => -abs($shippedQtyRaw),
                        'uom_id' => $soLine->uom_id,
                    ];

                    $soLine->shipped_qty += $shippedQtyRaw;
                    $soLine->save();
                }

                // Global fulfillment check (Refresh relationship to see the saves from $soLine->save() above)
                $salesOrder->unsetRelation('lines');
                $allShipped = true;
                foreach ($salesOrder->lines as $line) {
                    if ($line->shipped_qty < $line->ordered_qty) {
                        $allShipped = false;
                        break;
                    }
                }

                $transaction = $stockService->recordMovement($transactionData);

                $statusName = $allShipped ? SalesOrderStatus::SHIPPED : SalesOrderStatus::PARTIALLY_SHIPPED;
                $status = SalesOrderStatus::where('name', $statusName)->firstOrFail();

                $salesOrder->update([
                    'status_id' => $status->id,
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
            'message' => $salesOrder->status->name === SalesOrderStatus::SHIPPED ? 'Sales Order fully shipped.' : 'Sales Order partially shipped.',
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

            // If confirmed/picked/packed or partially fulfilled, release remaining reservations
            $reservableStatuses = [
                SalesOrderStatus::CONFIRMED,
                SalesOrderStatus::PICKED,
                SalesOrderStatus::PARTIALLY_PICKED,
                SalesOrderStatus::PACKED,
                SalesOrderStatus::PARTIALLY_PACKED,
                SalesOrderStatus::PARTIALLY_SHIPPED,
            ];

            if (in_array($so->status->name, $reservableStatuses)) {
                foreach ($so->lines as $line) {
                    $product = $line->product;
                    $location = $line->location;

                    $baseQty = (float) $line->ordered_qty - (float) $line->shipped_qty;
                    if ($baseQty > 0.00000001) {
                        if ($line->uom_id !== $product->uom_id) {
                            $factor = UomHelper::getConversionFactor($line->uom_id, $product->uom_id, $product->id);
                            $baseQty = round($baseQty * $factor, 8);
                        }
                        $stockService->releaseReservation($product, $location, $baseQty);
                    }
                }
            }

            $cancelStatus = SalesOrderStatus::where('name', SalesOrderStatus::CANCELLED)->firstOrFail();
            $so->update(['status_id' => $cancelStatus->id]);

            return $so;
        });

        return new SalesOrderResource($salesOrder->load('lines', 'status'));
    }

    public function print(SalesOrder $salesOrder)
    {
        $salesOrder->load([
            'customer',
            'lines.product.uom',
            'lines.location',
            'status',
            'creator',
            'approver',
        ]);

        $company = [
            'name' => config('app.name', 'Nexus Logistics'),
            'address' => 'Warehouse District, Sector 7',
            'phone' => '+1 (555) NEXUS-LOG',
            'email' => 'logistics@nexus-system.io',
        ];

        return view('sales.sales-order-print', compact('salesOrder', 'company'));
    }

    private function calculateLineSubtotal(array $data): float
    {
        $qty = (float) $data['ordered_qty'];
        $price = (float) $data['unit_price'];
        $taxRate = (float) ($data['tax_rate'] ?? 0);
        $discountRate = (float) ($data['discount_rate'] ?? 0);

        // All intermediate values are kept at full float precision.
        // Only the final result is rounded to 8 decimals to prevent drift
        // when summing many lines into a total_amount.
        $base = $qty * $price;
        $discount = $base * ($discountRate / 100);
        $taxable = $base - $discount;
        $tax = $taxable * ($taxRate / 100);

        return round($taxable + $tax, 8);
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

        // round to 8 to match the 8-decimal DB standard
        return round($taxable * ($taxRate / 100), 8);
    }

    private function calculateDiscountAmount(array $data): float
    {
        $qty = (float) $data['ordered_qty'];
        $price = (float) $data['unit_price'];
        $discountRate = (float) ($data['discount_rate'] ?? 0);

        // round to 8 to match the 8-decimal DB standard
        return round(($qty * $price) * ($discountRate / 100), 8);
    }
}
