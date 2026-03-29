<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'authenticate']);

    // Google OAuth
    Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // The Stock Command Center Placeholder
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // The Master Data Catalog Interface
    Route::get('/catalog', function () {
        return Inertia::render('Catalog');
    })->name('catalog');

    Route::get('/inventory-center', function () {
        return Inertia::render('InventoryCenter');
    })->name('inventory-center');

    Route::get('/location-center', function () {
        return Inertia::render('LocationCenter');
    })->name('location-center');

    Route::get('/vendor-center', function () {
        return Inertia::render('VendorCenter');
    })->name('vendor-center');

    Route::get('/uom-center', function () {
        return Inertia::render('UomCenter');
    })->name('uom-center');

    // --- Stock Movement (Phase 2.4) ---
    Route::get('/movements/receipt', function () {
        return Inertia::render('Movements/ReceiptForm');
    })->name('movements.receipt');

    Route::get('/movements/issue', function () {
        return Inertia::render('Movements/IssueForm');
    })->name('movements.issue');

    Route::get('/movements/transfer', function () {
        return Inertia::render('Movements/TransferForm');
    })->name('movements.transfer');

    Route::get('/movements/adjustment', function () {
        return Inertia::render('Movements/AdjustmentForm');
    })->name('movements.adjustment');
});
