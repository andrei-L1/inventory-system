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
        Schema::disableForeignKeyConstraints();

        Schema::table('uom_conversions', function (Blueprint $table) {
            // Add the column first
            $table->foreignId('product_id')->nullable()->after('id')->constrained('products')->cascadeOnDelete();
        });

        Schema::table('uom_conversions', function (Blueprint $table) {
            // Drop old index
            $table->dropUnique(['from_uom_id', 'to_uom_id']);
            // Add new index
            $table->unique(['from_uom_id', 'to_uom_id', 'product_id'], 'uom_conv_from_to_prod_unique');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uom_conversions', function (Blueprint $table) {
            $table->dropUnique('uom_conv_from_to_prod_unique');

            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');

            $table->unique(['from_uom_id', 'to_uom_id']);
        });
    }
};
