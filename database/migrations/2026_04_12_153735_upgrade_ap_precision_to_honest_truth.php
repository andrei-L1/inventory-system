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
        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 8)->change();
            if (! Schema::hasColumn('bills', 'paid_amount')) {
                $table->decimal('paid_amount', 18, 8)->default(0)->after('total_amount');
            } else {
                $table->decimal('paid_amount', 18, 8)->change();
            }
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->decimal('amount', 18, 8)->change();
        });

        Schema::table('vendor_payment_allocations', function (Blueprint $table) {
            $table->decimal('amount', 18, 8)->change();
        });

        Schema::table('bill_lines', function (Blueprint $table) {
            $table->decimal('unit_price', 18, 8)->change();
            $table->decimal('subtotal', 18, 8)->change();
        });

        Schema::table('debit_notes', function (Blueprint $table) {
            $table->decimal('amount', 18, 8)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('bill_lines', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->change();
            $table->decimal('subtotal', 15, 2)->change();
        });

        Schema::table('vendor_payment_allocations', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('total_amount', 15, 2)->change();
            $table->dropColumn('paid_amount');
        });
    }
};
