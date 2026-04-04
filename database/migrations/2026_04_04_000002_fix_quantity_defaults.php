<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix missing default values and upgrade COST precision to 8 decimals.
     */
    public function up(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->decimal('quantity', 18, 8)->default(0)->change();
            $table->decimal('unit_cost', 18, 8)->default(0)->change();
            $table->decimal('total_cost', 18, 8)->default(0)->change();
            $table->decimal('unit_price', 18, 8)->default(0)->change();
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->decimal('quantity_on_hand', 18, 8)->default(0)->change();
            if (Schema::hasColumn('inventories', 'reserved_qty')) {
                $table->decimal('reserved_qty', 18, 8)->default(0)->change();
            }
            $table->decimal('average_cost', 18, 8)->default(0)->change();
        });

        Schema::table('inventory_cost_layers', function (Blueprint $table) {
            $table->decimal('issued_qty', 18, 8)->default(0)->change();
            $table->decimal('unit_cost', 18, 8)->default(0)->change();
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('received_qty', 18, 8)->default(0)->change();
            if (Schema::hasColumn('purchase_order_lines', 'returned_qty')) {
                $table->decimal('returned_qty', 18, 8)->default(0)->change();
            }
            $table->decimal('unit_cost', 18, 8)->change();
        });

        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->decimal('shipped_qty', 18, 8)->default(0)->change();
            if (Schema::hasColumn('sales_order_lines', 'picked_qty')) {
                $table->decimal('picked_qty', 18, 8)->default(0)->change();
            }
            if (Schema::hasColumn('sales_order_lines', 'packed_qty')) {
                $table->decimal('packed_qty', 18, 8)->default(0)->change();
            }
            if (Schema::hasColumn('sales_order_lines', 'returned_qty')) {
                $table->decimal('returned_qty', 18, 8)->default(0)->change();
            }
            if (Schema::hasColumn('sales_order_lines', 'subtotal')) {
                $table->decimal('subtotal', 18, 8)->default(0)->change();
            }
            $table->decimal('unit_price', 18, 8)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
