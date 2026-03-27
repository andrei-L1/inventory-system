<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates purchase order tables.
     * Seed data lives in PurchaseOrderStatusSeeder — NOT here.
     */
    public function up(): void
    {
        // 1. Purchase Order Statuses
        Schema::create('purchase_order_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->boolean('is_editable')->default(true);
            $table->timestamps();
        });

        // 2. Purchase Orders
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 30)->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('status_id')->constrained('purchase_order_statuses')->restrictOnDelete();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['vendor_id', 'status_id', 'order_date']);
        });

        // 3. Purchase Order Lines
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('ordered_qty', 18, 4);
            $table->decimal('received_qty', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 6);
            $table->decimal('total_cost', 18, 6)->storedAs('ordered_qty * unit_cost');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'product_id']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(
                'ALTER TABLE purchase_order_lines ADD CONSTRAINT chk_po_line_received_qty_bounds CHECK (received_qty >= 0 AND received_qty <= ordered_qty)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_order_statuses');
    }
};
