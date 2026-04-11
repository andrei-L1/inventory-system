<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\TransactionLineResource;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Transaction;
use App\Models\TransactionLine;
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

        // Activity Feed: Using TransactionLine model & resource to ensure UOM formatting is applied.
        $recentTransactions = TransactionLineResource::collection(
            TransactionLine::with(['product.uom', 'transaction.type', 'location', 'uom'])
                ->latest()
                ->limit(5)
                ->get()
        );

        // Count pending POs (all except closed/cancelled)
        $pendingPoCount = PurchaseOrder::whereHas('status', function ($query) {
            $query->whereNotIn('name', ['closed', 'cancelled']);
        })->count();

        // Count pending SOs (all except closed/cancelled/shipped?) - user said "pending"
        // Most common: pending = not closed, not cancelled.
        $pendingSoCount = SalesOrder::whereHas('status', function ($query) {
            $query->whereNotIn('name', ['Closed', 'Cancelled']);
        })->count();

        // Calculate 7-day stock value trend
        $currentValue = (float) ($valuation->total_value ?? 0);
        $trend = [];

        // Get net changes per day for the last 7 days
        $netChanges = DB::table('transaction_lines')
            ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
            ->join('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
            ->selectRaw('DATE(transactions.transaction_date) as date')
            ->selectRaw('SUM(CASE WHEN transaction_types.is_debit = 1 THEN transaction_lines.quantity * transaction_lines.unit_cost ELSE -(transaction_lines.quantity * transaction_lines.unit_cost) END) as change_amount')
            ->where('transactions.transaction_date', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('date')
            ->get()
            ->pluck('change_amount', 'date');

        // Work backwards from today
        $tempValue = $currentValue;
        for ($i = 0; $i < 7; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            $trend[] = [
                'date' => $date,
                'value' => round($tempValue, 2),
            ];
            // Subtract the change that happened ON that day to get the value at the START of that day (which is the value at the END of the previous day)
            $changeOnThisDay = $netChanges->get($date, 0);
            $tempValue -= $changeOnThisDay;
        }

        // Reverse to chronological order (oldest to newest)
        $trend = array_reverse($trend);

        return response()->json([
            'stats' => [
                'total_products' => $totalProducts,
                'total_vendors' => $totalVendors,
                'inventory_value' => $currentValue,
                'low_stock_count' => $lowStockCount,
                'transactions_today' => $transactionsToday,
                'pending_po_count' => $pendingPoCount,
                'pending_so_count' => $pendingSoCount,
                'stock_value_trend' => $trend,
            ],
            'recent_transactions' => $recentTransactions,
            'system_status' => 'ONLINE',
        ]);
    }
}
