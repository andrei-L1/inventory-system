<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
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
    public function store(\App\Http\Requests\Inventory\TransactionStoreRequest $request, \App\Services\Inventory\StockService $stockService)
    {
        try {
            $data = $request->validated();
            
            // Force transaction type to 'adjustment'
            $adjType = \App\Models\TransactionType::where('code', 'ADJS')->firstOrFail();
            $data['header']['transaction_type_id'] = $adjType->id;

            $transaction = $stockService->recordMovement($data);

            return response()->json(
                new \App\Http\Resources\Inventory\TransactionResource(
                    $transaction->load(['type', 'status', 'fromLocation', 'toLocation', 'lines', 'adjustmentReason'])
                ),
                201
            );
        } catch (\App\Exceptions\InsufficientStockException $e) {
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
