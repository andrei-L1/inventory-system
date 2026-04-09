<?php

namespace App\Http\Resources\Sales;

use App\Helpers\UomHelper;
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
            'formatted_ordered_qty' => $this->formatted_ordered_qty,
            'shipped_qty' => (float) $this->shipped_qty,
            'formatted_shipped_qty' => $this->formatted_shipped_qty,
            'picked_qty' => (float) $this->picked_qty,
            'formatted_picked_qty' => $this->formatted_picked_qty,
            'packed_qty' => (float) $this->packed_qty,
            'formatted_packed_qty' => $this->formatted_packed_qty,
            'returned_qty' => (float) $this->returned_qty,
            'formatted_returned_qty' => $this->formatted_returned_qty,
            'unit_price' => (float) $this->unit_price,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'discount_rate' => (float) $this->discount_rate,
            'discount_amount' => (float) $this->discount_amount,
            'subtotal' => (float) $this->subtotal,
            'notes' => $this->notes,
            'remaining_qty' => (float) $this->remaining_qty,
            'formatted_remaining_qty' => $this->formatted_remaining_qty,
            'remaining_pick_qty' => (float) $this->remaining_pick_qty,
            'formatted_remaining_pick_qty' => $this->formatted_remaining_pick_qty,
            'remaining_pack_qty' => (float) $this->remaining_pack_qty,
            'formatted_remaining_pack_qty' => $this->formatted_remaining_pack_qty,
            'remaining_ship_qty' => (float) $this->remaining_ship_qty,
            'formatted_remaining_ship_qty' => $this->formatted_remaining_ship_qty,
            'remaining_return_qty' => (float) $this->remaining_return_qty,
            'formatted_remaining_return_qty' => $this->formatted_remaining_return_qty,
            'availability' => $this->product->inventories->map(fn ($inv) => [
                'location_name' => $inv->location->name,
                'quantity_on_hand' => (float) $inv->scaled_quantity_on_hand,
                'formatted_quantity_on_hand' => $inv->formatted_quantity_on_hand,
                'reserved_qty' => (float) $inv->scaled_reserved_qty,
                'formatted_reserved_qty' => $inv->formatted_reserved_qty,
                'available_qty' => (float) ($inv->scaled_quantity_on_hand - $inv->scaled_reserved_qty),
                'formatted_available_qty' => $inv->formatted_available_qty,
            ]),
            'total_available_qty' => (float) $this->product->inventories->sum(fn ($inv) => $inv->scaled_quantity_on_hand - $inv->scaled_reserved_qty),
            'formatted_total_available_qty' => UomHelper::format(
                $this->product->inventories->sum(fn ($inv) => $inv->scaled_quantity_on_hand - $inv->scaled_reserved_qty),
                $this->uom_id ?? $this->product->uom_id,
                $this->product_id,
                false
            ),
        ];
    }
}
