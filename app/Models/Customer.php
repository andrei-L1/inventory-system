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
    public function getExposureAttribute(): float
    {
        $unpaidInvoices = (float) $this->invoices()
            ->whereIn('status', [Invoice::STATUS_OPEN])
            ->get()
            ->sum(function ($inv) {
                return (float) ($inv->total_amount - $inv->paid_amount);
            });

        $unallocatedPayments = (float) $this->payments()
            ->get()
            ->sum(function ($pay) {
                return (float) $pay->unallocated_amount;
            });

        return max(0, $unpaidInvoices - $unallocatedPayments);
    }
}
