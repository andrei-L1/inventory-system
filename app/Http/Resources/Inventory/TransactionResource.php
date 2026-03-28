<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray($request)
    {
        // If we filtered lines in the controller for a specific product
        $line = $this->lines->first();

        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'type' => $this->type->name ?? 'unknown',
            'status' => $this->status->name ?? 'unknown',
            'from_location' => $this->fromLocation->name ?? 'N/A',
            'to_location' => $this->toLocation->name ?? 'N/A',
            'transaction_date' => $this->transaction_date->format('Y-m-d'),
            'vendor_name' => $this->vendor->name ?? null,
            'vendor_id' => $this->vendor->id ?? null,
            'customer_name' => $this->customer->name ?? null,
            'customer_id' => $this->customer->id ?? null,
            'notes' => $this->notes,
            'reference_doc' => $this->reference_doc,

            // Linkable documents (Relational)
            'po_number' => $this->purchaseOrder->po_number ?? null,
            'po_id' => $this->purchaseOrder->id ?? null,
            'so_number' => $this->salesOrder->so_number ?? null,
            'so_id' => $this->salesOrder->id ?? null,

            // Line specific data (for Inventory Center history)
            'quantity' => $line->quantity ?? null,
            'unit_cost' => $line->unit_cost ?? 0,
            'unit_price' => $line->unit_price ?? 0,
            'total_cost' => $line->total_cost ?? 0,

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
