<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Helpers\UomHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\InventoryResource;
use App\Models\Inventory;
use App\Models\InventoryCostLayer;
use App\Models\Product;
use App\Models\UnitOfMeasure;
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
                    'formatted_quantity_on_hand' => $product->formatted_total_qoh,
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
                    'location_id' => $inv->location_id,
                    'location_name' => $inv->location->name ?? 'Unknown Location',
                    'location_code' => $inv->location->code ?? 'N/A',
                    'quantity_on_hand' => (float) $inv->quantity_on_hand,
                    'formatted_quantity_on_hand' => $inv->formatted_quantity_on_hand,
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
        $layers = InventoryCostLayer::with(['location', 'transactionLine.transaction.purchaseOrder'])
            ->where('product_id', $product->id)
            ->where('is_exhausted', false)
            ->where('remaining_qty', '>', 0)
            ->orderBy('receipt_date', 'asc')
            ->get()
            ->map(function ($layer) {
                $po = $layer->transactionLine?->transaction?->purchaseOrder;

                return [
                    'id' => $layer->id,
                    'location_name' => $layer->location?->name ?? 'Unknown Location',
                    'receipt_date' => $layer->receipt_date,
                    'unit_cost' => (float) $layer->unit_cost,
                    'original_qty' => (float) $layer->received_qty,
                    'formatted_original_qty' => $layer->formatted_received_qty,
                    'remaining_qty' => (float) $layer->remaining_qty,
                    'formatted_remaining_qty' => $layer->formatted_remaining_qty,
                    'total_value' => round((float) $layer->remaining_qty * (float) $layer->unit_cost, 8),
                    'po_number' => $po?->po_number ?? null,
                    'po_id' => $po?->id ?? null,
                ];
            });

        return response()->json(['data' => $layers]);
    }

    /**
     * Get available stock for a product at a specific location, considering reservations.
     */
    public function getStockCheck(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'location_id' => 'required|exists:locations,id',
            'uom_id' => 'nullable|exists:units_of_measure,id',
        ]);

        $product = Product::findOrFail($request->product_id);

        $inventory = Inventory::where('product_id', $product->id)
            ->where('location_id', $request->location_id)
            ->first();

        $qoh = $inventory ? (float) $inventory->quantity_on_hand : 0;
        $reserved = $inventory ? (float) $inventory->reserved_qty : 0;
        $availableBase = max(0, $qoh - $reserved);

        $targetUomId = $request->uom_id ?? $product->uom_id;
        $targetUom = UnitOfMeasure::find($targetUomId);

        $availableTarget = $availableBase;
        if ($targetUomId != $product->uom_id) {
            try {
                $factor = UomHelper::getConversionFactor($product->uom_id, $targetUomId, $product->id);
                $availableTarget *= $factor;
            } catch (\Exception $e) {
                // Fallback to base if conversion fails
            }
        }

        return response()->json([
            'qoh' => $qoh,
            'reserved_qty' => $reserved,
            'available_qty' => round($availableTarget, 8),
            'uom_id' => $targetUomId,
            'uom_abbr' => $targetUom->abbreviation ?? 'pcs',
        ]);
    }
}
