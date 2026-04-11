<?php

namespace App\Services\Inventory\Costing\Strategies;

use App\Models\Inventory;
use App\Models\InventoryCostLayer;
use App\Models\TransactionLine;
use App\Services\Inventory\Costing\CostingStrategy;
use App\Services\Inventory\Costing\Traits\ManagesCostLayers;
use Illuminate\Support\Carbon;

class LifoCostingStrategy implements CostingStrategy
{
    use ManagesCostLayers;

    public function onReceipt(Inventory $inventory, TransactionLine $line, string $qty, string $unitCost): void
    {
        $this->updateRunningAverage($inventory, $qty, $unitCost);

        InventoryCostLayer::create([
            'product_id' => $inventory->product_id,
            'location_id' => $inventory->location_id,
            'transaction_line_id' => $line->id,
            'received_qty' => $qty,
            'unit_cost' => $unitCost,
            'receipt_date' => Carbon::now(),
        ]);
    }

    public function onIssue(Inventory $inventory, string $qty): string
    {
        return $this->consumeLayers($inventory, $qty, 'desc');
    }
}
