<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\InventoryCostLayer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\TransactionStatus;
use App\Models\Transfer;
use App\Models\UomConversion;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

class StockService
{
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

            foreach ($data['lines'] as $lineData) {
                $product = Product::findOrFail($lineData['product_id']);

                // FIX [UOM Conversion] — always convert before storing the line,
                // so the stored quantity/unit_cost is always in the product's base UOM.
                $lineData = $this->applyUomConversion($lineData, $product);

                // 2. Always create the transaction line (regardless of status).
                $line = $transaction->lines()->create(array_merge($lineData, [
                    'transaction_id' => $transaction->id,
                ]));

                // 3. Only touch inventory when the transaction is posted.
                if ($isPosted) {
                    $this->applyLineToInventory($line, $lineData);
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
            $transaction->loadMissing('status');

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
                    'quantity'   => (float) $line->quantity,
                    'unit_cost'  => (float) $line->unit_cost,
                ];
                $this->applyLineToInventory($line, $lineData);
            }

            $transaction->transaction_status_id = $postedStatus->id;
            $transaction->posted_at = \Illuminate\Support\Carbon::now();
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

            $originData = [
                'header' => array_merge($data['header'], [
                    'reference_number' => $ref . '-OUT',
                    'from_location_id' => $data['from_location_id'],
                    'to_location_id'   => $data['to_location_id'],
                    'notes'            => 'Transfer Out: ' . ($data['header']['notes'] ?? ''),
                ]),
                'lines' => collect($data['lines'])->map(function ($line) use ($data) {
                    return array_merge($line, [
                        'location_id' => $data['from_location_id'],
                        'quantity'    => -abs($line['quantity']),
                    ]);
                })->toArray(),
            ];

            $destData = [
                'header' => array_merge($data['header'], [
                    'reference_number' => $ref . '-IN',
                    'from_location_id' => $data['from_location_id'],
                    'to_location_id'   => $data['to_location_id'],
                    'notes'            => 'Transfer In: ' . ($data['header']['notes'] ?? ''),
                ]),
                'lines' => collect($data['lines'])->map(function ($line) use ($data) {
                    return array_merge($line, [
                        'location_id' => $data['to_location_id'],
                        'quantity'    => abs($line['quantity']),
                    ]);
                })->toArray(),
            ];

            $outgoing = $this->recordMovement($originData);
            $incoming = $this->recordMovement($destData);

            // FIX: Create the pivot record that permanently links both legs.
            $transfer = Transfer::create([
                'outgoing_transaction_id' => $outgoing->id,
                'incoming_transaction_id' => $incoming->id,
                'from_location_id'        => $data['from_location_id'],
                'to_location_id'          => $data['to_location_id'],
                'reference_number'        => (string) $ref,
            ]);

            return [
                'transfer'             => $transfer,
                'outgoing_transaction' => $outgoing,
                'incoming_transaction' => $incoming,
            ];
        });
    }

    // -------------------------------------------------------------------------
    // PRIVATE: Apply a single transaction line to the inventory layer.
    //   Extracted from the old inline loop so postTransaction() can reuse it.
    // -------------------------------------------------------------------------
    private function applyLineToInventory(TransactionLine $line, array $lineData): void
    {
        // Lock the inventory row to prevent concurrent race conditions.
        $inventory = Inventory::firstOrCreate(
            [
                'product_id'  => $lineData['product_id'],
                'location_id' => $lineData['location_id'],
            ],
            ['quantity_on_hand' => 0, 'average_cost' => 0]
        );

        $inventory = Inventory::where('id', $inventory->id)->lockForUpdate()->first();

        $isReceipt = (float) $lineData['quantity'] > 0;

        if ($isReceipt) {
            // Receipt: update location-level weighted average cost first (before QOH changes).
            $this->updateLocationAverageCost($inventory, (float) $lineData['quantity'], (float) $lineData['unit_cost']);
        }

        $inventory->quantity_on_hand += (float) $lineData['quantity'];
        $inventory->save();

        if ($isReceipt) {
            // FIX [Global WAC]: Recalculate the product-level global average cost
            // AFTER saving the updated location row, so the query sees correct data.
            $this->updateProductGlobalAverageCost((int) $lineData['product_id']);

            InventoryCostLayer::create([
                'product_id'          => $lineData['product_id'],
                'location_id'         => $lineData['location_id'],
                'transaction_line_id' => $line->id,
                'received_qty'        => $lineData['quantity'],
                'unit_cost'           => $lineData['unit_cost'],
                'receipt_date'        => now(),
            ]);
        } else {
            // FIX [unit_cost on issues]: consumeLayers() now returns the true
            // weighted average cost of the layers it consumed. We write that back
            // to the line so Gross Margin reports have accurate COGS data.
            $consumedUnitCost = $this->consumeLayers($inventory, abs((float) $lineData['quantity']));

            $line->unit_cost  = $consumedUnitCost;
            $line->total_cost = $consumedUnitCost * abs((float) $lineData['quantity']);
            $line->save();
        }
    }

    // -------------------------------------------------------------------------
    // PRIVATE: Consume cost layers FIFO or LIFO.
    //
    // FIX [unit_cost on issues]:
    //   Now returns the weighted-average unit cost of all layers consumed.
    //   This is the true COGS for the issue, enabling Gross Margin analysis.
    // -------------------------------------------------------------------------
    private function consumeLayers(Inventory $inventory, float $quantity): float
    {
        $product = $inventory->product;
        $method  = $product->costingMethod;

        $direction = ($method && $method->matchesName('lifo')) ? 'desc' : 'asc';

        /** @var \Illuminate\Database\Eloquent\Collection<int, InventoryCostLayer> $layers */
        $layers = InventoryCostLayer::where('product_id', $inventory->product_id)
            ->where('location_id', $inventory->location_id)
            ->where('is_exhausted', false)
            ->orderBy('receipt_date', $direction)
            ->orderBy('id', $direction)
            ->get();

        $remainingToConsume = $quantity;
        $totalCostConsumed  = 0.0;
        $totalQtyConsumed   = 0.0;

        foreach ($layers as $layer) {
            if ($remainingToConsume <= 0) {
                break;
            }

            $availableInLayer = $layer->remaining_qty;
            $consumeAmount    = min($availableInLayer, $remainingToConsume);

            $totalCostConsumed += $consumeAmount * (float) $layer->unit_cost;
            $totalQtyConsumed  += $consumeAmount;

            /** @var InventoryCostLayer $layer */
            $layer->issued_qty = (float) $layer->issued_qty + $consumeAmount;
            $remainingToConsume     -= $consumeAmount;

            if (($layer->received_qty - $layer->issued_qty) <= 0.00001) {
                $layer->is_exhausted = true;
            }

            $layer->save();
        }

        if ($remainingToConsume > 0.00001) {
            throw new InsufficientStockException(
                "Insufficient stock to consume {$quantity} for product ID: {$inventory->product_id} "
                . "at location ID: {$inventory->location_id}. Missing: {$remainingToConsume}"
            );
        }

        // Return the true weighted-average unit cost of what was consumed.
        return $totalQtyConsumed > 0 ? round($totalCostConsumed / $totalQtyConsumed, 6) : 0.0;
    }

    // -------------------------------------------------------------------------
    // PRIVATE: Update the location-level weighted average cost.
    //
    // Only touches $inventory->average_cost (in-memory — caller must save()).
    // Global product-level average is handled separately by updateProductGlobalAverageCost().
    // -------------------------------------------------------------------------
    private function updateLocationAverageCost(Inventory $inventory, float $newQty, float $newUnitCost): void
    {
        $currentQty     = (float) $inventory->quantity_on_hand;
        $currentAvgCost = (float) $inventory->average_cost;

        $totalValueBefore = $currentQty * $currentAvgCost;
        $newValueInbound  = $newQty * $newUnitCost;
        $totalQtyAfter    = $currentQty + $newQty;

        if ($totalQtyAfter > 0) {
            $inventory->average_cost = ($totalValueBefore + $newValueInbound) / $totalQtyAfter;
        }
    }

    // -------------------------------------------------------------------------
    // PRIVATE: Recalculate the product's global average cost across ALL locations.
    //
    // FIX [Global WAC formula]:
    //   The old code set product.average_cost from a single location's computation,
    //   which is mathematically wrong when stock exists at multiple locations at
    //   different costs. The correct formula aggregates every location:
    //     global_avg = SUM(location_QOH × location_avg_cost) / SUM(location_QOH)
    //
    //   This is called AFTER the inventory row has been saved so the DB query
    //   reflects the latest state.
    // -------------------------------------------------------------------------
    private function updateProductGlobalAverageCost(int $productId): void
    {
        $stats = Inventory::where('product_id', $productId)
            ->where('quantity_on_hand', '>', 0)
            ->selectRaw('SUM(quantity_on_hand * average_cost) as total_value, SUM(quantity_on_hand) as total_qty')
            ->first();

        if ($stats && (float) $stats->total_qty > 0) {
            Product::where('id', $productId)->update([
                'average_cost' => round((float) $stats->total_value / (float) $stats->total_qty, 6),
            ]);
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
            && (int) $lineData['uom_id'] !== (int) $product->uom_id
        ) {
            $conversion = UomConversion::where('from_uom_id', $lineData['uom_id'])
                ->where('to_uom_id', $product->uom_id)
                ->first();

            if ($conversion) {
                $lineData['quantity'] = $lineData['quantity'] * $conversion->conversion_factor;
                if (isset($lineData['unit_cost'])) {
                    $lineData['unit_cost'] = $lineData['unit_cost'] / $conversion->conversion_factor;
                }
            }
        }

        return $lineData;
    }
}
