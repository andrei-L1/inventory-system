<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'so_number' => $this->so_number,
            'customer_id' => $this->customer_id,
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'status_id' => $this->status_id,
            'status' => [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'is_editable' => $this->status->is_editable,
            ],
            'order_date' => $this->order_date,
            'expected_shipping_date' => $this->expected_shipping_date,
            'shipped_at' => $this->shipped_at,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'lines' => SalesOrderLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at,
        ];
    }
}
