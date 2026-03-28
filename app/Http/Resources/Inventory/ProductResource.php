<?php

namespace App\Http\Resources\Inventory;

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
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'product_code' => $this->product_code,
            'name' => $this->name,
            'description' => $this->description,
            'barcode' => $this->barcode,
            'brand' => $this->brand,
            'selling_price' => (float) $this->selling_price,
            'average_cost' => (float) $this->average_cost,
            'total_qoh' => (float) ($this->inventories_sum_quantity_on_hand ?? $this->inventories()->sum('quantity_on_hand')),
            'reorder_point' => (float) $this->reorder_point,
            'is_active' => (bool) $this->is_active,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'uom' => new UnitOfMeasureResource($this->whenLoaded('uom')),
            'preferred_vendor' => new VendorResource($this->whenLoaded('preferredVendor')),
            'costing_method' => $this->costingMethod->name ?? 'unknown',
            'main_image_url' => $this->attachmentsIn('main_image')->first()?->file_path ? asset('storage/'.$this->attachmentsIn('main_image')->first()->file_path) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
