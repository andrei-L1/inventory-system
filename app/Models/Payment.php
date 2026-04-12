<?php

namespace App\Models;

use App\Helpers\FinancialMath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payment_number',
        'customer_id',
        'payment_date',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date:Y-m-d',
        'amount' => 'decimal:8',
    ];

    protected $appends = [
        'unallocated_amount',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function getUnallocatedAmountAttribute(): string
    {
        $allocated = '0';
        foreach ($this->allocations as $allocation) {
            $allocated = FinancialMath::add($allocated, (string) $allocation->amount);
        }

        $refunded = '0';
        foreach ($this->refunds as $refund) {
            $refunded = FinancialMath::add($refunded, (string) $refund->amount);
        }

        $spent = FinancialMath::add($allocated, $refunded);
        return FinancialMath::sub((string) $this->amount, $spent);
    }
}
