<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\UomConversionException;
use App\Helpers\FinancialMath;
use App\Helpers\UomHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesOrderStoreRequest;
use App\Http\Requests\Sales\SalesOrderUpdateRequest;
use App\Http\Resources\Sales\SalesOrderResource;
use App\Models\Carrier;
use App\Models\ProductSerial;
use App\Models\SalesOrder;
use App\Models\SalesOrderStatus;
use App\Models\Shipment;
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

        if ($request->boolean('invoiceable')) {
            // S-M3: Deep SQL filter for orders that have truly remaining items to bill (Shipped > Invoiced).
            // This handles VOID invoices and soft-deletes at the DB layer for absolute accuracy.
            $query->whereHas('lines', function ($l) {
                $l->whereRaw('
                    shipped_qty > COALESCE(
                        (SELECT SUM(quantity) 
                         FROM invoice_lines 
                         WHERE invoice_lines.sales_order_line_id = sales_order_lines.id
                         AND invoice_lines.deleted_at IS NULL
                         AND NOT EXISTS (
                             SELECT 1 FROM invoices 
                             WHERE invoices.id = invoice_lines.invoice_id 
                             AND invoices.status = "VOID"
                         )
                        ), 0
                    )
                ');
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
            'shipments.carrier',
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

            $lineTotals = [];
            foreach ($data['lines'] as $lineData) {
                // Cast all financial inputs to string immediately to satisfy FinancialMath strictness
                $qty = (string) $lineData['ordered_qty'];
                $price = (string) $lineData['unit_price'];
                $discount = (string) ($lineData['discount_rate'] ?? 0);
                $tax = (string) ($lineData['tax_rate'] ?? 0);

                $lineTotal = FinancialMath::soLineSubtotal($qty, $price, $discount, $tax);
                $lineTotals[] = $lineTotal;

                $so->lines()->create([
                    'product_id' => $lineData['product_id'],
                    'location_id' => $lineData['location_id'],
                    'uom_id' => $lineData['uom_id'],
                    'ordered_qty' => FinancialMath::round($qty, FinancialMath::LINE_SCALE),
                    'unit_price' => FinancialMath::round($price, FinancialMath::LINE_SCALE),
                    'tax_rate' => $tax,
                    'tax_amount' => FinancialMath::soLineTax($qty, $price, $discount, $tax),
                    'discount_rate' => $discount,
                    'discount_amount' => FinancialMath::soLineDiscount($qty, $price, $discount),
                    'subtotal' => $lineTotal,
                ]);
            }

            $so->update(['total_amount' => FinancialMath::headerTotal($lineTotals)]);

            return $so;
        });

        return (new SalesOrderResource($so->refresh()->load('lines.product.uom')))->response()->setStatusCode(201);
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

            $lineTotals = [];
            foreach ($data['lines'] as $lineData) {
                $qty = (string) $lineData['ordered_qty'];
                $price = (string) $lineData['unit_price'];
                $discount = (string) ($lineData['discount_rate'] ?? 0);
                $tax = (string) ($lineData['tax_rate'] ?? 0);

                $lineTotal = FinancialMath::soLineSubtotal($qty, $price, $discount, $tax);
                $lineTotals[] = $lineTotal;

                $salesOrder->lines()->create([
                    'product_id' => $lineData['product_id'],
                    'location_id' => $lineData['location_id'],
                    'uom_id' => $lineData['uom_id'],
                    'ordered_qty' => FinancialMath::round($qty, FinancialMath::LINE_SCALE),
                    'unit_price' => FinancialMath::round($price, FinancialMath::LINE_SCALE),
                    'tax_rate' => $tax,
                    'tax_amount' => FinancialMath::soLineTax($qty, $price, $discount, $tax),
                    'discount_rate' => $discount,
                    'discount_amount' => FinancialMath::soLineDiscount($qty, $price, $discount),
                    'subtotal' => $lineTotal,
                ]);
            }

            $salesOrder->update(['total_amount' => FinancialMath::headerTotal($lineTotals)]);

            return $salesOrder;
        });

        return new SalesOrderResource($so->refresh()->load('lines.product.uom'));
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
                $newTotal = FinancialMath::add((string) $customer->exposure, (string) $so->total_amount);

                if (FinancialMath::gt($newTotal, (string) $customer->credit_limit)) {
                    abort(422, "Credit Limit Exceeded. Customer Limit: {$customer->credit_limit}, Current Exposure: {$customer->exposure}, New Order: {$so->total_amount}.");
                }
            }

            $confirmedStatus = SalesOrderStatus::where('name', SalesOrderStatus::CONFIRMED)->firstOrFail();

            // RESERVE STOCK
            foreach ($so->lines as $line) {
                $product = $line->product;
                $location = $line->location;

                // Convert ordered qty to base UOM for reservation
                // round(..., 8) prevents floating-point dust from UOM chain multiplication
                $baseQty = (string) $line->ordered_qty;
                if ($line->uom_id !== $product->uom_id) {
                    $factor = UomHelper::getConversionFactor($line->uom_id, $product->uom_id, $product->id);
                    $baseQty = FinancialMath::round(FinancialMath::mul($baseQty, $factor), FinancialMath::LINE_SCALE);
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
        $salesOrder = DB::transaction(function () use ($salesOrder) {
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
                $newPicked = (string) $item['picked_qty'];

                // Enforce cap: cannot pick more than ordered
                $remainingToPick = FinancialMath::sub((string) $line->ordered_qty, (string) $line->picked_qty);
                if (FinancialMath::gt($newPicked, $remainingToPick)) {
                    abort(422, "Cannot pick more than ordered for product: {$line->product->name}. Remaining to pick: {$remainingToPick}");
                }

                $line->picked_qty = FinancialMath::add((string) $line->picked_qty, $newPicked);
                $line->save();

                if (FinancialMath::lt((string) $line->picked_qty, (string) $line->ordered_qty)) {
                    $allPicked = false;
                }
            }

            // Global check across all lines
            foreach ($so->lines as $line) {
                if (FinancialMath::lt((string) $line->picked_qty, (string) $line->ordered_qty)) {
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
                $newPacked = (string) $item['packed_qty'];

                // Enforce cap: cannot pack more than picked
                $remainingToPack = FinancialMath::sub((string) $line->picked_qty, (string) $line->packed_qty);
                if (FinancialMath::gt($newPacked, $remainingToPack)) {
                    abort(422, "Cannot pack more than picked for product: {$line->product->name}. Remaining to pack: {$remainingToPack}");
                }

                $line->packed_qty = FinancialMath::add((string) $line->packed_qty, $newPacked);
                $line->save();

                if (FinancialMath::lt((string) $line->packed_qty, (string) $line->ordered_qty)) {
                    $allPacked = false;
                }
            }

            // Global check
            foreach ($so->lines as $line) {
                if (FinancialMath::lt((string) $line->packed_qty, (string) $line->ordered_qty)) {
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
            // Phase 6.3: optional serial IDs per line (must be in_stock serials)
            'lines.*.serial_ids' => 'nullable|array',
            'lines.*.serial_ids.*' => 'integer|exists:product_serials,id',
            'carrier_id' => 'required|exists:carriers,id',
            'tracking_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ], [
            'lines.min' => 'Please select at least one item to ship.',
            'carrier_id.required' => 'A carrier is required for fulfillment.',
            'carrier_id.exists' => 'The selected carrier does not exist.',
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
                    $shippedQtyRaw = (string) $item['shipped_qty'];
                    $product = $soLine->product;

                    $remainingToShip = FinancialMath::sub((string) $soLine->packed_qty, (string) $soLine->shipped_qty);
                    if (FinancialMath::gt($shippedQtyRaw, $remainingToShip)) {
                        abort(422, "Cannot ship more than what was packed for line: {$product->name}. Remaining: {$remainingToShip}");
                    }

                    // Convert to base UOM
                    $factor = '1';
                    if ($soLine->uom_id !== $product->uom_id) {
                        $factor = UomHelper::getConversionFactor($soLine->uom_id, $product->uom_id, $product->id);
                    }
                    $baseShippedQty = FinancialMath::round(FinancialMath::mul($shippedQtyRaw, $factor), FinancialMath::LINE_SCALE);

                    $stockService->releaseReservation($product, $soLine->location, $baseShippedQty);

                    // Negate safely without (float) cast
                    $negQty = '-'.ltrim($shippedQtyRaw, '-');
                    $transactionData['lines'][] = [
                        'product_id' => $soLine->product_id,
                        'location_id' => $soLine->location_id,
                        'quantity' => $negQty,
                        'uom_id' => $soLine->uom_id,
                    ];

                    $soLine->shipped_qty = FinancialMath::add((string) $soLine->shipped_qty, $shippedQtyRaw);
                    $soLine->save();
                }

                // Global fulfillment check (Refresh relationship to see the saves from $soLine->save() above)
                $salesOrder->unsetRelation('lines');
                $allShipped = true;
                foreach ($salesOrder->lines as $line) {
                    if (FinancialMath::lt((string) $line->shipped_qty, (string) $line->ordered_qty)) {
                        $allShipped = false;
                        break;
                    }
                }

                $transaction = $stockService->recordMovement($transactionData);

                $statusName = $allShipped ? SalesOrderStatus::SHIPPED : SalesOrderStatus::PARTIALLY_SHIPPED;
                $status = SalesOrderStatus::where('name', $statusName)->firstOrFail();

                // Resolve the carrier model for name storage and Shipment creation
                $carrier = Carrier::findOrFail($request->carrier_id);

                $salesOrder->update([
                    'status_id' => $status->id,
                    'shipped_at' => now(),
                    'carrier' => $carrier->name,
                    'tracking_number' => $request->tracking_number,
                ]);

                // --- Phase 6.1: Auto-create a Shipment entity ---
                Shipment::create([
                    'shipment_number' => 'SHP-'.$salesOrder->so_number.'-'.strtoupper(substr(uniqid(), -5)),
                    'sales_order_id' => $salesOrder->id,
                    'transaction_id' => $transaction->id,
                    'carrier_id' => $carrier->id,
                    'tracking_number' => $request->tracking_number,
                    'status' => 'shipped',
                    'shipped_at' => now(),
                    'notes' => $request->notes ?? null,
                ]);

                // --- Phase 6.3: Mark shipped serials as sold (opt-in) ---
                // Build a map of so_line_id → [serial_ids]
                $serialIdsByLineId = [];
                foreach ($request->lines as $item) {
                    if (! empty($item['serial_ids'])) {
                        $serialIdsByLineId[$item['so_line_id']] = $item['serial_ids'];
                    }
                }

                if (! empty($serialIdsByLineId)) {
                    $transaction->load('lines');
                    // Map product_id → transaction_line for quick lookup
                    $txLinesByProductId = $transaction->lines->keyBy('product_id');

                    foreach ($request->lines as $item) {
                        $serialIds = $item['serial_ids'] ?? [];
                        if (empty($serialIds)) {
                            continue;
                        }

                        $soLine = $soLines->get($item['so_line_id']);
                        if (! $soLine) {
                            continue;
                        }

                        $txLine = $txLinesByProductId->get($soLine->product_id);
                        if (! $txLine) {
                            continue;
                        }

                        $serialsToShip = ProductSerial::whereIn('id', $serialIds)
                            ->where('product_id', $soLine->product_id)
                            ->where('status', ProductSerial::STATUS_IN_STOCK)
                            ->get();

                        foreach ($serialsToShip as $serial) {
                            $serial->update([
                                'status' => ProductSerial::STATUS_SOLD,
                                'current_location_id' => null,
                            ]);
                            $txLine->serials()->syncWithoutDetaching([$serial->id]);
                        }
                    }
                }

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

                    $baseQty = FinancialMath::sub((string) $line->ordered_qty, (string) $line->shipped_qty);
                    if (FinancialMath::isPositive($baseQty)) {
                        if ($line->uom_id !== $product->uom_id) {
                            $factor = UomHelper::getConversionFactor($line->uom_id, $product->uom_id, $product->id);
                            $baseQty = FinancialMath::round(FinancialMath::mul($baseQty, $factor), FinancialMath::LINE_SCALE);
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

    private function calculateLineSubtotal(array $data): string
    {
        return FinancialMath::soLineSubtotal(
            $data['ordered_qty'],
            $data['unit_price'],
            $data['discount_rate'] ?? 0,
            $data['tax_rate'] ?? 0,
        );
    }

    private function calculateTaxAmount(array $data): string
    {
        return FinancialMath::soLineTax(
            $data['ordered_qty'],
            $data['unit_price'],
            $data['discount_rate'] ?? 0,
            $data['tax_rate'] ?? 0,
        );
    }

    private function calculateDiscountAmount(array $data): string
    {
        return FinancialMath::soLineDiscount(
            $data['ordered_qty'],
            $data['unit_price'],
            $data['discount_rate'] ?? 0,
        );
    }
}
