<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Audit Log ────────────────────────────────────────────────────────
        // Immutable append-only table. Never update or delete rows here.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Who
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_snapshot', 191)->nullable(); // email at time of action
            // What
            $table->string('event', 60);         // created, updated, deleted, login, logout, etc.
            $table->string('auditable_type', 191)->nullable(); // App\Models\Product
            $table->unsignedBigInteger('auditable_id')->nullable();
            // Changes
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            // Context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('http_method', 10)->nullable();
            $table->string('session_id', 191)->nullable();
            $table->json('tags')->nullable();     // grouping / filtering
            $table->timestamp('created_at');      // no updated_at — immutable

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });

        // ─── Activity/Access Logs (coarser, non-model events) ────────────────
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);           // LOGIN, LOGOUT, EXPORT_REPORT, etc.
            $table->string('description', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id', 191)->nullable();
            $table->json('metadata')->nullable();     // extra context (filters used, etc.)
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('audit_logs');
    }
};
