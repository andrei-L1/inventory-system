<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Aligns DOCUMENT HEADERS to 2-decimal GAAP standard.
     * Line items remain at 8-decimal "Honest Truth" (set in other migrations).
     * Enforces non-nullable safety defaults to prevent insertion crashes.
     */
    public function up(): void
    {
        // ─── Accounts Payable Headers ───────────────────────────────────────
        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 2)->default(0)->change();
            $table->decimal('paid_amount', 18, 2)->default(0)->change();
        });

        Schema::table('debit_notes', function (Blueprint $table) {
            $table->decimal('amount', 18, 2)->default(0)->change();
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->decimal('amount', 18, 2)->default(0)->change();
        });

        // ─── Accounts Receivable Headers ──────────────────────────────────────
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 2)->default(0)->change();
            $table->decimal('paid_amount', 18, 2)->default(0)->change();
        });

        // ─── Procurement & Sales Headers ──────────────────────────────────────
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 2)->default(0)->change();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // NOOP: Reversing precision cleanup is not recommended as it may lose data.
        // We revert to 8dp if absolutely necessary, but we keep the defaults.
        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('total_amount', 18, 8)->change();
        });
    }
};
