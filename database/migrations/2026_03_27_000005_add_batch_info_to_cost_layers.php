<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_cost_layers', function (Blueprint $table) {
            $table->string('batch_number', 50)->nullable()->after('transaction_line_id');
            $table->date('expiry_date')->nullable()->after('batch_number');

            $table->index(['product_id', 'batch_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_cost_layers', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'batch_number']);
            $table->dropColumn(['batch_number', 'expiry_date']);
        });
    }
};
