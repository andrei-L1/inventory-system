<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Transaction Headers ──────────────────────────────────────────────
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            // Human-readable reference number: TRX-2026-000001
            $table->string('reference_number', 30)->unique();
            $table->enum('type', [
                'receipt',          // incoming stock (purchase, return from customer)
                'issue',            // outgoing stock (sale, return to supplier)
                'transfer',         // move between locations
                'adjustment',       // stock count correction
                'opening_balance',  // initial stock entry
            ]);
            $table->enum('status', ['draft', 'pending', 'posted', 'cancelled'])->default('draft');
            // Source & destination (used in transfers)
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->date('transaction_date');
            $table->text('notes')->nullable();
            $table->string('reference_doc', 100)->nullable();   // PO#, SO#, etc.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('transaction_date');
            $table->index('created_by');
        });

        // ─── Transaction Lines ────────────────────────────────────────────────
        Schema::create('transaction_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 6)->default(0);    // cost at time of transaction
            $table->decimal('total_cost', 18, 6)->default(0);   // qty × unit_cost
            $table->decimal('unit_price', 18, 6)->default(0);   // selling price (issues)
            // Snapshot of costing method used
            $table->enum('costing_method', ['fifo', 'lifo', 'average'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'product_id']);
            $table->index(['product_id', 'location_id']);
        });

        // DB-level guard: prevent zero or negative quantities on transaction lines
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE transaction_lines ADD CONSTRAINT chk_txn_line_qty_not_zero CHECK (quantity != 0)');
        }

        // ─── Add FK to cost layers now that transaction_lines exists ─────────
        Schema::table('inventory_cost_layers', function (Blueprint $table) {
            $table->foreign('transaction_line_id')
                ->references('id')
                ->on('transaction_lines')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_cost_layers', function (Blueprint $table) {
            $table->dropForeign(['transaction_line_id']);
        });
        Schema::dropIfExists('transaction_lines');
        Schema::dropIfExists('transactions');
    }
};
