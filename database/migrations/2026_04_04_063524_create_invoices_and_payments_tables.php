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
        // 1. Create Invoices Table
        Schema::create('invoices', function (Blueprint $row) {
            $row->id();
            $row->string('invoice_number')->unique();
            $row->unsignedBigInteger('customer_id');
            $row->unsignedBigInteger('sales_order_id')->nullable();
            $row->date('invoice_date');
            $row->date('due_date')->nullable();
            $row->decimal('total_amount', 18, 8)->default(0);
            $row->decimal('paid_amount', 18, 8)->default(0);
            $row->string('status')->default('DRAFT'); // DRAFT, OPEN, PAID, VOID
            $row->string('type')->default('INVOICE'); // INVOICE, CREDIT_NOTE
            $row->text('notes')->nullable();
            $row->timestamps();
            $row->softDeletes();

            $row->foreign('customer_id')->references('id')->on('customers');
            $row->foreign('sales_order_id')->references('id')->on('sales_orders');
        });

        // 2. Create Invoice Lines Table
        Schema::create('invoice_lines', function (Blueprint $row) {
            $row->id();
            $row->unsignedBigInteger('invoice_id');
            $row->unsignedBigInteger('sales_order_line_id')->nullable();
            $row->unsignedBigInteger('product_id');
            $row->decimal('quantity', 18, 8);
            $row->decimal('unit_price', 18, 8);
            $row->decimal('subtotal', 18, 8);
            $row->timestamps();

            $row->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $row->foreign('sales_order_line_id')->references('id')->on('sales_order_lines');
            $row->foreign('product_id')->references('id')->on('products');
        });

        // 3. Create Payments Table
        Schema::create('payments', function (Blueprint $row) {
            $row->id();
            $row->string('payment_number')->unique();
            $row->unsignedBigInteger('customer_id');
            $row->date('payment_date');
            $row->decimal('amount', 18, 8);
            $row->string('payment_method')->nullable();
            $row->string('reference_number')->nullable();
            $row->text('notes')->nullable();
            $row->timestamps();
            $row->softDeletes();

            $row->foreign('customer_id')->references('id')->on('customers');
        });

        // 4. Create Payment Allocations Table
        Schema::create('payment_allocations', function (Blueprint $row) {
            $row->id();
            $row->unsignedBigInteger('payment_id');
            $row->unsignedBigInteger('invoice_id');
            $row->decimal('amount', 18, 8);
            $row->timestamps();

            $row->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $row->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
        });

        // 5. Update Transactions Table
        Schema::table('transactions', function (Blueprint $row) {
            $row->string('return_reason')->nullable()->after('reverses_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $row) {
            $row->dropColumn('return_reason');
        });

        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
