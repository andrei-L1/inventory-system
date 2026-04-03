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
            'sku' => $this->sku ?? $this->product_code, // Guaranteed display ID
            'product_code' => $this->product_code,
            'name' => $this->name,
            'description' => $this->description,
            'barcode' => $this->barcode,
            'brand' => $this->brand,
            'selling_price' => (float) $this->selling_price,
            'average_cost' => (float) $this->average_cost,
            'total_qoh' => (float) ($this->inventories_sum_quantity_on_hand ?? ($this->relationLoaded('inventories') ? $this->inventories->sum('quantity_on_hand') : $this->inventories()->sum('quantity_on_hand'))),
            'formatted_total_qoh' => $this->formatted_total_qoh,
            'reorder_point' => (float) $this->reorder_point,
            'reorder_quantity' => (float) $this->reorder_quantity,

            'is_active' => (bool) $this->is_active,
            'category_id' => $this->category_id,
            'uom_id' => $this->uom_id,
            'costing_method_id' => $this->costing_method_id,
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
