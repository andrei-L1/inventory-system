<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\InventoryCostLayer;
use Illuminate\Support\Facades\DB;
use Exception;

class StockService
{
    /**
     * Record a stock movement with strict integrity.
     * 
     * @param array $data Transaction header and lines
     * @return Transaction
     * @throws Exception
     */
    public function recordMovement(array $data): Transaction
    {
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
                        'location_id' => $lineData['location_id']
                    ], [
                        'quantity_on_hand' => 0
                    ]);

                // 3. Create Transaction Line
                $line = $transaction->lines()->create(array_merge($lineData, [
                    'transaction_id' => $transaction->id
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
     * Logic to consume cost layers based on FIFO/LIFO (placeholder).
     */
    protected function consumeLayers(Inventory $inventory, float $quantity)
    {
        // Implementation for FIFO/LIFO consumption would go here.
        // It should update remaining_qty and is_exhausted fields.
    }
}
