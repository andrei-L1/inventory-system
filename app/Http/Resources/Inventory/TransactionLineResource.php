<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionLineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product->name ?? 'DELETED PRODUCT',
            'product' => [
                'sku' => $this->product->sku ?? 'N/A',
                'product_code' => $this->product->product_code ?? 'N/A',
                'uom' => [
                    'abbreviation' => $this->product->uom->abbreviation ?? 'PCS',
                ],
            ],
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) ($this->unit_cost ?? 0),
            'unit_price' => (float) ($this->unit_price ?? 0),
            'total_cost' => (float) (($this->unit_cost ?? 0) * abs($this->quantity)),
            'location_id' => $this->location_id,
            'location_name' => $this->location->name ?? null,
        ];
    }
}
