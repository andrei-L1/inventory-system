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
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->foreignId('uom_id')->nullable()->after('product_id')->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('tax_rate', 8, 4)->default(0)->after('unit_price');
            $table->decimal('tax_amount', 18, 4)->default(0)->after('tax_rate');
            $table->decimal('discount_rate', 8, 4)->default(0)->after('tax_amount');
            $table->decimal('discount_amount', 18, 4)->default(0)->after('discount_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uom_id');
            $table->dropColumn(['tax_rate', 'tax_amount', 'discount_rate', 'discount_amount']);
        });
    }
};
