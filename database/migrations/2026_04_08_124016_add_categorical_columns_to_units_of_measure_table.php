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
        Schema::table('units_of_measure', function (Blueprint $table) {
            $table->string('category', 50)->default('count')->after('abbreviation');
            $table->boolean('is_base')->default(false)->after('category');
            $table->decimal('conversion_factor_to_base', 18, 8)->nullable()->after('is_base');
            $table->integer('decimals')->default(0)->after('conversion_factor_to_base');
        });

        // Seed categorical data for existing default units
        DB::table('units_of_measure')->where('abbreviation', 'pcs')->update([
            'category' => 'count',
            'is_base' => true,
            'conversion_factor_to_base' => 1.0,
            'decimals' => 0,
        ]);

        DB::table('units_of_measure')->where('abbreviation', 'bx')->update([
            'category' => 'count',
            'is_base' => false,
            'conversion_factor_to_base' => null, // Contextual (Product Specific packaging)
            'decimals' => 0,
        ]);

        DB::table('units_of_measure')->where('abbreviation', 'kg')->update([
            'category' => 'mass',
            'is_base' => false,
            'conversion_factor_to_base' => 1000.0,
            'decimals' => 3,
        ]);

        // The bulk of standard unit initialization is now safely handled by Database\Seeders\UomSeeder.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units_of_measure', function (Blueprint $table) {
            $table->dropColumn(['category', 'is_base', 'conversion_factor_to_base', 'decimals']);
        });

        DB::table('units_of_measure')->whereIn('abbreviation', ['g', 'ml'])->delete();
    }
};
