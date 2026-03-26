<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add CHECK constraints for Transaction Integrity
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT chk_transaction_transfer_locations CHECK (
            (type = 'transfer' AND from_location_id IS NOT NULL AND to_location_id IS NOT NULL) OR 
            (type <> 'transfer')
        )");

        DB::statement("ALTER TABLE transactions ADD CONSTRAINT chk_transaction_receipt_vendor CHECK (
            (type = 'receipt' AND vendor_id IS NOT NULL) OR 
            (type <> 'receipt')
        )");

        DB::statement("ALTER TABLE transactions ADD CONSTRAINT chk_transaction_issue_no_vendor CHECK (
            (type = 'issue' AND vendor_id IS NULL) OR 
            (type <> 'issue')
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT chk_transaction_transfer_locations");
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT chk_transaction_receipt_vendor");
        DB::statement("ALTER TABLE transactions DROP CONSTRAINT chk_transaction_issue_no_vendor");
    }
};
