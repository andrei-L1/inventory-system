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
            $table->timestamp('sent_at')->after('approved_at')->nullable();
            $table->timestamp('shipped_at')->after('sent_at')->nullable();
            $table->string('carrier')->after('shipped_at')->nullable();
            $table->string('tracking_number')->after('carrier')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['sent_at', 'shipped_at', 'carrier', 'tracking_number']);
        });
    }
};
