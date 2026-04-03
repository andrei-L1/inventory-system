<?php

namespace App\Http\Resources\Sales;

use App\Helpers\UomHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderLineResource extends JsonResource
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
            'so_line_id' => $this->id, // Frontend uses this to map fulfillments
            'product_id' => $this->product_id,
            'sku' => $this->product->sku ?? 'N/A',
            'product_name' => $this->product->name ?? 'Unknown',
            'product_code' => $this->product->product_code ?? null,
            'location_id' => $this->location_id,
            'location_name' => $this->location->name ?? 'N/A',
            'uom_id' => $this->uom_id,
            'uom_abbreviation' => $this->uom ? ($this->uom->abbreviation ?? 'PCS') : ($this->product->uom->abbreviation ?? 'PCS'),
            'ordered_qty' => (float) $this->ordered_qty,
            'picked_qty' => (float) $this->picked_qty,
            'packed_qty' => (float) $this->packed_qty,
            'shipped_qty' => (float) $this->shipped_qty,
            'returned_qty' => (float) $this->returned_qty,
            'pending_qty' => (float) $this->remaining_qty,
            'formatted_ordered_qty' => UomHelper::format($this->ordered_qty, $this->uom_id),
            'formatted_shipped_qty' => UomHelper::format($this->shipped_qty, $this->uom_id),
            'formatted_pending_qty' => UomHelper::format($this->remaining_qty, $this->uom_id),
            'unit_price' => (float) $this->unit_price,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'discount_rate' => (float) $this->discount_rate,
            'discount_amount' => (float) $this->discount_amount,
            'total_line_cost' => (float) ($this->ordered_qty * $this->unit_price) + (float) $this->tax_amount - (float) $this->discount_amount,
            'notes' => $this->notes,
        ];
    }
}
