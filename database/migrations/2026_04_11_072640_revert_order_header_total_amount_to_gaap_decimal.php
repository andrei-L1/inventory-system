<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * S-M3: The Batch 2 precision migration was too aggressive — it changed both
     * sales_orders and purchase_orders total_amount to decimal(18, 8).
     * Order header totals must be decimal(18, 2) for GAAP/accounting compliance
     * (e.g. banks cannot process more than 2 decimal places).
     * Line-level columns correctly remain at decimal(18, 8) for operational precision.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 2)->default(0)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 8)->default(0)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 8)->default(0)->change();
        });
    }
};
