<?php

namespace App\Models;

use App\Helpers\FinancialMath;
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
        'sent_at',
        'shipped_at',
        'carrier',
        'tracking_number',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($po) {
            if (! $po->created_by && auth()->check()) {
                $po->created_by = auth()->id();
            }
        });
    }

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'shipped_at' => 'datetime',
        'total_amount' => 'decimal:2', // DB column is decimal(18,2); GAAP compliant header total
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
            // FinancialMath::gte uses cmp() at scale=0 — strictly deterministic, no epsilon.
            return FinancialMath::gte((string) $line->received_qty, (string) $line->ordered_qty);
        });
    }

    /**
     * Determine if the PO is in a state where it can be formally cancelled.
     * Orders with existing receipts should be 'closed' instead.
     */
    public function getCanBeCancelledAttribute(): bool
    {
        if (in_array($this->status?->name, ['draft', 'cancelled', 'closed'])) {
            return false;
        }

        // Must not have any received quantity across all lines
        return $this->lines->every(fn ($l) => FinancialMath::isZero((string) $l->received_qty));
    }
}
