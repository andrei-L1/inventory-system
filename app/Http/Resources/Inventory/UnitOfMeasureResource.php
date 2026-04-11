<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

class UnitOfMeasureResource extends JsonResource
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
            'abbreviation' => $this->abbreviation,
            'category' => $this->category,
            'is_base' => (bool) $this->is_base,
            'decimals' => (int) $this->decimals,
            'conversion_factor_to_base' => (string) $this->conversion_factor_to_base,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
