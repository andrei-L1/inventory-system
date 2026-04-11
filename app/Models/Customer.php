<?php

namespace App\Models;

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
        $unpaidInvoices = '0';
        foreach ($this->invoices()->whereIn('status', [Invoice::STATUS_OPEN])->get() as $inv) {
            $unpaidInvoices = \App\Helpers\FinancialMath::add($unpaidInvoices, \App\Helpers\FinancialMath::sub((string) $inv->total_amount, (string) $inv->paid_amount));
        }

        $unallocatedPayments = '0';
        foreach ($this->payments()->get() as $pay) {
            $unallocatedPayments = \App\Helpers\FinancialMath::add($unallocatedPayments, (string) $pay->unallocated_amount);
        }

        return \App\Helpers\FinancialMath::max('0', \App\Helpers\FinancialMath::sub($unpaidInvoices, $unallocatedPayments));
    }
}
