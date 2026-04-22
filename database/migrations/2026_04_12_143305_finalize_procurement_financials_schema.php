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
        // ─── Extend PO Lines ────────────────────────────────────────────────
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('billed_qty', 18, 8)->default(0)->after('received_qty');
        });

        // ─── Bill Lines (The GRN Match) ──────────────────────────────────────
        Schema::create('bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 8);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['bill_id', 'transaction_line_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_lines');
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn('billed_qty');
        });
    }
};
