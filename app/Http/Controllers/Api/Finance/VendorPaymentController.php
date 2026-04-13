<?php

namespace App\Http\Controllers\Api\Finance;

use App\Helpers\FinancialMath;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\VendorPayment;
use App\Models\VendorPaymentAllocation;
use App\Models\VendorPaymentRefund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = VendorPayment::with(['vendor', 'allocations.bill', 'refunds'])->latest();

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        return response()->json($query->paginate($request->get('limit', 15)));
    }

    public function show(VendorPayment $payment): JsonResponse
    {
        $payment->load(['vendor', 'allocations.bill', 'refunds']);
        return response()->json([
            'data' => $payment
        ]);
    }

    /**
     * VOID a disbursement.
     */
    public function void(VendorPayment $payment): JsonResponse
    {
        if ($payment->status === VendorPayment::STATUS_VOID) {
            return response()->json(['message' => 'Disbursement already voided.'], 200);
        }

        $payment->void();

        return response()->json([
            'message' => 'Disbursement voided successfully. Funds released back to bill balances.',
            'payment' => $payment
        ]);
    }

    /**
     * Record a vendor payment with optional allocations.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'amount' => 'required|numeric|min:0.0001',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'allocations' => 'nullable|array',
            'allocations.*.bill_id' => 'required|exists:bills,id',
            'allocations.*.amount' => 'required|numeric|min:0.0001',
        ]);

        try {
            $payment = DB::transaction(function () use ($request) {
                // Parity: Disbursement Numbering
                $dsbNumber = 'DSB-' . now()->format('Ym') . '-' . str_pad(VendorPayment::max('id') + 1, 4, '0', STR_PAD_LEFT);

                $payment = VendorPayment::create([
                    'payment_number' => $dsbNumber,
                    'vendor_id' => $request->vendor_id,
                    'amount' => $request->amount,
                    'payment_date' => $request->payment_date,
                    'reference_number' => $request->reference_number,
                    'payment_method' => $request->payment_method,
                    'notes' => $request->notes,
                ]);

                if ($request->has('allocations') && !empty($request->allocations)) {
                    // We call the internal allocation logic to reuse guards
                    $this->performAllocations($payment, $request->allocations);
                }

                return $payment;
            });

            return response()->json([
                'message' => 'Vendor Payment recorded successfully.',
                'payment' => $payment->load('allocations.bill'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Set-based allocation of unallocated funds.
     */
    public function allocate(Request $request, VendorPayment $payment): JsonResponse
    {
        $request->validate([
            'allocations' => 'required|array|min:1',
            'allocations.*.bill_id' => 'required|exists:bills,id',
            'allocations.*.amount' => 'required|numeric|min:0.0001',
        ]);

        try {
            DB::transaction(function () use ($request, $payment) {
                $this->performAllocations($payment, $request->allocations);
            });

            return response()->json([
                'message' => 'Disbursement allocated successfully.',
                'payment' => $payment->fresh(['allocations.bill']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove a payment allocation (Undo).
     */
    public function unallocate(VendorPayment $payment, VendorPaymentAllocation $allocation): JsonResponse
    {
        if ($allocation->vendor_payment_id !== $payment->id) {
            return response()->json(['message' => 'Allocation does not belong to this payment.'], 422);
        }

        try {
            DB::transaction(function () use ($allocation) {
                $bill = $allocation->bill;

                // Restore Bill Balance
                $newPaidAmount = FinancialMath::sub((string) $bill->paid_amount, (string) $allocation->amount);
                $bill->paid_amount = FinancialMath::max('0', FinancialMath::round($newPaidAmount, 2));

                // Re-evaluate status
                if (FinancialMath::lt((string) $bill->paid_amount, (string) $bill->total_amount, FinancialMath::HEADER_SCALE)) {
                    $bill->status = Bill::STATUS_POSTED; // Change back from PAID if now under-paid
                }

                $bill->save();
                $allocation->delete();
            });

            return response()->json([
                'message' => 'Allocation removed successfully.',
                'payment' => $payment->fresh(['allocations.bill', 'refunds']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Issue a cash refund from unallocated disbursement credit.
     */
    public function refund(Request $request, VendorPayment $payment): JsonResponse
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
                'message' => "Cannot refund {$amountToRefund}. Only {$unallocated} unallocated credit remains on this disbursement.",
            ], 422);
        }

        try {
            $refund = DB::transaction(function () use ($payment, $request, $amountToRefund) {
                return VendorPaymentRefund::create([
                    'vendor_payment_id' => $payment->id,
                    'vendor_id' => $payment->vendor_id,
                    'amount' => $amountToRefund,
                    'refund_number' => 'VRF-' . now()->format('Ym') . '-' . str_pad(VendorPaymentRefund::max('id') + 1, 4, '0', STR_PAD_LEFT),
                    'refund_date' => $request->input('refund_date'),
                    'refund_method' => $request->input('refund_method'),
                    'reference_number' => $request->input('reference_number'),
                    'notes' => $request->input('notes'),
                ]);
            });

            return response()->json([
                'message' => 'Refund issued successfully.',
                'refund' => $refund,
                'payment' => $payment->fresh(['allocations.bill', 'refunds']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Internal logic shared between store and allocate to maintain strict audit guards.
     */
    private function performAllocations(VendorPayment $payment, array $allocations): void
    {
        $payment->load(['allocations']);
        $remainingBudget = $payment->unallocated_amount;

        foreach ($allocations as $item) {
            $bill = Bill::lockForUpdate()->findOrFail($item['bill_id']);
            $amountToAllocate = (string) $item['amount'];

            // Guard: Enough credit?
            if (FinancialMath::gt($amountToAllocate, $remainingBudget)) {
                abort(422, "Insufficient Credit: Trying to allocate {$amountToAllocate} but only {$remainingBudget} remains unallocated.");
            }

            // Guard: Valid bill status?
            if ($bill->status === Bill::STATUS_PAID || $bill->status === Bill::STATUS_VOID) {
                abort(422, "Logic Error: Bill #{$bill->bill_number} is already {$bill->status}.");
            }

            // Guard: No overpayment
            if (FinancialMath::gt($amountToAllocate, (string) $bill->balance_due)) {
                abort(422, "Overpayment: Allocating {$amountToAllocate} to Bill #{$bill->bill_number} but current balance is {$bill->balance_due}.");
            }

            $payment->allocations()->create([
                'bill_id' => $bill->id,
                'amount' => $amountToAllocate,
            ]);

            $remainingBudget = FinancialMath::sub($remainingBudget, $amountToAllocate);

            // Update Bill Progress
            $bill->paid_amount = FinancialMath::add((string) $bill->paid_amount, $amountToAllocate);
            
            if (FinancialMath::equals((string) $bill->paid_amount, (string) $bill->total_amount, FinancialMath::HEADER_SCALE)) {
                $bill->status = Bill::STATUS_PAID;
            }

            $bill->save();
        }
    }
}
