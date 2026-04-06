<?php

namespace App\Services\Inventory\Costing\Strategies;

use App\Models\Inventory;
use App\Models\InventoryCostLayer;
use App\Models\TransactionLine;
use App\Services\Inventory\Costing\CostingStrategy;
use App\Services\Inventory\Costing\Traits\ManagesCostLayers;
use Illuminate\Support\Carbon;

class AverageCostingStrategy implements CostingStrategy
{
    use ManagesCostLayers;

    /**
     * Handle stock receipt for Weighted Average products.
     * Implements "Layer Leveling" to keep layers synced with running average.
     */
    public function onReceipt(Inventory $inventory, TransactionLine $line, float $qty, float $unitCost): void
    {
        // 1. Maintain running average cost (global invariant)
        $this->updateRunningAverage($inventory, $qty, $unitCost);

        // 2. Create the specific layer for FIFO tracking
        InventoryCostLayer::create([
            'product_id' => $inventory->product_id,
            'location_id' => $inventory->location_id,
            'transaction_line_id' => $line->id,
            'received_qty' => $qty,
            'unit_cost' => round($unitCost, 8),
            'receipt_date' => Carbon::now(),
        ]);

        // 3. Level all active layers to the new running average
        $this->levelCostLayers($inventory);
    }

    public function onIssue(Inventory $inventory, float $qty): float
    {
        // Even for Average products, we consume layers in FIFO order
        // to maintain the physical audit trail (traceability).
        // Since layers are "leveled", this naturally returns the running average cost.
        return $this->consumeLayers($inventory, $qty, 'asc');
    }
}
