<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Resources\Json\JsonResource;

class VendorPaymentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'vendor' => [
                'id' => $this->vendor->id,
                'name' => $this->vendor->name,
            ],
            'amount' => $this->amount,
            'payment_date' => $this->payment_date,
            'reference_number' => $this->reference_number,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'unallocated_amount' => $this->unallocated_amount, // VendorPayment model logic
            'created_at' => $this->created_at,
        ];
    }
}
