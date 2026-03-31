<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

class CostingMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => match ($this->name) {
                'average' => 'Weighted Average (Layered)',
                'fifo' => 'FIFO (First-In, First-Out)',
                'lifo' => 'LIFO (Last-In, First-Out)',
                default => $this->label,
            },
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
