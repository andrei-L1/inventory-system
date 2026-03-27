<?php

namespace App\Services\Inventory;

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

                // 4. Update Inventory Cache
                $inventory->quantity_on_hand += $lineData['quantity'];
                $inventory->save();

                // 5. Handle Cost Layers (FIFO/LIFO)
                if ($lineData['quantity'] > 0) {
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
                    // Issue: Consume existing layers (simplification here)
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

            // Mark as exhausted if remaining is effectively zero (handling float precision)
            if (($layer->received_qty - $layer->issued_qty) <= 0.00001) {
                $layer->is_exhausted = true;
            }

            $layer->save();
        }

        if ($remainingToConsume > 0.00001) {
            throw new Exception("Insufficient cost layers to consume {$quantity} for product ID: {$inventory->product_id} at location ID: {$inventory->location_id}. Missing: {$remainingToConsume}");
        }
    }
}
