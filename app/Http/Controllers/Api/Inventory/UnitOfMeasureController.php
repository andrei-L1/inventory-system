<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\UnitOfMeasureResource;
use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;

class UnitOfMeasureController extends Controller
{
    public function index()
    {
        return UnitOfMeasureResource::collection(UnitOfMeasure::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:units_of_measure,name',
            'abbreviation' => 'required|string|max:10|unique:units_of_measure,abbreviation',
        ]);

        return new UnitOfMeasureResource(UnitOfMeasure::create($validated));
    }

    public function show(UnitOfMeasure $uom)
    {
        return new UnitOfMeasureResource($uom);
    }

    public function update(Request $request, UnitOfMeasure $uom)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:units_of_measure,name,'.$uom->id,
            'abbreviation' => 'required|string|max:10|unique:units_of_measure,abbreviation,'.$uom->id,
        ]);
        $uom->update($validated);

        return new UnitOfMeasureResource($uom);
    }

    public function destroy(UnitOfMeasure $uom)
    {
        $uom->delete();

        return response()->json(['message' => 'UOM deleted']);
    }
}
