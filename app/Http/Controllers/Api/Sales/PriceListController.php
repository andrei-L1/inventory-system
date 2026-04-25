<?php

namespace App\Http\Controllers\Api\Sales;

use App\Helpers\FinancialMath;
use App\Http\Controllers\Controller;
use App\Models\PriceList;
use App\Models\PriceListItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceListController extends Controller
{
    // ─── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $lists = PriceList::withCount('customers')
            ->with(['items.product:id,name,sku'])
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate($request->get('limit', 25));

        return response()->json($lists->through(fn ($l) => $this->format($l)));
    }

    // ─── Show ──────────────────────────────────────────────────────────────────

    public function show(PriceList $priceList): JsonResponse
    {
        $priceList->loadCount('customers')->load('items.product:id,name,sku,selling_price');

        return response()->json(['data' => $this->format($priceList)]);
    }

    // ─── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:price_lists,name',
            'currency' => 'required|string|max:3',
            'is_active' => 'boolean',
        ]);

        $list = PriceList::create($validated);

        return response()->json(['data' => $this->format($list)], 201);
    }

    // ─── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, PriceList $priceList): JsonResponse
    {
        $validated = $request->validate([
            'name' => "required|string|max:100|unique:price_lists,name,{$priceList->id}",
            'currency' => 'required|string|max:3',
            'is_active' => 'boolean',
        ]);

        $priceList->update($validated);

        return response()->json(['data' => $this->format($priceList->refresh())]);
    }

    // ─── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(PriceList $priceList): JsonResponse
    {
        abort_if(
            $priceList->customers()->exists(),
            409,
            'This price list is assigned to one or more customers. Reassign them first.'
        );

        $priceList->delete();

        return response()->json(['message' => 'Price list deleted.']);
    }

    // ─── Items (nested) ────────────────────────────────────────────────────────

    /**
     * Upsert a product price inside this list (create or update by product + min_qty).
     */
    public function upsertItem(Request $request, PriceList $priceList): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'price' => 'required|numeric|min:0',
            'min_quantity' => 'nullable|numeric|min:0',
        ]);

        $item = PriceListItem::updateOrCreate(
            [
                'price_list_id' => $priceList->id,
                'product_id' => $validated['product_id'],
                'min_quantity' => FinancialMath::round((string) ($validated['min_quantity'] ?? 0), 4),
            ],
            [
                'price' => FinancialMath::round((string) $validated['price'], 6),
            ]
        );

        $item->load('product:id,name,sku,selling_price');

        return response()->json(['data' => $this->formatItem($item)], $item->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Remove a single item from this price list.
     */
    public function destroyItem(PriceList $priceList, PriceListItem $priceListItem): JsonResponse
    {
        abort_if($priceListItem->price_list_id !== $priceList->id, 404);
        $priceListItem->delete();

        return response()->json(['message' => 'Price list item removed.']);
    }

    /**
     * Resolve price for a specific product + quantity.
     * Used by the SO Create form to auto-fill unit price.
     */
    public function resolvePrice(Request $request, PriceList $priceList): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'nullable|numeric|min:0',
        ]);

        $resolved = $priceList->resolvePrice(
            $request->product_id,
            (string) ($request->qty ?? 1)
        );

        return response()->json([
            'price_list_id' => $priceList->id,
            'product_id' => $request->product_id,
            'qty' => $request->qty ?? 1,
            'resolved_price' => $resolved,
        ]);
    }

    // ─── Private Helpers ───────────────────────────────────────────────────────

    private function format(PriceList $list): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'currency' => $list->currency,
            'is_active' => $list->is_active,
            'customers_count' => $list->customers_count ?? null,
            'items' => $list->relationLoaded('items')
                ? $list->items->map(fn ($i) => $this->formatItem($i))->values()
                : null,
            'created_at' => $list->created_at?->toDateString(),
        ];
    }

    private function formatItem(PriceListItem $item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'sku' => $item->product?->sku,
            'product_name' => $item->product?->name,
            'selling_price' => $item->product?->selling_price ? (string) $item->product->selling_price : null,
            'price' => (string) $item->price,
            'min_quantity' => (string) $item->min_quantity,
        ];
    }
}
