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
            ->with(['category', 'uom', 'preferredVendor', 'costingMethod', 'inventories.location']);

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

            // 1.5 Handle Initial Conversion Rule (Atomic Packaging)
            if (!empty($data['initial_conversion_factor'])) {
                $toUomId = $data['initial_to_uom_id'] ?? \App\Helpers\UomHelper::getSmallestUnitId($product->uom_id);
                
                if ($toUomId) {
                    \App\Models\UomConversion::create([
                        'product_id' => $product->id,
                        'from_uom_id' => $product->uom_id,
                        'to_uom_id' => $toUomId,
                        'conversion_factor' => (float) $data['initial_conversion_factor'],
                    ]);

                    // Clear cache to ensure the new rule is visible in the current request (e.g. for Resource response)
                    \App\Helpers\UomHelper::clearCache();
                }
            }

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
     * Update an existing product.
     */
    public function updateProduct(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $image = $data['image'] ?? null;
            unset($data['image']);

            // 1. Update basic product data
            $product->update($data);

            // 2. Handle Initial Conversion Rule Update
            if (!empty($data['initial_conversion_factor'])) {
                $toUomId = $data['initial_to_uom_id'] ?? \App\Helpers\UomHelper::getSmallestUnitId($product->uom_id);

                if ($toUomId) {
                    // Update existing or create if missing
                    \App\Models\UomConversion::updateOrCreate(
                        ['product_id' => $product->id, 'from_uom_id' => $product->uom_id],
                        ['to_uom_id' => $toUomId, 'conversion_factor' => (float) $data['initial_conversion_factor']]
                    );

                    \App\Helpers\UomHelper::clearCache();
                }
            }

            // 3. Handle Image Attachment
            if ($image) {
                $this->handleAttachment($product, $image, 'main_image');
            }

            return $product->refresh();
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
