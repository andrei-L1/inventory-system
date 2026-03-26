<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the ENUM costing_method columns on products and transaction_lines
     * with a proper FK to a costing_methods lookup table.
     *
     * Before: products.costing_method ENUM('fifo','lifo','average')
     *         transaction_lines.costing_method ENUM('fifo','lifo','average') NULLABLE
     *
     * After:  products.costing_method_id FK → costing_methods (NOT NULL)
     *         transaction_lines.costing_method_id FK → costing_methods (NULLABLE)
     */
    public function up(): void
    {
        // ── 1. Create costing_methods lookup table ─────────────────────────────
        Schema::create('costing_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();        // fifo, lifo, average
            $table->string('label', 60);                 // FIFO, LIFO, Weighted Average
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed costing methods (small reference data — appropriate here)
        DB::table('costing_methods')->insert([
            ['name' => 'fifo',    'label' => 'First In, First Out (FIFO)',   'description' => 'Oldest cost layers consumed first',              'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'lifo',    'label' => 'Last In, First Out (LIFO)',    'description' => 'Newest cost layers consumed first',              'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'average', 'label' => 'Weighted Average Cost',        'description' => 'Running weighted average across all layers',     'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── 2. Migrate products.costing_method → costing_method_id ────────────

        // A: Add nullable FK column next to old ENUM
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('costing_method_id')->nullable()->after('costing_method');
        });

        // B: Back-fill from ENUM values
        foreach (DB::table('costing_methods')->get() as $method) {
            DB::table('products')
                ->where('costing_method', $method->name)
                ->update(['costing_method_id' => $method->id]);
        }

        // Safety: default any unset rows to 'average'
        $avgId = DB::table('costing_methods')->where('name', 'average')->value('id');
        DB::table('products')->whereNull('costing_method_id')->update(['costing_method_id' => $avgId]);

        // C: Drop old ENUM column (also drops the index('costing_method') from 000002)
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('costing_method');
        });

        // D: Make FK NOT NULL and add constraint
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('costing_method_id')->nullable(false)->change();
            $table->foreign('costing_method_id')
                ->references('id')
                ->on('costing_methods')
                ->restrictOnDelete();
            $table->index('costing_method_id'); // restore index for catalogue filtering
        });

        // ── 3. Migrate transaction_lines.costing_method → costing_method_id ──

        // A: Add nullable FK column (snapshot of method used — stays nullable)
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('costing_method_id')->nullable()->after('costing_method');
        });

        // B: Back-fill from ENUM values
        foreach (DB::table('costing_methods')->get() as $method) {
            DB::table('transaction_lines')
                ->where('costing_method', $method->name)
                ->update(['costing_method_id' => $method->id]);
        }

        // C: Drop old ENUM column
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropColumn('costing_method');
        });

        // D: Add FK constraint (nullable — nullOnDelete keeps historical lines intact if a method is removed)
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->foreign('costing_method_id')
                ->references('id')
                ->on('costing_methods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // ── Restore transaction_lines ──────────────────────────────────────────

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->enum('costing_method', ['fifo', 'lifo', 'average'])->nullable()->after('costing_method_id');
        });

        foreach (DB::table('costing_methods')->get() as $method) {
            DB::table('transaction_lines')
                ->where('costing_method_id', $method->id)
                ->update(['costing_method' => $method->name]);
        }

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropForeign(['costing_method_id']);
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropColumn('costing_method_id');
        });

        // ── Restore products ───────────────────────────────────────────────────

        Schema::table('products', function (Blueprint $table) {
            $table->enum('costing_method', ['fifo', 'lifo', 'average'])
                ->default('average')->nullable()->after('costing_method_id');
        });

        foreach (DB::table('costing_methods')->get() as $method) {
            DB::table('products')
                ->where('costing_method_id', $method->id)
                ->update(['costing_method' => $method->name]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['costing_method_id']);
            $table->dropForeign(['costing_method_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('costing_method_id');
            $table->enum('costing_method', ['fifo', 'lifo', 'average'])
                ->default('average')->nullable(false)->change();
        });

        Schema::dropIfExists('costing_methods');
    }
};
