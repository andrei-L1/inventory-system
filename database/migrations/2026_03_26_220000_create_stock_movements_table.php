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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('transaction_line_id')->nullable()->constrained('transaction_lines')->nullOnDelete();
            
            $table->enum('movement_type', ['in', 'out']);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->decimal('total_cost', 18, 6)->default(0);
            
            $table->timestamp('movement_date')->useCurrent();
            $table->timestamp('created_at')->nullable();

            // Indexes for performance
            $table->index(['product_id', 'location_id', 'movement_date']);
            $table->index('movement_type');
            $table->index('movement_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
