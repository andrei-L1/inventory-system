<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to upgrade quantity and cost precision from 4/6 to 8 decimal places.
     */
    public function up(): void
    {
        // 1. Transaction Lines
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->decimal('quantity', 18, 8)->default(0)->change();
            $table->decimal('unit_cost', 18, 8)->default(0)->change();
            $table->decimal('total_cost', 18, 8)->default(0)->change();
            $table->decimal('unit_price', 18, 8)->default(0)->change();
        });

        // 2. Inventories
        Schema::table('inventories', function (Blueprint $table) {
            $table->decimal('quantity_on_hand', 18, 8)->default(0)->change();
            if (Schema::hasColumn('inventories', 'reserved_qty')) {
                $table->decimal('reserved_qty', 18, 8)->default(0)->change();
            }
            $table->decimal('average_cost', 18, 8)->default(0)->change();
        });

        // 3. Inventory Cost Layers
        Schema::table('inventory_cost_layers', function (Blueprint $table) {
            $table->dropColumn('remaining_qty');
        });

        Schema::table('inventory_cost_layers', function (Blueprint $table) {
            $table->decimal('received_qty', 18, 8)->change();
            $table->decimal('issued_qty', 18, 8)->default(0)->change();
            $table->decimal('unit_cost', 18, 8)->default(0)->change();
        });

        Schema::table('inventory_cost_layers', function (Blueprint $table) {
            $table->decimal('remaining_qty', 18, 8)
                ->storedAs('received_qty - issued_qty')
                ->after('issued_qty');
        });

        // 4. Purchase Order Lines
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('ordered_qty', 18, 8)->change();
            $table->decimal('received_qty', 18, 8)->default(0)->change();
            if (Schema::hasColumn('purchase_order_lines', 'returned_qty')) {
                $table->decimal('returned_qty', 18, 8)->default(0)->change();
            }
            $table->decimal('unit_cost', 18, 8)->change();
        });

        // 5. Sales Order Lines
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->decimal('ordered_qty', 18, 8)->change();
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
    public function down(): void
    {
        // Revert to original precisions (not implemented for brevity, but could be)
    }
};
