<?php

namespace App\Services\Inventory\Costing;

use App\Models\Inventory;
use App\Models\TransactionLine;

interface CostingStrategy
{
    /**
     * Handle stock receipt (Inbound) logic.
     * $qty and $unitCost must be BCMath-safe numeric strings or ints.
     */
    public function onReceipt(Inventory $inventory, TransactionLine $line, string $qty, string $unitCost): void;

    /**
     * Handle stock issue (Outbound) logic.
     * $qty must be a BCMath-safe numeric string or int (always positive).
     * Returns the unit cost as an 8dp BCMath string.
     */
    public function onIssue(Inventory $inventory, string $qty): string;
}
