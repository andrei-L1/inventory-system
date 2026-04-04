<?php

namespace App\Models;

use App\Helpers\UomHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'uom_id',
        'ordered_qty',
        'received_qty',
        'returned_qty',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'ordered_qty' => 'decimal:8',
        'received_qty' => 'decimal:8',
        'returned_qty' => 'decimal:8',
        'unit_cost' => 'decimal:8',
        'total_cost' => 'decimal:8', // Virtual column from DB
    ];

    /**
     * Get the PO this line belongs to.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the product being ordered.
     * Including trashed allows historical POs to show product info.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Get the UOM used for this specific order line.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Get the remaining quantity to be received.
     */
    public function getRemainingQtyAttribute(): float
    {
        return max(0, (float) $this->ordered_qty - (float) $this->received_qty);
    }

    public function getFormattedOrderedQtyAttribute(): string
    {
        return UomHelper::format($this->ordered_qty, $this->uom_id ?? $this->product->uom_id);
    }

    public function getFormattedReceivedQtyAttribute(): string
    {
        return UomHelper::format($this->received_qty, $this->uom_id ?? $this->product->uom_id);
    }

    public function getFormattedReturnedQtyAttribute(): string
    {
        return UomHelper::format($this->returned_qty, $this->uom_id ?? $this->product->uom_id);
    }

    public function getFormattedPendingQtyAttribute(): string
    {
        return UomHelper::format($this->remaining_qty, $this->uom_id ?? $this->product->uom_id);
    }
}
