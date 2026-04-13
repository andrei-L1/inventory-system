<?php

namespace App\Models;

use App\Helpers\FinancialMath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_DRAFT = 'DRAFT';

    const STATUS_OPEN = 'OPEN';

    const STATUS_PAID = 'PAID';

    const STATUS_VOID = 'VOID';

    const TYPE_INVOICE = 'INVOICE';

    const TYPE_CREDIT_NOTE = 'CREDIT_NOTE';

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'sales_order_id',
        'invoice_date',
        'due_date',
        'total_amount',
        'paid_amount',
        'status',
        'type',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    protected $appends = [
        'balance_due',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * The running balance owed on this invoice (exposed in JSON via $appends).
     */
    public function getBalanceDueAttribute(): string
    {
        return FinancialMath::sub((string) $this->total_amount, (string) $this->paid_amount);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    /**
     * Formally void the invoice and reverse its financial impact.
     * 
     * Actions:
     * 1. Clears payment allocations (returning funds to the customer payment pool).
     * 2. Sets status to VOID.
     * 
     * Note: SalesOrderLine invoicing progress is calculated dynamically,
     * so it will automatically reflect the release of these items.
     */
    public function void(): bool
    {
        if ($this->status === self::STATUS_VOID) {
            return true;
        }

        return DB::transaction(function () {
            // 1. Reverse financial allocations (un-pay the invoice)
            foreach ($this->payments as $allocation) {
                $allocation->delete();
            }

            // 2. Update Invoice status
            $success = $this->update([
                'status' => self::STATUS_VOID,
                'paid_amount' => 0,
            ]);

            // 3. Sync Sales Order Billing Status and overall Fulfillment Status
            if ($this->salesOrder) {
                $this->salesOrder->syncBillingStatus();
                $this->salesOrder->recalculateStatus();
            }

            return $success;
        });
    }
}
