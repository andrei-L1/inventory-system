<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * THE PURGE: Removing the physical 'billed_qty' column from purchase_order_lines.
     * This forces the system to use the new Dynamic Accessor, ensuring 100% 
     * architectural parity with the Sales module and preventing 'stuck' quantities.
     */
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_lines', 'billed_qty')) {
                $table->dropColumn('billed_qty');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('billed_qty', 18, 8)->default(0)->after('received_qty');
        });
    }
};
