<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\UomConversion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UomConversionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = UomConversion::with(['fromUom', 'toUom']);

        if ($request->has('uom_id')) {
            $query->where('from_uom_id', $request->uom_id)
                ->orWhere('to_uom_id', $request->uom_id);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_uom_id' => 'required|exists:units_of_measure,id',
            'to_uom_id' => 'required|exists:units_of_measure,id|different:from_uom_id',
            'conversion_factor' => 'required|numeric|min:0.000001',
        ]);

        $conversion = UomConversion::create($validated);

        return response()->json(['data' => $conversion->load(['fromUom', 'toUom'])], 201);
    }

    public function show(UomConversion $uomConversion): JsonResponse
    {
        return response()->json(['data' => $uomConversion->load(['fromUom', 'toUom'])]);
    }

    public function update(Request $request, UomConversion $uomConversion): JsonResponse
    {
        $validated = $request->validate([
            'from_uom_id' => 'sometimes|exists:units_of_measure,id',
            'to_uom_id' => 'sometimes|exists:units_of_measure,id|different:from_uom_id',
            'conversion_factor' => 'sometimes|numeric|min:0.000001',
        ]);

        $uomConversion->update($validated);

        return response()->json(['data' => $uomConversion->load(['fromUom', 'toUom'])]);
    }

    public function destroy(UomConversion $uomConversion): JsonResponse
    {
        $uomConversion->delete();

        return response()->json(null, 204);
    }
}
