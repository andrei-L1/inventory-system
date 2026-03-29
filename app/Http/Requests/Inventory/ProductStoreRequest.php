<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ProductStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     * Auto-generate code and SKU if missing.
     */
    protected function prepareForValidation(): void
    {
        if (empty($this->product_code)) {
            $this->merge([
                'product_code' => 'PRD-'.strtoupper(Str::random(8)),
            ]);
        }

        if (empty($this->sku)) {
            // Generate SKU based on name if blank
            $sku = Str::slug($this->name);
            if (empty($sku)) {
                $sku = 'SKU-'.strtoupper(Str::random(6));
            } else {
                $sku = strtoupper($sku).'-'.strtoupper(Str::random(4));
            }
            $this->merge(['sku' => $sku]);
        }
    }

    public function rules(): array
    {
        return [
            'product_code' => 'required|string|max:100|unique:products,product_code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sku' => 'required|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
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
        ];
    }

    /**
     * Detailed, business-friendly validation messages.
     */
    public function messages(): array
    {
        return [
            'product_code.required' => 'A unique system identifier (Product Code) is required.',
            'product_code.unique' => 'This product code is already assigned to another record.',
            'name.required' => 'The product name field is mandatory.',
            'sku.required' => 'A Stock Keeping Unit (SKU) is required for inventory tracking.',
            'sku.unique' => 'This SKU is already in use by another product.',
            'category_id.required' => 'Please select a valid product category.',
            'uom_id.required' => 'Unit of Measure must be defined.',
            'costing_method_id.required' => 'Inventory costing method is a required parameter.',
            'selling_price.numeric' => 'Selling price must be a valid numerical value.',
            'image.max' => 'The uploaded image size cannot exceed 2MB.',
        ];
    }
}
