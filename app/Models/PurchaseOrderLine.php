<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'ordered_qty',
        'received_qty',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'ordered_qty' => 'decimal:4',
        'received_qty' => 'decimal:4',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:6', // Virtual column from DB
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
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the remaining quantity to be received.
     */
    public function getRemainingQtyAttribute(): float
    {
        return max(0, $this->ordered_qty - $this->received_qty);
    }
}
