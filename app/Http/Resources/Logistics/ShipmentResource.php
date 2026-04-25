<?php

namespace App\Http\Resources\Logistics;

use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray($request): array
    {
        // Build tracking URL from carrier template + tracking number
        $trackingUrl = null;
        if ($this->tracking_number && $this->carrier?->tracking_url_template) {
            $trackingUrl = str_replace('{tracking_number}', $this->tracking_number, $this->carrier->tracking_url_template);
        }

        return [
            'id'              => $this->id,
            'shipment_number' => $this->shipment_number,
            'sales_order_id'  => $this->sales_order_id,
            'transaction_id'  => $this->transaction_id,
            'carrier'         => $this->whenLoaded('carrier', fn () => new CarrierResource($this->carrier)),
            'tracking_number' => $this->tracking_number,
            'tracking_url'    => $trackingUrl,
            'status'          => $this->status,
            'shipping_cost'   => (string) $this->shipping_cost,
            'notes'           => $this->notes,
            'shipped_at'      => $this->shipped_at?->toDateTimeString(),
            'delivered_at'    => $this->delivered_at?->toDateTimeString(),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
