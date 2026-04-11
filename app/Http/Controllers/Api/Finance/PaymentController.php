<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
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
        return response()->json($payment->load(['customer', 'allocations.invoice']));
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
                foreach ($request->allocations as $item) {
                    $invoice = Invoice::lockForUpdate()->findOrFail($item['invoice_id']);
                    $amountToAllocate = (float) $item['amount'];

                    if ($amountToAllocate > ($payment->unallocated_amount + 0.00000001)) {
                        abort(422, "Cannot allocate {$amountToAllocate} for invoice #{$invoice->invoice_number}. Only {$payment->unallocated_amount} unallocated remains on this payment.");
                    }

                    if ($amountToAllocate > ($invoice->balance + 0.00000001)) {
                        abort(422, "Cannot allocate {$amountToAllocate} for invoice #{$invoice->invoice_number}. Current balance is {$invoice->balance}.");
                    }

                    // Create allocation
                    PaymentAllocation::create([
                        'payment_id' => $payment->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $amountToAllocate,
                    ]);

                    // Update invoice paid amount and status
                    $invoice->paid_amount += $amountToAllocate;

                    if ($invoice->paid_amount >= ($invoice->total_amount - 0.000001)) {
                        $invoice->status = Invoice::STATUS_PAID;
                    }

                    $invoice->save();
                }
            });

            return response()->json([
                'message' => 'Payment allocated successfully.',
                'payment' => $payment->fresh('allocations.invoice'),
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

        $payment->delete();

        return response()->json(null, 204);
    }
}
