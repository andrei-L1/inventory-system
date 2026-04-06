<?php

namespace App\Services\Inventory\Costing\Traits;

use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\InventoryCostLayer;

trait ManagesCostLayers
{
    /**
     * Consumes cost layers using FIFO or LIFO.
     * Returns the weighted-average unit cost of all layers consumed.
     */
    protected function consumeLayers(Inventory $inventory, float $quantity, string $direction = 'asc'): float
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $qtyEpsilon = 0.00000001;
        $costEpsilon = 0.00000001;

        $layers = InventoryCostLayer::where('product_id', $inventory->product_id)
            ->where('location_id', $inventory->location_id)
            ->where('is_exhausted', false)
            ->orderBy('receipt_date', $direction)
            ->orderBy('id', $direction)
            ->select('*')
            ->selectRaw('(received_qty - issued_qty) as calc_remaining_qty')
            ->lockForUpdate()
            ->get();

        $remainingToConsume = $quantity;
        $totalCostConsumed = 0.0;
        $totalQtyConsumed = 0.0;

        foreach ($layers as $layer) {
            /** @var InventoryCostLayer $layer */
            if ($remainingToConsume <= $qtyEpsilon) {
                break;
            }

            $availableInLayer = (float) $layer->calc_remaining_qty;
            $consumeAmount = min($availableInLayer, $remainingToConsume);

            $totalCostConsumed += $consumeAmount * (float) $layer->unit_cost;
            $totalQtyConsumed += $consumeAmount;

            $layer->issued_qty = round((float) $layer->issued_qty + $consumeAmount, 8);
            $remainingToConsume -= $consumeAmount;

            if (($layer->received_qty - $layer->issued_qty) <= $qtyEpsilon) {
                $layer->is_exhausted = true;
            }

            $layer->save();
        }

        if ($remainingToConsume > $qtyEpsilon) {
            throw new InsufficientStockException(
                "Insufficient stock to consume {$quantity} for product ID: {$inventory->product_id} "
                ."at location ID: {$inventory->location_id}. Missing: {$remainingToConsume}"
            );
        }

        return $totalQtyConsumed > 0 ? round($totalCostConsumed / $totalQtyConsumed, 8) : 0.0;
    }

    /**
     * Level all non-exhausted layers to the current inventory average cost.
     * Used exclusively for Weighted Average (Layered Averaging).
     */
    protected function levelCostLayers(Inventory $inventory): void
    {
        $avg = round((float) $inventory->average_cost, 8);

        InventoryCostLayer::where('product_id', $inventory->product_id)
            ->where('location_id', $inventory->location_id)
            ->where('is_exhausted', false)
            ->lockForUpdate()
            ->update(['unit_cost' => $avg]);
    }

    /**
     * Common logic to update the running weighted average cost on the Inventory record.
     * This is an absolute requirement for reporting and system-wide valuation,
     * regardless of the specific costing strategy used for COGS on issue.
     */
    protected function updateRunningAverage(Inventory $inventory, float $newQty, float $newUnitCost): void
    {
        $currentQty = (float) $inventory->getRawOriginal('quantity_on_hand');
        $currentAvgCost = (float) $inventory->getRawOriginal('average_cost');

        $totalValueBefore = $currentQty * $currentAvgCost;
        $newValueInbound = $newQty * $newUnitCost;
        $totalQtyAfter = $currentQty + $newQty;

        if ($totalQtyAfter > 0) {
            $inventory->average_cost = round(($totalValueBefore + $newValueInbound) / $totalQtyAfter, 8);
        }
    }
}
