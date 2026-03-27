<?php

namespace App\Models;

use App\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasAttachments, HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_number',
        'transaction_type_id',
        'vendor_id',
        'customer_id',
        'transaction_status_id',
        'from_location_id',
        'to_location_id',
        'transaction_date',
        'notes',
        'reference_doc',
        'purchase_order_id',
        'sales_order_id',
        'adjustment_reason_id',
        'created_by',
        'posted_by',
        'posted_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the purchase order that generated this movement.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the sales order that generated this movement.
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * Get the customer for this transaction.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the adjustment reason for this transaction.
     */
    public function adjustmentReason(): BelongsTo
    {
        return $this->belongsTo(AdjustmentReason::class);
    }

    /**
     * Get the type of this transaction.
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }

    /**
     * Get the status of this transaction.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TransactionStatus::class, 'transaction_status_id');
    }

    /**
     * Get the vendor for this transaction.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the shipments associated with this transaction.
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Get the source location (for transfers/issues).
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /**
     * Get the destination location (for transfers/receipts).
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    /**
     * Get the user who created the transaction.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who posted the transaction.
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Get the user who cancelled the transaction.
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Get the transaction lines.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }
}
