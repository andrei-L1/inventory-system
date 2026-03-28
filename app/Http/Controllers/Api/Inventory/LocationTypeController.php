<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\LocationType;
use Illuminate\Http\JsonResponse;

class LocationTypeController extends Controller
{
    /**
     * Display a listing of the location types.
     */
    public function index(): JsonResponse
    {
        $types = LocationType::orderBy('name')->get();
        return response()->json(['data' => $types]);
    }
}
