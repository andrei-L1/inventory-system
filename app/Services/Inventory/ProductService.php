<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * Search and filter the product catalog.
     */
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['category', 'uom', 'preferredVendor', 'costingMethod']);

        if (! empty($filters['query'])) {
            $keyword = '%' . $filters['query'] . '%';
            $query->where(function (Builder $q) use ($keyword) {
                $q->where('name', 'like', $keyword)
                    ->orWhere('sku', 'like', $keyword)
                    ->orWhere('product_code', 'like', $keyword);
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new product and initialize stock records.
     */
    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            // 1. Create the Product
            $product = Product::create($data);

            // 2. Initialize Inventory records for all active locations
            // This ensures every product is "Ready to Receive" everywhere.
            $locations = Location::where('is_active', true)->get();

            foreach ($locations as $location) {
                Inventory::create([
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'quantity_on_hand' => 0,
                    'average_cost' => 0,
                ]);
            }

            return $product;
        });
    }
}
