<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\Inventory;
use App\Models\TransactionLine;
use App\Models\InventoryCostLayer;
use App\Helpers\FinancialMath;

$product = Product::where('sku', 'ELE-MJO-0001')->first();
if (!$product) {
    die("Mjolnir not found.\n");
}

echo "Repairing Average Cost and Cost Layers for Mjolnir (SKU: {$product->sku})...\n";

$locations = Inventory::where('product_id', $product->id)->get()->pluck('location_id');

foreach ($locations as $locId) {
    echo "  Location ID {$locId}:\n";
    
    // 1. Fix Transaction Lines
    $lines = TransactionLine::where('product_id', $product->id)
        ->where('location_id', $locId)
        ->get();
        
    foreach ($lines as $line) {
        if (FinancialMath::cmp((string)$line->unit_cost, '76800') === 0) {
            echo "    - Found bugged TransactionLine cost 76800. Fixed to 19200.\n";
            $line->unit_cost = 19200;
            $line->total_cost = FinancialMath::mul((string)$line->quantity, '19200');
            $line->save();
        }
    }

    // 2. Fix Cost Layers
    $layers = InventoryCostLayer::where('product_id', $product->id)
        ->where('location_id', $locId)
        ->get();

    foreach ($layers as $layer) {
        if (FinancialMath::cmp((string)$layer->unit_cost, '76800') === 0) {
            echo "    - Found bugged InventoryCostLayer cost 76800. Fixed to 19200.\n";
            $layer->unit_cost = 19200;
            $layer->save();
        }
    }
    
    // 3. Recalculate WAC for this location
    $rQty = '0';
    $rVal = '0';
    $receiptLines = TransactionLine::where('product_id', $product->id)
        ->where('location_id', $locId)
        ->where('quantity', '>', 0)
        ->get();

    foreach($receiptLines as $r) {
        $rQty = FinancialMath::add($rQty, (string)$r->quantity);
        $rVal = FinancialMath::add($rVal, FinancialMath::mul((string)$r->quantity, (string)$r->unit_cost));
    }
    
    if (FinancialMath::isPositive($rQty)) {
        $newWac = FinancialMath::round(FinancialMath::div($rVal, $rQty), 8);
        echo "    - New Correct WAC for Location: {$newWac}\n";
        
        Inventory::where('product_id', $product->id)
            ->where('location_id', $locId)
            ->update(['average_cost' => $newWac]);
    }
}

// 4. Final Global Sync
$stats = Inventory::where('product_id', $product->id)
    ->where('quantity_on_hand', '>', 0)
    ->selectRaw('SUM(quantity_on_hand * average_cost) as total_value, SUM(quantity_on_hand) as total_qty')
    ->first();

if ($stats && FinancialMath::isPositive((string) ($stats->total_qty ?? '0'))) {
    $newAvg = FinancialMath::round(
        FinancialMath::div((string) $stats->total_value, (string) $stats->total_qty),
        8
    );
    $product->update(['average_cost' => $newAvg]);
    echo "\nGlobal Product Average Cost updated to: {$newAvg}\n";
}

echo "Repair Complete.\n";
