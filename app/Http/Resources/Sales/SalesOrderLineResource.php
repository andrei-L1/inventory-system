<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderLineResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sales_order_id' => $this->sales_order_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product->name,
            'sku' => $this->product->sku,
            'location_name' => $this->location?->name,
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ],
            'location_id' => $this->location_id,
            'location' => [
                'id' => $this->location?->id,
                'name' => $this->location?->name,
            ],
            'uom_id' => $this->uom_id,
            'uom' => [
                'id' => $this->uom->id,
                'name' => $this->uom->name,
                'abbreviation' => $this->uom->abbreviation,
            ],
            'ordered_qty' => (float) $this->ordered_qty,
            'shipped_qty' => (float) $this->shipped_qty,
            'picked_qty' => (float) $this->picked_qty,
            'packed_qty' => (float) $this->packed_qty,
            'returned_qty' => (float) $this->returned_qty,
            'unit_price' => (float) $this->unit_price,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'discount_rate' => (float) $this->discount_rate,
            'discount_amount' => (float) $this->discount_amount,
            'subtotal' => (float) $this->subtotal,
            'notes' => $this->notes,
            'remaining_qty' => (float) $this->remaining_qty,
            'remaining_pick_qty' => (float) $this->remaining_pick_qty,
            'remaining_pack_qty' => (float) $this->remaining_pack_qty,
            'remaining_ship_qty' => (float) $this->remaining_ship_qty,
            'availability' => $this->product->inventories->map(fn ($inv) => [
                'location_name' => $inv->location->name,
                'quantity_on_hand' => (float) $inv->scaled_quantity_on_hand,
                'reserved_qty' => (float) $inv->scaled_reserved_qty,
                'available_qty' => (float) ($inv->scaled_quantity_on_hand - $inv->scaled_reserved_qty),
            ]),
        ];
    }
}
