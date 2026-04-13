<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\Bill;
use App\Models\VendorPayment;
use App\Models\DebitNote;
use App\Helpers\FinancialMath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorStatementController extends Controller
{
    /**
     * Generate a unified running-balance ledger for a vendor.
     * 
     * Formula:
     * - Bill (STATUS_POSTED, PAID) = Debit (Inc balance)
     * - Payment = Credit (Dec balance)
     * - Debit Note = Credit (Dec balance)
     */
    public function show(Vendor $vendor)
    {
        $bills = Bill::where('vendor_id', $vendor->id)
            ->whereIn('status', ['POSTED', 'PAID'])
            ->get();

        $payments = VendorPayment::where('vendor_id', $vendor->id)
            ->get();

        $debitNotes = DebitNote::where('vendor_id', $vendor->id)
            ->whereIn('status', ['POSTED', 'APPLIED'])
            ->get();

        $lines = collect();

        // Add Bills (Debits - Vendor Charges Us)
        foreach ($bills as $bill) {
            $lines->push([
                'date' => $bill->bill_date,
                'type' => 'BILL',
                'id' => $bill->id,
                'reference' => $bill->bill_number,
                'description' => 'Vendor Bill: ' . $bill->bill_number,
                'debit' => $bill->total_amount,
                'credit' => '0',
                'timestamp' => $bill->created_at,
            ]);
        }

        // Add Payments (Credits - We Pay Vendor)
        foreach ($payments as $payment) {
            $lines->push([
                'date' => $payment->payment_date,
                'type' => 'PAYMENT',
                'id' => $payment->id,
                'reference' => $payment->reference_number,
                'description' => 'Vendor Payment: ' . $payment->reference_number,
                'debit' => '0',
                'credit' => $payment->amount,
                'timestamp' => $payment->created_at,
            ]);
        }

        // Add Debit Notes (Credits - Returns/Adjustments)
        foreach ($debitNotes as $dn) {
            $lines->push([
                'date' => $dn->created_at->format('Y-m-d'),
                'type' => 'DEBIT_NOTE',
                'id' => $dn->id,
                'reference' => $dn->debit_note_number,
                'description' => 'Debit Note (Return Credit): ' . $dn->debit_note_number,
                'debit' => '0',
                'credit' => $dn->amount,
                'timestamp' => $dn->created_at,
            ]);
        }

        // Sort by date then timestamp
        $lines = $lines->sortBy([
            ['date', 'asc'],
            ['timestamp', 'asc']
        ])->values();

        $runningBalance = '0';
        $totalDebits = '0';
        $totalCredits = '0';

        $processedLines = $lines->map(function ($line) use (&$runningBalance, &$totalDebits, &$totalCredits) {
            $totalDebits = FinancialMath::add($totalDebits, $line['debit']);
            $totalCredits = FinancialMath::add($totalCredits, $line['credit']);
            
            // For Vendors: Bill increases what we owe (Debit), Payment decreases it (Credit)
            $runningBalance = FinancialMath::add($runningBalance, $line['debit']);
            $runningBalance = FinancialMath::sub($runningBalance, $line['credit']);

            $link = match ($line['type']) {
                'BILL' => "/finance/bills/{$line['id']}",
                'PAYMENT' => "/finance/vendor-payments/{$line['id']}",
                default => null
            };

            return array_merge($line, [
                'balance' => $runningBalance,
                'balance_raw' => (float)$runningBalance,
                'link' => $link
            ]);
        });

        return response()->json([
            'vendor' => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'vendor_code' => $vendor->vendor_code,
            ],
            'summary' => [
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'closing_balance' => $runningBalance,
            ],
            'lines' => $processedLines
        ]);
    }
}
