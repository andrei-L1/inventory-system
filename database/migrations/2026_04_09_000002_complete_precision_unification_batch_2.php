<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to finalize precision unification across Order Headers, Financial Limits, and Logistics metrics.
     */
    public function up(): void
    {
        // 1. Order Headers (Aligning with 8-decimal lines)
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 8)->default(0)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 8)->default(0)->change();
        });

        // 2. Financial Limits
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit_limit', 18, 8)->default(0)->change();
        });

        // 3. Logistics Costs & Physical Metrics
        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('shipping_cost', 18, 8)->default(0)->change();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('weight', 18, 8)->nullable()->change();
            $table->decimal('length', 18, 8)->nullable()->change();
            $table->decimal('width', 18, 8)->nullable()->change();
            $table->decimal('height', 18, 8)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 2)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 2)->change();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit_limit', 18, 2)->change();
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('shipping_cost', 18, 2)->change();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('weight', 10, 2)->change();
            $table->decimal('length', 10, 2)->change();
            $table->decimal('width', 10, 2)->change();
            $table->decimal('height', 10, 2)->change();
        });
    }
};
