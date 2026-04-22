<?php

use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fixes the data corruption where 0/0 orders were marked as BILLED.
     */
    public function up(): void
    {
        echo "Repairing Purchase Order Billing Statuses...\n";
        PurchaseOrder::chunk(100, function ($pos) {
            foreach ($pos as $po) {
                // calls the hardened logic in the model
                $po->syncBillingStatus();
            }
        });

        echo "Repairing Sales Order Invoicing Statuses...\n";
        SalesOrder::chunk(100, function ($sos) {
            foreach ($sos as $so) {
                // calls the hardened logic in the model
                $so->syncBillingStatus();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data fix is permanent/state-correcting; no rollback needed.
    }
};
