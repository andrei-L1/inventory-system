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
        Schema::table('inventory_cost_layers', function (Blueprint $table) {
            $table->decimal('issued_qty', 18, 4)->default(0)->after('received_qty')
                  ->comment('Total quantity consumed from this layer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_cost_layers', function (Blueprint $table) {
            $table->dropColumn('issued_qty');
        });
    }
};
