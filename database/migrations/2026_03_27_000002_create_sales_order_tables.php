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
        Schema::create('sales_order_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->boolean('is_editable')->default(true);
            $table->timestamps();
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('so_number', 30)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('status_id')->constrained('sales_order_statuses')->restrictOnDelete();
            $table->date('order_date');
            $table->date('requested_delivery_date')->nullable();
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['customer_id', 'status_id', 'order_date']);
        });

        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('ordered_qty', 18, 4);
            $table->decimal('shipped_qty', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 6);
            $table->decimal('total_price', 18, 6)->storedAs('ordered_qty * unit_price');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sales_order_id', 'product_id']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(
                'ALTER TABLE sales_order_lines ADD CONSTRAINT chk_so_line_shipped_qty_bounds CHECK (shipped_qty >= 0 AND shipped_qty <= ordered_qty)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('sales_order_statuses');
    }
};
