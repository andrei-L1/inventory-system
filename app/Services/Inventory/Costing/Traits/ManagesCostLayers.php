<?php

namespace App\Services\Inventory\Costing\Traits;

use App\Exceptions\InsufficientStockException;
use App\Helpers\FinancialMath;
use App\Models\Inventory;
use App\Models\InventoryCostLayer;

trait ManagesCostLayers
{
    /**
     * Consumes cost layers using FIFO or LIFO.
     * Returns the weighted-average unit cost of all layers consumed, as an 8dp string.
     *
     * All arithmetic uses BCMath via FinancialMath — no PHP floats, no epsilon constants.
     * Loop accumulation uses string variables initialized to '0' to prevent drift.
     */
    protected function consumeLayers(Inventory $inventory, string $quantity, string $direction = 'asc'): string
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        $layers = InventoryCostLayer::where('product_id', $inventory->product_id)
            ->where('location_id', $inventory->location_id)
            ->where('is_exhausted', false)
            ->orderBy('receipt_date', $direction)
            ->orderBy('id', $direction)
            ->select('*')
            ->selectRaw('(received_qty - issued_qty) as calc_remaining_qty')
            ->lockForUpdate()
            ->get();

        // Initialize accumulators as BCMath strings — never floats.
        $remainingToConsume = $quantity;
        $totalCostConsumed  = '0';
        $totalQtyConsumed   = '0';

        foreach ($layers as $layer) {
            /** @var InventoryCostLayer $layer */
            if (! FinancialMath::isPositive($remainingToConsume)) {
                break;
            }

            // DB decimal:8 casts return strings — safe to pass directly.
            $availableInLayer = (string) $layer->calc_remaining_qty;

            // Deterministic min() replacement: avoids PHP's float min() for decimal strings.
            $consumeAmount = FinancialMath::lte($availableInLayer, $remainingToConsume)
                ? $availableInLayer
                : $remainingToConsume;

            $totalCostConsumed = FinancialMath::add(
                $totalCostConsumed,
                FinancialMath::mul($consumeAmount, (string) $layer->unit_cost)
            );
            $totalQtyConsumed   = FinancialMath::add($totalQtyConsumed, $consumeAmount);
            $remainingToConsume = FinancialMath::sub($remainingToConsume, $consumeAmount);

            $layer->issued_qty = FinancialMath::round(
                FinancialMath::add((string) $layer->issued_qty, $consumeAmount),
                FinancialMath::LINE_SCALE
            );

            // Layer is exhausted when remaining ≤ 0 (no epsilon needed with BCMath).
            $remaining = FinancialMath::sub((string) $layer->received_qty, (string) $layer->issued_qty);
            if (! FinancialMath::isPositive($remaining)) {
                $layer->is_exhausted = true;
            }

            $layer->save();
        }

        if (FinancialMath::isPositive($remainingToConsume)) {
            throw new InsufficientStockException(
                "Insufficient stock to consume {$quantity} for product ID: {$inventory->product_id} "
                ."at location ID: {$inventory->location_id}. Missing: {$remainingToConsume}"
            );
        }

        // Weighted-average unit cost of consumed layers.
        return FinancialMath::isPositive($totalQtyConsumed)
            ? FinancialMath::round(FinancialMath::div($totalCostConsumed, $totalQtyConsumed), FinancialMath::LINE_SCALE)
            : '0';
    }

    /**
     * Level all non-exhausted layers to the current inventory average cost.
     * Used exclusively for Weighted Average (Layered Averaging).
     */
    protected function levelCostLayers(Inventory $inventory): void
    {
        // DB decimal:8 cast returns string — safe to pass directly.
        $avg = FinancialMath::round((string) $inventory->average_cost, FinancialMath::LINE_SCALE);

        InventoryCostLayer::where('product_id', $inventory->product_id)
            ->where('location_id', $inventory->location_id)
            ->where('is_exhausted', false)
            ->lockForUpdate()
            ->update(['unit_cost' => $avg]);
    }

    /**
     * Update the running weighted-average cost on the Inventory record.
     *
     * Formula: (current_value + inbound_value) / (current_qty + inbound_qty)
     * All arithmetic in BCMath — no PHP floats.
     */
    protected function updateRunningAverage(Inventory $inventory, string $newQty, string $newUnitCost): void
    {
        // getRawOriginal() returns the raw DB string — safe for BCMath.
        $currentQty     = (string) $inventory->getRawOriginal('quantity_on_hand');
        $currentAvgCost = (string) $inventory->getRawOriginal('average_cost');

        $totalValueBefore = FinancialMath::mul($currentQty, $currentAvgCost);
        $newValueInbound  = FinancialMath::mul($newQty, $newUnitCost);
        $totalQtyAfter    = FinancialMath::add($currentQty, $newQty);

        if (FinancialMath::isPositive($totalQtyAfter)) {
            $inventory->average_cost = FinancialMath::round(
                FinancialMath::div(
                    FinancialMath::add($totalValueBefore, $newValueInbound),
                    $totalQtyAfter
                ),
                FinancialMath::LINE_SCALE
            );
        }
    }
}
