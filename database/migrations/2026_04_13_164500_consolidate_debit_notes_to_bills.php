<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add audit columns to bills table
        Schema::table('bills', function (Blueprint $table) {
            if (! Schema::hasColumn('bills', 'ref_transaction_id')) {
                $table->foreignId('ref_transaction_id')->nullable()->after('purchase_order_id')->constrained('transactions')->nullOnDelete();
            }
            if (! Schema::hasColumn('bills', 'reason')) {
                $table->text('reason')->nullable()->after('notes');
            }
        });

        // 2. Migrate existing data from debit_notes to bills
        if (Schema::hasTable('debit_notes')) {
            $debitNotes = DB::table('debit_notes')->get();
            foreach ($debitNotes as $dn) {
                DB::table('bills')->insert([
                    'vendor_id' => $dn->vendor_id,
                    'purchase_order_id' => $dn->purchase_order_id,
                    'bill_number' => $dn->debit_note_number,
                    'type' => 'DEBIT_NOTE',
                    'bill_date' => $dn->created_at,
                    'due_date' => null,
                    'total_amount' => $dn->amount,
                    'paid_amount' => 0,
                    'status' => $dn->status,
                    'notes' => $dn->notes ?? 'Migrated from Debit Notes',
                    'reason' => $dn->reason ?? null,
                    'ref_transaction_id' => $dn->ref_transaction_id ?? null,
                    'created_at' => $dn->created_at,
                    'updated_at' => $dn->updated_at,
                ]);
            }

            // 3. Drop the now-redundant table
            Schema::dropIfExists('debit_notes');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not easily reversible due to data merge, but we can recreate the table structure
        Schema::create('debit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ref_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->decimal('amount', 18, 8);
            $table->string('debit_note_number')->unique();
            $table->enum('status', ['DRAFT', 'POSTED', 'APPLIED', 'VOID'])->default('DRAFT');
            $table->text('notes')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropForeign(['ref_transaction_id']);
            $table->dropColumn(['ref_transaction_id', 'reason']);
        });
    }
};
