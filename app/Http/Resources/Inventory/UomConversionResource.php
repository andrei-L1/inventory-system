<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UomConversionResource extends JsonResource
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
            'product_id' => $this->product_id,
            'from_uom_id' => $this->from_uom_id,
            'to_uom_id' => $this->to_uom_id,
            'conversion_factor' => (float) $this->conversion_factor,
            
            // Relationships
            'from_uom' => new UnitOfMeasureResource($this->whenLoaded('fromUom')),
            'to_uom' => new UnitOfMeasureResource($this->whenLoaded('toUom')),
            'product_name' => $this->product?->name,
            'product_sku' => $this->product?->sku,
        ];
    }
}
