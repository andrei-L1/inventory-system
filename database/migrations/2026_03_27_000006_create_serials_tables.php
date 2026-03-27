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
        Schema::create('product_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('serial_number', 100);
            $table->string('status', 30)->default('in_stock'); // in_stock, sold, returned, damaged
            $table->foreignId('current_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'serial_number']);
            $table->index('status');
        });

        Schema::create('transaction_line_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_line_id')->constrained('transaction_lines')->cascadeOnDelete();
            $table->foreignId('product_serial_id')->constrained('product_serials')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['transaction_line_id', 'product_serial_id'], 'txn_line_serial_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_line_serials');
        Schema::dropIfExists('product_serials');
    }
};
