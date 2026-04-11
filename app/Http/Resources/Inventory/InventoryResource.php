<?php

namespace App\Http\Resources\Inventory;

use App\Helpers\UomHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $targetUomId = $request->query('target_uom_id');
        $qoh = (string) $this->quantity_on_hand;

        if ($targetUomId) {
            $multiplier = (string) UomHelper::getMultiplierToSmallest((int) $targetUomId, $this->product_id, false);
            $scaledQoh = \App\Helpers\FinancialMath::isPositive($multiplier) ? \App\Helpers\FinancialMath::div($qoh, $multiplier) : $qoh;
            $formattedQuantityOnHand = UomHelper::format($scaledQoh, (int) $targetUomId, $this->product_id, false);
        } else {
            $formattedQuantityOnHand = $this->formatted_quantity_on_hand;
        }

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'location_id' => $this->location_id,
            'quantity_on_hand' => $qoh,
            'formatted_quantity_on_hand' => $formattedQuantityOnHand,
            'average_cost' => (string) $this->average_cost,
            'formatted_average_cost' => $this->formatted_average_cost,
            'formatted_average_cost_8dp' => $this->formatted_average_cost_8dp,
            'total_value' => \App\Helpers\FinancialMath::mul($qoh, (string) $this->average_cost),
            'last_movement_date' => $this->updated_at,

            // Relationships
            'product' => new ProductResource($this->whenLoaded('product')),
            'location' => [
                'id' => $this->location?->id ?? null,
                'name' => $this->location?->name ?? 'Unknown',
                'code' => $this->location?->code ?? 'N/A',
            ],
        ];
    }
}
