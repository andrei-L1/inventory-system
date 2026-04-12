<?php

namespace App\Models;

use App\Helpers\FinancialMath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'customer_code',
        'email',
        'phone',
        'billing_address',
        'shipping_address',
        'tax_number',
        'credit_limit',
        'price_list_id',
        'is_active',
    ];

    public function priceList()
    {
        return $this->belongsTo(PriceList::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Total exposure = Unpaid balance on all Open Invoices - Unallocated Payment balances.
     */
    public function getExposureAttribute(): string
    {
        $unpaidDebits = '0';
        $unappliedCredits = '0';

        foreach ($this->invoices()->whereIn('status', [Invoice::STATUS_OPEN])->get() as $inv) {
            $balanceStr = FinancialMath::sub((string) $inv->total_amount, (string) $inv->paid_amount);
            
            if ($inv->type === Invoice::TYPE_CREDIT_NOTE) {
                // An open credit note acts as negative exposure (a credit we owe the customer)
                $unappliedCredits = FinancialMath::add($unappliedCredits, $balanceStr);
            } else {
                // A normal invoice adds to exposure
                $unpaidDebits = FinancialMath::add($unpaidDebits, $balanceStr);
            }
        }

        $unallocatedPayments = '0';
        foreach ($this->payments()->get() as $pay) {
            $unallocatedPayments = FinancialMath::add($unallocatedPayments, (string) $pay->unallocated_amount);
        }

        // Total Credits = Payments + Credit Notes
        $totalCredits = FinancialMath::add($unallocatedPayments, $unappliedCredits);

        return FinancialMath::max('0', FinancialMath::sub($unpaidDebits, $totalCredits));
    }
}
