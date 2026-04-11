<?php

namespace App\Services\Finance;

use App\Helpers\FinancialMath;
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
                $amountStr = (string) $inv->total_amount;
                $negAmount = FinancialMath::isNegative($amountStr) ? $amountStr : '-'.ltrim($amountStr, '-');
                $posAmount = ltrim($amountStr, '-');

                return [
                    'date' => $inv->invoice_date,
                    'reference' => $inv->invoice_number,
                    'type' => $inv->type === Invoice::TYPE_CREDIT_NOTE ? 'Credit Note' : 'Invoice',
                    'amount' => $amountStr,
                    'balance_impact' => $inv->type === Invoice::TYPE_CREDIT_NOTE ? $negAmount : $posAmount,
                ];
            });

        $payments = $customer->payments()
            ->when($startDate, fn ($q) => $q->where('payment_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('payment_date', '<=', $endDate))
            ->get()
            ->map(function ($pay) {
                $payAmtStr = (string) $pay->amount;
                $negPayAmt = FinancialMath::isNegative($payAmtStr) ? $payAmtStr : '-'.ltrim($payAmtStr, '-');

                return [
                    'date' => $pay->payment_date,
                    'reference' => $pay->payment_number,
                    'type' => 'Payment',
                    'amount' => $payAmtStr,
                    'balance_impact' => $negPayAmt,
                ];
            });

        return $invoices->concat($payments)->sortBy('date')->values();
    }
}
