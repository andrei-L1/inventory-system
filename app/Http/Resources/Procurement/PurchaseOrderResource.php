<?php

namespace App\Http\Resources\Procurement;

use App\Helpers\FinancialMath;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'vendor_id' => $this->vendor_id,
            'vendor_name' => $this->vendor->name ?? null,
            'status' => $this->status->name ?? 'unknown',
            'is_editable' => $this->status->is_editable ?? false,
            'order_date' => $this->order_date ? $this->order_date->format('Y-m-d') : null,
            'expected_delivery_date' => $this->expected_delivery_date ? $this->expected_delivery_date->format('Y-m-d') : null,
            'total_amount' => (string) $this->total_amount,
            'formatted_total_amount' => FinancialMath::format($this->total_amount, 2),
            'currency' => $this->currency,
            'notes' => $this->notes,
            'created_by' => $this->creator->name ?? 'System',
            'approved_by' => $this->approver->name ?? null,
            'approved_at' => $this->approved_at ? $this->approved_at->format('Y-m-d H:i') : null,
            'sent_at' => $this->sent_at ? $this->sent_at->format('Y-m-d H:i') : null,
            'shipped_at' => $this->shipped_at ? $this->shipped_at->format('Y-m-d H:i') : null,
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'lines' => PurchaseOrderLineResource::collection($this->whenLoaded('lines')),
            'receipts' => $this->when($this->relationLoaded('transactions'), function () {
                return $this->transactions->filter(fn ($t) => $t->type->code === 'RCPT')->values()->map(fn ($t) => [
                    'id' => $t->id,
                    'reference_number' => $t->reference_number,
                    'received_by' => $t->createdBy->name ?? 'System',
                    'received_at' => $t->created_at->format('Y-m-d H:i'),
                    'to_location' => $t->toLocation->name ?? $t->lines->first()?->location?->name ?? 'Mixed/Unknown',
                    'lines' => $t->lines->map(fn ($l) => [
                        'transaction_line_id' => $l->id,
                        'po_line_id' => $this->lines->firstWhere('product_id', $l->product_id)?->id,
                        'sku' => $l->product->sku ?? 'N/A',
                        'product_name' => $l->product->name ?? 'Unknown',
                        'quantity' => (string) $l->quantity,
                        'billed_qty' => (string) $l->billed_qty,
                        'billable_qty' => (string) ($this->lines->firstWhere('product_id', $l->product_id)?->billable_qty ?? '0'),
                        'formatted_quantity' => $l->formatted_quantity,
                        'uom_id' => $l->uom_id ?? $l->product->uom_id,
                        'product_id' => $l->product_id,
                        'uom_abbreviation' => $l->uom->abbreviation ?? $l->product->uom->abbreviation ?? 'PCS',
                        'base_uom' => (function() use ($l) {
                            $category = $l->product?->uom?->category;
                            if (!$category) return null;
                            $baseUom = \App\Models\UnitOfMeasure::where('category', $category)->where('is_base', 1)->first();
                            if (!$baseUom) return null;
                            return [
                                'id'           => $baseUom->id,
                                'abbreviation' => $baseUom->abbreviation,
                                'name'         => $baseUom->name,
                                'category'     => $baseUom->category,
                                'decimals'     => $baseUom->decimals,
                            ];
                        })(),
                    ]),
                ]);
            }),
            'returns' => $this->when($this->relationLoaded('transactions'), function () {
                return $this->transactions->filter(fn ($t) => $t->type->code === 'PRET')->values()->map(fn ($t) => [
                    'id' => $t->id,
                    'reference_number' => $t->reference_number,
                    'returned_by' => $t->createdBy->name ?? 'System',
                    'returned_at' => $t->created_at->format('Y-m-d H:i'),
                    'from_location' => $t->fromLocation->name ?? $t->lines->first()?->location?->name ?? 'Mixed/Unknown',
                    'lines' => $t->lines->map(function ($l) {
                        // Recovery logic: if notes are empty, try to find the resolution in the PO line notes
                        $notes = $l->notes;
                        if (! $notes && $this->relationLoaded('lines')) {
                            $poLine = $this->lines->firstWhere('product_id', $l->product_id);
                            if ($poLine && $poLine->notes && preg_match('/\((replacement|credit)\)/', $poLine->notes, $matches)) {
                                $notes = 'Resolution: '.ucfirst($matches[1]);
                            }
                        }

                        return [
                            'sku' => $l->product->sku ?? 'N/A',
                            'product_name' => $l->product->name ?? 'Unknown',
                            'quantity' => str_replace('-', '', (string) $l->quantity),
                            'formatted_quantity' => $l->formatted_quantity,
                            'uom_abbreviation' => $l->uom->abbreviation ?? $l->product->uom->abbreviation ?? 'PCS',
                            'base_uom' => (function() use ($l) {
                                $category = $l->product?->uom?->category;
                                if (!$category) return null;
                                $baseUom = \App\Models\UnitOfMeasure::where('category', $category)->where('is_base', 1)->first();
                                if (!$baseUom) return null;
                                return [
                                    'id'           => $baseUom->id,
                                    'abbreviation' => $baseUom->abbreviation,
                                    'name'         => $baseUom->name,
                                    'category'     => $baseUom->category,
                                    'decimals'     => $baseUom->decimals,
                                ];
                            })(),
                            'notes' => $notes,
                        ];
                    }),
                ]);
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
