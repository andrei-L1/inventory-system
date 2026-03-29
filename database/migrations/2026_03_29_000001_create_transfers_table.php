<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transfers pivot table.
 *
 * Solves the orphan-transaction problem in recordTransfer().
 * Both legs of a transfer are linked here so the ledger remains coherent:
 * given either the outgoing or incoming transaction, you can always find
 * its mirror leg without relying on a naming convention like "-OUT / -IN".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outgoing_transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();
            $table->foreignId('incoming_transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();
            $table->foreignId('from_location_id')
                ->constrained('locations');
            $table->foreignId('to_location_id')
                ->constrained('locations');
            $table->string('reference_number')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
