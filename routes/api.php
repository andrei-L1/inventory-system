<?php

use App\Http\Controllers\Api\Inventory\AdjustmentController;
use App\Http\Controllers\Api\Inventory\AdjustmentReasonController;
use App\Http\Controllers\Api\Inventory\CategoryController;
use App\Http\Controllers\Api\Inventory\CostingMethodController;
use App\Http\Controllers\Api\Inventory\DashboardController;
use App\Http\Controllers\Api\Inventory\InventoryQueryController;
use App\Http\Controllers\Api\Inventory\LocationController;
use App\Http\Controllers\Api\Inventory\LocationTypeController;
use App\Http\Controllers\Api\Inventory\ProductController;
use App\Http\Controllers\Api\Inventory\TransactionController;
use App\Http\Controllers\Api\Inventory\UnitOfMeasureController;
use App\Http\Controllers\Api\Inventory\UomConversionController;
use App\Http\Controllers\Api\Inventory\VendorController;
use App\Http\Controllers\Api\Procurement\PurchaseOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/**
 * Master Data API
 */
Route::middleware(['auth:sanctum'])->group(function () {
    // Categories
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show'])->middleware('permission:view-products');
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show'])->middleware('permission:manage-products');

    // Products
    Route::apiResource('products', ProductController::class)->only(['index', 'show'])->middleware('permission:view-products');
    Route::apiResource('products', ProductController::class)->except(['index', 'show'])->middleware('permission:manage-products');

    // Vendors
    Route::apiResource('vendors', VendorController::class)->only(['index', 'show'])->middleware('permission:view-products');
    Route::apiResource('vendors', VendorController::class)->except(['index', 'show'])->middleware('permission:manage-products');

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

    // Cancel a draft transaction (posted transactions require reversal — not yet implemented).
    Route::patch('transactions/{transaction}/cancel', [TransactionController::class, 'cancel'])
        ->middleware('permission:manage-inventory');

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
    Route::apiResource('purchase-orders', PurchaseOrderController::class)->middleware('permission:manage-inventory');
    Route::patch('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->middleware('permission:manage-inventory');
    Route::patch('purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->middleware('permission:manage-inventory');
    Route::post('purchase-orders/{purchaseOrder}/ship', [PurchaseOrderController::class, 'markAsShipped'])->middleware('permission:manage-inventory');
    Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->middleware('permission:manage-inventory');

    // Replenishment (Phase 4.2)
    Route::get('replenishment/suggestions', [PurchaseOrderController::class, 'getSuggestions'])->middleware('permission:manage-inventory');
    Route::post('replenishment/suggestions/bulk-po', [PurchaseOrderController::class, 'bulkCreateFromSuggestions'])->middleware('permission:manage-inventory');

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
})->middleware('auth:sanctum');
