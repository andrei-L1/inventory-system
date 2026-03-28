<?php

use App\Http\Controllers\Api\Inventory\CategoryController;
use App\Http\Controllers\Api\Inventory\CostingMethodController;
use App\Http\Controllers\Api\Inventory\ProductController;
use App\Http\Controllers\Api\Inventory\TransactionController;
use App\Http\Controllers\Api\Inventory\UnitOfMeasureController;
use App\Http\Controllers\Api\Inventory\VendorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Master Data API
 */
Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('vendors', VendorController::class);
    Route::apiResource('uom', UnitOfMeasureController::class);
    Route::apiResource('costing-methods', CostingMethodController::class)->only(['index', 'show']);

    Route::get('products/{product}/transactions', [TransactionController::class, 'forProduct']);
    Route::get('vendors/{vendor}/transactions', [TransactionController::class, 'forVendor']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
