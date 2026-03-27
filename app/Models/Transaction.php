<?php

namespace App\Models;

use App\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

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
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($transaction) {
            $transaction->validateIntegrity();
        });
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
     * Validates transaction business logic rules.
     */
    public function validateIntegrity()
    {
        // For performance, we check the type code if the relation is missing or already loaded
        $type = $this->type;

        if ($type) {
            if ($type->matchesCode('TRFR')) {
                if (! $this->from_location_id || ! $this->to_location_id) {
                    throw ValidationException::withMessages([
                        'to_location_id' => 'A transfer transaction must specify both origin and destination.',
                    ]);
                }
            }

            if ($type->matchesCode('RCPT') && ! $this->vendor_id) {
                throw ValidationException::withMessages([
                    'vendor_id' => 'A receipt transaction must specify a vendor.',
                ]);
            }

            if ($type->matchesCode('ISSU') && $this->vendor_id) {
                throw ValidationException::withMessages([
                    'vendor_id' => 'An issue transaction must NOT have a vendor.',
                ]);
            }
        }
    }

    /**
     * Get the vendor for this transaction.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the transaction lines.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }
}
