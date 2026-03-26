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
