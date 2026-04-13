<?php

namespace App\Http\Resources\Procurement;

use App\Helpers\FinancialMath;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product->name ?? '[DELETED PRODUCT]',
            'sku' => $this->product?->sku ?? 'N/A',
            'product_code' => $this->product?->product_code ?? 'N/A',
            'uom_id' => $this->uom_id ?? $this->product->uom_id,
            'uom' => $this->uom_id ? ($this->uom->abbreviation ?? null) : ($this->product->uom->abbreviation ?? null),
            'ordered_qty' => (string) $this->ordered_qty,
            'received_qty' => (string) $this->received_qty,
            'formatted_received_qty' => $this->formatted_received_qty,
            'returned_qty' => (string) $this->returned_qty,
            'formatted_returned_qty' => $this->formatted_returned_qty,
            'pending_qty' => FinancialMath::max('0', FinancialMath::sub((string) $this->ordered_qty, (string) $this->received_qty)), // H-6: guard against negative after credit return
            'uom_id' => $this->uom_id,
            'uom_abbreviation' => $this->uom->abbreviation ?? 'pcs',
            'ordered_qty' => (string) $this->ordered_qty,
            'formatted_ordered_qty' => $this->formatted_ordered_qty,
            'formatted_pending_qty' => $this->formatted_pending_qty,
            'billed_qty' => (string) $this->billed_qty,
            'billable_qty' => (string) $this->billable_qty, // Net available (Received - Billed - Returns)
            'unit_cost' => (string) $this->unit_cost,
            'formatted_unit_cost' => $this->formatted_unit_cost,
            'total_line_cost' => (string) $this->total_cost,
        ];
    }
}
