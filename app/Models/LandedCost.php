<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCost extends Model
{
    /**
     * Valid cost type options.
     */
    public const COST_TYPES = ['Freight', 'Duty', 'Insurance', 'Handling', 'Other'];

    /**
     * Valid allocation methods.
     */
    public const METHOD_BY_VALUE    = 'by_value';
    public const METHOD_BY_QUANTITY = 'by_quantity';

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'cost_type',
        'amount',
        'notes',
        'allocation_method',
        'allocated_at',
        'allocated_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:8',
        'allocated_at' => 'datetime',
    ];

    // ─── Relationships ──────────────────────────────────────────────────────────

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function allocatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    // ─── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Whether this landed cost has already been applied to cost layers.
     */
    public function getIsAllocatedAttribute(): bool
    {
        return $this->allocated_at !== null;
    }
}
