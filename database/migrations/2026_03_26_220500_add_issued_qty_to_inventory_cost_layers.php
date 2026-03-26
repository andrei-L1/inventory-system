<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NOTE: issued_qty has been consolidated into 000003_create_inventories_table.
     * This migration is kept to preserve migration history. It is a safe no-op
     * for fresh installs (where the column already exists from migration 000003),
     * and a real migration for any database created before the consolidation.
     */
    public function up(): void
    {
        // Guard: only run if the column does not already exist.
        // On fresh installs, issued_qty is created by migration 000003.
        if (! Schema::hasColumn('inventory_cost_layers', 'issued_qty')) {
            Schema::table('inventory_cost_layers', function (Blueprint $table) {
                $table->decimal('issued_qty', 18, 4)->default(0)->after('received_qty')
                    ->comment('Total quantity consumed from this layer');
            });
        }
    }

    public function down(): void
    {
        // Intentional no-op: issued_qty underpins the generated remaining_qty column.
        // To fully reverse, roll back migration 000003 instead.
    }
};
