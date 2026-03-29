<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the body for POST /api/transactions.
 *
 * Expected shape:
 * {
 *   "header": {
 *     "transaction_type_id": 1,
 *     "transaction_status_id": 1,   // 'draft' or 'posted'
 *     "transaction_date": "2026-03-29",
 *     "reference_number": "RCV-2026-001",  // optional but recommended
 *     "from_location_id": null,
 *     "to_location_id": 3,
 *     "vendor_id": 2,           // required for receipts
 *     "customer_id": null,
 *     "notes": "...",
 *     "adjustment_reason_id": null
 *   },
 *   "lines": [
 *     {
 *       "product_id": 5,
 *       "location_id": 3,
 *       "quantity": 100,         // positive = receipt/in, negative = issue/out
 *       "unit_cost": 12.50,
 *       "uom_id": null           // null = use product base UOM, otherwise auto-convert
 *     }
 *   ]
 * }
 */
class TransactionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handled by CheckPermission middleware on the route.
    }

    public function rules(): array
    {
        return [
            // Header
            'header'                          => ['required', 'array'],
            'header.transaction_type_id'      => ['required', 'integer', 'exists:transaction_types,id'],
            'header.transaction_status_id'    => ['required', 'integer', 'exists:transaction_statuses,id'],
            'header.transaction_date'         => ['required', 'date'],
            'header.reference_number'         => ['nullable', 'string', 'max:100'],
            'header.from_location_id'         => ['nullable', 'integer', 'exists:locations,id'],
            'header.to_location_id'           => ['nullable', 'integer', 'exists:locations,id'],
            'header.vendor_id'                => ['nullable', 'integer', 'exists:vendors,id'],
            'header.customer_id'              => ['nullable', 'integer', 'exists:customers,id'],
            'header.purchase_order_id'        => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'header.sales_order_id'           => ['nullable', 'integer', 'exists:sales_orders,id'],
            'header.adjustment_reason_id'     => ['nullable', 'integer', 'exists:adjustment_reasons,id'],
            'header.notes'                    => ['nullable', 'string', 'max:1000'],

            // Lines
            'lines'                           => ['required', 'array', 'min:1'],
            'lines.*.product_id'              => ['required', 'integer', 'exists:products,id'],
            'lines.*.location_id'             => ['required', 'integer', 'exists:locations,id'],
            'lines.*.quantity'                => ['required', 'numeric'],
            'lines.*.unit_cost'               => ['required', 'numeric', 'min:0'],
            'lines.*.uom_id'                  => ['nullable', 'integer', 'exists:unit_of_measures,id'],
            'lines.*.notes'                   => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'header.transaction_type_id.required'   => 'A transaction type is required.',
            'header.transaction_status_id.required' => 'A transaction status is required.',
            'header.transaction_date.required'      => 'A transaction date is required.',
            'lines.required'                        => 'At least one line item is required.',
            'lines.min'                             => 'At least one line item is required.',
            'lines.*.product_id.required'           => 'Each line must specify a product.',
            'lines.*.location_id.required'          => 'Each line must specify a location.',
            'lines.*.quantity.required'             => 'Each line must specify a quantity.',
            'lines.*.unit_cost.required'            => 'Each line must specify a unit cost.',
        ];
    }
}
