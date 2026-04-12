<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\CustomerStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerStatementController extends Controller
{
    /**
     * Get the customer statement logic
     */
    public function show(Request $request, int $customerId, CustomerStatementService $service): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        try {
            $statement = $service->generateStatement(
                $customerId,
                $request->date_from,
                $request->date_to
            );

            return response()->json($statement);
            
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate statement: ' . $e->getMessage()], 500);
        }
    }
}
