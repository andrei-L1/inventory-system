<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reorder_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->cascadeOnDelete();
            $table->unsignedBigInteger('location_unique_key')->storedAs('COALESCE(location_id, 0)');
            $table->decimal('min_stock', 18, 4);
            $table->decimal('max_stock', 18, 4);
            $table->decimal('reorder_qty', 18, 4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'location_unique_key'], 'reorder_rules_product_location_unique');
        });

        Schema::create('replenishment_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->decimal('current_stock', 18, 4);
            $table->decimal('suggested_qty', 18, 4);
            $table->string('reason', 100); // Low stock, Demand spike, etc.
            $table->enum('status', ['pending', 'ordered', 'ignored'])->default('pending');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(
                'ALTER TABLE replenishment_suggestions ADD CONSTRAINT chk_replenishment_suggested_qty_non_negative CHECK (suggested_qty >= 0)'
            );
            DB::statement(
                'ALTER TABLE replenishment_suggestions ADD CONSTRAINT chk_replenishment_current_stock_non_negative CHECK (current_stock >= 0)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replenishment_suggestions');
        Schema::dropIfExists('reorder_rules');
    }
};
