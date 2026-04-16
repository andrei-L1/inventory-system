<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Estimated Tax on Purchase Order Lines
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('discount_amount');   // e.g. 12.00 = 12%
            $table->decimal('tax_amount', 18, 8)->default(0)->after('tax_rate'); // computed based on subtotal after discount
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount']);
        });
    }
};
