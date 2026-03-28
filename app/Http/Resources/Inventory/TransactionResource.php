<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'type' => $this->type->name ?? 'unknown',
            'status' => $this->status->name ?? 'unknown',
            'from_location' => $this->fromLocation->name ?? 'N/A',
            'to_location' => $this->toLocation->name ?? 'N/A',
            'transaction_date' => $this->transaction_date->format('Y-m-d'),
            'vendor_name' => $this->vendor->name ?? null,
            'notes' => $this->notes,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
