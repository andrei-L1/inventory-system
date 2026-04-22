<?php

namespace App\Models;

use App\Helpers\FinancialMath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Bill extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_DRAFT = 'DRAFT';

    const STATUS_POSTED = 'POSTED';

    const STATUS_PAID = 'PAID';

    const STATUS_VOID = 'VOID';

    const TYPE_BILL = 'BILL';

    const TYPE_DEBIT_NOTE = 'DEBIT_NOTE';

    protected $fillable = [
        'vendor_id',
        'purchase_order_id',
        'ref_transaction_id',
        'bill_number',
        'bill_date',
        'due_date',
        'total_amount',
        'paid_amount',
        'status',
        'type',
        'notes',
        'reason',
    ];

    protected $casts = [
        'bill_date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'total_amount' => 'decimal:8',
        'paid_amount' => 'decimal:8',
    ];

    protected $appends = [
        'balance_due',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPaymentAllocation::class);
    }

    /**
     * The running balance owed on this bill.
     */
    public function getBalanceDueAttribute(): string
    {
        return FinancialMath::sub((string) $this->total_amount, (string) ($this->paid_amount ?? '0'));
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Formally void the bill and reverse its impact on procurement and finance.
     *
     * Actions:
     * 1. Reverts billed_qty on linked PO lines.
     * 2. Clears payment allocations (returning funds to the vendor payment pool).
     * 3. Sets status to VOID.
     */
    public function void(): bool
    {
        if ($this->status === self::STATUS_VOID) {
            return true;
        }

        return DB::transaction(function () {
            // 1. Reverse financial allocations (un-pay the bill)
            foreach ($this->payments as $allocation) {
                $allocation->delete();
            }

            // 2. Update Bill status
            $success = $this->update([
                'status' => self::STATUS_VOID,
                'paid_amount' => 0,
            ]);

            // 3. Sync PO Billing Status and overall Fulfillment Status
            if ($this->purchaseOrder) {
                $this->purchaseOrder->syncBillingStatus();
                $this->purchaseOrder->recalculateStatus();
            }

            return $success;
        });
    }
}
