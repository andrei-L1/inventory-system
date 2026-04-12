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
    /**
     * Total exposure = Unpaid balance on all Open Invoices 
     *                + Uninvoiced value of Approved Sales Orders
     *                - Unallocated Payment balances.
     */
    public function getExposureAttribute(): string
    {
        // 1. Debt from Invoiced items (Debits - Credits already in AR)
        $unpaidDebits = '0';
        $openCreditNotes = '0';

        $activeInvoices = $this->invoices()->whereIn('status', [Invoice::STATUS_OPEN])->get();
        foreach ($activeInvoices as $inv) {
            $balanceStr = FinancialMath::sub((string) $inv->total_amount, (string) $inv->paid_amount);
            
            if ($inv->type === Invoice::TYPE_CREDIT_NOTE) {
                // An open credit note acts as negative exposure
                $openCreditNotes = FinancialMath::add($openCreditNotes, $balanceStr);
            } else {
                // A normal invoice adds to exposure
                $unpaidDebits = FinancialMath::add($unpaidDebits, $balanceStr);
            }
        }

        // 2. Pending Debt (Approved Sales Orders that are shipped/picked but not yet invoiced)
        $pendingBilling = '0';
        $openSOs = $this->salesOrders()
            ->whereNotIn('status_id', [
                SalesOrderStatus::where('name', SalesOrderStatus::QUOTATION)->first()?->id,
                SalesOrderStatus::where('name', SalesOrderStatus::CANCELLED)->first()?->id,
            ])
            ->with('lines')
            ->get();
            
        foreach ($openSOs as $so) {
            $pendingBilling = FinancialMath::add($pendingBilling, $so->uninvoiced_amount);
        }

        // 3. Unapplied Cash (Payments sitting in unallocated bucket)
        $unallocatedPayments = '0';
        $activePayments = $this->payments()->with(['allocations', 'refunds'])->get();
        foreach ($activePayments as $pay) {
            $unallocatedPayments = FinancialMath::add($unallocatedPayments, (string) $pay->unallocated_amount);
        }

        // Exposure = (AR Invoices + Pending SOs) - (Credit Notes + Cash on Hand)
        $totalDebits = FinancialMath::add($unpaidDebits, $pendingBilling);
        $totalCredits = FinancialMath::add($unallocatedPayments, $openCreditNotes);

        return FinancialMath::max('0', FinancialMath::sub($totalDebits, $totalCredits));
    }
}
