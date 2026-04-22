<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'vendor' => [
                'id' => $this->vendor->id,
                'name' => $this->vendor->name,
                'vendor_code' => $this->vendor->vendor_code,
            ],
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order' => $this->purchaseOrder ? [
                'id' => $this->purchaseOrder->id,
                'po_number' => $this->purchaseOrder->po_number,
            ] : null,
            'bill_number' => $this->bill_number,
            'bill_date' => $this->bill_date,
            'due_date' => $this->due_date,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'balance' => $this->balance, // Bill model calculates balance (Total - Payments)
            'created_at' => $this->created_at,
        ];
    }
}
