<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\InventoryCostLayer;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\DB;

class StockService
{
    protected TransactionValidator $validator;

    public function __construct(TransactionValidator $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Record a stock movement with strict integrity.
     *
     * @param  array  $data  Transaction header and lines
     *
     * @throws Exception
     */
    public function recordMovement(array $data): Transaction
    {
        $this->validator->validate($data);

        return DB::transaction(function () use ($data) {
            // 1. Create Transaction Header
            $transaction = Transaction::create($data['header']);

            foreach ($data['lines'] as $lineData) {
                // 2. Lock the Inventory row (Pessimistic Locking)
                // This prevents race conditions during concurrent stock updates.
                $inventory = Inventory::where('product_id', $lineData['product_id'])
                    ->where('location_id', $lineData['location_id'])
                    ->lockForUpdate()
                    ->firstOrCreate([
                        'product_id' => $lineData['product_id'],
                        'location_id' => $lineData['location_id'],
                    ], [
                        'quantity_on_hand' => 0,
                    ]);

                // 3. Create Transaction Line
                $line = $transaction->lines()->create(array_merge($lineData, [
                    'transaction_id' => $transaction->id,
                ]));

                // 4. Update Inventory Cache & Weighted Average Cost
                $isReceipt = $lineData['quantity'] > 0;
                
                if ($isReceipt) {
                    $this->updateAverageCost($inventory, $lineData['quantity'], $lineData['unit_cost']);
                }

                $inventory->quantity_on_hand += $lineData['quantity'];
                $inventory->save();

                // 5. Handle Cost Layers (FIFO/LIFO)
                if ($isReceipt) {
                    // Receipt: Add new layer
                    InventoryCostLayer::create([
                        'product_id' => $lineData['product_id'],
                        'location_id' => $lineData['location_id'],
                        'transaction_line_id' => $line->id,
                        'received_qty' => $lineData['quantity'],
                        'remaining_qty' => $lineData['quantity'],
                        'unit_cost' => $lineData['unit_cost'],
                        'receipt_date' => now(),
                    ]);
                } else {
                    // Issue: Consume existing layers
                    $this->consumeLayers($inventory, abs($lineData['quantity']));
                }
            }

            return $transaction;
        });
    }

    /**
     * Logic to consume cost layers based on FIFO/LIFO.
     */
    protected function consumeLayers(Inventory $inventory, float $quantity)
    {
        $product = $inventory->product;
        $method = $product->costingMethod;

        // Default to FIFO if no method or 'average' is selected for layer exhaustion.
        // In some systems, even if accounting is average, physical items are consumed FIFO.
        $direction = ($method && $method->matchesName('lifo')) ? 'desc' : 'asc';

        $layers = InventoryCostLayer::where('product_id', $inventory->product_id)
            ->where('location_id', $inventory->location_id)
            ->where('is_exhausted', false)
            ->orderBy('receipt_date', $direction)
            ->orderBy('id', $direction)
            ->get();

        $remainingToConsume = $quantity;

        foreach ($layers as $layer) {
            if ($remainingToConsume <= 0) {
                break;
            }

            $availableInLayer = $layer->remaining_qty;
            $consumeAmount = min($availableInLayer, $remainingToConsume);

            // Accessing generated column 'remaining_qty' might require refresh,
            // but we can update issued_qty directly.
            $layer->issued_qty += $consumeAmount;
            $remainingToConsume -= $consumeAmount;

            // Mark as exhausted if remaining is effectively zero
            if (($layer->received_qty - $layer->issued_qty) <= 0.00001) {
                $layer->is_exhausted = true;
            }

            if ($layer instanceof \Illuminate\Database\Eloquent\Model) {
                $layer->save();
            }
        }

        if ($remainingToConsume > 0.00001) {
            throw new InsufficientStockException(
                "Insufficient stock to consume {$quantity} for product ID: {$inventory->product_id} at location ID: {$inventory->location_id}. Missing: {$remainingToConsume}"
            );
        }
    }

    /**
     * Record an atomic internal stock transfer between two locations.
     */
    public function recordTransfer(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Log as two linked transactions or a double-sided transaction header
            // For now, we simulate an Issue from Origin and a Receipt to Destination
            $originData = [
                'header' => array_merge($data['header'], [
                    'to_location_id' => $data['to_location_id'], // reference dest
                    'notes' => 'Transfer Out: ' . ($data['header']['notes'] ?? ''),
                ]),
                'lines' => collect($data['lines'])->map(function ($line) use ($data) {
                    return array_merge($line, [
                        'location_id' => $data['from_location_id'],
                        'quantity' => -abs($line['quantity']),
                    ]);
                })->toArray(),
            ];

            $destData = [
                'header' => array_merge($data['header'], [
                    'from_location_id' => $data['from_location_id'], // reference source
                    'notes' => 'Transfer In: ' . ($data['header']['notes'] ?? ''),
                ]),
                'lines' => collect($data['lines'])->map(function ($line) use ($data) {
                    return array_merge($line, [
                        'location_id' => $data['to_location_id'],
                        'quantity' => abs($line['quantity']),
                    ]);
                })->toArray(),
            ];

            $outgoing = $this->recordMovement($originData);
            $incoming = $this->recordMovement($destData);

            return [
                'outgoing_transaction' => $outgoing,
                'incoming_transaction' => $incoming
            ];
        });
    }

    /**
     * Recalculate Weighted Average Cost.
     * Formula: (Total_Value + New_Value) / (Total_Qty + New_Qty)
     */
    protected function updateAverageCost(Inventory $inventory, float $newQty, float $newUnitCost): void
    {
        $currentQty = (float) $inventory->quantity_on_hand;
        $currentAvgCost = (float) $inventory->average_cost;

        $totalValueBefore = $currentQty * $currentAvgCost;
        $newValueInbound = $newQty * $newUnitCost;

        $totalQtyAfter = $currentQty + $newQty;

        if ($totalQtyAfter > 0) {
            $newAvgCost = ($totalValueBefore + $newValueInbound) / $totalQtyAfter;
            $inventory->average_cost = $newAvgCost;
            
            // Also update the master product's global average cost
            $product = $inventory->product;
            if ($product && $product instanceof \Illuminate\Database\Eloquent\Model) {
                $product->average_cost = $newAvgCost;
                $product->save();
            }
        }
    }
}
