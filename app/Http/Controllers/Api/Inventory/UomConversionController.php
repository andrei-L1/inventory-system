<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\UomConversion;
use App\Models\UnitOfMeasure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UomConversionController extends Controller
{
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $query = UomConversion::with(['fromUom', 'toUom', 'product']);

        if ($request->has('uom_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('from_uom_id', $request->uom_id)
                    ->orWhere('to_uom_id', $request->uom_id);
            });
        }
        
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        return \App\Http\Resources\Inventory\UomConversionResource::collection($query->get());
    }

    public function store(Request $request): \App\Http\Resources\Inventory\UomConversionResource
    {
        $validated = $request->validate([
            'from_uom_id' => 'required|exists:units_of_measure,id',
            'to_uom_id' => 'required|exists:units_of_measure,id|different:from_uom_id',
            'conversion_factor' => 'required|numeric|min:0.000001',
            'product_id' => 'nullable|exists:products,id',
        ]);
        
        $this->enforceStarSchema($validated['to_uom_id']);

        $conversion = UomConversion::create($validated);

        return new \App\Http\Resources\Inventory\UomConversionResource($conversion->load(['fromUom', 'toUom']));
    }

    public function show(UomConversion $uomConversion): \App\Http\Resources\Inventory\UomConversionResource
    {
        return new \App\Http\Resources\Inventory\UomConversionResource($uomConversion->load(['fromUom', 'toUom']));
    }

    public function update(Request $request, UomConversion $uomConversion): \App\Http\Resources\Inventory\UomConversionResource
    {
        $validated = $request->validate([
            'from_uom_id' => 'sometimes|exists:units_of_measure,id',
            'to_uom_id' => 'sometimes|exists:units_of_measure,id|different:from_uom_id',
            'conversion_factor' => 'sometimes|numeric|min:0.000001',
            'product_id' => 'nullable|exists:products,id',
        ]);

        $toUomId = $validated['to_uom_id'] ?? $uomConversion->to_uom_id;
        $this->enforceStarSchema($toUomId);

        $uomConversion->update($validated);

        return new \App\Http\Resources\Inventory\UomConversionResource($uomConversion->load(['fromUom', 'toUom']));
    }

    public function destroy(UomConversion $uomConversion): JsonResponse
    {
        // TODO: Enforce lock if transaction history exists for this conversion
        $uomConversion->delete();

        return response()->json(null, 204);
    }
    
    private function enforceStarSchema($toUomId): void
    {
        $toUom = UnitOfMeasure::find($toUomId);
        if (!$toUom || !$toUom->is_base) {
            throw ValidationException::withMessages([
                'to_uom_id' => 'Conversions must translate directly back to an Atomic Base Unit to prevent recursive loops (Star Schema enforcement).'
            ]);
        }
    }
}
