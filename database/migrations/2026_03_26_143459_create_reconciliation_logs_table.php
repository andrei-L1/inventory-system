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
        Schema::create('reconciliation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('recorded_qty', 18, 4);    // inventories.quantity_on_hand
            $table->decimal('calculated_qty', 18, 4);  // SUM(transaction_lines)
            $table->decimal('layers_qty', 18, 4);      // SUM(inventory_cost_layers)
            $table->decimal('discrepancy', 18, 4);
            $table->boolean('is_corrected')->default(false);
            $table->timestamp('run_date')->useCurrent();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_logs');
    }
};
