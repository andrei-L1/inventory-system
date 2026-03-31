<?php

namespace App\Http\Resources\Inventory;

use App\Models\Transaction;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    public function toArray($request)
    {
        // If we filtered lines in the controller for a specific product
        $line = $this->lines ? $this->lines->first() : null;

        // Enhanced type name for better ledger distinction
        $typeName = $this->type->name ?? 'unknown';
        if ($this->purchase_order_id && strtolower($typeName) === 'receipt') {
            $typeName = 'good_receipt'; // Consistent identifier for frontend mapping
        }

        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'type' => [
                'id' => $this->transaction_type_id,
                'name' => $this->type->name ?? 'unknown',
                'display' => $typeName,
            ],
            'display_type' => $this->purchase_order_id ? 'GOODS RECEIPT' : strtoupper($this->type->name ?? 'unknown'),
            'status' => [
                'id' => $this->transaction_status_id,
                'name' => $this->status->name ?? 'unknown',
            ],
            'status_name' => $this->status->name ?? 'unknown', // Fallback for flat tables
            'from_location' => $this->fromLocation->name ?? 'N/A',
            'to_location' => $this->toLocation->name ?? $line?->location?->name ?? 'N/A',
            'from_location_name' => $this->fromLocation->name ?? 'N/A', // Direct property for consistency
            'to_location_name' => $this->toLocation->name ?? $line?->location?->name ?? 'N/A',
            'transaction_date' => $this->transaction_date->format('Y-m-d'),
            'vendor_name' => $this->vendor->name ?? null,
            'vendor_id' => $this->vendor_id,
            'customer_name' => $this->customer->name ?? null,
            'customer_id' => $this->customer_id,
            'notes' => $this->notes,
            'reference_doc' => $this->reference_doc,

            // Linkable documents (Relational)
            'po_number' => $this->purchaseOrder->po_number ?? null,
            'purchase_order_number' => $this->purchaseOrder->po_number ?? null,
            'purchase_order_id' => $this->purchase_order_id,
            'po_id' => $this->purchase_order_id,
            'so_number' => $this->salesOrder->so_number ?? null,
            'so_id' => $this->sales_order_id,

            // Multi-line support for receipts/audits
            'lines' => TransactionLineResource::collection($this->whenLoaded('lines')),

            // Line specific data (for Inventory Center history - single product view)
            'product_id' => $line->product_id ?? null,
            'product_name' => $line->product->name ?? null,
            'quantity' => (float) ($line->quantity ?? null),
            'uom_abbreviation' => $line->uom->abbreviation ?? $line->product->uom->abbreviation ?? 'PCS',
            'unit_cost' => (float) ($line->unit_cost ?? 0),
            'unit_price' => (float) ($line->unit_price ?? 0),
            'total_cost' => (float) ($line->total_cost ?? 0),

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
