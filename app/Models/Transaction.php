<?php

namespace App\Models;

use App\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference_number
 * @property int $transaction_type_id
 * @property int|null $vendor_id
 * @property int|null $customer_id
 * @property int $transaction_status_id
 * @property int|null $from_location_id
 * @property int|null $to_location_id
 * @property Carbon $transaction_date
 * @property string|null $notes
 * @property string|null $reference_doc
 * @property int|null $purchase_order_id
 * @property int|null $sales_order_id
 * @property int|null $adjustment_reason_id
 * @property int $created_by
 * @property int|null $posted_by
 * @property Carbon|null $posted_at
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TransactionType $type
 * @property-read TransactionStatus $status
 * @property-read Location|null $fromLocation
 * @property-read Location|null $toLocation
 * @property-read Vendor|null $vendor
 * @property-read User $createdBy
 * @property-read Collection|TransactionLine[] $lines
 */
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
        'reverses_transaction_id',
        'created_by',
        'posted_by',
        'posted_at',
        'cancelled_by',
        'cancelled_at',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($transaction) {
            if (! $transaction->created_by && auth()->check()) {
                $transaction->created_by = auth()->id();
            }
        });
    }

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
     * Original transaction that this reversal entry voids (if this row is a reversal).
     */
    public function reversesTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transaction_id');
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
