<?php

namespace App\Http\Controllers\Api\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Resources\Logistics\ProductSerialResource;
use App\Models\ProductSerial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductSerialController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ProductSerial::with(['product', 'currentLocation']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('location_id')) {
            $query->where('current_location_id', $request->location_id);
        }

        if ($request->filled('serial_number')) {
            $query->where('serial_number', 'like', '%' . $request->serial_number . '%');
        }

        $serials = $query->latest('id')->paginate($request->get('limit', 50));

        return ProductSerialResource::collection($serials);
    }

    public function show(ProductSerial $serial): ProductSerialResource
    {
        $serial->load(['product.uom', 'currentLocation', 'transactionLines.transaction.type']);

        return new ProductSerialResource($serial);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'         => 'required|exists:products,id',
            'serial_number'      => 'required|string|max:100',
            'status'             => 'in:in_stock,sold,returned,damaged',
            'current_location_id'=> 'nullable|exists:locations,id',
        ]);

        // Enforce uniqueness per product
        $exists = ProductSerial::where('product_id', $data['product_id'])
            ->where('serial_number', $data['serial_number'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => "Serial number \"{$data['serial_number']}\" already exists for this product.",
            ], 422);
        }

        $serial = ProductSerial::create([
            'product_id'          => $data['product_id'],
            'serial_number'       => $data['serial_number'],
            'status'              => $data['status'] ?? ProductSerial::STATUS_IN_STOCK,
            'current_location_id' => $data['current_location_id'] ?? null,
        ]);

        $serial->load(['product', 'currentLocation']);

        return (new ProductSerialResource($serial))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, ProductSerial $serial): ProductSerialResource
    {
        $data = $request->validate([
            'status'              => 'sometimes|in:in_stock,sold,returned,damaged',
            'current_location_id' => 'nullable|exists:locations,id',
        ]);

        $serial->update($data);
        $serial->load(['product', 'currentLocation']);

        return new ProductSerialResource($serial->fresh(['product', 'currentLocation']));
    }

    public function destroy(ProductSerial $serial): JsonResponse
    {
        if ($serial->status !== ProductSerial::STATUS_IN_STOCK) {
            return response()->json([
                'message' => 'Only in-stock serials with no movement history can be deleted.',
            ], 422);
        }

        if ($serial->transactionLines()->exists()) {
            return response()->json([
                'message' => 'This serial number has transaction history and cannot be deleted.',
            ], 422);
        }

        $serial->delete();

        return response()->json(null, 204);
    }
}
