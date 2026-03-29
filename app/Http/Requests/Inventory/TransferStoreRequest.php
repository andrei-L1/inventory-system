<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the body for POST /api/transfers.
 *
 * Expected shape:
 * {
 *   "header": {
 *     "transaction_type_id": <TRFR type id>,
 *     "transaction_status_id": <posted or draft>,
 *     "transaction_date": "2026-03-29",
 *     "reference_number": "TRF-2026-001",
 *     "notes": "Replenish store from main warehouse"
 *   },
 *   "from_location_id": 1,
 *   "to_location_id": 3,
 *   "lines": [
 *     { "product_id": 5, "quantity": 50, "unit_cost": 12.50 }
 *   ]
 * }
 */
class TransferStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'header' => ['required', 'array'],
            'header.transaction_type_id' => ['required', 'integer', 'exists:transaction_types,id'],
            'header.transaction_status_id' => ['required', 'integer', 'exists:transaction_statuses,id'],
            'header.transaction_date' => ['required', 'date'],
            'header.reference_number' => ['nullable', 'string', 'max:100'],
            'header.notes' => ['nullable', 'string', 'max:1000'],

            'from_location_id' => ['required', 'integer', 'exists:locations,id', 'different:to_location_id'],
            'to_location_id' => ['required', 'integer', 'exists:locations,id'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.uom_id' => ['nullable', 'integer', 'exists:unit_of_measures,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_location_id.different' => 'Origin and destination locations must be different.',
            'lines.*.quantity.min' => 'Transfer quantity must be greater than zero.',
        ];
    }
}
