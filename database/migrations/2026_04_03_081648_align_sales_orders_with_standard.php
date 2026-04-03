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
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('carrier')->nullable()->after('notes');
            $table->string('tracking_number', 100)->nullable()->after('carrier');
            $table->timestamp('sent_at')->nullable()->after('approved_at');
            $table->timestamp('confirmed_at')->nullable()->after('approved_at');
            $table->timestamp('shipped_at')->nullable()->after('sent_at');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['carrier', 'tracking_number', 'sent_at', 'confirmed_at', 'shipped_at', 'delivered_at']);
        });
    }
};
