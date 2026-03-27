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
        Schema::create('uom_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_uom_id')->constrained('units_of_measure')->cascadeOnDelete();
            $table->foreignId('to_uom_id')->constrained('units_of_measure')->cascadeOnDelete();
            $table->decimal('conversion_factor', 18, 8)->comment('multiply from_uom quantity by this factor to get to_uom quantity');
            $table->timestamps();

            $table->unique(['from_uom_id', 'to_uom_id']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(
                'ALTER TABLE uom_conversions ADD CONSTRAINT chk_uom_conversion_factor_positive CHECK (conversion_factor > 0)'
            );
            DB::statement(
                'ALTER TABLE uom_conversions ADD CONSTRAINT chk_uom_conversion_from_to_different CHECK (from_uom_id <> to_uom_id)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uom_conversions');
    }
};
