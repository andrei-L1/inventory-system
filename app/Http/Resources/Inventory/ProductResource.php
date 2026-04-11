<?php

namespace App\Http\Resources\Inventory;

use App\Helpers\FinancialMath;
use App\Helpers\UomHelper;
use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $targetUomId = $request->query('target_uom_id');
        $qoh = (string) ($this->inventories_sum_quantity_on_hand ?? ($this->relationLoaded('inventories') ? $this->inventories->sum('quantity_on_hand') : $this->inventories()->sum('quantity_on_hand')));

        $sellingPrice = (string) $this->selling_price;
        $averageCost = (string) $this->average_cost;
        $reorderPoint = (string) $this->reorder_point;
        $reorderQuantity = (string) $this->reorder_quantity;
        $formattedSellingPrice = $this->formatted_selling_price;
        $formattedAverageCost = $this->formatted_average_cost;
        $formattedAverageCost8dp = $this->formatted_average_cost_8dp;
        $targetUomAbbr = $this->uom->abbreviation ?? 'pcs';

        if ($targetUomId) {
            $targetUomId = (int) $targetUomId;
            $targetMultiplier = UomHelper::getMultiplierToSmallest($targetUomId, $this->id, false);
            $productMultiplier = UomHelper::getMultiplierToSmallest((int) $this->uom_id, $this->id, false);
            
            // Normalize to Atomic (Price per Piece) then scale to Target
            if (FinancialMath::gt($productMultiplier, '0')) {
                // Selling Price scaling
                $perPiecePrice = FinancialMath::div($sellingPrice, $productMultiplier);
                $sellingPrice = FinancialMath::mul($perPiecePrice, $targetMultiplier);
                
                // Average Cost scaling
                $perPieceCost = FinancialMath::div($averageCost, $productMultiplier);
                $averageCost = FinancialMath::mul($perPieceCost, $targetMultiplier);

                // Reorder Point/Qty scaling (Quantity context)
                $reorderPoint = FinancialMath::div(FinancialMath::mul($reorderPoint, $productMultiplier), $targetMultiplier);
                $reorderQuantity = FinancialMath::div(FinancialMath::mul($reorderQuantity, $productMultiplier), $targetMultiplier);
            }

            $scaledQoh = FinancialMath::gt($targetMultiplier, '0') 
                ? FinancialMath::div($qoh, $targetMultiplier) 
                : $qoh;
                
            $formattedTotalQoh = UomHelper::format((float) $scaledQoh, $targetUomId, $this->id, false);
            
            $targetUom = UnitOfMeasure::find($targetUomId);
            $targetUomAbbr = $targetUom->abbreviation ?? 'pcs';
            $formattedSellingPrice = '₱'.FinancialMath::format($sellingPrice, 2).' / '.$targetUomAbbr;
            $formattedAverageCost = '₱'.FinancialMath::format($averageCost, 2).' / '.$targetUomAbbr;
            $formattedAverageCost8dp = '₱'.FinancialMath::format($averageCost, 8).' / '.$targetUomAbbr;
        } else {
            $formattedTotalQoh = $this->formatted_total_qoh;
        }

        return [
            'id' => $this->id,
            'sku' => $this->sku ?? $this->product_code,
            'product_code' => $this->product_code,
            'name' => $this->name,
            'description' => $this->description,
            'barcode' => $this->barcode,
            'brand' => $this->brand,
            'selling_price' => $sellingPrice,
            'formatted_selling_price' => $formattedSellingPrice,
            'average_cost' => $averageCost,
            'formatted_average_cost' => $formattedAverageCost,
            'formatted_average_cost_8dp' => $formattedAverageCost8dp,
            'total_qoh' => $qoh,
            'formatted_total_qoh' => $formattedTotalQoh,
            'reorder_point' => $reorderPoint,
            'reorder_quantity' => $reorderQuantity,
            'formatted_total_stock_value_8dp' => '₱' . FinancialMath::format(FinancialMath::mul($averageCost, $qoh), 8),

            'is_active' => (bool) $this->is_active,
            'category_id' => $this->category_id,
            'uom_id' => $this->uom_id,
            'costing_method_id' => $this->costing_method_id,
            'costing_method_name' => $this->costingMethod->name ?? 'average',
            'preferred_vendor_id' => $this->preferred_vendor_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'uom' => new UnitOfMeasureResource($this->whenLoaded('uom')),
            'preferred_vendor' => new VendorResource($this->whenLoaded('preferredVendor')),
            'inventories' => InventoryResource::collection($this->whenLoaded('inventories')),
            'costing_method' => match ($this->costingMethod->name ?? '') {
                'average' => 'Weighted Average (Layered)',
                'fifo' => 'FIFO (First-In, First-Out)',
                'lifo' => 'LIFO (Last-In, First-Out)',
                default => $this->costingMethod->label ?? 'unknown',
            },
            'main_image_url' => $this->attachmentsIn('main_image')->first()?->file_path ? asset('storage/' . $this->attachmentsIn('main_image')->first()->file_path) : null,
            'has_history' => (bool) ($this->transaction_lines_count ?? $this->transactionLines()->exists()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
