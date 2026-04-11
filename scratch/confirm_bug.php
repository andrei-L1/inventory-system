<?php
// Use workspace root path for vendor
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\UomConversion;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\Inventory;
use App\Helpers\FinancialMath;

$po = PurchaseOrder::find(1);
if (!$po) {
    die("PO #1 not found\n");
}
echo "PO #{$po->po_number} Status: " . $po->status->name . "\n";

foreach ($po->lines as $line) {
    echo "Line ID: {$line->id}\n";
    echo "  Product: {$line->product->name} (SKU: {$line->product->sku})\n";
    echo "  Ordered Raw: '{$line->ordered_qty}'\n";
    echo "  Received Raw: '{$line->received_qty}'\n";
    
    // Demonstrate the bug
    $a = (string)$line->received_qty;
    $b = (string)$line->ordered_qty;
    $cmp_buggy = bccomp(bcadd($a, '0', 8), bcadd($b, '0', 8), 0);
    $cmp_correct = bccomp(bcadd($a, '0', 8), bcadd($b, '0', 8), 8);
    
    echo "  bccomp(Rcv, Ord, 0): {$cmp_buggy} (THIS IS THE BUG)\n";
    echo "  bccomp(Rcv, Ord, 8): {$cmp_correct} (THIS IS CORRECT)\n";
    echo "  FinancialMath::gte(Rcv, Ord): " . (FinancialMath::gte($line->received_qty, $line->ordered_qty) ? "TRUE" : "FALSE") . "\n";
}
