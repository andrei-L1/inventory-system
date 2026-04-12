<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\SalesOrder;
use Illuminate\Contracts\Console\Kernel;

$so = SalesOrder::where('so_number', 'SO-20260412-0238-40')->with(['lines.product', 'invoices', 'status'])->first();

if (! $so) {
    echo "SO-20260412-0238-40 not found.\n";
    exit;
}

echo 'Found SO: '.$so->so_number.' (ID: '.$so->id.")\n";
echo 'Status: '.$so->status->name."\n";
echo 'Total Amount: '.$so->total_amount."\n";

foreach ($so->lines as $line) {
    echo "--- Line (#{$line->id}) ---\n";
    echo 'Product: '.$line->product->name."\n";
    echo 'Ordered: '.$line->ordered_qty."\n";
    echo 'Shipped: '.$line->shipped_qty."\n";
    echo 'Invoiced: '.$line->invoiced_qty." (Attribute)\n";
    echo 'Uninvoiced: '.$line->uninvoiced_qty." (Attribute)\n";
}

foreach ($so->invoices as $inv) {
    echo "--- Invoice (#{$inv->id}) ---\n";
    echo 'Number: '.$inv->invoice_number."\n";
    echo 'Type: '.$inv->type."\n";
    echo 'Status: '.$inv->status."\n";
    echo 'Total: '.$inv->total_amount."\n";
    echo 'Paid: '.$inv->paid_amount."\n";
}
