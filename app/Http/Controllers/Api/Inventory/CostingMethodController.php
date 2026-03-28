<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\CostingMethodResource;
use App\Models\CostingMethod;
use Illuminate\Http\Request;

class CostingMethodController extends Controller
{
    /**
     * Display a listing of active costing methods for dropdowns.
     */
    public function index()
    {
        return CostingMethodResource::collection(CostingMethod::where('is_active', true)->get());
    }

    /**
     * Display the specified costing method.
     */
    public function show(CostingMethod $costingMethod)
    {
        return new CostingMethodResource($costingMethod);
    }
}
