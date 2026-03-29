<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryCostLayer;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class InventoryQueryController extends Controller
{
    /**
     * Get inventory locations and QOH for a specific product.
     */
    public function getLocations(Product $product): JsonResponse
    {
        $inventories = Inventory::with(['location'])
            ->where('product_id', $product->id)
            ->where('quantity_on_hand', '>', 0)
            ->get()
            ->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'location_name' => $inv->location->name ?? 'Unknown Location',
                    'location_code' => $inv->location->code ?? 'N/A',
                    'quantity_on_hand' => (float) $inv->quantity_on_hand,
                    'average_cost' => (float) $inv->average_cost,
                    'last_movement_date' => $inv->last_count_date ?? $inv->updated_at,
                ];
            });

        return response()->json(['data' => $inventories]);
    }

    /**
     * Get active cost layers (FIFO/LIFO representation) for a product.
     */
    public function getCostLayers(Product $product): JsonResponse
    {
        $layers = InventoryCostLayer::with('location')
            ->where('product_id', $product->id)
            ->where('is_exhausted', false)
            ->where('remaining_qty', '>', 0)
            ->orderBy('receipt_date', 'asc')
            ->get()
            ->map(function ($layer) {
                return [
                    'id' => $layer->id,
                    'location_name' => $layer->location->name ?? 'Unknown Location',
                    'receipt_date' => $layer->receipt_date,
                    'unit_cost' => (float) $layer->unit_cost,
                    'original_qty' => (float) $layer->received_qty,
                    'remaining_qty' => (float) $layer->remaining_qty,
                    'total_value' => (float) ($layer->remaining_qty * $layer->unit_cost),
                ];
            });

        return response()->json(['data' => $layers]);
    }
}
