<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('product'); // assuming standard resource routes
        if (is_object($id)) {
            $id = $id->id;
        }

        return [
            'product_code' => "required|string|max:100|unique:products,product_code,{$id}",
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sku' => "required|string|max:100|unique:products,sku,{$id}",
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
        ];
    }
}
