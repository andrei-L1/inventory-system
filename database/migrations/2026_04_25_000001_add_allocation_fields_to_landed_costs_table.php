<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landed_costs', function (Blueprint $table) {
            // Allocation method: 'by_value' (proportional to line cost) or 'by_quantity' (per unit, uniform)
            $table->string('allocation_method', 20)->nullable()->after('notes');
            // Timestamp + user recorded when allocation is applied to cost layers
            $table->timestamp('allocated_at')->nullable()->after('allocation_method');
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete()->after('allocated_at');
        });
    }

    public function down(): void
    {
        Schema::table('landed_costs', function (Blueprint $table) {
            $table->dropForeign(['allocated_by']);
            $table->dropColumn(['allocation_method', 'allocated_at', 'allocated_by']);
        });
    }
};
