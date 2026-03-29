<?php

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product->name ?? null,
            'sku' => $this->product->sku ?? null,
            'uom' => $this->product->uom->abbreviation ?? null,
            'ordered_qty' => (float) $this->ordered_qty,
            'received_qty' => (float) $this->received_qty,
            'pending_qty' => (float) ($this->ordered_qty - $this->received_qty),
            'unit_cost' => (float) $this->unit_cost,
            'total_line_cost' => (float) $this->total_cost,
        ];
    }
}
