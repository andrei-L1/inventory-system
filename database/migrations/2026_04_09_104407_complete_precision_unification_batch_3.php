<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Completes the precision unification for Sales Order financials.
     */
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->decimal('tax_amount', 18, 8)->default(0)->change();
            $table->decimal('discount_amount', 18, 8)->default(0)->change();
            
            // Keeping rate multipliers at (8,4) as they are percentage constants (e.g. 12.5000), 
            // but explicitly standardizing them here for audit visibility.
            $table->decimal('tax_rate', 8, 4)->default(0)->change();
            $table->decimal('discount_rate', 8, 4)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->decimal('tax_amount', 18, 4)->default(0)->change();
            $table->decimal('discount_amount', 18, 4)->default(0)->change();
            $table->decimal('tax_rate', 8, 4)->default(0)->change();
            $table->decimal('discount_rate', 8, 4)->default(0)->change();
        });
    }
};
