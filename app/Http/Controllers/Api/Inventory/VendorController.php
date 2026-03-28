<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\VendorResource;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index()
    {
        return VendorResource::collection(Vendor::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_code' => 'required|string|unique:vendors,vendor_code',
            'name' => 'required|string',
            'email' => 'nullable|email|unique:vendors,email',
            'phone' => 'nullable|string',
            'contact_person' => 'nullable|string',
        ]);
        return new VendorResource(Vendor::create($validated));
    }

    public function show(Vendor $vendor)
    {
        return new VendorResource($vendor);
    }

    public function update(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'vendor_code' => 'required|string|unique:vendors,vendor_code,' . $vendor->id,
            'name' => 'required|string',
            'email' => 'nullable|email|unique:vendors,email,' . $vendor->id,
            'phone' => 'nullable|string',
            'contact_person' => 'nullable|string',
        ]);
        $vendor->update($validated);
        return new VendorResource($vendor);
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();
        return response()->json(['message' => 'Vendor deleted']);
    }
}
