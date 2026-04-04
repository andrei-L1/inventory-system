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
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('product_id')->constrained('locations')->nullOnDelete();
            $table->decimal('picked_qty', 18, 4)->default(0)->after('shipped_qty');
            $table->decimal('packed_qty', 18, 4)->default(0)->after('picked_qty');
            $table->decimal('returned_qty', 18, 4)->default(0)->after('packed_qty');
            $table->decimal('subtotal', 18, 4)->default(0)->after('returned_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['picked_qty', 'packed_qty', 'returned_qty', 'subtotal']);
        });
    }
};
