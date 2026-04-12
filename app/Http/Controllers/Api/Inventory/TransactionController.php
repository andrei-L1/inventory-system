<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\UomConversionException;
use App\Helpers\FinancialMath;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\TransactionStoreRequest;
use App\Http\Requests\Inventory\TransferStoreRequest;
use App\Http\Resources\Inventory\TransactionResource;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionStatus;
use App\Models\Vendor;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;

class TransactionController extends Controller
{
    public function __construct(protected StockService $stockService) {}

    // -------------------------------------------------------------------------
    // POST /api/transactions
    // Create any stock movement: Receipt, Issue, or Adjustment.
    // Pass { header: {...}, lines: [...] }.
    // If header.transaction_status_id = draft, inventory is NOT touched.
    // If header.transaction_status_id = posted, inventory updates immediately.
    // -------------------------------------------------------------------------
    public function store(TransactionStoreRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $header = $validated['header'];

            // Map user input to reference_doc (physical paperwork)
            $header['reference_doc'] = $header['reference_number'] ?? null;

            // Auto-generate a guaranteed unique system reference_number
            $typeMap = [1 => 'RCV', 2 => 'ISS', 3 => 'TRF', 4 => 'ADJ'];
            $prefix = $typeMap[$header['transaction_type_id'] ?? 0] ?? 'TRX';
            $header['reference_number'] = $prefix.'-'.now()->format('YmdHis').'-'.mt_rand(100, 999);

            $validated['header'] = $header;

            // Normalize numeric inputs to strings for FinancialMath compliance
            $validated['lines'] = collect($validated['lines'])->map(function ($line) {
                return array_merge($line, [
                    'quantity' => (string) ($line['quantity'] ?? '0'),
                    'unit_cost' => FinancialMath::round((string) ($line['unit_cost'] ?? '0'), FinancialMath::LINE_SCALE),
                ]);
            })->toArray();

            $transaction = $this->stockService->recordMovement($validated);

            return response()->json(
                new TransactionResource($transaction->load(['type', 'status', 'fromLocation', 'toLocation', 'vendor', 'lines'])),
                201
            );
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (UomConversionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/transfers
    // Atomic two-leg transfer between locations.
    // Creates an outgoing + incoming transaction, linked by a Transfer record.
    // -------------------------------------------------------------------------
    public function storeTransfer(TransferStoreRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $header = $validated['header'];

            // Map user input to reference_doc (physical paperwork)
            $header['reference_doc'] = $header['reference_number'] ?? null;

            // Auto-generate a guaranteed unique system reference_number for the overall transfer
            $header['reference_number'] = 'TRF-'.now()->format('YmdHis').'-'.mt_rand(100, 999);

            $validated['header'] = $header;

            // Normalize numeric inputs to strings for FinancialMath compliance
            $validated['lines'] = collect($validated['lines'])->map(function ($line) {
                return array_merge($line, [
                    'quantity' => (string) ($line['quantity'] ?? '0'),
                    'unit_cost' => FinancialMath::round((string) ($line['unit_cost'] ?? '0'), FinancialMath::LINE_SCALE),
                ]);
            })->toArray();

            $result = $this->stockService->recordTransfer($validated);

            return response()->json([
                'transfer_id' => $result['transfer']->id,
                'outgoing_transaction' => new TransactionResource(
                    $result['outgoing_transaction']->load(['type', 'status', 'fromLocation', 'toLocation', 'lines'])
                ),
                'incoming_transaction' => new TransactionResource(
                    $result['incoming_transaction']->load(['type', 'status', 'fromLocation', 'toLocation', 'lines'])
                ),
            ], 201);
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (UomConversionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // PATCH /api/transactions/{transaction}/post
    // Transitions a DRAFT transaction → POSTED.
    // This is the moment inventory is actually updated.
    // -------------------------------------------------------------------------
    public function post(Transaction $transaction): JsonResponse
    {
        try {
            $posted = $this->stockService->postTransaction($transaction);

            return response()->json(
                new TransactionResource($posted->load(['type', 'status', 'fromLocation', 'toLocation', 'vendor', 'lines']))
            );
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (UomConversionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 409); // Conflict
        }
    }

    // -------------------------------------------------------------------------
    // PATCH /api/transactions/{transaction}/cancel
    // Voids a draft or posted transaction.
    // NOTE: Reversal logic (un-doing inventory) is a planned future feature.
    //       For now, only drafts can be cancelled without side effects.
    // -------------------------------------------------------------------------
    public function cancel(Transaction $transaction): JsonResponse
    {
        $transaction->loadMissing('status');

        if ($transaction->status->name === 'cancelled') {
            return response()->json(['message' => 'Transaction is already cancelled.'], 409);
        }

        if ($transaction->status->name === 'posted') {
            try {
                $reversal = $this->stockService->reverseTransaction($transaction);

                return response()->json([
                    'message' => 'Transaction was posted, a reversal entry has been created to void stock.',
                    'reversal_id' => $reversal->id,
                    'reversal' => new TransactionResource($reversal->load(['lines', 'status'])),
                ]);
            } catch (InsufficientStockException $e) {
                return response()->json(['message' => 'Reversal failed: '.$e->getMessage()], 422);
            }
        }

        $cancelledStatus = TransactionStatus::where('name', 'cancelled')->firstOrFail();
        $transaction->transaction_status_id = $cancelledStatus->id;
        $transaction->cancelled_at = Carbon::now();
        $transaction->save();

        return response()->json(['message' => 'Transaction cancelled successfully.', 'id' => $transaction->id]);
    }

    // -------------------------------------------------------------------------
    // GET /api/products/{product}/transactions
    // Transaction history for a specific product (Inventory Center ledger).
    // Supports: ?date_from=YYYY-MM-DD &date_to=YYYY-MM-DD &type=receipt|issue|transfer|adjustment &page=1 &per_page=25
    // -------------------------------------------------------------------------
    public function forProduct(Product $product, Request $request): AnonymousResourceCollection
    {
        $query = Transaction::whereHas('lines', function ($q) use ($product) {
            $q->where('product_id', $product->id);
        })
            ->with([
                'type', 'status', 'fromLocation', 'toLocation', 'vendor', 'customer',
                'purchaseOrder', 'salesOrder',
                'lines' => function ($q) use ($product) {
                    $q->where('product_id', $product->id)->with(['product.uom', 'uom']);
                },
            ])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');

        // --- Date range filter ---
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        // --- Transaction type filter ---
        if ($request->filled('type') && $request->type !== 'all') {
            $query->whereHas('type', function ($q) use ($request) {
                $q->where('name', strtolower($request->type));
            });
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return TransactionResource::collection($query->paginate($perPage));
    }

    // -------------------------------------------------------------------------
    // GET /api/transactions/{transaction}
    // Single transaction detail view.
    // -------------------------------------------------------------------------
    public function show(Transaction $transaction): TransactionResource
    {
        return new TransactionResource(
            $transaction->load([
                'type', 'status', 'fromLocation', 'toLocation', 'vendor', 'customer',
                'purchaseOrder', 'salesOrder', 'lines.product.uom',
            ])
        );
    }

    // -------------------------------------------------------------------------
    // GET /api/vendors/{vendor}/transactions
    // Transaction history for a specific vendor.
    // -------------------------------------------------------------------------
    public function forVendor(Vendor $vendor): AnonymousResourceCollection
    {
        $transactions = Transaction::where('vendor_id', $vendor->id)
            ->with(['type', 'status', 'fromLocation', 'toLocation', 'purchaseOrder', 'lines.product.uom', 'lines.uom'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return TransactionResource::collection($transactions);
    }

    /**
     * Generate a printable voucher for the Stock Movement.
     */
    public function print(Transaction $transaction)
    {
        $transaction->load([
            'type',
            'status',
            'fromLocation',
            'toLocation',
            'vendor',
            'customer',
            'purchaseOrder',
            'salesOrder',
            'lines.product.uom',
            'lines.uom',
            'createdBy',
        ]);

        return view('inventory.transaction-print', [
            'trx' => $transaction,
            'company' => [
                'name' => 'Nexus Logistics',
                'address' => '123 Logistics Way, Suite 100, Tech City, TC 54321',
                'phone' => '+1 (555) 123-4567',
                'email' => 'logistics@nexus.com',
                'website' => 'www.nexus.com',
            ],
        ]);
    }
}
