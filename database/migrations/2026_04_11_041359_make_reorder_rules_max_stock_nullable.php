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
        Schema::table('reorder_rules', function (Blueprint $table) {
            $table->decimal('max_stock', 18, 8)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reorder_rules', function (Blueprint $table) {
            $table->decimal('max_stock', 18, 8)->nullable(false)->change();
        });
    }
};
