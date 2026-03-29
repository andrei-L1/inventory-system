<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'location_id' => $this->location_id,
            'quantity_on_hand' => (float) $this->quantity_on_hand,
            'average_cost' => (float) $this->average_cost,
            'total_value' => (float) ($this->quantity_on_hand * $this->average_cost),
            'last_movement_date' => $this->updated_at,

            // Relationships
            'product' => new ProductResource($this->whenLoaded('product')),
            'location' => [
                'id' => $this->location->id ?? null,
                'name' => $this->location->name ?? 'Unknown',
                'code' => $this->location->code ?? 'N/A',
            ],
        ];
    }
}
