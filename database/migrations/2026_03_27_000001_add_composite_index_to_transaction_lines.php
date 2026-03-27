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
        Schema::table('transaction_lines', function (Blueprint $table) {
            // Index for faster historical product movement reports
            $table->index(['product_id', 'created_at'], 'txn_lines_product_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropIndex('txn_lines_product_date_idx');
        });
    }
};
