<?php

namespace App\Services\Procurement;

use App\Helpers\FinancialMath;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ReorderRule;
use App\Models\ReplenishmentSuggestion;
use App\Models\SalesOrderLine;
use App\Models\SalesOrderStatus;

class ReplenishmentService
{
    /**
     * Run the replenishment engine across all active products.
     * Evaluates both static Reorder Rules AND dynamic Backorder Demand.
     */
    public function generateSuggestions(): int
    {
        $products = Product::where('is_active', true)->get();
        $generatedCount = 0;

        $activeSoStatuses = [
            SalesOrderStatus::CONFIRMED,
            SalesOrderStatus::PARTIALLY_PICKED,
            SalesOrderStatus::PICKED,
            SalesOrderStatus::PARTIALLY_PACKED,
            SalesOrderStatus::PACKED,
            SalesOrderStatus::PARTIALLY_SHIPPED,
        ];

        foreach ($products as $product) {
            // 1. Calculate Supply (Current Global Stock - or we could do location-specific if needed later. Sticking to global for MVP)
            $currentStock = $this->calculateStock($product->id, null);

            // 2. Calculate Demand (Unfulfilled SO Quantities)
            $unfulfilledDemand = '0';
            $activeSoLines = SalesOrderLine::where('product_id', $product->id)
                ->whereHas('salesOrder', function ($q) use ($activeSoStatuses) {
                    $q->whereHas('status', function ($s) use ($activeSoStatuses) {
                        $s->whereIn('name', $activeSoStatuses);
                    });
                })->get();

            foreach ($activeSoLines as $line) {
                // Demand = Ordered - Shipped
                $remaining = FinancialMath::sub((string) $line->ordered_qty, (string) $line->shipped_qty);
                if (FinancialMath::isPositive($remaining)) {
                    $unfulfilledDemand = FinancialMath::add($unfulfilledDemand, $remaining);
                }
            }

            // 3. Evaluate against Reorder Rules (if any)
            $rule = ReorderRule::where('product_id', $product->id)->where('is_active', true)->first();

            $minStockStr = $rule ? (string) $rule->min_stock : '0';
            $defaultReorderQty = $rule ? (string) $rule->reorder_qty : '0';

            // 4. Trigger Condition:
            // A) Stock is below minimum rule
            // OR
            // B) Stock is insufficient to cover unfulfilled demand
            $isBelowMin = FinancialMath::lt($currentStock, $minStockStr);
            $isShortForDemand = FinancialMath::lt($currentStock, $unfulfilledDemand);

            if ($isBelowMin || $isShortForDemand) {
                // How much do we actually need?
                $shortfall = FinancialMath::max('0', FinancialMath::sub($unfulfilledDemand, $currentStock));

                // Suggested Qty = Max between standard reorder rule and actual shortfall
                $suggestedQty = FinancialMath::max($defaultReorderQty, $shortfall);

                $reason = $isShortForDemand
                    ? "Backorder Demand: Short by {$shortfall} units."
                    : "Stock below minimum rule ({$minStockStr}).";

                // Deduplication Logic
                $existing = ReplenishmentSuggestion::where('product_id', $product->id)
                    // ->where('location_id', $rule->location_id ?? null) // MVP uses global suggestions if no rule
                    ->where('status', 'pending')
                    ->first();

                if (! $existing) {
                    ReplenishmentSuggestion::create([
                        'product_id' => $product->id,
                        'location_id' => $rule ? $rule->location_id : null,
                        'current_stock' => $currentStock,
                        'suggested_qty' => $suggestedQty,
                        'reason' => $reason,
                        'status' => 'pending',
                    ]);
                    $generatedCount++;
                } else {
                    // Strictly update existing ticket to prevent duplicates
                    $existing->update([
                        'current_stock' => $currentStock,
                        'suggested_qty' => $suggestedQty,
                        'reason' => $reason,
                    ]);
                }
            } else {
                // Clean up suggestions that are no longer needed (e.g., SO was cancelled, or stock manually adjusted)
                ReplenishmentSuggestion::where('product_id', $product->id)
                    ->where('status', 'pending')
                    ->delete();
            }
        }

        return $generatedCount;
    }

    /**
     * Calculate stock for a product, optionally filtered by location.
     */
    protected function calculateStock(int $productId, ?int $locationId): string
    {
        $query = Inventory::where('product_id', $productId);

        if ($locationId) {
            return (string) $query->where('location_id', $locationId)->sum('quantity_on_hand');
        }

        return (string) $query->sum('quantity_on_hand');
    }
}
