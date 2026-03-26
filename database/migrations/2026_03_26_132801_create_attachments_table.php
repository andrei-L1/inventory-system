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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            // morphs() automatically creates a composite index on (attachable_id, attachable_type)
            $table->morphs('attachable');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->bigInteger('file_size')->default(0);
            $table->string('collection_name')->default('general');
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            // NOTE: do NOT add ->index(['attachable_id','attachable_type']) — morphs() already does it
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
