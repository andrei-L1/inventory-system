<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get real-time stats for the dashboard.
     */
    public function getStats(): JsonResponse
    {
        $totalProducts = Product::count();
        $totalVendors = Vendor::count();
        $valuation = DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->selectRaw('SUM(inventories.quantity_on_hand * products.average_cost) as total_value')
            ->first();

        // Count products where collective QOH < reorder_point
        $lowStockCount = DB::table('products')
            ->leftJoin('inventories', 'products.id', '=', 'inventories.product_id')
            ->select('products.id', 'products.reorder_point')
            ->selectRaw('COALESCE(SUM(inventories.quantity_on_hand), 0) as total_qoh')
            ->groupBy('products.id', 'products.reorder_point')
            ->havingRaw('COALESCE(SUM(inventories.quantity_on_hand), 0) < products.reorder_point')
            ->get()
            ->count();

        // Count transactions posted today
        $transactionsToday = Transaction::whereDate('transaction_date', today())->count();

        // Activity Feed is actually a list of recent transaction lines
        $recentTransactions = DB::table('transaction_lines')
            ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_lines.product_id', '=', 'products.id')
            ->join('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
            ->select(
                'transactions.reference_number',
                'transactions.transaction_date',
                'products.name as product_name',
                'transaction_types.name as type_name',
                'transaction_lines.quantity'
            )
            ->latest('transactions.transaction_date')
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => [
                'total_products' => $totalProducts,
                'total_vendors' => $totalVendors,
                'inventory_value' => (float) ($valuation->total_value ?? 0),
                'low_stock_count' => $lowStockCount,
                'transactions_today' => $transactionsToday,
            ],
            'recent_transactions' => $recentTransactions,
            'system_status' => 'ONLINE',
        ]);
    }
}
