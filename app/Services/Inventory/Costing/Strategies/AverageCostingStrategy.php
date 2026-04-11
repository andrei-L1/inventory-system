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
    public function onReceipt(Inventory $inventory, TransactionLine $line, string $qty, string $unitCost): void
    {
        $this->updateRunningAverage($inventory, $qty, $unitCost);

        InventoryCostLayer::create([
            'product_id' => $inventory->product_id,
            'location_id' => $inventory->location_id,
            'transaction_line_id' => $line->id,
            'received_qty' => $qty,
            'unit_cost' => $unitCost, // already 8dp string
            'receipt_date' => Carbon::now(),
        ]);

        // Level all active layers to the new running average
        $this->levelCostLayers($inventory);
    }

    public function onIssue(Inventory $inventory, string $qty): string
    {
        // Even for Average products, consume layers in FIFO order for audit traceability.
        // Since layers are "leveled", this naturally returns the running average cost.
        return $this->consumeLayers($inventory, $qty, 'asc');
    }
}
