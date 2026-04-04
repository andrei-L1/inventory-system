<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
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
            'so_number' => $this->so_number,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer->name ?? null,
            'status' => $this->status->name ?? 'unknown',
            'is_editable' => $this->status->is_editable ?? false,
            'order_date' => $this->order_date ? $this->order_date->format('Y-m-d') : null,
            'requested_delivery_date' => $this->requested_delivery_date ? $this->requested_delivery_date->format('Y-m-d') : null,
            'total_amount' => (float) $this->total_amount,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'created_by' => $this->creator->name ?? 'System',
            'approved_by' => $this->approver->name ?? null,
            'approved_at' => $this->approved_at ? $this->approved_at->format('Y-m-d H:i') : null,
            'confirmed_at' => $this->confirmed_at ? $this->confirmed_at->format('Y-m-d H:i') : null,
            'sent_at' => $this->sent_at ? $this->sent_at->format('Y-m-d H:i') : null,
            'shipped_at' => $this->shipped_at ? $this->shipped_at->format('Y-m-d H:i') : null,
            'delivered_at' => $this->delivered_at ? $this->delivered_at->format('Y-m-d H:i') : null,
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'lines' => SalesOrderLineResource::collection($this->whenLoaded('lines')),
            'fulfillments' => $this->when($this->relationLoaded('transactions'), function () {
                return $this->transactions->filter(fn ($t) => $t->type->code === 'ISS')->values()->map(fn ($t) => [
                    'id' => $t->id,
                    'reference_number' => $t->reference_number,
                    'shipped_by' => $t->createdBy->name ?? 'System',
                    'shipped_at' => $t->created_at->format('Y-m-d H:i'),
                    'from_location' => $t->fromLocation->name ?? $t->lines->first()?->location?->name ?? 'Mixed/Unknown',
                    'lines' => $t->lines->map(fn ($l) => [
                        'sku' => $l->product->sku ?? 'N/A',
                        'product_name' => $l->product->name ?? 'Unknown',
                        'quantity' => (float) abs($l->quantity),
                        'formatted_quantity' => $l->formatted_quantity,
                        'uom_abbreviation' => $l->uom->abbreviation ?? $l->product->uom->abbreviation ?? 'PCS',
                    ]),
                ]);
            }),
            'returns' => $this->when($this->relationLoaded('transactions'), function () {
                return $this->transactions->filter(fn ($t) => $t->type->code === 'SRET')->values()->map(fn ($t) => [
                    'id' => $t->id,
                    'reference_number' => $t->reference_number,
                    'returned_by' => $t->createdBy->name ?? 'System',
                    'returned_at' => $t->created_at->format('Y-m-d H:i'),
                    'to_location' => $t->toLocation->name ?? $t->lines->first()?->location?->name ?? 'Mixed/Unknown',
                    'lines' => $t->lines->map(fn ($l) => [
                        'sku' => $l->product->sku ?? 'N/A',
                        'product_name' => $l->product->name ?? 'Unknown',
                        'quantity' => (float) $l->quantity,
                        'formatted_quantity' => $l->formatted_quantity,
                        'uom_abbreviation' => $l->uom->abbreviation ?? $l->product->uom->abbreviation ?? 'PCS',
                        'notes' => $l->notes,
                    ]),
                ]);
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
