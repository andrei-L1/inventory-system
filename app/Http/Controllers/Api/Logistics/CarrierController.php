<?php

namespace App\Http\Controllers\Api\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Resources\Logistics\CarrierResource;
use App\Models\Carrier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CarrierController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Carrier::query();

        if ($request->has('active')) {
            $query->where('is_active', true);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $carriers = $query->orderBy('name')->paginate($request->get('limit', 100));

        return CarrierResource::collection($carriers);
    }

    public function show(Carrier $carrier): CarrierResource
    {
        return new CarrierResource($carrier);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:100|unique:carriers,name',
            'contact_person'        => 'nullable|string|max:100',
            'phone'                 => 'nullable|string|max:30',
            'tracking_url_template' => 'nullable|string|max:255',
            'is_active'             => 'boolean',
        ]);

        $carrier = Carrier::create($data);

        return (new CarrierResource($carrier))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Carrier $carrier): CarrierResource
    {
        $data = $request->validate([
            'name'                  => 'sometimes|required|string|max:100|unique:carriers,name,' . $carrier->id,
            'contact_person'        => 'nullable|string|max:100',
            'phone'                 => 'nullable|string|max:30',
            'tracking_url_template' => 'nullable|string|max:255',
            'is_active'             => 'boolean',
        ]);

        $carrier->update($data);

        return new CarrierResource($carrier->fresh());
    }

    public function destroy(Carrier $carrier): JsonResponse
    {
        // Prevent deletion if carrier has associated shipments
        if ($carrier->shipments()->exists()) {
            return response()->json([
                'message' => 'Cannot delete carrier with existing shipments. Deactivate it instead.',
            ], 422);
        }

        $carrier->delete();

        return response()->json(null, 204);
    }
}
