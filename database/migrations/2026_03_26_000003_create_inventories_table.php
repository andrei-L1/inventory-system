<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

            $table->unique(['product_id', 'location_id']);
            $table->index('location_id');
            $table->index('product_id');
        });

        // ─── FIFO / LIFO Cost Layers ──────────────────────────────────────────
        // Each receipt creates a cost layer. Issues consume from layers per method.
        Schema::create('inventory_cost_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('transaction_line_id')->nullable(); // linked later via FK
            $table->decimal('received_qty', 18, 4);
            $table->decimal('remaining_qty', 18, 4);
            $table->decimal('unit_cost', 18, 6);
            $table->date('receipt_date');
            $table->boolean('is_exhausted')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'location_id', 'is_exhausted', 'receipt_date'], 'inv_cost_layers_query_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_layers');
        Schema::dropIfExists('inventories');
    }
};
