<?php

use App\Http\Controllers\Api\Inventory\CategoryController;
use App\Http\Controllers\Api\Inventory\CostingMethodController;
use App\Http\Controllers\Api\Inventory\ProductController;
use App\Http\Controllers\Api\Inventory\TransactionController;
use App\Http\Controllers\Api\Inventory\UnitOfMeasureController;
use App\Http\Controllers\Api\Inventory\VendorController;
use App\Http\Controllers\Api\Inventory\LocationController;
use App\Http\Controllers\Api\Inventory\LocationTypeController;
use Illuminate\Http\Request;
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

    // Unit of Measure
    Route::apiResource('uom', UnitOfMeasureController::class)->only(['index', 'show'])->middleware('permission:view-products');
    Route::apiResource('uom', UnitOfMeasureController::class)->except(['index', 'show'])->middleware('permission:manage-products');

    // Costing Methods (Read Only)
    Route::apiResource('costing-methods', CostingMethodController::class)->only(['index', 'show'])->middleware('permission:view-products');

    // Transactions API 
    Route::get('products/{product}/transactions', [TransactionController::class, 'forProduct'])->middleware('permission:view-transactions');
    Route::get('vendors/{vendor}/transactions', [TransactionController::class, 'forVendor'])->middleware('permission:view-transactions');

    // Locations API
    Route::apiResource('locations', LocationController::class)->only(['index', 'show'])->middleware('permission:view-inventory');
    Route::apiResource('locations', LocationController::class)->except(['index', 'show'])->middleware('permission:manage-inventory');
    Route::get('location-types', [LocationTypeController::class, 'index'])->middleware('permission:view-inventory');
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
