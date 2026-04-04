<?php

namespace App\Http\Resources\Sales;

use App\Http\Resources\Inventory\TransactionResource;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'so_number' => $this->so_number,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'customer_code' => $this->customer?->customer_code,
            'customer' => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
                'code' => $this->customer?->customer_code,
            ],
            'status_id' => $this->status_id,
            'status' => [
                'id' => $this->status?->id,
                'name' => $this->status?->name,
                'is_editable' => $this->status?->is_editable,
            ],
            'order_date' => $this->order_date,
            'expected_shipping_date' => $this->expected_shipping_date,
            'shipped_at' => $this->shipped_at,
            'total_amount' => (float) $this->total_amount,
            'subtotal' => (float) ($this->lines->sum('subtotal') - $this->lines->sum('tax_amount')),
            'total_tax' => (float) $this->lines->sum('tax_amount'),
            'total_discount' => (float) $this->lines->sum('discount_amount'),
            'currency' => $this->currency,
            'notes' => $this->notes,
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'lines' => SalesOrderLineResource::collection($this->whenLoaded('lines')),
            'transactions' => TransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
