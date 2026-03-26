<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'po_number',
        'vendor_id',
        'status_id',
        'order_date',
        'expected_delivery_date',
        'total_amount',
        'currency',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the vendor this PO is issued to.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the status of this PO.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderStatus::class, 'status_id');
    }

    /**
     * Get the line items for this PO.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * Get the inventory transactions (receipts) related to this PO.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the user who created this PO.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this PO.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if the PO is fully received.
     */
    public function isCompleted(): bool
    {
        return $this->lines->every(function ($line) {
            return $line->received_qty >= $line->ordered_qty;
        });
    }
}
