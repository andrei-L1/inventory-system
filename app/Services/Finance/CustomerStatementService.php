<?php

namespace App\Services\Finance;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Collection;

class CustomerStatementService
{
    /**
     * Generate customer transaction history.
     */
    public function getStatement(Customer $customer, $startDate = null, $endDate = null): Collection
    {
        $invoices = $customer->invoices()
            ->when($startDate, fn ($q) => $q->where('invoice_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('invoice_date', '<=', $endDate))
            ->get()
            ->map(function ($inv) {
                return [
                    'date' => $inv->invoice_date,
                    'reference' => $inv->invoice_number,
                    'type' => $inv->type === Invoice::TYPE_CREDIT_NOTE ? 'Credit Note' : 'Invoice',
                    'amount' => (float) $inv->total_amount,
                    'balance_impact' => (float) ($inv->type === Invoice::TYPE_CREDIT_NOTE ? -abs($inv->total_amount) : abs($inv->total_amount)),
                ];
            });

        $payments = $customer->payments()
            ->when($startDate, fn ($q) => $q->where('payment_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('payment_date', '<=', $endDate))
            ->get()
            ->map(function ($pay) {
                return [
                    'date' => $pay->payment_date,
                    'reference' => $pay->payment_number,
                    'type' => 'Payment',
                    'amount' => (float) $pay->amount,
                    'balance_impact' => -(float) abs($pay->amount),
                ];
            });

        return $invoices->concat($payments)->sortBy('date')->values();
    }
}
