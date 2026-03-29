<?php

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'vendor_id' => $this->vendor_id,
            'vendor_name' => $this->vendor->name ?? null,
            'status' => $this->status->name ?? 'unknown',
            'is_editable' => $this->status->is_editable ?? false,
            'order_date' => $this->order_date ? $this->order_date->format('Y-m-d') : null,
            'expected_delivery_date' => $this->expected_delivery_date ? $this->expected_delivery_date->format('Y-m-d') : null,
            'total_amount' => (float) $this->total_amount,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'created_by' => $this->creator->name ?? 'System',
            'approved_by' => $this->approver->name ?? null,
            'approved_at' => $this->approved_at ? $this->approved_at->format('Y-m-d H:i') : null,
            'sent_at' => $this->sent_at ? $this->sent_at->format('Y-m-d H:i') : null,
            'shipped_at' => $this->shipped_at ? $this->shipped_at->format('Y-m-d H:i') : null,
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'lines' => PurchaseOrderLineResource::collection($this->whenLoaded('lines')),
            'receipts' => $this->transactions->map(fn ($t) => [
                'id' => $t->id,
                'reference_number' => $t->reference_number,
                'received_by' => $t->createdBy->name ?? 'System Administrator',
                'received_at' => $t->created_at->format('Y-m-d H:i'),
                'to_location' => $t->toLocation->name ?? 'Multiple Points',
                'lines' => $t->lines->map(fn ($l) => [
                    'sku' => $l->product->sku ?? 'N/A',
                    'product_name' => $l->product->name ?? 'Unknown',
                    'quantity' => (float) $l->quantity,
                    'uom' => $l->product->uom->abbreviation ?? '',
                ]),
            ]),
            'created_at' => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
