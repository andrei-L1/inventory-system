<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Discount on Purchase Order Lines (negotiated at draft time)
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('discount_rate', 5, 2)->default(0)->after('unit_cost');   // e.g. 10.00 = 10%
            $table->decimal('discount_amount', 18, 8)->default(0)->after('discount_rate'); // computed: qty * cost * rate / 100
        });

        // Discount (carry from PO) + Tax (confirmed from vendor invoice) on Bill Lines
        Schema::table('bill_lines', function (Blueprint $table) {
            $table->decimal('discount_rate', 5, 2)->default(0)->after('unit_price');
            $table->decimal('discount_amount', 18, 8)->default(0)->after('discount_rate');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('discount_amount');   // e.g. 12.00 = 12% VAT
            $table->decimal('tax_amount', 18, 8)->default(0)->after('tax_rate');       // computed: (subtotal - discount) * rate / 100
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn(['discount_rate', 'discount_amount']);
        });

        Schema::table('bill_lines', function (Blueprint $table) {
            $table->dropColumn(['discount_rate', 'discount_amount', 'tax_rate', 'tax_amount']);
        });
    }
};
