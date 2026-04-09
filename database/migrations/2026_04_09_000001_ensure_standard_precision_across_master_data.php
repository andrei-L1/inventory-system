<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to unify precision at 8 decimal places across master data and replenishment tables.
     */
    public function up(): void
    {
        // 1. Products Master Data
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('average_cost', 18, 8)->default(0)->change();
            $table->decimal('selling_price', 18, 8)->default(0)->change();
            $table->decimal('reorder_point', 18, 8)->default(0)->change();
            $table->decimal('reorder_quantity', 18, 8)->default(0)->change();
        });

        // 2. Pricing & Discounts
        Schema::table('price_list_items', function (Blueprint $table) {
            $table->decimal('price', 18, 8)->change();
            $table->decimal('min_quantity', 18, 8)->default(0)->change();
        });

        Schema::table('discounts', function (Blueprint $table) {
            $table->decimal('value', 18, 8)->change();
        });

        // 3. Replenishment & Reorder Rules
        Schema::table('reorder_rules', function (Blueprint $table) {
            $table->decimal('min_stock', 18, 8)->change();
            $table->decimal('max_stock', 18, 8)->change();
            $table->decimal('reorder_qty', 18, 8)->change();
        });

        Schema::table('replenishment_suggestions', function (Blueprint $table) {
            $table->decimal('current_stock', 18, 8)->change();
            $table->decimal('suggested_qty', 18, 8)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting to previous precisions (approximate, as per original migrations)
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('average_cost', 18, 6)->change();
            $table->decimal('selling_price', 18, 6)->change();
            $table->decimal('reorder_point', 18, 4)->change();
            $table->decimal('reorder_quantity', 18, 4)->change();
        });

        Schema::table('price_list_items', function (Blueprint $table) {
            $table->decimal('price', 18, 6)->change();
            $table->decimal('min_quantity', 18, 4)->change();
        });

        Schema::table('discounts', function (Blueprint $table) {
            $table->decimal('value', 18, 4)->change();
        });

        Schema::table('reorder_rules', function (Blueprint $table) {
            $table->decimal('min_stock', 18, 4)->change();
            $table->decimal('max_stock', 18, 4)->change();
            $table->decimal('reorder_qty', 18, 4)->change();
        });

        Schema::table('replenishment_suggestions', function (Blueprint $table) {
            $table->decimal('current_stock', 18, 4)->change();
            $table->decimal('suggested_qty', 18, 4)->change();
        });
    }
};
