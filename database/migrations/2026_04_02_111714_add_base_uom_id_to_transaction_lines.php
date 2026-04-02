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
            $table->foreignId('base_uom_id')->nullable()->after('uom_id')->constrained('units_of_measure');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropForeign(['base_uom_id']);
            $table->dropColumn('base_uom_id');
        });
    }
};
