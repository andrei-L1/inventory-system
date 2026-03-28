<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ProductStoreRequest;
use App\Http\Requests\Inventory\ProductUpdateRequest;
use App\Http\Resources\Inventory\ProductResource;
use App\Models\Product;
use App\Services\Inventory\ProductService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Display a listing of products with filtering.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = $this->productService->search($request->all());
        return ProductResource::collection($products);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(ProductStoreRequest $request): ProductResource
    {
        $product = $this->productService->createProduct($request->validated());
        return new ProductResource($product->load(['category', 'uom', 'costingMethod']));
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'uom', 'preferredVendor', 'costingMethod']));
    }

    /**
     * Update the specified product in storage.
     */
    public function update(ProductUpdateRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());
        return new ProductResource($product->refresh()->load(['category', 'uom', 'costingMethod']));
    }

    /**
     * Remove the specified product from storage (Soft Delete).
     */
    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }
}
