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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('billing_status')->default('UNBILLED')->after('status_id')->index();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('billing_status')->default('UNBILLED')->after('status_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('billing_status');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('billing_status');
        });
    }
};
