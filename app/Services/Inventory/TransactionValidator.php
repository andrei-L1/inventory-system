<?php

namespace App\Services\Inventory;

use App\Models\Transaction;
use App\Models\TransactionType;
use Illuminate\Validation\ValidationException;

class TransactionValidator
{
    /**
     * Validate transaction business logic rules.
     *
     * @param  array  $data  Transaction data (header and lines)
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $header = $data['header'];
        $typeId = $header['transaction_type_id'] ?? null;

        if (!$typeId) {
            return;
        }

        $type = TransactionType::find($typeId);

        if ($type) {
            // 1. Transfers: Must have both origin and destination
            if ($type->matchesCode('TRFR')) {
                if (empty($header['from_location_id']) || empty($header['to_location_id'])) {
                    throw ValidationException::withMessages([
                        'to_location_id' => 'A transfer transaction must specify both origin and destination.',
                    ]);
                }
            }

            // 2. Receipts (Incoming): Must specify a vendor
            if ($type->matchesCode('RCPT') && empty($header['vendor_id'])) {
                throw ValidationException::withMessages([
                    'vendor_id' => 'A receipt transaction must specify a vendor.',
                ]);
            }

            // 3. Issues (Outgoing): Must NOT have a vendor (vendor is for income/purchases)
            if ($type->matchesCode('ISSU') && !empty($header['vendor_id'])) {
                throw ValidationException::withMessages([
                    'vendor_id' => 'An issue transaction must NOT have a vendor.',
                ]);
            }
        }
    }
}
