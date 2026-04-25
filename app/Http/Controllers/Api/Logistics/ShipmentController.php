<?php

namespace App\Http\Controllers\Api\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Resources\Logistics\ShipmentResource;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShipmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Shipment::with('carrier');

        if ($request->has('sales_order_id')) {
            $query->where('sales_order_id', $request->sales_order_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $shipments = $query->latest('id')->paginate($request->get('limit', 25));

        return ShipmentResource::collection($shipments);
    }

    public function show(Shipment $shipment): ShipmentResource
    {
        $shipment->load('carrier');

        return new ShipmentResource($shipment);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'carrier_id' => 'required|exists:carriers,id',
            'tracking_number' => 'nullable|string|max:100',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'shipped_at' => 'nullable|date',
        ]);

        $shipment = Shipment::create([
            'shipment_number' => 'SHP-'.strtoupper(substr(uniqid(), -8)),
            'sales_order_id' => $data['sales_order_id'],
            'carrier_id' => $data['carrier_id'],
            'tracking_number' => $data['tracking_number'] ?? null,
            'status' => 'shipped',
            'shipping_cost' => $data['shipping_cost'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'shipped_at' => $data['shipped_at'] ?? now(),
        ]);

        $shipment->load('carrier');

        return (new ShipmentResource($shipment))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Shipment $shipment): ShipmentResource
    {
        $data = $request->validate([
            'carrier_id' => 'sometimes|exists:carriers,id',
            'tracking_number' => 'nullable|string|max:100',
            'status' => 'sometimes|in:pending,shipped,in_transit,delivered,failed',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'shipped_at' => 'nullable|date',
            'delivered_at' => 'nullable|date',
        ]);

        $shipment->update($data);
        $shipment->load('carrier');

        return new ShipmentResource($shipment->fresh(['carrier']));
    }

    public function destroy(Shipment $shipment): JsonResponse
    {
        if (! in_array($shipment->status, ['pending', 'failed'])) {
            return response()->json([
                'message' => 'Only pending or failed shipments can be deleted.',
            ], 422);
        }

        $shipment->delete();

        return response()->json(null, 204);
    }
}
