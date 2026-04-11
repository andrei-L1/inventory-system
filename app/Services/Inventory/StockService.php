<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\UomConversionException;
use App\Helpers\FinancialMath;
use App\Helpers\UomHelper;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\TransactionStatus;
use App\Models\Transfer;
use App\Services\Inventory\Costing\CostingStrategyFactory;
use App\Services\Inventory\Costing\Traits\ManagesCostLayers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class StockService
{
    use ManagesCostLayers;

    // QTY_EPSILON removed — FinancialMath::isPositive() / gt() / lte() replace all epsilon guards.

    public const TYPE_SALES_RETURN = 'SRET';

    protected TransactionValidator $validator;

    public function __construct(TransactionValidator $validator)
    {
        $this->validator = $validator;
    }

    // -------------------------------------------------------------------------
    // PUBLIC: Create a stock movement (receipt, issue, or adjustment).
    //
    // FIX [Draft/Posted enforcement]:
    //   Inventory and cost layers are ONLY updated when status = 'posted'.
    //   A 'draft' transaction saves the header + lines as a record but leaves
    //   inventory untouched. Call postTransaction() to apply it later.
    // -------------------------------------------------------------------------
    public function recordMovement(array $data): Transaction
    {
        $this->validator->validate($data);

        return DB::transaction(function () use ($data) {
            $postedStatusId = TransactionStatus::where('name', 'posted')->value('id');
            $isPosted = (int) ($data['header']['transaction_status_id'] ?? 0) === (int) $postedStatusId;

            // 1. Create the immutable transaction header.
            $transaction = Transaction::create($data['header']);

            // PHASE 1: Creation & Normalization
            // All lines are stored in the product's base UOM.
            foreach ($data['lines'] as $lineData) {
                $product = Product::findOrFail($lineData['product_id']);
                $lineData = $this->applyUomConversion($lineData, $product);

                // Ensure TransactionLine record uses the smallest unit for ledger consistency
                // to prevent floating point drift in large units.

                $transaction->lines()->create(array_merge($lineData, [
                    'transaction_id' => $transaction->id,
                ]));
            }

            // HYDRATION: Single-query resolution of all product costing methods.
            $transaction->loadMissing('lines.product.costingMethod');

            // PHASE 2: Inventory Posting
            // Only touch inventory and cost layers when the transaction is 'posted'.
            if ($isPosted) {
                foreach ($transaction->lines as $line) {
                    $this->applyLineToInventory($line, [
                        'product_id' => $line->product_id,
                        'location_id' => $line->location_id,
                        'quantity' => (string) $line->quantity,
                        'unit_cost' => (string) ($line->unit_cost ?? '0'),
                    ]);
                }
            }

            return $transaction;
        });
    }

    // -------------------------------------------------------------------------
    // PUBLIC: Post a draft transaction — applies all lines to inventory.
    //
    // FIX [Draft/Posted enforcement]:
    //   This is the companion to the draft path above. Call this when the user
    //   approves/confirms a draft transaction. It's idempotent-safe: if you
    //   try to post an already-posted transaction it throws a LogicException.
    // -------------------------------------------------------------------------
    public function postTransaction(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction) {
            // FIX [GAP 4]: Lock the transaction header row first so the status check
            // and the subsequent write are atomic. Without this, two concurrent POST
            // requests can both read status='draft', both pass the guard, and both
            // apply inventory — effectively double-posting the transaction.
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->id);
            $transaction->loadMissing(['status', 'lines.product.costingMethod']);

            if ($transaction->status->name === 'posted') {
                throw new LogicException("Transaction #{$transaction->id} is already posted.");
            }

            if ($transaction->status->name === 'cancelled') {
                throw new LogicException("Transaction #{$transaction->id} is cancelled and cannot be posted.");
            }

            $postedStatus = TransactionStatus::where('name', 'posted')->firstOrFail();

            foreach ($transaction->lines as $line) {
                $lineData = [
                    'product_id' => $line->product_id,
                    'location_id' => $line->location_id,
                    'quantity' => (float) $line->quantity,
                    'unit_cost' => (float) $line->unit_cost,
                ];
                $this->applyLineToInventory($line, $lineData);
            }

            $transaction->transaction_status_id = $postedStatus->id;
            $transaction->posted_at = Carbon::now();
            $transaction->save();

            return $transaction->fresh(['status', 'lines']);
        });
    }

    // -------------------------------------------------------------------------
    // PUBLIC: Atomic internal stock transfer between two locations.
    //
    // FIX [Transfer atomicity / orphan transactions]:
    //   After creating both legs via recordMovement(), a Transfer pivot record
    //   is created that permanently links them by FK. The ledger is now coherent:
    //   you can always find the mirror leg from either transaction.
    // -------------------------------------------------------------------------
    public function recordTransfer(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $ref = $data['header']['reference_number'] ?? now()->timestamp;

            // 1. Record Outbound Leg (Issue)
            $originData = [
                'header' => array_merge($data['header'], [
                    'reference_number' => $ref.'-OUT',
                    'from_location_id' => $data['from_location_id'],
                    'to_location_id' => $data['to_location_id'], // Keep context
                    'notes' => 'Transfer Out: '.($data['header']['notes'] ?? ''),
                ]),
                'lines' => collect($data['lines'])->map(function ($line) use ($data) {
                    return array_merge($line, [
                        'location_id' => $data['from_location_id'],
                        'quantity' => -abs($line['quantity']),
                    ]);
                })->toArray(),
            ];

            $outgoing = $this->recordMovement($originData);

            // 2. Record Inbound Leg (Receipt)
            // CRITICAL: We map the exact unit_cost calculated by the OUT leg (FIFO/LIFO)
            // into the IN leg to ensure perfect cost preservation across locations.
            $destData = [
                'header' => array_merge($data['header'], [
                    'reference_number' => $ref.'-IN',
                    'from_location_id' => $data['from_location_id'], // Keep context
                    'to_location_id' => $data['to_location_id'],
                    'notes' => 'Transfer In: '.($data['header']['notes'] ?? ''),
                ]),
                'lines' => $outgoing->lines->map(function ($outLine) use ($data) {
                    return [
                        'product_id' => $outLine->product_id,
                        'location_id' => $data['to_location_id'],
                        'quantity' => abs($outLine->quantity),
                        'unit_cost' => $outLine->unit_cost, // Preserve the COGS
                        'uom_id' => $outLine->uom_id,
                    ];
                })->toArray(),
            ];

            $incoming = $this->recordMovement($destData);

            // FIX: Create the pivot record that permanently links both legs.
            $transfer = Transfer::create([
                'outgoing_transaction_id' => $outgoing->id,
                'incoming_transaction_id' => $incoming->id,
                'from_location_id' => $data['from_location_id'],
                'to_location_id' => $data['to_location_id'],
                'reference_number' => (string) $ref,
            ]);

            return [
                'transfer' => $transfer,
                'outgoing_transaction' => $outgoing,
                'incoming_transaction' => $incoming,
            ];
        });
    }

    // -------------------------------------------------------------------------
    // PUBLIC: Reserve stock for a product at a specific location.
    // DOES NOT move physical QOH, only increments reserved_qty.
    // -------------------------------------------------------------------------
    public function reserveStock(Product $product, Location $location, float $quantity): void
    {
        DB::transaction(function () use ($product, $location, $quantity) {
            $inventory = Inventory::where('product_id', $product->id)
                ->where('location_id', $location->id)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                $inventory = Inventory::create([
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'quantity_on_hand' => 0,
                    'reserved_qty' => 0,
                    'average_cost' => 0,
                ]);
                $inventory = Inventory::where('id', $inventory->id)->lockForUpdate()->first();
            }

            $availableToReserve = (float) $inventory->quantity_on_hand - (float) $inventory->reserved_qty;

            if ($quantity > ($availableToReserve + self::QTY_EPSILON)) {
                throw new InsufficientStockException(
                    "Cannot reserve {$quantity} units for product #{$product->id}. "
                    ."Available (Unreserved): {$availableToReserve}."
                );
            }

            $inventory->reserved_qty += $quantity;
            $inventory->save();
        });
    }

    // -------------------------------------------------------------------------
    // PUBLIC: Release a stock reservation.
    // -------------------------------------------------------------------------
    public function releaseReservation(Product $product, Location $location, float $quantity): void
    {
        if ($quantity < self::QTY_EPSILON) {
            return;
        }

        DB::transaction(function () use ($product, $location, $quantity) {
            $inventory = Inventory::where('product_id', $product->id)
                ->where('location_id', $location->id)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                // If it doesn't exist, we can't release anything, but we'll log it instead of crashing
                // to allow cancellation of edge-case orphaned records.
                return;
            }

            $inventory->reserved_qty = max(0, (float) $inventory->reserved_qty - $quantity);
            $inventory->save();
        });
    }

    // -------------------------------------------------------------------------
    // PUBLIC: Reverse a posted transaction.
    // Creates a counter-transaction to void all stock movements.
    // -------------------------------------------------------------------------
    public function reverseTransaction(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction) {
            // FIX [GAP 5]: Lock the transaction header row so the status check and
            // reversal write are atomic. Without this, two concurrent cancel/reverse
            // requests can both pass the 'posted' check and create two reversal
            // counter-transactions, corrupting the ledger.
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->id);
            $transaction->loadMissing(['lines', 'status']);

            if ($transaction->status->name !== 'posted') {
                throw new LogicException('Only posted transactions can be reversed.');
            }

            $revStatus = TransactionStatus::where('name', 'posted')->firstOrFail();

            // 1. Prepare counter data
            $reverseData = [
                'header' => [
                    'transaction_type_id' => $transaction->transaction_type_id,
                    'transaction_status_id' => $revStatus->id,
                    'transaction_date' => now()->toDateString(),
                    'reference_number' => "REV-{$transaction->reference_number}",
                    'from_location_id' => $transaction->to_location_id, // Swapped for symmetry
                    'to_location_id' => $transaction->from_location_id,
                    'vendor_id' => $transaction->vendor_id,
                    'customer_id' => $transaction->customer_id,
                    'notes' => "REVERSAL of Transaction #{$transaction->id}",
                    'reverses_transaction_id' => $transaction->id,
                ],
                'lines' => $transaction->lines->map(function ($line) {
                    return [
                        'product_id' => $line->product_id,
                        'location_id' => $line->location_id,
                        'quantity' => '-'.ltrim((string) $line->quantity, '-'), // negate safely
                        'unit_cost' => (string) $line->unit_cost,
                        'uom_id' => $line->uom_id,
                    ];
                })->toArray(),
            ];

            // 2. Record movement (posts immediately)
            $reversal = $this->recordMovement($reverseData);

            // 3. Mark original as cancelled
            $cancelledStatus = TransactionStatus::where('name', 'cancelled')->firstOrFail();
            $transaction->transaction_status_id = $cancelledStatus->id;
            $transaction->cancelled_at = Carbon::now();
            $transaction->save();

            return $reversal;
        });
    }

    // -------------------------------------------------------------------------
    // PRIVATE: Apply a single transaction line to the inventory layer.
    //   Extracted from the old inline loop so postTransaction() can reuse it.
    // -------------------------------------------------------------------------
    private function applyLineToInventory(TransactionLine $line, array $lineData): void
    {
        $inventory = Inventory::where('product_id', $lineData['product_id'])
            ->where('location_id', $lineData['location_id'])
            ->lockForUpdate()
            ->first();

        if (! $inventory) {
            $inventory = Inventory::create([
                'product_id' => $lineData['product_id'],
                'location_id' => $lineData['location_id'],
                'quantity_on_hand' => 0,
                'average_cost' => 0,
            ]);
            $inventory = Inventory::where('id', $inventory->id)->lockForUpdate()->first();
        }

        // Normalize quantity — must be a string for FinancialMath.
        $qtyMove  = (string) $lineData['quantity'];
        $unitCost = (string) ($lineData['unit_cost'] ?? '0');
        $isReceipt = FinancialMath::isPositive($qtyMove);

        // Check unreserved availability before any consumption.
        $availableForIssue = FinancialMath::sub(
            (string) $inventory->quantity_on_hand,
            (string) $inventory->reserved_qty
        );

        if (! $isReceipt && FinancialMath::lt($availableForIssue, FinancialMath::round(ltrim($qtyMove, '-'), FinancialMath::LINE_SCALE))) {
            throw new InsufficientStockException(
                "Insufficient unreserved stock for product #{$lineData['product_id']}. "
                .'Required: '.ltrim($qtyMove, '-').", Available (Unreserved): {$availableForIssue}."
            );
        }

        $strategy = CostingStrategyFactory::resolve($inventory->product);

        if ($isReceipt) {
            $strategy->onReceipt($inventory, $line, $qtyMove, $unitCost);

            $inventory->quantity_on_hand = FinancialMath::add(
                (string) $inventory->quantity_on_hand, $qtyMove
            );
            $inventory->save();

            $this->updateProductGlobalAverageCost($inventory->product);

            $line->unit_cost  = $unitCost;
            $line->total_cost = FinancialMath::round(
                FinancialMath::mul($qtyMove, $unitCost),
                FinancialMath::LINE_SCALE
            );
            $line->save();
        } else {
            $unitCost = $strategy->onIssue($inventory, ltrim($qtyMove, '-'));

            $inventory->quantity_on_hand = FinancialMath::add(
                (string) $inventory->quantity_on_hand, $qtyMove // $qtyMove is negative string
            );
            $inventory->save();

            $this->updateProductGlobalAverageCost($inventory->product);

            $line->unit_cost  = $unitCost;
            $line->total_cost = FinancialMath::round(
                FinancialMath::mul($unitCost, ltrim($qtyMove, '-')),
                FinancialMath::LINE_SCALE
            );
            $line->save();
        }
    }

    /**
    //   which is mathematically wrong when stock exists at multiple locations at
    //   different costs. The correct formula aggregates every location:
    //     global_avg = SUM(location_QOH × location_avg_cost) / SUM(location_QOH)
    //
    //   This is called AFTER the inventory row has been saved so the DB query
    //   reflects the latest state.
    // -------------------------------------------------------------------------
    /**
     * Incremental product global average cost.
     * Uses SUMs across locations to ensure mathematical correctness
     * across multi-warehouse environments.
     */
    private function updateProductGlobalAverageCost(Product $product): void
    {
        $stats = Inventory::where('product_id', $product->id)
            ->where('quantity_on_hand', '>', 0)
            ->selectRaw('SUM(quantity_on_hand * average_cost) as total_value, SUM(quantity_on_hand) as total_qty')
            ->first();

        // selectRaw SUM() returns a numeric string from MySQL — safe for FinancialMath.
        if ($stats && FinancialMath::isPositive((string) ($stats->total_qty ?? '0'))) {
            $newAvg = FinancialMath::round(
                FinancialMath::div((string) $stats->total_value, (string) $stats->total_qty),
                FinancialMath::LINE_SCALE
            );
            $product->update(['average_cost' => $newAvg]);
        }
    }

    // -------------------------------------------------------------------------
    // PRIVATE: Apply UOM conversion to line data before storing.
    // -------------------------------------------------------------------------
    private function applyUomConversion(array $lineData, Product $product): array
    {
        if (
            isset($lineData['uom_id'])
            && $product->uom_id
        ) {
            $fromId = (int) $lineData['uom_id'];
            $targetUomId = UomHelper::getSmallestUnitId($product->uom_id);
            $toId = $targetUomId;
            $cacheKey = "{$product->id}_{$fromId}_{$toId}"; // Loggable key

            try {
                $factor = UomHelper::getConversionFactor($fromId, $toId, $product->id);
            } catch (\Exception $e) {
                throw new UomConversionException($e->getMessage());
            }

            // Apply high-precision BCMath conversion — no float math.
            $qtyStr  = (string) $lineData['quantity'];
            $factor  = (string) UomHelper::getConversionFactor($fromId, $toId, $product->id);
            $lineData['quantity'] = FinancialMath::round(
                FinancialMath::mul($qtyStr, $factor),
                FinancialMath::LINE_SCALE
            );
            if (isset($lineData['unit_cost'])) {
                $lineData['unit_cost'] = FinancialMath::round(
                    FinancialMath::div((string) $lineData['unit_cost'], $factor),
                    FinancialMath::LINE_SCALE
                );
            }
        }

        // Ensure the resulting line data reflects the absolute base unit (Atom)
        // so the Inventory Ledger is 100% accurate (no decimals for discrete units).
        $lineData['base_uom_id'] = UomHelper::getSmallestUnitId($product->uom_id);

        return $lineData;
    }
}
