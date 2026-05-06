<?php

namespace App\Http\Resources\Logistics;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductSerialResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'serial_number' => $this->serial_number,
            'status' => $this->status,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'current_location' => $this->whenLoaded('currentLocation', fn () => [
                'id' => $this->currentLocation?->id,
                'name' => $this->currentLocation?->name,
                'code' => $this->currentLocation?->code,
            ]),
            'transaction_history' => $this->whenLoaded('transactionLines', fn () => $this->transactionLines->map(fn ($line) => [
                'transaction_line_id' => $line->id,
                'reference_number' => $line->transaction?->reference_number,
                'transaction_date' => $line->transaction?->transaction_date,
                'transaction_type' => $line->transaction?->type?->name,
                'quantity' => (string) $line->quantity,
                'formatted_quantity' => $line->formatted_quantity,
            ])
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
