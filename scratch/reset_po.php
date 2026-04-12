<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatus;
use Illuminate\Contracts\Console\Kernel;

$po = PurchaseOrder::find(1);
if ($po) {
    // Reopen the PO so the user can continue their testing
    $po->status_id = PurchaseOrderStatus::where('name', 'partially_received')->first()->id;
    $po->save();
    echo "\nUpdated PO #1 to 'partially_received' status.\n";
}
