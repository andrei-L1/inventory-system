<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\UomConversionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\TransactionStoreRequest;
use App\Http\Resources\Inventory\TransactionResource;
use App\Models\TransactionType;
use App\Services\Inventory\StockService;
use Illuminate\Http\Request;

class AdjustmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TransactionStoreRequest $request, StockService $stockService)
    {
        try {
            $data = $request->validated();
            $header = $data['header'];

            // Map user input to reference_doc (physical paperwork)
            $header['reference_doc'] = $header['reference_number'] ?? null;

            // Auto-generate a guaranteed unique system reference_number
            $header['reference_number'] = 'ADJ-'.now()->format('YmdHis').'-'.mt_rand(100, 999);

            // Force transaction type to 'adjustment'
            $adjType = TransactionType::where('code', 'ADJS')->firstOrFail();
            $header['transaction_type_id'] = $adjType->id;

            $data['header'] = $header;

            // Normalize numeric inputs to strings for FinancialMath compliance
            $data['lines'] = collect($data['lines'])->map(function ($line) {
                return array_merge($line, [
                    'quantity' => (string) ($line['quantity'] ?? '0'),
                    'unit_cost' => \App\Helpers\FinancialMath::round((string) ($line['unit_cost'] ?? '0'), \App\Helpers\FinancialMath::LINE_SCALE),
                ]);
            })->toArray();

            $transaction = $stockService->recordMovement($data);

            return response()->json(
                new TransactionResource(
                    $transaction->load(['type', 'status', 'fromLocation', 'toLocation', 'lines', 'adjustmentReason'])
                ),
                201
            );
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (UomConversionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Adjustment failed.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
