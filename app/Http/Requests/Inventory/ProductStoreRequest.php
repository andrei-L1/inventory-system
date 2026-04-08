<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

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
        // Automation is now handled by the Product Model observer
        // to ensure proper ID-based sequencing (0001-HAM format).
    }

    public function rules(): array
    {
        return [
            'product_code' => 'nullable|string|max:100|unique:products,product_code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'category_id' => 'required|exists:categories,id',
            'uom_id' => 'required|exists:units_of_measure,id',
            
            // Conditional Packaging Validation
            'initial_conversion_factor' => [
                'nullable',
                'numeric',
                'min:0.000001',
                function ($attribute, $value, $fail) {
                    $uomId = $this->input('uom_id');
                    if (!$uomId) return;

                    $uom = \App\Models\UnitOfMeasure::find($uomId);
                    if (!$uom || $uom->is_base) return;

                    // If it's not a base unit, check if a global rule exists
                    $hasGlobalRule = \App\Models\UomConversion::where('from_uom_id', $uomId)
                        ->whereNull('product_id')
                        ->exists();

                    if (!$hasGlobalRule && empty($value)) {
                        $fail("A conversion factor is required for this unit ({$uom->abbreviation}) because no global default exists.");
                    }
                }
            ],
            
            'initial_to_uom_id' => 'nullable|exists:units_of_measure,id',
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
