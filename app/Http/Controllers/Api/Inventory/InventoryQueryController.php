<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Helpers\FinancialMath;
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
                $shortage = FinancialMath::sub((string) $product->reorder_point, (string) $product->total_qoh);
                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category->name ?? 'N/A',
                    'uom' => $product->uom->abbreviation ?? 'pcs',
                    'quantity_on_hand' => (float) $product->total_qoh,
                    'formatted_quantity_on_hand' => $product->formatted_total_qoh,
                    'reorder_point' => (float) $product->reorder_point,
                    'shortage' => (float) $shortage,
                    'formatted_shortage' => UomHelper::format((float) $shortage, $product->uom_id, $product->id, false),
                    'preferred_vendor' => $product->preferredVendor->name ?? 'None',
                    'status' => 'critical',
                ];
            });

        return response()->json(['data' => $products]);
    }

    /**
     * Get inventory locations and QOH for a specific product.
     */
    public function getLocations(Request $request, Product $product): JsonResponse
    {
        $targetUomId = $request->query('target_uom_id') ? (int) $request->query('target_uom_id') : $product->uom_id;
        $multiplier = UomHelper::getMultiplierToSmallest($targetUomId, $product->id, false);

        $inventories = Inventory::with(['location'])
            ->where('product_id', $product->id)
            ->where('quantity_on_hand', '>', 0)
            ->get()
            ->map(function ($inv) use ($targetUomId, $multiplier, $product) {
                $qoh = (string) $inv->quantity_on_hand;
                $scaledQoh = FinancialMath::gt($multiplier, '0') 
                    ? FinancialMath::div($qoh, $multiplier) 
                    : $qoh;

                return [
                    'id' => $inv->id,
                    'location_id' => $inv->location_id,
                    'location_name' => $inv->location->name ?? 'Unknown Location',
                    'location_code' => $inv->location->code ?? 'N/A',
                    'quantity_on_hand' => (float) $qoh,
                    'formatted_quantity_on_hand' => UomHelper::format((float) $scaledQoh, $targetUomId, $product->id, false),
                    'average_cost' => (float) $inv->average_cost,
                    'last_movement_date' => $inv->updated_at,
                ];
            });

        return response()->json(['data' => $inventories]);
    }

    /**
     * Get active cost layers (FIFO/LIFO representation) for a product.
     */
    public function getCostLayers(Request $request, Product $product): JsonResponse
    {
        $targetUomId = $request->query('target_uom_id') ? (int) $request->query('target_uom_id') : $product->uom_id;
        $multiplier = UomHelper::getMultiplierToSmallest($targetUomId, $product->id, false);

        $layers = InventoryCostLayer::with(['location', 'transactionLine.transaction.purchaseOrder'])
            ->where('product_id', $product->id)
            ->where('is_exhausted', false)
            ->where('remaining_qty', '>', 0)
            ->orderBy('receipt_date', 'asc')
            ->get()
            ->map(function ($layer) use ($targetUomId, $multiplier, $product) {
                $po = $layer->transactionLine?->transaction?->purchaseOrder;

                $receivedScaled = FinancialMath::gt($multiplier, '0') 
                    ? FinancialMath::div((string) $layer->received_qty, $multiplier) 
                    : (string) $layer->received_qty;
                $remainingScaled = FinancialMath::gt($multiplier, '0') 
                    ? FinancialMath::div((string) $layer->remaining_qty, $multiplier) 
                    : (string) $layer->remaining_qty;
                $unitCostScaled = FinancialMath::gt($multiplier, '0') 
                    ? FinancialMath::mul((string) $layer->unit_cost, $multiplier) 
                    : (string) $layer->unit_cost;

                $roundedUnitCost = FinancialMath::round($unitCostScaled, 2);
                $totalValue = FinancialMath::round(FinancialMath::mul((string) $layer->remaining_qty, (string) $layer->unit_cost), 2);

                $targetUom = UnitOfMeasure::find($targetUomId);

                return [
                    'id' => $layer->id,
                    'location_name' => $layer->location?->name ?? 'Unknown Location',
                    'receipt_date' => $layer->receipt_date,
                    'unit_cost' => (float) $layer->unit_cost,
                    'formatted_unit_cost' => '₱'.number_format((float) $unitCostScaled, 2).' / '.($targetUom->abbreviation ?? 'pcs'),
                    'original_qty' => (float) $layer->received_qty,
                    'formatted_original_qty' => UomHelper::format((float) $receivedScaled, $targetUomId, $product->id, false),
                    'remaining_qty' => (float) $layer->remaining_qty,
                    'formatted_remaining_qty' => UomHelper::format((float) $remainingScaled, $targetUomId, $product->id, false),
                    'total_value' => (float) $totalValue,
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

        $qoh = $inventory ? (string) $inventory->quantity_on_hand : '0';
        $reserved = $inventory ? (string) $inventory->reserved_qty : '0';
        $baseDiff = FinancialMath::sub($qoh, $reserved);
        $availableBase = FinancialMath::isNegative($baseDiff) ? '0' : $baseDiff;

        $targetUomId = $request->uom_id ?? $product->uom_id;
        $targetUom = UnitOfMeasure::find($targetUomId);

        $availableTarget = $availableBase;
        if ((int) $targetUomId !== (int) $product->uom_id) {
            try {
                $factor = UomHelper::getConversionFactor($product->uom_id, $targetUomId, $product->id);
                // factor is a string from getConversionFactor
                $availableTarget = FinancialMath::mul($availableBase, $factor);
            } catch (\Exception $e) {
                // Fallback to base if conversion fails
            }
        }

        return response()->json([
            'qoh' => (float) $qoh,
            'reserved_qty' => (float) $reserved,
            'available_qty' => (float) FinancialMath::round($availableTarget, 8),
            'uom_id' => $targetUomId,
            'uom_abbr' => $targetUom->abbreviation ?? 'pcs',
        ]);
    }
}
