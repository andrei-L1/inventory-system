<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $request->input('query');
        $customers = Customer::query()
            ->when($query, function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('customer_code', 'like', "%{$query}%");
            })
            ->get();

        return CustomerResource::collection($customers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_code' => 'required|string|unique:customers,customer_code',
            'name' => 'required|string',
            'email' => 'nullable|email|unique:customers,email',
            'phone' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'tax_number' => 'nullable|string',
            'credit_limit' => 'nullable|numeric',
            'price_list_id' => 'nullable|exists:price_lists,id',
            'is_active' => 'boolean',
        ]);

        return new CustomerResource(Customer::create($validated));
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        return new CustomerResource($customer);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'customer_code' => 'required|string|unique:customers,customer_code,'.$customer->id,
            'name' => 'required|string',
            'email' => 'nullable|email|unique:customers,email,'.$customer->id,
            'phone' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'tax_number' => 'nullable|string',
            'credit_limit' => 'nullable|numeric',
            'price_list_id' => 'nullable|exists:price_lists,id',
            'is_active' => 'boolean',
        ]);
        $customer->update($validated);

        return new CustomerResource($customer);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        // Check for existing sales orders before deletion
        if ($customer->salesOrders()->exists()) {
            return response()->json(['message' => 'Cannot delete customer with active sales orders.'], 422);
        }

        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }

    /**
     * Display sales history for the customer.
     */
    public function transactions(Customer $customer)
    {
        // Return sales orders for history tab
        $orders = $customer->salesOrders()
            ->with(['status'])
            ->latest()
            ->get();

        return response()->json(['data' => $orders]);
    }
}
