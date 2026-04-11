<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
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
    echo "  Ordered: '{$line->ordered_qty}' (Scale normalized: " . bcadd((string)$line->ordered_qty, '0', 8) . ")\n";
    echo "  Received: '{$line->received_qty}' (Scale normalized: " . bcadd((string)$line->received_qty, '0', 8) . ")\n";
    echo "  Returned: '{$line->returned_qty}'\n";
    
    $cmp = FinancialMath::cmp($line->received_qty, $line->ordered_qty);
    echo "  FinancialMath::cmp(Rcv, Ord): {$cmp}\n";
    echo "  FinancialMath::gte(Rcv, Ord): " . (FinancialMath::gte($line->received_qty, $line->ordered_qty) ? "TRUE" : "FALSE") . "\n";
}

$mjolnir = Product::where('sku', 'ELE-MJ0-0001')->first();
$box = \App\Models\UnitOfMeasure::where('abbreviation', 'bx')->first();
$piece = \App\Models\UnitOfMeasure::where('abbreviation', 'pcs')->first();

if ($mjolnir && $box && $piece) {
    $conv = UomConversion::where('product_id', $mjolnir->id)
        ->where('from_uom_id', $box->id)
        ->where('to_uom_id', $piece->id)
        ->first();
    if ($conv) {
        echo "\nConversion Box -> Piece for Mjolnir: {$conv->conversion_factor}\n";
    } else {
        echo "\nNo specific Box -> Piece conversion for Mjolnir. Checking global...\n";
        $conv = UomConversion::whereNull('product_id')
            ->where('from_uom_id', $box->id)
            ->where('to_uom_id', $piece->id)
            ->first();
        if ($conv) {
             echo "Global Box -> Piece conversion: {$conv->conversion_factor}\n";
        }
    }
}
