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
        // 1. Refactor locations.type
        Schema::table('locations', function (Blueprint $table) {
            $table->unsignedBigInteger('location_type_id')->nullable()->after('name');
        });

        // Set initial location_type_id based on ENUM values
        $locationTypes = DB::table('location_types')->get();
        foreach ($locationTypes as $type) {
            DB::table('locations')->where('type', $type->name)->update(['location_type_id' => $type->id]);
        }

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->unsignedBigInteger('location_type_id')->nullable(false)->change();
            $table->foreign('location_type_id')->references('id')->on('location_types');
        });

        // 2. Refactor transactions.type and status
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_type_id')->nullable()->after('reference_number');
            $table->unsignedBigInteger('transaction_status_id')->nullable()->after('transaction_type_id');
        });

        // Set initial transaction_type_id based on ENUM values
        $transactionTypes = DB::table('transaction_types')->get();
        foreach ($transactionTypes as $type) {
            DB::table('transactions')->where('type', $type->name)->update(['transaction_type_id' => $type->id]);
        }

        // Set initial transaction_status_id based on ENUM values
        $transactionStatuses = DB::table('transaction_statuses')->get();
        foreach ($transactionStatuses as $status) {
            DB::table('transactions')->where('status', $status->name)->update(['transaction_status_id' => $status->id]);
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['type', 'status']);
            $table->unsignedBigInteger('transaction_type_id')->nullable(false)->change();
            $table->unsignedBigInteger('transaction_status_id')->nullable(false)->change();
            $table->foreign('transaction_type_id')->references('id')->on('transaction_types');
            $table->foreign('transaction_status_id')->references('id')->on('transaction_statuses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse refactor locations
        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign(['location_type_id']);
            $table->enum('type', ['warehouse', 'zone', 'aisle', 'bin'])->default('warehouse')->after('name');
        });
        
        // Reverse refactor transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['transaction_type_id']);
            $table->dropForeign(['transaction_status_id']);
            $table->enum('type', ['receipt', 'issue', 'transfer', 'adjustment', 'opening_balance'])->after('reference_number');
            $table->enum('status', ['draft', 'pending', 'posted', 'cancelled'])->default('draft')->after('type');
        });

        // (We would then need to repopulate the ENUM columns but that's complex to reverse correctly with data)
        // This down() is mostly structural.
    }
};
