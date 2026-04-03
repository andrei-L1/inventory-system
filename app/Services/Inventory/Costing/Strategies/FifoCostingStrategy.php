<?php

namespace App\Services\Inventory\Costing\Strategies;

use App\Models\Inventory;
use App\Models\InventoryCostLayer;
use App\Models\TransactionLine;
use App\Services\Inventory\Costing\CostingStrategy;
use App\Services\Inventory\Costing\Traits\ManagesCostLayers;
use Illuminate\Support\Carbon;

class FifoCostingStrategy implements CostingStrategy
{
    use ManagesCostLayers;

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
            'unit_cost' => $unitCost,
            'receipt_date' => Carbon::now(),
        ]);
    }

    public function onIssue(Inventory $inventory, float $qty): float
    {
        return $this->consumeLayers($inventory, $qty, 'asc');
    }
}
