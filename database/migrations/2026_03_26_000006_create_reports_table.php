<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Reports Metadata (saved/scheduled report definitions) ───────────
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->enum('type', [
                'stock_on_hand',
                'stock_movement',
                'transaction_history',
                'valuation',        // FIFO / LIFO / Average stock value
                'transfer_history',
                'low_stock',
                'audit_trail',
                'user_activity',
            ]);
            $table->json('filters')->nullable();   // date range, location, product, etc.
            $table->string('format', 10)->default('pdf'); // pdf, xlsx, csv
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_scheduled')->default(false);
            $table->string('schedule_cron', 60)->nullable();
            $table->timestamps();

            $table->index(['type', 'created_by']);
        });

        // ─── Report Runs (historical outputs) ────────────────────────────────
        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('file_path', 255)->nullable();  // path to generated file
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['report_id', 'status']);
        });

        // ─── Stock Snapshots (daily/periodic stock value summary for reports) ─
        Schema::create('stock_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost_avg', 18, 6)->default(0);
            $table->decimal('unit_cost_fifo', 18, 6)->default(0);
            $table->decimal('unit_cost_lifo', 18, 6)->default(0);
            $table->decimal('total_value_avg', 18, 6)->default(0);
            $table->decimal('total_value_fifo', 18, 6)->default(0);
            $table->decimal('total_value_lifo', 18, 6)->default(0);
            $table->timestamps();

            $table->unique(['snapshot_date', 'product_id', 'location_id']);
            $table->index('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_snapshots');
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('reports');
    }
};
