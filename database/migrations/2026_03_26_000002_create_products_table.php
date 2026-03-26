<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Categories ──────────────────────────────────────────────────────
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // ─── Units of Measure ────────────────────────────────────────────────
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();           // piece, box, kg
            $table->string('abbreviation', 10)->unique();   // pcs, bx, kg
            $table->timestamps();
        });

        // ─── Products (master catalogue) ─────────────────────────────────────
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code', 60)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->string('brand', 100)->nullable();
            $table->string('sku', 100)->nullable()->unique();
            $table->string('barcode', 100)->nullable()->unique();
            // Costing method: fifo | lifo | average
            $table->enum('costing_method', ['fifo', 'lifo', 'average'])->default('average');
            // Running weighted-average cost (for "average" method)
            $table->decimal('average_cost', 18, 6)->default(0);
            // Standard sale price
            $table->decimal('selling_price', 18, 6)->default(0);
            // Reorder info
            $table->decimal('reorder_point', 18, 4)->default(0);
            $table->decimal('reorder_quantity', 18, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index('costing_method');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('categories');
    }
};
