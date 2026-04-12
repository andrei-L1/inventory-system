<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Helpers\FinancialMath;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\UnitOfMeasure;
use App\Models\UomConversion;
use Illuminate\Contracts\Console\Kernel;

$po = PurchaseOrder::find(1);
if (! $po) {
    exit("PO #1 not found\n");
}
echo "PO #{$po->po_number} Status: ".$po->status->name."\n";

foreach ($po->lines as $line) {
    echo "Line ID: {$line->id}\n";
    echo "  Product: {$line->product->name} (SKU: {$line->product->sku})\n";
    echo "  Ordered: '{$line->ordered_qty}' (Scale normalized: ".bcadd((string) $line->ordered_qty, '0', 8).")\n";
    echo "  Received: '{$line->received_qty}' (Scale normalized: ".bcadd((string) $line->received_qty, '0', 8).")\n";
    echo "  Returned: '{$line->returned_qty}'\n";

    $cmp = FinancialMath::cmp($line->received_qty, $line->ordered_qty);
    echo "  FinancialMath::cmp(Rcv, Ord): {$cmp}\n";
    echo '  FinancialMath::gte(Rcv, Ord): '.(FinancialMath::gte($line->received_qty, $line->ordered_qty) ? 'TRUE' : 'FALSE')."\n";
}

$mjolnir = Product::where('sku', 'ELE-MJ0-0001')->first();
$box = UnitOfMeasure::where('abbreviation', 'bx')->first();
$piece = UnitOfMeasure::where('abbreviation', 'pcs')->first();

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
