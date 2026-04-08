<?php

namespace App\Http\Requests\Inventory;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class ProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $id = is_object($product) ? $product->id : $product;

        $existingProduct = Product::find($id);
        $hasHistory = $existingProduct && $existingProduct->transactionLines()->exists();

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'barcode' => "nullable|string|max:100|unique:products,barcode,{$id}",
            'category_id' => 'required|exists:categories,id',
            'uom_id' => 'required|exists:units_of_measure,id',
            'costing_method_id' => 'required|exists:costing_methods,id',
            'preferred_vendor_id' => 'nullable|exists:vendors,id',
            'brand' => 'nullable|string|max:100',
            'selling_price' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|numeric|min:0',
            'reorder_quantity' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:2048',
            'initial_conversion_factor' => [
                'nullable',
                'numeric',
                'min:0.000001',
                function ($attribute, $value, $fail) {
                    $uomId = $this->input('uom_id');
                    if (!$uomId) return;

                    $uom = \App\Models\UnitOfMeasure::find($uomId);
                    if (!$uom || $uom->is_base) return;

                    $productId = $this->route('product');
                    $id = is_object($productId) ? $productId->id : $productId;

                    // Check if a global rule exists
                    $hasGlobalRule = \App\Models\UomConversion::where('from_uom_id', $uomId)
                        ->whereNull('product_id')
                        ->exists();

                    // Check if a product-specific rule exists (in case of update)
                    $hasProductRule = false;
                    if ($id) {
                        $hasProductRule = \App\Models\UomConversion::where('from_uom_id', $uomId)
                            ->where('product_id', $id)
                            ->exists();
                    }

                    if (!$hasGlobalRule && !$hasProductRule && empty($value)) {
                        $fail("A conversion factor is required for this unit ({$uom->abbreviation}) because no packaging definition exists for this product.");
                    }
                }
            ],
            'initial_to_uom_id' => 'nullable|exists:units_of_measure,id',
        ];

        // If the product has history, lock the core identifiers & accounting math fields
        if ($hasHistory) {
            $rules['product_code'] = "required|string|in:{$existingProduct->product_code}";
            $rules['sku'] = "required|string|in:{$existingProduct->sku}";
            $rules['uom_id'] = "required|integer|in:{$existingProduct->uom_id}";
            $rules['costing_method_id'] = "required|integer|in:{$existingProduct->costing_method_id}";
        } else {
            $rules['product_code'] = "nullable|string|max:100|unique:products,product_code,{$id}";
            $rules['sku'] = "nullable|string|max:100|unique:products,sku,{$id}";
        }

        return $rules;
    }

    /**
     * Detailed, business-friendly validation messages.
     */
    public function messages(): array
    {
        return [
            'product_code.required' => 'A unique system identifier (Product Code) is required.',
            'product_code.unique' => 'This product code is already assigned to another record.',
            'product_code.in' => 'Product Code cannot be modified once it has transaction history.',
            'name.required' => 'The product name field is mandatory.',
            'sku.required' => 'A Stock Keeping Unit (SKU) is required for inventory tracking.',
            'sku.unique' => 'This SKU is already in use by another product.',
            'sku.in' => 'Internal ID (SKU) cannot be modified once it has transaction history.',
            'category_id.required' => 'Please select a valid product category.',
            'uom_id.required' => 'Unit of Measure must be defined.',
            'costing_method_id.required' => 'Inventory costing method is a required parameter.',
            'selling_price.numeric' => 'Selling price must be a valid numerical value.',
            'image.max' => 'The uploaded image size cannot exceed 2MB.',
        ];
    }
}
