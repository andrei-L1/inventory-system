<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates reference/lookup tables.
     * Seed data lives in dedicated Seeder classes — NOT here.
     * See: LocationTypeSeeder, TransactionTypeSeeder, TransactionStatusSeeder
     */
    public function up(): void
    {
        // 1. Location Types (warehouse, zone, aisle, bin)
        Schema::create('location_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        // 2. Transaction Types (receipt, issue, transfer, adjustment, opening_balance)
        Schema::create('transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('code', 20)->unique();
            $table->boolean('affects_inventory')->default(true);
            $table->boolean('is_debit')->default(true); // true = increases stock, false = decreases
            $table->timestamps();
        });

        // 3. Transaction Statuses (draft, pending, posted, cancelled)
        Schema::create('transaction_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->boolean('is_modifiable')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_statuses');
        Schema::dropIfExists('transaction_types');
        Schema::dropIfExists('location_types');
    }
};
