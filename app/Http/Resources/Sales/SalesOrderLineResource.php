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
            'ordered_qty' => (string) $this->ordered_qty,
            'formatted_ordered_qty' => $this->formatted_ordered_qty,
            'shipped_qty' => (string) $this->shipped_qty,
            'formatted_shipped_qty' => $this->formatted_shipped_qty,
            'picked_qty' => (string) $this->picked_qty,
            'formatted_picked_qty' => $this->formatted_picked_qty,
            'packed_qty' => (string) $this->packed_qty,
            'formatted_packed_qty' => $this->formatted_packed_qty,
            'returned_qty' => (string) $this->returned_qty,
            'formatted_returned_qty' => $this->formatted_returned_qty,
            'unit_price' => (string) $this->unit_price,
            'formatted_unit_price' => $this->formatted_unit_price,
            'tax_rate' => (string) $this->tax_rate,
            'tax_amount' => (string) $this->tax_amount,
            'discount_rate' => (string) $this->discount_rate,
            'discount_amount' => (string) $this->discount_amount,
            'subtotal' => (string) $this->subtotal,
            'notes' => $this->notes,
            'remaining_qty' => (string) $this->remaining_qty,
            'formatted_remaining_qty' => $this->formatted_remaining_qty,
            'remaining_pick_qty' => (string) $this->remaining_pick_qty,
            'formatted_remaining_pick_qty' => $this->formatted_remaining_pick_qty,
            'remaining_pack_qty' => (string) $this->remaining_pack_qty,
            'formatted_remaining_pack_qty' => $this->formatted_remaining_pack_qty,
            'remaining_ship_qty' => (string) $this->remaining_ship_qty,
            'formatted_remaining_ship_qty' => $this->formatted_remaining_ship_qty,
            'remaining_return_qty' => (string) $this->remaining_return_qty,
            'formatted_remaining_return_qty' => $this->formatted_remaining_return_qty,
            'availability' => $this->product->inventories->map(fn ($inv) => [
                'location_name' => $inv->location->name,
                'quantity_on_hand' => (string) $inv->scaled_quantity_on_hand,
                'formatted_quantity_on_hand' => $inv->formatted_quantity_on_hand,
                'reserved_qty' => (string) $inv->scaled_reserved_qty,
                'formatted_reserved_qty' => $inv->formatted_reserved_qty,
                'available_qty' => \App\Helpers\FinancialMath::sub((string) $inv->scaled_quantity_on_hand, (string) $inv->scaled_reserved_qty),
                'formatted_available_qty' => $inv->formatted_available_qty,
            ]),
            'total_available_qty' => (string) $this->product->inventories->reduce(fn ($carry, $inv) => \App\Helpers\FinancialMath::add($carry, \App\Helpers\FinancialMath::sub((string) $inv->scaled_quantity_on_hand, (string) $inv->scaled_reserved_qty)), '0'),
            'formatted_total_available_qty' => UomHelper::format(
                (string) $this->product->inventories->reduce(fn ($carry, $inv) => \App\Helpers\FinancialMath::add($carry, \App\Helpers\FinancialMath::sub((string) $inv->scaled_quantity_on_hand, (string) $inv->scaled_reserved_qty)), '0'),
                $this->uom_id ?? $this->product->uom_id,
                $this->product_id,
                false
            ),
        ];
    }
}
