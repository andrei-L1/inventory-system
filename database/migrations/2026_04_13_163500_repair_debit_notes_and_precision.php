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
        Schema::table('debit_notes', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('debit_notes', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
            
            if (!Schema::hasColumn('debit_notes', 'reason')) {
                $table->text('reason')->nullable()->after('notes');
            }

            if (!Schema::hasColumn('debit_notes', 'ref_transaction_id')) {
                $table->foreignId('ref_transaction_id')->nullable()->after('purchase_order_id')->constrained('transactions')->nullOnDelete();
            }

            // Enforce precision 8
            $table->decimal('amount', 18, 8)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->dropForeign(['ref_transaction_id']);
            $table->dropColumn(['notes', 'reason', 'ref_transaction_id']);
            $table->decimal('amount', 15, 2)->change();
        });
    }
};
