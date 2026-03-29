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
            'created_by' => $this->creator->name ?? null,
            'approved_by' => $this->approver->name ?? null,
            'lines' => PurchaseOrderLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
