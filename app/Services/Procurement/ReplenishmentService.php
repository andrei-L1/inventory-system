<?php

namespace App\Services\Procurement;

use App\Helpers\FinancialMath;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ReorderRule;
use App\Models\ReplenishmentSuggestion;

class ReplenishmentService
{
    /**
     * Run the replenishment engine across all products and locations with active reorder rules.
     */
    public function generateSuggestions(): int
    {
        $rules = ReorderRule::where('is_active', true)->get();
        $generatedCount = 0;

        foreach ($rules as $rule) {
            $currentStock = $this->calculateStock($rule->product_id, $rule->location_id);
            $minStockStr = (string) $rule->min_stock;

            if (FinancialMath::lt($currentStock, $minStockStr)) {
                // Check if a pending suggestion already exists
                $existing = ReplenishmentSuggestion::where('product_id', $rule->product_id)
                    ->where('location_id', $rule->location_id)
                    ->where('status', 'pending')
                    ->first();

                if (! $existing) {
                    ReplenishmentSuggestion::create([
                        'product_id' => $rule->product_id,
                        'location_id' => $rule->location_id,
                        'current_stock' => $currentStock,
                        'suggested_qty' => (string) $rule->reorder_qty,
                        'reason' => 'Stock below minimum ('.$rule->min_stock.')',
                        'status' => 'pending',
                    ]);
                    $generatedCount++;
                } else {
                    // Update existing suggestion with latest stock
                    $existing->update([
                        'current_stock' => $currentStock,
                        'suggested_qty' => (string) $rule->reorder_qty,
                    ]);
                }
            } else {
                // Clean up suggestions that are no longer needed
                ReplenishmentSuggestion::where('product_id', $rule->product_id)
                    ->where('location_id', $rule->location_id)
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
