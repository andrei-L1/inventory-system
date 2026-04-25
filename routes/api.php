<?php

use App\Http\Controllers\Api\Finance\BillController;
use App\Http\Controllers\Api\Finance\CustomerStatementController;
use App\Http\Controllers\Api\Finance\InvoiceController;
use App\Http\Controllers\Api\Finance\PaymentController;
use App\Http\Controllers\Api\Finance\VendorPaymentController;
use App\Http\Controllers\Api\Finance\VendorStatementController;
use App\Http\Controllers\Api\Inventory\AdjustmentController;
use App\Http\Controllers\Api\Inventory\AdjustmentReasonController;
use App\Http\Controllers\Api\Inventory\CategoryController;
use App\Http\Controllers\Api\Inventory\CostingMethodController;
use App\Http\Controllers\Api\Inventory\CustomerController;
use App\Http\Controllers\Api\Inventory\DashboardController;
use App\Http\Controllers\Api\Inventory\InventoryQueryController;
use App\Http\Controllers\Api\Inventory\LocationController;
use App\Http\Controllers\Api\Inventory\LocationTypeController;
use App\Http\Controllers\Api\Inventory\ProductController;
use App\Http\Controllers\Api\Inventory\ReorderRuleController;
use App\Http\Controllers\Api\Inventory\TransactionController;
use App\Http\Controllers\Api\Inventory\UnitOfMeasureController;
use App\Http\Controllers\Api\Inventory\UomConversionController;
use App\Http\Controllers\Api\Inventory\VendorController;
use App\Http\Controllers\Api\Logistics\CarrierController;
use App\Http\Controllers\Api\Logistics\ProductSerialController;
use App\Http\Controllers\Api\Logistics\ShipmentController;
use App\Http\Controllers\Api\Procurement\LandedCostController;
use App\Http\Controllers\Api\Procurement\PurchaseOrderController;
use App\Http\Controllers\Api\Sales\DiscountController;
use App\Http\Controllers\Api\Sales\PriceListController;
use App\Http\Controllers\Api\Sales\SalesOrderController;
use App\Http\Controllers\Api\Sales\SalesOrderReturnController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/**
 * Master Data API
 */
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    // Categories
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show'])->middleware('permission:view-products');
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show'])->middleware('permission:manage-products');

    // Products
    Route::apiResource('products', ProductController::class)->only(['index', 'show'])->middleware('permission:view-products');
    Route::apiResource('products', ProductController::class)->except(['index', 'show'])->middleware('permission:manage-products');

    // Vendors
    Route::apiResource('vendors', VendorController::class)->only(['index', 'show'])->middleware('permission:view-products');
    Route::apiResource('vendors', VendorController::class)->except(['index', 'show'])->middleware('permission:manage-products');

    // Customers
    Route::apiResource('customers', CustomerController::class)->only(['index', 'show'])->middleware('permission:view-customers');
    Route::apiResource('customers', CustomerController::class)->except(['index', 'show'])->middleware('permission:manage-customers');
    Route::get('customers/{customer}/transactions', [CustomerController::class, 'transactions'])->middleware('permission:view-customers');

    // Unit of Measure & Conversions
    Route::apiResource('uom', UnitOfMeasureController::class)->only(['index', 'show'])->middleware('permission:view-products');
    Route::apiResource('uom', UnitOfMeasureController::class)->except(['index', 'show'])->middleware('permission:manage-products');
    Route::apiResource('uom-conversions', UomConversionController::class)->only(['index', 'show'])->middleware('permission:view-products');
    Route::apiResource('uom-conversions', UomConversionController::class)->except(['index', 'show'])->middleware('permission:manage-products');

    // Costing Methods (Read Only)
    Route::apiResource('costing-methods', CostingMethodController::class)->only(['index', 'show'])->middleware('permission:view-products');

    // -----------------------------------------------------------------------
    // Stock Movement API (Phase 2.1)
    // -----------------------------------------------------------------------

    // Create any movement (receipt, issue, adjustment) — draft or posted.
    Route::post('transactions', [TransactionController::class, 'store'])
        ->middleware('permission:manage-inventory');

    // Transition a draft transaction to posted (inventory is updated at this point).
    Route::patch('transactions/{transaction}/post', [TransactionController::class, 'post'])
        ->middleware('permission:manage-inventory');

    // Cancel draft, or reverse posted (creates counter-entry and links via reverses_transaction_id).
    Route::patch('transactions/{transaction}/cancel', [TransactionController::class, 'cancel'])
        ->middleware('permission:manage-inventory');

    // Get single transaction detail
    Route::get('transactions/{transaction}', [TransactionController::class, 'show'])
        ->middleware('permission:view-transactions');

    // Atomic two-leg internal stock transfer.
    Route::post('transfers', [TransactionController::class, 'storeTransfer'])
        ->middleware('permission:manage-inventory');

    // Transaction history reads.
    Route::get('products/{product}/transactions', [TransactionController::class, 'forProduct'])
        ->middleware('permission:view-transactions');
    Route::get('vendors/{vendor}/transactions', [TransactionController::class, 'forVendor'])
        ->middleware('permission:view-transactions');

    // Phase 2.2: Inventory Queries
    Route::get('inventory', [InventoryQueryController::class, 'index'])
        ->middleware('permission:view-inventory');
    Route::get('inventory/low-stock', [InventoryQueryController::class, 'getLowStock'])
        ->middleware('permission:view-inventory');
    Route::get('inventory/{product}/locations', [InventoryQueryController::class, 'getLocations'])
        ->middleware('permission:view-inventory');
    Route::get('inventory/{product}/cost-layers', [InventoryQueryController::class, 'getCostLayers'])
        ->middleware('permission:view-inventory');
    Route::get('inventory/stock-check', [InventoryQueryController::class, 'getStockCheck'])
        ->middleware('permission:view-inventory');

    // Dashboard Stats
    Route::get('dashboard/stats', [DashboardController::class, 'getStats'])->middleware('permission:view-products');

    // Locations API
    Route::apiResource('locations', LocationController::class)->only(['index', 'show'])->middleware('permission:view-inventory');
    Route::apiResource('locations', LocationController::class)->except(['index', 'show'])->middleware('permission:manage-inventory');
    Route::get('location-types', [LocationTypeController::class, 'index'])->middleware('permission:view-inventory');

    // Adjustment Reasons & Dedicated Adjustments (Phase 2.1)
    Route::get('adjustment-reasons', [AdjustmentReasonController::class, 'index'])->middleware('permission:view-inventory');
    Route::post('adjustments', [AdjustmentController::class, 'store'])->middleware('permission:manage-inventory');

    // -----------------------------------------------------------------------
    // Procurement API (Phase 4.1)
    // -----------------------------------------------------------------------
    Route::apiResource('purchase-orders', PurchaseOrderController::class)->only(['index', 'show'])->middleware('permission:view-purchase-orders');
    Route::apiResource('purchase-orders', PurchaseOrderController::class)->except(['index', 'show'])->middleware('permission:manage-purchase-orders');
    Route::patch('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->middleware('permission:manage-purchase-orders');
    Route::patch('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:manage-purchase-orders');
    Route::patch('purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->middleware('permission:manage-purchase-orders');
    Route::post('purchase-orders/{purchaseOrder}/ship', [PurchaseOrderController::class, 'markAsShipped'])->middleware('permission:manage-purchase-orders');
    Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->middleware('permission:manage-purchase-orders');
    Route::post('purchase-orders/{purchaseOrder}/return', [PurchaseOrderController::class, 'processReturn'])->middleware('permission:manage-purchase-orders');
    Route::patch('purchase-orders/{purchaseOrder}/close', [PurchaseOrderController::class, 'close'])->middleware('permission:manage-purchase-orders');
    Route::patch('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:manage-purchase-orders');

    // Landed Costs (Phase 6.4) — nested under purchase orders
    Route::get('purchase-orders/{purchaseOrder}/landed-costs', [LandedCostController::class, 'index'])->middleware('permission:view-purchase-orders');
    Route::post('purchase-orders/{purchaseOrder}/landed-costs', [LandedCostController::class, 'store'])->middleware('permission:manage-purchase-orders');
    Route::delete('purchase-orders/{purchaseOrder}/landed-costs/{landedCost}', [LandedCostController::class, 'destroy'])->middleware('permission:manage-purchase-orders');
    Route::post('purchase-orders/{purchaseOrder}/landed-costs/{landedCost}/allocate', [LandedCostController::class, 'allocate'])->middleware('permission:manage-purchase-orders');

    // -----------------------------------------------------------------------
    // Sales API (Phase 5)
    // -----------------------------------------------------------------------
    Route::apiResource('sales-orders', SalesOrderController::class)->only(['index', 'show'])->middleware('permission:view-sales-orders');
    Route::apiResource('sales-orders', SalesOrderController::class)->except(['index', 'show'])->middleware('permission:manage-sales-orders');
    Route::patch('sales-orders/{salesOrder}/approve', [SalesOrderController::class, 'approve'])->middleware('permission:manage-sales-orders');
    Route::patch('sales-orders/{salesOrder}/send', [SalesOrderController::class, 'send'])->middleware('permission:manage-sales-orders');
    Route::patch('sales-orders/{salesOrder}/pick', [SalesOrderController::class, 'pick'])->middleware('permission:manage-sales-orders');
    Route::patch('sales-orders/{salesOrder}/pack', [SalesOrderController::class, 'pack'])->middleware('permission:manage-sales-orders');
    Route::post('sales-orders/{salesOrder}/ship', [SalesOrderController::class, 'ship'])->middleware('permission:manage-sales-orders');
    Route::post('sales-orders/{salesOrder}/return', [SalesOrderReturnController::class, 'store'])->middleware('permission:manage-sales-orders');
    Route::patch('sales-orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])->middleware('permission:manage-sales-orders');

    // -----------------------------------------------------------------------
    // Price Lists & Discounts (Phase 7.2)
    // -----------------------------------------------------------------------
    Route::apiResource('price-lists', PriceListController::class)->only(['index', 'show'])->middleware('permission:view-sales-orders');
    Route::apiResource('price-lists', PriceListController::class)->except(['index', 'show'])->middleware('permission:manage-sales-orders');
    Route::post('price-lists/{priceList}/items', [PriceListController::class, 'upsertItem'])->middleware('permission:manage-sales-orders');
    Route::delete('price-lists/{priceList}/items/{priceListItem}', [PriceListController::class, 'destroyItem'])->middleware('permission:manage-sales-orders');
    Route::get('price-lists/{priceList}/resolve', [PriceListController::class, 'resolvePrice'])->middleware('permission:view-sales-orders');

    Route::apiResource('discounts', DiscountController::class)->only(['index'])->middleware('permission:view-sales-orders');
    Route::apiResource('discounts', DiscountController::class)->except(['index', 'show'])->middleware('permission:manage-sales-orders');
    Route::get('discounts/resolve', [DiscountController::class, 'resolve'])->middleware('permission:view-sales-orders');

    // -----------------------------------------------------------------------
    // Finance API (Phase 5.5)
    // -----------------------------------------------------------------------
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show', 'destroy'])->middleware('permission:view-sales-orders');
    Route::post('sales-orders/{salesOrder}/invoice', [InvoiceController::class, 'storeFromSalesOrder'])->middleware('permission:manage-sales-orders');
    Route::patch('invoices/{invoice}/post', [InvoiceController::class, 'post'])->middleware('permission:manage-sales-orders');
    Route::patch('invoices/{invoice}/void', [InvoiceController::class, 'void'])->middleware('permission:manage-sales-orders');
    Route::get('customers/{customer}/statement', [CustomerStatementController::class, 'show'])->middleware('permission:view-sales-orders');

    Route::apiResource('payments', PaymentController::class)->only(['index', 'show', 'store', 'destroy'])->middleware('permission:view-sales-orders');
    Route::post('payments/{payment}/allocate', [PaymentController::class, 'allocate'])->middleware('permission:manage-sales-orders');
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->middleware('permission:manage-sales-orders');
    Route::delete('payments/{payment}/unallocate/{allocation}', [PaymentController::class, 'unallocate'])->middleware('permission:manage-sales-orders');
    Route::patch('payments/{payment}/void', [PaymentController::class, 'void'])->middleware('permission:manage-sales-orders');

    // -----------------------------------------------------------------------
    // Accounts Payable (A/P) - Phase 5.7
    // -----------------------------------------------------------------------
    Route::apiResource('bills', BillController::class)->only(['index', 'show', 'destroy', 'store'])->middleware('permission:view-purchase-orders');
    Route::post('purchase-orders/{purchaseOrder}/bill', [BillController::class, 'storeFromPurchaseOrder'])->middleware('permission:manage-purchase-orders');
    Route::patch('bills/{bill}/post', [BillController::class, 'post'])->middleware('permission:manage-purchase-orders');
    Route::patch('bills/{bill}/void', [BillController::class, 'void'])->middleware('permission:manage-purchase-orders');

    Route::apiResource('vendor-payments', VendorPaymentController::class)->only(['index', 'show', 'store', 'destroy'])->parameters(['vendor-payments' => 'payment'])->middleware('permission:view-purchase-orders');
    Route::post('vendor-payments/{payment}/allocate', [VendorPaymentController::class, 'allocate'])->middleware('permission:manage-purchase-orders');
    Route::post('vendor-payments/{payment}/refund', [VendorPaymentController::class, 'refund'])->middleware('permission:manage-purchase-orders');
    Route::delete('vendor-payments/{payment}/unallocate/{allocation}', [VendorPaymentController::class, 'unallocate'])->middleware('permission:manage-purchase-orders');
    Route::patch('vendor-payments/{payment}/void', [VendorPaymentController::class, 'void'])->middleware('permission:manage-purchase-orders');
    Route::get('vendors/{vendor}/statement', [VendorStatementController::class, 'show'])->middleware('permission:view-purchase-orders');

    // -----------------------------------------------------------------------
    // Logistics API (Phase 6.1)
    // -----------------------------------------------------------------------

    // Carriers (read by anyone with view-products, write requires manage-products)
    Route::get('carriers', [CarrierController::class, 'index'])->middleware('permission:view-products');
    Route::get('carriers/{carrier}', [CarrierController::class, 'show'])->middleware('permission:view-products');
    Route::post('carriers', [CarrierController::class, 'store'])->middleware('permission:manage-products');
    Route::patch('carriers/{carrier}', [CarrierController::class, 'update'])->middleware('permission:manage-products');
    Route::delete('carriers/{carrier}', [CarrierController::class, 'destroy'])->middleware('permission:manage-products');

    // Shipments (read requires view-sales-orders, write requires manage-sales-orders)
    Route::get('shipments', [ShipmentController::class, 'index'])->middleware('permission:view-sales-orders');
    Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])->middleware('permission:view-sales-orders');
    Route::post('shipments', [ShipmentController::class, 'store'])->middleware('permission:manage-sales-orders');
    Route::patch('shipments/{shipment}', [ShipmentController::class, 'update'])->middleware('permission:manage-sales-orders');
    Route::delete('shipments/{shipment}', [ShipmentController::class, 'destroy'])->middleware('permission:manage-sales-orders');

    // Serials (Phase 6.3) — read: view-products, write: manage-products
    Route::get('serials', [ProductSerialController::class, 'index'])->middleware('permission:view-products');
    Route::get('serials/{serial}', [ProductSerialController::class, 'show'])->middleware('permission:view-products');
    Route::post('serials', [ProductSerialController::class, 'store'])->middleware('permission:manage-products');
    Route::patch('serials/{serial}', [ProductSerialController::class, 'update'])->middleware('permission:manage-products');
    Route::delete('serials/{serial}', [ProductSerialController::class, 'destroy'])->middleware('permission:manage-products');

    // Replenishment (Phase 4.2)
    Route::get('replenishment/suggestions', [PurchaseOrderController::class, 'getSuggestions'])->middleware('permission:view-purchase-orders');
    Route::post('replenishment/suggestions/bulk-po', [PurchaseOrderController::class, 'bulkCreateFromSuggestions'])->middleware('permission:manage-purchase-orders');

    // Reorder Rules (Phase 4.2)
    Route::get('reorder-rules', [ReorderRuleController::class, 'index'])->middleware('permission:view-inventory');
    Route::post('reorder-rules', [ReorderRuleController::class, 'store'])->middleware('permission:manage-inventory');
    Route::put('reorder-rules/{reorderRule}', [ReorderRuleController::class, 'update'])->middleware('permission:manage-inventory');
    Route::delete('reorder-rules/{reorderRule}', [ReorderRuleController::class, 'destroy'])->middleware('permission:manage-inventory');

    // Utility route for Artisan commands (Phase 4.2)
    Route::post('run-command', function (Request $request) {
        if (! in_array($request->command, ['stock:check-levels'])) {
            abort(403);
        }
        Artisan::call($request->command);

        return response()->json(['message' => 'Command executed successfully.']);
    })->middleware('permission:manage-inventory');
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', 'active']);
