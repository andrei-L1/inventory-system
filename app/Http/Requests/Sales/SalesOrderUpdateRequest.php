<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SalesOrderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'order_date' => ['required', 'date'],
            'expected_shipping_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency' => ['required', 'string', 'max:3'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'exists:sales_order_lines,id'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.location_id' => ['required', 'exists:locations,id'],
            'lines.*.uom_id' => ['required', 'exists:units_of_measure,id'],
            'lines.*.ordered_qty' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
