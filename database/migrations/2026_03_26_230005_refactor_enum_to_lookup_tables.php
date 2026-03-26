<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replaces ENUM columns with proper FK references to lookup tables.
     * Each structural operation is isolated in its own Schema::table() call
     * to avoid doctrine/dbal conflicts when combining dropColumn, change(), and foreign().
     */
    public function up(): void
    {
        // ══════════════════════════════════════════════════════════════════════
        // 1. Refactor locations.type → location_type_id (FK to location_types)
        // ══════════════════════════════════════════════════════════════════════

        // A: Add new FK column as nullable
        Schema::table('locations', function (Blueprint $table) {
            $table->unsignedBigInteger('location_type_id')->nullable()->after('name');
        });

        // B: Back-fill from existing ENUM values (no-op on fresh install)
        foreach (DB::table('location_types')->get() as $type) {
            DB::table('locations')
                ->where('type', $type->name)
                ->update(['location_type_id' => $type->id]);
        }

        // C: Drop the old ENUM column (separate call — avoids doctrine conflict)
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        // D: Make FK NOT NULL and add constraint (separate call)
        Schema::table('locations', function (Blueprint $table) {
            $table->unsignedBigInteger('location_type_id')->nullable(false)->change();
            $table->foreign('location_type_id')
                ->references('id')
                ->on('location_types')
                ->restrictOnDelete();
        });

        // ══════════════════════════════════════════════════════════════════════
        // 2. Refactor transactions.type/status → FK lookup columns
        // ══════════════════════════════════════════════════════════════════════

        // A: Add new FK columns as nullable
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_type_id')->nullable()->after('reference_number');
            $table->unsignedBigInteger('transaction_status_id')->nullable()->after('transaction_type_id');
        });

        // B: Back-fill type from ENUM data (no-op on fresh install)
        foreach (DB::table('transaction_types')->get() as $type) {
            DB::table('transactions')
                ->where('type', $type->name)
                ->update(['transaction_type_id' => $type->id]);
        }

        // B2: Back-fill status from ENUM data
        foreach (DB::table('transaction_statuses')->get() as $status) {
            DB::table('transactions')
                ->where('status', $status->name)
                ->update(['transaction_status_id' => $status->id]);
        }

        // C: Drop old ENUM columns (separate call)
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['type', 'status']);
        });

        // D: Make FK columns NOT NULL, add constraints + restore the composite
        //    index that was on (type, status) — now on the FK id columns.
        //    This was silently dropped when the ENUM columns were deleted above.
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_type_id')->nullable(false)->change();
            $table->unsignedBigInteger('transaction_status_id')->nullable(false)->change();
            $table->foreign('transaction_type_id')
                ->references('id')
                ->on('transaction_types');
            $table->foreign('transaction_status_id')
                ->references('id')
                ->on('transaction_statuses');
            // Restore the composite filtering index that was lost when (type, status) columns were dropped
            $table->index(
                ['transaction_type_id', 'transaction_status_id', 'transaction_date'],
                'transactions_type_status_date_idx'
            );
        });
    }

    /**
     * Fully reverses the refactor — repopulates ENUM columns from FK data
     * before dropping the FK columns. Both structural halves are properly reversed.
     */
    public function down(): void
    {
        // ══════════════════════════════════════════════════════════════════════
        // Reverse transactions refactor
        // ══════════════════════════════════════════════════════════════════════

        // A: Add back ENUM columns (nullable so existing rows don't violate NOT NULL)
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('type', ['receipt', 'issue', 'transfer', 'adjustment', 'opening_balance'])
                ->nullable()->after('reference_number');
            $table->enum('status', ['draft', 'pending', 'posted', 'cancelled'])
                ->nullable()->after('type');
        });

        // B: Repopulate ENUM values from FK data
        foreach (DB::table('transaction_types')->get() as $type) {
            DB::table('transactions')
                ->where('transaction_type_id', $type->id)
                ->update(['type' => $type->name]);
        }
        foreach (DB::table('transaction_statuses')->get() as $status) {
            DB::table('transactions')
                ->where('transaction_status_id', $status->id)
                ->update(['status' => $status->name]);
        }

        // C: Drop composite index and FK constraints
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_type_status_date_idx');
            $table->dropForeign(['transaction_type_id']);
            $table->dropForeign(['transaction_status_id']);
        });

        // D: Drop FK columns
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['transaction_type_id', 'transaction_status_id']);
        });

        // E: Make ENUM columns NOT NULL
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('type', ['receipt', 'issue', 'transfer', 'adjustment', 'opening_balance'])
                ->nullable(false)->change();
            $table->enum('status', ['draft', 'pending', 'posted', 'cancelled'])
                ->default('draft')->nullable(false)->change();
        });

        // ══════════════════════════════════════════════════════════════════════
        // Reverse locations refactor
        // ══════════════════════════════════════════════════════════════════════

        // A: Add back ENUM column as nullable
        Schema::table('locations', function (Blueprint $table) {
            $table->enum('type', ['warehouse', 'zone', 'aisle', 'bin'])
                ->nullable()->after('name');
        });

        // B: Repopulate ENUM from FK data
        foreach (DB::table('location_types')->get() as $type) {
            DB::table('locations')
                ->where('location_type_id', $type->id)
                ->update(['type' => $type->name]);
        }

        // C: Drop FK constraint
        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign(['location_type_id']);
        });

        // D: Drop FK column
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('location_type_id');
        });

        // E: Make ENUM NOT NULL
        Schema::table('locations', function (Blueprint $table) {
            $table->enum('type', ['warehouse', 'zone', 'aisle', 'bin'])
                ->default('warehouse')->nullable(false)->change();
        });
    }
};
