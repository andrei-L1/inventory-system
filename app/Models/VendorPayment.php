<?php

namespace App\Models;

use App\Helpers\FinancialMath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class VendorPayment extends Model
{
    use HasFactory;

    const STATUS_PAID = 'PAID';

    const STATUS_VOID = 'VOID';

    protected $fillable = [
        'payment_number',
        'vendor_id',
        'amount',
        'payment_date',
        'reference_number',
        'payment_method',
        'notes',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date:Y-m-d',
    ];

    protected $appends = [
        'unallocated_amount',
        'refunded_amount',
    ];

    public function getRefundedAmountAttribute(): string
    {
        $refunded = '0';
        foreach ($this->refunds as $refund) {
            $refunded = FinancialMath::add($refunded, (string) $refund->amount);
        }

        return $refunded;
    }

    public function getUnallocatedAmountAttribute(): string
    {
        $allocated = '0';
        foreach ($this->allocations as $allocation) {
            $allocated = FinancialMath::add($allocated, (string) $allocation->amount);
        }

        $spent = FinancialMath::add($allocated, $this->refunded_amount);

        return FinancialMath::sub((string) $this->amount, $spent);
    }

    /**
     * Officially void the disbursement and reverse bill impact.
     */
    public function void(): bool
    {
        if ($this->status === self::STATUS_VOID) {
            return true;
        }

        return DB::transaction(function () {
            foreach ($this->allocations as $allocation) {
                $bill = $allocation->bill;
                if ($bill) {
                    $newPaidAmount = FinancialMath::sub((string) $bill->paid_amount, (string) $allocation->amount);
                    $bill->update([
                        'paid_amount' => $newPaidAmount,
                        'status' => Bill::STATUS_POSTED, // Revert to posted
                    ]);
                }
                $allocation->delete();
            }

            return $this->update(['status' => self::STATUS_VOID]);
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(VendorPaymentAllocation::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(VendorPaymentRefund::class);
    }
}
