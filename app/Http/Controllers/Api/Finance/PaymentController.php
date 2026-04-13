<?php

namespace App\Http\Controllers\Api\Finance;

use App\Helpers\FinancialMath;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentRefund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['customer'])->latest();

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        return response()->json($query->paginate($request->get('limit', 15)));
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json($payment->load(['customer', 'allocations.invoice', 'refunds']));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.0001',
            'payment_method' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $payment = Payment::create([
            'payment_number' => 'PAY-'.now()->format('Ymd-Hi').'-'.rand(10, 99),
            'customer_id' => $request->customer_id,
            'payment_date' => $request->payment_date,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'reference_number' => $request->reference_number,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'payment' => $payment,
        ], 201);
    }

    /**
     * Allocate payment amount to one or more invoices.
     */
    public function allocate(Request $request, Payment $payment): JsonResponse
    {
        $request->validate([
            'allocations' => 'required|array',
            'allocations.*.invoice_id' => 'required|exists:invoices,id',
            'allocations.*.amount' => 'required|numeric|min:0.0001',
        ]);

        try {
            DB::transaction(function () use ($request, $payment) {
                // Load relationships and compute the remaining budget ONCE before the loop.
                // We track it manually so each iteration in the same batch sees the
                // running subtotal — not the stale cached value from before the transaction.
                $payment->load(['allocations', 'refunds']);
                $remainingBudget = $payment->unallocated_amount; // BCMath string

                foreach ($request->allocations as $item) {
                    $invoice = Invoice::lockForUpdate()->findOrFail($item['invoice_id']);
                    $amountToAllocate = (string) $item['amount'];

                    // Guard 1: enough unallocated credit still available in this batch?
                    if (FinancialMath::gt($amountToAllocate, $remainingBudget)) {
                        abort(422, "Cannot allocate {$amountToAllocate} for invoice #{$invoice->invoice_number}. Only {$remainingBudget} unallocated remains.");
                    }

                    // Guard: don't allocate to PAID or VOID invoices.
                    if ($invoice->status === Invoice::STATUS_PAID || $invoice->status === Invoice::STATUS_VOID) {
                        abort(422, "Payment Error: Cannot allocate payment to Invoice #{$invoice->invoice_number} because it is already {$invoice->status}.");
                    }

                    // Guard: don't over-pay the invoice.
                    if (FinancialMath::gt($amountToAllocate, (string) $invoice->balance_due)) {
                        abort(422, "Cannot allocate {$amountToAllocate} for invoice #{$invoice->invoice_number}. Current balance is {$invoice->balance_due}.");
                    }

                    PaymentAllocation::create([
                        'payment_id' => $payment->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $amountToAllocate,
                    ]);

                    // Deduct from our running budget so the next iteration is aware.
                    $remainingBudget = FinancialMath::sub($remainingBudget, $amountToAllocate);

                    $invoice->paid_amount = FinancialMath::add((string) $invoice->paid_amount, $amountToAllocate);

                    // Final status check must use HEADER_SCALE (2) to align with actual header totals
                    if (FinancialMath::equals((string) $invoice->paid_amount, (string) $invoice->total_amount, FinancialMath::HEADER_SCALE)) {
                        $invoice->status = Invoice::STATUS_PAID;
                    }

                    $invoice->save();
                }
            });

            return response()->json([
                'message' => 'Payment allocated successfully.',
                'payment' => $payment->fresh(['allocations.invoice', 'refunds']),
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Issue a cash refund against the payment's unallocated balance.
     */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.0001',
            'refund_date' => 'required|date',
            'refund_method' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $payment->load(['allocations', 'refunds']);
        $amountToRefund = (string) $request->input('amount');
        $unallocated = $payment->unallocated_amount;

        if (FinancialMath::gt($amountToRefund, $unallocated)) {
            return response()->json([
                'message' => "Cannot refund {$amountToRefund}. Only {$unallocated} unallocated credit remains on this payment.",
            ], 422);
        }

        $refund = PaymentRefund::create([
            'payment_id' => $payment->id,
            'customer_id' => $payment->customer_id,
            'amount' => $amountToRefund,
            'refund_number' => 'REF-'.now()->format('Ymd-Hi').'-'.rand(10, 99),
            'refund_date' => $request->input('refund_date'),
            'refund_method' => $request->input('refund_method'),
            'reference_number' => $request->input('reference_number'),
            'notes' => $request->input('notes'),
        ]);

        return response()->json([
            'message' => 'Refund issued successfully.',
            'refund' => $refund,
            'payment' => $payment->fresh(['allocations.invoice', 'refunds']),
        ], 201);
    }

    /**
     * Remove a payment allocation (Undo).
     */
    public function unallocate(Payment $payment, PaymentAllocation $allocation): JsonResponse
    {
        // 1. Audit check: Ensure this allocation belongs to this payment
        if ($allocation->payment_id !== $payment->id) {
            return response()->json(['message' => 'Allocation does not belong to this payment.'], 422);
        }

        try {
            DB::transaction(function () use ($allocation) {
                $invoice = $allocation->invoice;

                // 2. Decrement the invoice's recorded payment (Defensive floor at 0)
                $newPaidAmount = FinancialMath::sub((string) $invoice->paid_amount, (string) $allocation->amount);
                $invoice->paid_amount = FinancialMath::max('0', FinancialMath::round($newPaidAmount, 2));

                // 3. Re-evaluate status (Standard industry logic: If unpaid, it's OPEN)
                if (FinancialMath::lt((string) $invoice->paid_amount, (string) $invoice->total_amount, FinancialMath::HEADER_SCALE)) {
                    $invoice->status = Invoice::STATUS_OPEN;
                }

                $invoice->save();

                // 4. Delete the allocation record
                $allocation->delete();
            });

            return response()->json([
                'message' => 'Allocation removed successfully.',
                'payment' => $payment->fresh(['allocations.invoice', 'refunds']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Payment $payment): JsonResponse
    {
        if ($payment->allocations()->count() > 0) {
            return response()->json(['message' => 'Cannot delete a payment that has active allocations.'], 400);
        }

        if ($payment->refunds()->count() > 0) {
            return response()->json(['message' => 'Cannot delete a payment that has issued refunds.'], 400);
        }

        $payment->delete();

        return response()->json(null, 204);
    }

    public function void(Payment $payment): JsonResponse
    {
        if ($payment->status === Payment::STATUS_VOID) {
            return response()->json(['message' => 'Payment already voided.'], 200);
        }

        $payment->void();

        return response()->json([
            'message' => 'Payment voided successfully. Funds released back to invoice balances.',
            'payment' => $payment
        ]);
    }

    public function print(Payment $payment)
    {
        $payment->load(['customer', 'allocations.invoice']);

        $company = [
            'name' => config('app.company_name', 'Nexus Logistics Corp.'),
            'address' => config('app.company_address', '123 Corporate Ave, Matrix City'),
            'phone' => config('app.company_phone', '+1 (800) 000-0000'),
            'email' => config('app.company_email', 'accounting@nexuscorp.com'),
            'website' => config('app.company_website', 'www.nexuscorp.com'),
            'tax_id' => config('app.company_tax_id'),
        ];

        return view('finance.payment-print', compact('payment', 'company'));
    }
}
