<?php

namespace App\Http\Resources\Inventory;

use App\Helpers\UomHelper;
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
        $qoh = (float) ($this->inventories_sum_quantity_on_hand ?? ($this->relationLoaded('inventories') ? $this->inventories->sum('quantity_on_hand') : $this->inventories()->sum('quantity_on_hand')));

        if ($targetUomId) {
            $multiplier = UomHelper::getMultiplierToSmallest((int) $targetUomId, $this->id, false);
            $scaledQoh = $multiplier > 0 ? $qoh / $multiplier : $qoh;
            $formattedTotalQoh = UomHelper::format($scaledQoh, (int) $targetUomId, $this->id, false);
        } else {
            $formattedTotalQoh = $this->formatted_total_qoh;
        }

        return [
            'id' => $this->id,
            'sku' => $this->sku ?? $this->product_code, // Guaranteed display ID
            'product_code' => $this->product_code,
            'name' => $this->name,
            'description' => $this->description,
            'barcode' => $this->barcode,
            'brand' => $this->brand,
            'selling_price' => (float) $this->selling_price,
            'formatted_selling_price' => $this->formatted_selling_price,
            'average_cost' => (float) $this->average_cost,
            'formatted_average_cost' => $this->formatted_average_cost,
            'total_qoh' => $qoh,
            'formatted_total_qoh' => $formattedTotalQoh,
            'reorder_point' => (float) $this->reorder_point,
            'reorder_quantity' => (float) $this->reorder_quantity,

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
            'main_image_url' => $this->attachmentsIn('main_image')->first()?->file_path ? asset('storage/'.$this->attachmentsIn('main_image')->first()->file_path) : null,
            'has_history' => (bool) ($this->transaction_lines_count ?? $this->transactionLines()->exists()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
