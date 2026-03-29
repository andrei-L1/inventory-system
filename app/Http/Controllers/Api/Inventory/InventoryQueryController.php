<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\InventoryResource;
use App\Models\Inventory;
use App\Models\InventoryCostLayer;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryQueryController extends Controller
{
    /**
     * Get the global stock list (Product x Location x QOH).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Inventory::with(['product.category', 'location'])
            ->where('quantity_on_hand', '>', 0);

        if ($request->has('location_id')) {
            $query->where('location_id', (int) $request->location_id);
        }

        if ($request->has('product_id')) {
            $query->where('product_id', (int) $request->product_id);
        }

        return InventoryResource::collection($query->paginate($request->get('limit', 50)));
    }

    /**
     * Get all items where QOH is below reorder point.
     * Aggregates by product if necessary.
     */
    public function getLowStock(): JsonResponse
    {
        // Aggregate QOH per product across ALL locations
        $products = Product::whereHas('inventories')
            ->with(['category', 'uom', 'preferredVendor'])
            ->get()
            ->filter(function ($product) {
                /** @var Product $product */
                return $product->total_qoh < $product->reorder_point;
            })
            ->values()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category->name ?? 'N/A',
                    'uom' => $product->uom->abbreviation ?? 'pcs',
                    'quantity_on_hand' => (float) $product->total_qoh,
                    'reorder_point' => (float) $product->reorder_point,
                    'shortage' => (float) ($product->reorder_point - $product->total_qoh),
                    'preferred_vendor' => $product->preferredVendor->name ?? 'None',
                    'status' => 'critical',
                ];
            });

        return response()->json(['data' => $products]);
    }

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
                    'last_movement_date' => $inv->updated_at,
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
