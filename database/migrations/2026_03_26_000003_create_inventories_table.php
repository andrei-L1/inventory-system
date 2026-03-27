<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Inventory (product × location stock ledger) ──────────────────────
        // One row per product per location.
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('quantity_on_hand', 18, 4)->default(0);
            // Running average cost specific to this location slot (for average-cost method)
            $table->decimal('average_cost', 18, 6)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'location_id']); // also covers product_id as leading key
            $table->index('location_id');                   // for location-only queries
            // NOTE: no separate index('product_id') — the unique key already covers it
        });

        // DB-level guard: prevent negative stock at the database engine level
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE inventories ADD CONSTRAINT chk_inventory_qty_non_negative CHECK (quantity_on_hand >= 0)');
        }

        // ─── FIFO / LIFO Cost Layers ──────────────────────────────────────────
        // Each receipt creates a cost layer. Issues consume from layers per method.
        Schema::create('inventory_cost_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('transaction_line_id')->nullable(); // FK added in 000004
            $table->decimal('received_qty', 18, 4);
            // issued_qty defined here so remaining_qty storedAs can reference it
            $table->decimal('issued_qty', 18, 4)->default(0)->comment('Total quantity consumed from this layer');
            // GENERATED ALWAYS — engine-enforced, can never drift from received_qty - issued_qty
            $table->decimal('remaining_qty', 18, 4)->storedAs('received_qty - issued_qty')->comment('Auto-calculated: received_qty - issued_qty');
            $table->decimal('unit_cost', 18, 6);
            $table->date('receipt_date');
            $table->boolean('is_exhausted')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'location_id', 'is_exhausted', 'receipt_date'], 'inv_cost_layers_query_idx');
        });

        // DB-level guards on cost layer quantities
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE inventory_cost_layers ADD CONSTRAINT chk_layers_received_qty_positive CHECK (received_qty > 0)');
            DB::statement('ALTER TABLE inventory_cost_layers ADD CONSTRAINT chk_layers_issued_qty_non_negative CHECK (issued_qty >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_layers');
        Schema::dropIfExists('inventories');
    }
};
