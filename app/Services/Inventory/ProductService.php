<?php

namespace App\Services\Inventory;

use App\Models\Attachment;
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
            $keyword = '%'.$filters['query'].'%';
            $query->where(function (Builder $q) use ($keyword) {
                $q->where('name', 'like', $keyword)
                    ->orWhere('sku', 'like', $keyword)
                    ->orWhere('product_code', 'like', $keyword);
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['vendor_id'])) {
            $query->where('preferred_vendor_id', $filters['vendor_id']);
        }

        return $query->paginate($filters['per_page'] ?? $perPage);
    }

    /**
     * Create a new product and initialize stock records.
     */
    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $image = $data['image'] ?? null;
            unset($data['image']);

            // 1. Create the Product
            if (! isset($data['created_by']) && auth()->check()) {
                $data['created_by'] = auth()->id();
            }

            $product = Product::create($data);

            // 2. Handle Image Attachment if provided
            if ($image) {
                $this->handleAttachment($product, $image, 'main_image');
            }

            // 3. Initialize Inventory records for all active locations
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

    /**
     * Handle file attachment for a model.
     */
    public function handleAttachment(Product $product, $file, string $collection = 'attachments'): Attachment
    {
        $path = $file->store('products/attachments', 'public');

        return Attachment::create([
            'attachable_id' => $product->id,
            'attachable_type' => Product::class,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'collection_name' => $collection,
            'uploader_id' => auth()->id(),
        ]);
    }
}
