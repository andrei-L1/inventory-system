<?php

namespace App\Services\Finance;

use App\Helpers\FinancialMath;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CustomerStatementService
{
    /**
     * Generate a chronological running balance statement for a customer.
     *
     * @param int $customerId
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array
     */
    public function generateStatement(int $customerId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $customer = Customer::findOrFail($customerId);

        // Fetch Invoices (Debits) and Credit Notes (Credits)
        $invoicesQuery = Invoice::where('customer_id', $customerId)
            ->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PAID]);
            
        if ($dateFrom) $invoicesQuery->whereDate('invoice_date', '>=', $dateFrom);
        if ($dateTo) $invoicesQuery->whereDate('invoice_date', '<=', $dateTo);
        
        $invoices = $invoicesQuery->get();

        // Fetch Payments (Credits)
        $paymentsQuery = Payment::where('customer_id', $customerId);
        if ($dateFrom) $paymentsQuery->whereDate('payment_date', '>=', $dateFrom);
        if ($dateTo) $paymentsQuery->whereDate('payment_date', '<=', $dateTo);
        
        $payments = $paymentsQuery->get();

        // Standardize structure for sorting
        $transactions = collect();

        foreach ($invoices as $invoice) {
            $isCreditNote = $invoice->type === Invoice::TYPE_CREDIT_NOTE;
            
            $transactions->push([
                'id' => $invoice->id,
                'date' => Carbon::parse($invoice->invoice_date)->toDateString(),
                'type' => $isCreditNote ? 'Credit Note' : 'Invoice',
                'reference' => $invoice->invoice_number,
                'description' => $invoice->notes ?? ($isCreditNote ? 'Credit applied' : 'Sales Invoice'),
                'debit' => $isCreditNote ? '0' : (string) $invoice->total_amount,
                'credit' => $isCreditNote ? (string) $invoice->total_amount : '0',
                'raw_date' => Carbon::parse($invoice->invoice_date),
            ]);
        }

        foreach ($payments as $payment) {
            $transactions->push([
                'id' => $payment->id,
                'date' => Carbon::parse($payment->payment_date)->toDateString(),
                'type' => 'Payment',
                'reference' => $payment->payment_number,
                'description' => $payment->notes ?? 'Payment received ' . ($payment->payment_method ? "({$payment->payment_method})" : ''),
                'debit' => '0',
                'credit' => (string) $payment->amount,
                'raw_date' => Carbon::parse($payment->payment_date),
            ]);
        }

        // Sort chronologically
        $sortedTransactions = $transactions->sortBy(function ($item) {
            return $item['raw_date']->timestamp . '_' . ($item['type'] === 'Invoice' ? 'A' : 'B');
        })->values();

        $runningBalance = '0';
        $finalStatement = [];
        $totalDebits = '0';
        $totalCredits = '0';

        foreach ($sortedTransactions as $txn) {
            // Summary Totals
            $totalDebits = FinancialMath::add($totalDebits, $txn['debit']);
            $totalCredits = FinancialMath::add($totalCredits, $txn['credit']);

            // Balance = Previous + Debit (What they owe us) - Credit (What they paid/refunded)
            $runningBalance = FinancialMath::add($runningBalance, $txn['debit']);
            $runningBalance = FinancialMath::sub($runningBalance, $txn['credit']);

            $finalStatement[] = [
                'id' => $txn['id'],
                'date' => $txn['date'],
                'type' => $txn['type'],
                'reference' => $txn['reference'],
                'description' => $txn['description'],
                'debit' => FinancialMath::round($txn['debit'], 2),
                'credit' => FinancialMath::round($txn['credit'], 2),
                'balance' => FinancialMath::round($runningBalance, 2),
                'balance_raw' => $runningBalance,
            ];
        }

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'code' => $customer->customer_code,
                'exposure' => $customer->exposure,
            ],
            'statement' => $finalStatement,
            'summary' => [
                'total_debits' => FinancialMath::round($totalDebits, 2),
                'total_credits' => FinancialMath::round($totalCredits, 2),
                'closing_balance' => FinancialMath::round($runningBalance, 2),
            ],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ]
        ];
    }
}
