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
            'total_amount' => (string) $this->total_amount,
            'formatted_total_amount' => \App\Helpers\FinancialMath::format($this->total_amount, 2),
            'subtotal' => (string) $this->lines->reduce(fn ($carry, $line) => \App\Helpers\FinancialMath::add($carry, \App\Helpers\FinancialMath::sub((string) $line->subtotal, (string) $line->tax_amount)), '0'),
            'formatted_subtotal' => \App\Helpers\FinancialMath::format($this->lines->reduce(fn ($carry, $line) => \App\Helpers\FinancialMath::add($carry, \App\Helpers\FinancialMath::sub((string) $line->subtotal, (string) $line->tax_amount)), '0'), 2),
            'total_tax' => (string) $this->lines->reduce(fn ($carry, $line) => \App\Helpers\FinancialMath::add($carry, (string) $line->tax_amount), '0'),
            'formatted_total_tax' => \App\Helpers\FinancialMath::format($this->lines->reduce(fn ($carry, $line) => \App\Helpers\FinancialMath::add($carry, (string) $line->tax_amount), '0'), 2),
            'total_discount' => (string) $this->lines->reduce(fn ($carry, $line) => \App\Helpers\FinancialMath::add($carry, (string) $line->discount_amount), '0'),
            'formatted_total_discount' => \App\Helpers\FinancialMath::format($this->lines->reduce(fn ($carry, $line) => \App\Helpers\FinancialMath::add($carry, (string) $line->discount_amount), '0'), 2),
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
