<?php

namespace App\Services\Finance;

use App\Helpers\FinancialMath;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentRefund;
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
                'type' => $isCreditNote ? 'CREDIT_NOTE' : 'INVOICE',
                'reference' => $invoice->invoice_number,
                'description' => $invoice->notes ?? ($isCreditNote ? 'Credit applied' : 'Sales Invoice'),
                'debit' => $isCreditNote ? '0' : (string) $invoice->total_amount,
                'credit' => $isCreditNote ? (string) $invoice->total_amount : '0',
                'raw_date' => Carbon::parse($invoice->invoice_date),
            ]);
        }

        foreach ($payments as $payment) {
            $transactions->push([
                'id'          => $payment->id,
                'date'        => Carbon::parse($payment->payment_date)->toDateString(),
                'type'        => 'PAYMENT',
                'reference'   => $payment->payment_number,
                'description' => $payment->notes ?? 'Payment received ' . ($payment->payment_method ? "({$payment->payment_method})" : ''),
                'debit'       => '0',
                'credit'      => (string) $payment->amount,
                'raw_date'    => Carbon::parse($payment->payment_date),
            ]);
        }

        // Fetch Refunds (Debits — money going back to customer)
        $refundsQuery = PaymentRefund::where('customer_id', $customerId);
        if ($dateFrom) $refundsQuery->whereDate('refund_date', '>=', $dateFrom);
        if ($dateTo) $refundsQuery->whereDate('refund_date', '<=', $dateTo);
        $refunds = $refundsQuery->get();

        foreach ($refunds as $refund) {
            $transactions->push([
                'id'          => $refund->payment_id,
                'date'        => Carbon::parse($refund->refund_date)->toDateString(),
                'type'        => 'REFUND',
                'reference'   => $refund->refund_number,
                'description' => $refund->notes ?? 'Cash refund issued' . ($refund->refund_method ? " ({$refund->refund_method})" : ''),
                'debit'       => (string) $refund->amount,
                'credit'      => '0',
                'raw_date'    => Carbon::parse($refund->refund_date),
            ]);
        }

        // 1. Calculate historical "Opening Balance" (transactions before dateFrom)
        $startingBalance = '0';
        if ($dateFrom) {
            $preInvoices = Invoice::where('customer_id', $customerId)
                ->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PAID])
                ->whereDate('invoice_date', '<', $dateFrom)
                ->get();
            
            foreach ($preInvoices as $inv) {
                if ($inv->type === Invoice::TYPE_CREDIT_NOTE) {
                    $startingBalance = FinancialMath::sub($startingBalance, (string) $inv->total_amount);
                } else {
                    $startingBalance = FinancialMath::add($startingBalance, (string) $inv->total_amount);
                }
            }

            $prePayments = Payment::where('customer_id', $customerId)
                ->whereDate('payment_date', '<', $dateFrom)
                ->get();
            foreach ($prePayments as $pay) {
                $startingBalance = FinancialMath::sub($startingBalance, (string) $pay->amount);
            }

            $preRefunds = PaymentRefund::where('customer_id', $customerId)
                ->whereDate('refund_date', '<', $dateFrom)
                ->get();
            foreach ($preRefunds as $ref) {
                $startingBalance = FinancialMath::add($startingBalance, (string) $ref->amount);
            }
        }

        // Sort chronologically
        // Priority logic: INVOICE appears before PAYMENT/REFUND on same day to reflect logical accrual flow.
        $sortedTransactions = $transactions->sortBy(function ($item) {
            $priority = match($item['type']) {
                'INVOICE'     => '1',
                'REFUND'      => '2',
                'PAYMENT'     => '3',
                'CREDIT_NOTE' => '4',
                default       => '9'
            };
            return $item['raw_date']->timestamp . '_' . $priority;
        })->values();

        $runningBalance = $startingBalance;
        $finalStatement = [];

        // 2. Inject Virtual Opening Balance Row
        if ($dateFrom) {
            $finalStatement[] = [
                'id'          => 0,
                'date'        => $dateFrom,
                'type'        => 'OPENING_BALANCE',
                'reference'   => 'BAL-FWD',
                'description' => 'Balance brought forward from previous period',
                'debit'       => FinancialMath::gt($startingBalance, '0') ? $startingBalance : '0',
                'credit'      => FinancialMath::lt($startingBalance, '0') ? FinancialMath::sub('0', $startingBalance) : '0',
                'running_balance' => FinancialMath::round($startingBalance, 2),
                'balance_raw' => $startingBalance,
                'link'        => null,
            ];
        }

        $totalDebits = FinancialMath::gt($startingBalance, '0') ? $startingBalance : '0';
        $totalCredits = FinancialMath::lt($startingBalance, '0') ? FinancialMath::sub('0', $startingBalance) : '0';

        foreach ($sortedTransactions as $txn) {
            // Summary Totals
            $totalDebits = FinancialMath::add($totalDebits, $txn['debit']);
            $totalCredits = FinancialMath::add($totalCredits, $txn['credit']);

            // Balance = Previous + Debit (What they owe us) - Credit (What they paid/refunded)
            $runningBalance = FinancialMath::add($runningBalance, $txn['debit']);
            $runningBalance = FinancialMath::sub($runningBalance, $txn['credit']);

            $finalStatement[] = [
                'id'          => $txn['id'],
                'date'        => $txn['date'],
                'type'        => $txn['type'],
                'reference'   => $txn['reference'],
                'description' => $txn['description'],
                'debit'       => FinancialMath::round($txn['debit'], 2),
                'credit'      => FinancialMath::round($txn['credit'], 2),
                'amount'      => FinancialMath::gt($txn['debit'], '0') ? $txn['debit'] : $txn['credit'],
                'running_balance' => FinancialMath::round($runningBalance, 2),
                'balance_raw' => $runningBalance,
                'link'        => $this->getLink($txn['type'], $txn['id']),
            ];
        }

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'code' => $customer->customer_code,
                'exposure' => $customer->exposure,
            ],
            'lines' => $finalStatement,
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

    private function getLink(string $type, int $id): ?string
    {
        return match ($type) {
            'INVOICE', 'CREDIT_NOTE' => "/finance/invoices/{$id}",
            'PAYMENT'                => "/finance/payments/{$id}",
            'REFUND'                 => "/finance/payments/{$id}", 
            default                  => null,
        };
    }
}
