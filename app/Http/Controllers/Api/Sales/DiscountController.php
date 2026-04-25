<?php

namespace App\Http\Controllers\Api\Sales;

use App\Helpers\FinancialMath;
use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscountController extends Controller
{
    // ─── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = Discount::with(['product:id,name,sku', 'category:id,name', 'customer:id,name'])
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->product_id))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->customer_id))
            ->latest('id');

        return response()->json($query->paginate($request->get('limit', 25))
            ->through(fn ($d) => $this->format($d)));
    }

    // ─── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'type'        => ['required', Rule::in([Discount::TYPE_PERCENTAGE, Discount::TYPE_FIXED])],
            'value'       => 'required|numeric|min:0',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'product_id'  => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
            'customer_id' => 'nullable|exists:customers,id',
            'is_active'   => 'boolean',
        ]);

        $validated['value'] = FinancialMath::round((string) $validated['value'], 4);

        $discount = Discount::create($validated);
        $discount->load(['product:id,name,sku', 'category:id,name', 'customer:id,name']);

        return response()->json(['data' => $this->format($discount)], 201);
    }

    // ─── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, Discount $discount): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'type'        => ['required', Rule::in([Discount::TYPE_PERCENTAGE, Discount::TYPE_FIXED])],
            'value'       => 'required|numeric|min:0',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'product_id'  => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
            'customer_id' => 'nullable|exists:customers,id',
            'is_active'   => 'boolean',
        ]);

        $validated['value'] = FinancialMath::round((string) $validated['value'], 4);

        $discount->update($validated);
        $discount->load(['product:id,name,sku', 'category:id,name', 'customer:id,name']);

        return response()->json(['data' => $this->format($discount->refresh())]);
    }

    // ─── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(Discount $discount): JsonResponse
    {
        $discount->delete();

        return response()->json(['message' => 'Discount deleted.']);
    }

    // ─── Resolve (used by SO Create to suggest discount for a line) ─────────────

    /**
     * Find the best active discount for a given product/category/customer combo.
     * Priority: customer-specific > product-specific > category-wide.
     */
    public function resolve(Request $request): JsonResponse
    {
        $request->validate([
            'product_id'  => 'required|exists:products,id',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $productId  = $request->product_id;
        $customerId = $request->customer_id;

        // Load product to get its category
        $product = \App\Models\Product::select('id', 'category_id')->findOrFail($productId);

        $discount = Discount::active()
            ->where(function ($q) use ($productId, $customerId, $product) {
                // Most specific: customer + product
                if ($customerId) {
                    $q->orWhere(function ($sq) use ($productId, $customerId) {
                        $sq->where('customer_id', $customerId)->where('product_id', $productId);
                    });
                    // Customer-level catch-all
                    $q->orWhere(function ($sq) use ($customerId) {
                        $sq->where('customer_id', $customerId)->whereNull('product_id')->whereNull('category_id');
                    });
                }
                // Product-specific (any customer)
                $q->orWhere(function ($sq) use ($productId) {
                    $sq->where('product_id', $productId)->whereNull('customer_id');
                });
                // Category-wide
                if ($product->category_id) {
                    $q->orWhere(function ($sq) use ($product) {
                        $sq->where('category_id', $product->category_id)->whereNull('product_id')->whereNull('customer_id');
                    });
                }
            })
            // Prefer customer-specific, then product-specific, then category
            ->orderByRaw('CASE WHEN customer_id IS NOT NULL THEN 0 WHEN product_id IS NOT NULL THEN 1 ELSE 2 END')
            ->first();

        return response()->json([
            'discount'   => $discount ? $this->format($discount) : null,
            'product_id' => $productId,
            'customer_id' => $customerId,
        ]);
    }

    // ─── Private Helpers ───────────────────────────────────────────────────────

    private function format(Discount $d): array
    {
        return [
            'id'          => $d->id,
            'name'        => $d->name,
            'type'        => $d->type,
            'value'       => (string) $d->value,
            'label'       => $d->type === Discount::TYPE_PERCENTAGE
                ? FinancialMath::format($d->value, 2).'%'
                : '₱'.FinancialMath::format($d->value, 2).' off',
            'start_date'  => $d->start_date?->toDateString(),
            'end_date'    => $d->end_date?->toDateString(),
            'is_active'   => $d->is_active,
            'product_id'  => $d->product_id,
            'product'     => $d->product ? ['id' => $d->product->id, 'name' => $d->product->name, 'sku' => $d->product->sku] : null,
            'category_id' => $d->category_id,
            'category'    => $d->category ? ['id' => $d->category->id, 'name' => $d->category->name] : null,
            'customer_id' => $d->customer_id,
            'customer'    => $d->customer ? ['id' => $d->customer->id, 'name' => $d->customer->name] : null,
            'created_at'  => $d->created_at?->toDateString(),
        ];
    }
}
