<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('sales_order_statuses')->insert([
            ['name' => 'partially_picked', 'is_editable' => false, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'partially_packed', 'is_editable' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('sales_order_statuses')
            ->whereIn('name', ['partially_picked', 'partially_packed'])
            ->delete();
    }
};
