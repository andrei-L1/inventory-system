<?php

namespace Tests\Unit\Inventory;

use App\Models\TransactionType;
use App\Services\Inventory\TransactionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransactionValidatorTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new TransactionValidator;

        // Seed basic types
        TransactionType::create(['name' => 'receipt', 'code' => 'RCPT']);
        TransactionType::create(['name' => 'issue', 'code' => 'ISSU']);
        TransactionType::create(['name' => 'transfer', 'code' => 'TRFR']);
    }

    public function test_receipt_requires_vendor()
    {
        $type = TransactionType::where('code', 'RCPT')->first();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A receipt transaction must specify a vendor.');

        $this->validator->validate([
            'header' => [
                'transaction_type_id' => $type->id,
                'vendor_id' => null,
            ],
        ]);
    }

    public function test_issue_must_not_have_vendor()
    {
        $type = TransactionType::where('code', 'ISSU')->first();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('An issue transaction must NOT have a vendor.');

        $this->validator->validate([
            'header' => [
                'transaction_type_id' => $type->id,
                'vendor_id' => 99,
            ],
        ]);
    }

    public function test_transfer_requires_both_locations()
    {
        $type = TransactionType::where('code', 'TRFR')->first();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A transfer transaction must specify both origin and destination.');

        $this->validator->validate([
            'header' => [
                'transaction_type_id' => $type->id,
                'from_location_id' => 1,
                'to_location_id' => null,
            ],
        ]);
    }

    public function test_valid_transaction_passes()
    {
        $type = TransactionType::where('code', 'RCPT')->first();

        $this->validator->validate([
            'header' => [
                'transaction_type_id' => $type->id,
                'vendor_id' => 1,
            ],
        ]);

        $this->assertTrue(true); // Should not throw exception
    }
}
