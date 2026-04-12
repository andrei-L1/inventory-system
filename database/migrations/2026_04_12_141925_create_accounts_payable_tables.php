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
        // ─── Vendor Bills (Accounts Payable) ─────────────────────────────────
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bill_number')->unique();
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->decimal('total_amount', 15, 2);
            $table->enum('status', ['DRAFT', 'POSTED', 'PAID', 'VOID'])->default('DRAFT');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_id', 'status']);
            $table->index('bill_date');
        });

        // ─── Vendor Payments ───────────────────────────────────────────────
        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('reference_number')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'payment_date']);
        });

        // ─── Debit Notes (Purchase Returns Credits) ────────────────────────
        Schema::create('debit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('debit_note_number')->unique();
            $table->enum('status', ['DRAFT', 'POSTED', 'APPLIED', 'VOID'])->default('DRAFT');
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debit_notes');
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('bills');
    }
};
