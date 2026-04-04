<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderLineResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sales_order_id' => $this->sales_order_id,
            'product_id' => $this->product_id,
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ],
            'location_id' => $this->location_id,
            'location' => [
                'id' => $this->location->id,
                'name' => $this->location->name,
            ],
            'uom_id' => $this->uom_id,
            'uom' => [
                'id' => $this->uom->id,
                'name' => $this->uom->name,
                'abbreviation' => $this->uom->abbreviation,
            ],
            'ordered_qty' => $this->ordered_qty,
            'shipped_qty' => $this->shipped_qty,
            'picked_qty' => $this->picked_qty,
            'packed_qty' => $this->packed_qty,
            'returned_qty' => $this->returned_qty,
            'unit_price' => $this->unit_price,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'discount_rate' => $this->discount_rate,
            'discount_amount' => $this->discount_amount,
            'subtotal' => $this->subtotal,
            'notes' => $this->notes,
            'remaining_qty' => $this->remaining_qty,
        ];
    }
}
