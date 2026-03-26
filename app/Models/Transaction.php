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
        'type',
        'vendor_id',
        'status',
        'from_location_id',
        'to_location_id',
        'transaction_date',
        'notes',
        'reference_doc',
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
     * Validates transaction business logic rules.
     */
    public function validateIntegrity()
    {
        if ($this->type === 'transfer') {
            if (!$this->from_location_id || !$this->to_location_id) {
                throw ValidationException::withMessages([
                    'type' => 'A transfer transaction must specify both from and to locations.'
                ]);
            }
        }

        if ($this->type === 'receipt') {
            if (!$this->vendor_id) {
                throw ValidationException::withMessages([
                    'vendor_id' => 'A receipt transaction must specify a vendor.'
                ]);
            }
        }

        if ($this->type === 'issue') {
            if ($this->vendor_id) {
                throw ValidationException::withMessages([
                    'vendor_id' => 'An issue transaction must NOT have a vendor.'
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
