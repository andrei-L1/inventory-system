<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\TransactionResource;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Vendor;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Get transaction history for a specific product.
     */
    public function forProduct(Product $product)
    {
        $transactions = Transaction::whereHas('lines', function ($q) use ($product) {
            $q->where('product_id', $product->id);
        })
        ->with(['type', 'status', 'fromLocation', 'toLocation', 'vendor'])
        ->orderBy('transaction_date', 'desc')
        ->orderBy('id', 'desc')
        ->get();

        return TransactionResource::collection($transactions);
    }

    /**
     * Get transaction history for a specific vendor.
     */
    public function forVendor(Vendor $vendor)
    {
        $transactions = Transaction::where('vendor_id', $vendor->id)
            ->with(['type', 'status', 'fromLocation', 'toLocation'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return TransactionResource::collection($transactions);
    }
}
