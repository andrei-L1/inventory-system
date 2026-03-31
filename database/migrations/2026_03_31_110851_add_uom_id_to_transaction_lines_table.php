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
            $table->foreignId('uom_id')->nullable()->after('product_id')->constrained('units_of_measure')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropForeign(['uom_id']);
            $table->dropColumn('uom_id');
        });
    }
};
