<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create location_types
        Schema::create('location_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        // Seed location_types
        DB::table('location_types')->insert([
            ['name' => 'warehouse', 'description' => 'Main storage facility', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'zone', 'description' => 'Specific area within a warehouse', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'aisle', 'description' => 'Passageway between rows', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'bin', 'description' => 'Smallest storage unit/shelf location', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. Create transaction_types
        Schema::create('transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('code', 20)->unique();
            $table->boolean('affects_inventory')->default(true);
            $table->boolean('is_debit')->default(true); // 1 = Increases stock, 0 = Decreases stock
            $table->timestamps();
        });

        // Seed transaction_types
        DB::table('transaction_types')->insert([
            ['name' => 'receipt', 'code' => 'RCPT', 'affects_inventory' => true, 'is_debit' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'issue', 'code' => 'ISSU', 'affects_inventory' => true, 'is_debit' => false, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'transfer', 'code' => 'TRFR', 'affects_inventory' => true, 'is_debit' => false, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'adjustment', 'code' => 'ADJS', 'affects_inventory' => true, 'is_debit' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'opening_balance', 'code' => 'OPNB', 'affects_inventory' => true, 'is_debit' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 3. Create transaction_statuses
        Schema::create('transaction_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->boolean('is_modifiable')->default(true);
            $table->timestamps();
        });

        // Seed transaction_statuses
        DB::table('transaction_statuses')->insert([
            ['name' => 'draft', 'is_modifiable' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'pending', 'is_modifiable' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'posted', 'is_modifiable' => false, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'cancelled', 'is_modifiable' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_statuses');
        Schema::dropIfExists('transaction_types');
        Schema::dropIfExists('location_types');
    }
};
