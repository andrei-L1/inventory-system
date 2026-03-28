<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\LocationStoreRequest;
use App\Http\Requests\Inventory\LocationUpdateRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class LocationController extends Controller
{
    /**
     * Display a listing of the locations.
     */
    public function index(): AnonymousResourceCollection
    {
        $locations = Location::with(['locationType', 'parent'])
            ->when(request('query'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->when(request('location_type_id'), function ($query, $typeId) {
                $query->where('location_type_id', $typeId);
            })
            ->latest()
            ->paginate(request('per_page', 50));

        return LocationResource::collection($locations);
    }

    /**
     * Store a newly created location.
     */
    public function store(LocationStoreRequest $request): LocationResource
    {
        $location = Location::create($request->validated());

        $location->load(['locationType', 'parent']);

        return new LocationResource($location);
    }

    /**
     * Display the specified location.
     */
    public function show(Location $location): LocationResource
    {
        $location->load(['locationType', 'parent']);

        return new LocationResource($location);
    }

    /**
     * Update the specified location.
     */
    public function update(LocationUpdateRequest $request, Location $location): LocationResource
    {
        $location->update($request->validated());

        $location->load(['locationType', 'parent']);

        return new LocationResource($location);
    }

    /**
     * Remove the specified location.
     */
    public function destroy(Location $location): Response
    {
        $location->delete();

        return response()->noContent();
    }
}
