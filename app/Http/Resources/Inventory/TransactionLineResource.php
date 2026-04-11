<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionLineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product->name ?? 'DELETED PRODUCT',
            'product' => [
                'sku' => $this->product->sku ?? 'N/A',
                'product_code' => $this->product->product_code ?? 'N/A',
                'uom' => [
                    'abbreviation' => $this->uom->abbreviation ?? $this->product->uom->abbreviation ?? 'PCS',
                ],
                'uom_abbreviation' => $this->uom->abbreviation ?? $this->product->uom->abbreviation ?? 'PCS',
            ],
            'quantity' => (string) $this->quantity,
            'formatted_quantity' => $this->formatted_quantity,
            'uom_abbreviation' => $this->uom->abbreviation ?? $this->product->uom->abbreviation ?? 'PCS',
            'unit_cost' => $this->unit_cost ? (string) $this->unit_cost : '0',
            'formatted_unit_cost' => $this->formatted_unit_cost,
            'formatted_unit_cost_8dp' => $this->formatted_unit_cost_8dp,
            'unit_price' => $this->unit_price ? (string) $this->unit_price : '0',
            'formatted_unit_price' => $this->formatted_unit_price,
            'formatted_unit_price_8dp' => $this->formatted_unit_price_8dp,
            'total_cost' => \App\Helpers\FinancialMath::mul($this->unit_cost ? (string) $this->unit_cost : '0', str_replace('-', '', (string) $this->quantity)),
            'total_cost_8dp' => \App\Helpers\FinancialMath::mul($this->unit_cost ? (string) $this->unit_cost : '0', str_replace('-', '', (string) $this->quantity)),
            'location_id' => $this->location_id,
            'location_name' => $this->location->name ?? null,
            'type_name' => $this->transaction->type->label ?? $this->transaction->type->name ?? 'MOVEMENT',
            'transaction' => [
                'id' => $this->transaction_id,
                'reference_number' => $this->transaction->reference_number ?? 'N/A',
                'transaction_date' => $this->transaction->transaction_date ?? null,
                'type' => [
                    'name' => $this->transaction->type->name ?? 'unknown',
                    'label' => $this->transaction->type->label ?? 'Unknown',
                ],
            ],
        ];
    }
}
