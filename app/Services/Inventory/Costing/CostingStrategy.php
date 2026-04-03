<?php

namespace App\Services\Inventory\Costing;

use App\Models\Inventory;
use App\Models\TransactionLine;

interface CostingStrategy
{
    /**
     * Handle stock receipt (Inbound) logic.
     * Strategies fully own layer creation and averaging math.
     */
    public function onReceipt(Inventory $inventory, TransactionLine $line, float $qty, float $unitCost): void;

    /**
     * Handle stock issue (Outbound) logic and return the unit cost to be used for the issue line.
     */
    public function onIssue(Inventory $inventory, float $qty): float;
}
