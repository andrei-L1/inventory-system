<?php

use App\Models\PurchaseOrder;
use App\Models\Transaction;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Thorough regex search for any 8+ digit sequences (typical for our date-based PO numbers)
$transactions = Transaction::whereNull('purchase_order_id')
    ->where(function ($q) {
        $q->where('reference_doc', 'LIKE', '%PO%')
            ->orWhere('reference_number', 'LIKE', '%PO%')
            ->orWhere('notes', 'LIKE', '%PO%');
    })
    ->get();

foreach ($transactions as $t) {
    echo "Processing Transaction #{$t->id}: {$t->reference_doc} | {$t->reference_number}\n";

    // Check reference_doc, reference_number and notes for PO numbers
    $searchStrings = [$t->reference_doc, $t->reference_number, $t->notes];

    foreach ($searchStrings as $str) {
        if (! $str) {
            continue;
        }

        // Find things that look like PO-YYYYMMDD-XXXX
        if (preg_match('/PO-\d{8}-\d{4}/', $str, $matches)) {
            $poNum = $matches[0];
            echo "Searching for PO: {$poNum}\n";
            $po = PurchaseOrder::where('po_number', 'LIKE', "%{$poNum}%")->first();
            if ($po) {
                break;
            }
        }

        // Just find the digits
        if (preg_match('/\d{8}/', $str, $matches)) {
            $po = PurchaseOrder::where('po_number', 'LIKE', "%{$matches[0]}%")->first();
            if ($po) {
                break;
            }
        }
    }

    if ($po) {
        $t->purchase_order_id = $po->id;
        $t->save();
        echo "✅ Linked Transaction #{$t->id} to PO: {$po->po_number}\n";
    } else {
        echo "❌ No matching PO found.\n";
    }
}
