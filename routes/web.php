<?php

use App\Http\Controllers\Api\Finance\InvoiceController;
use App\Http\Controllers\Api\Finance\PaymentController;
use App\Http\Controllers\Api\Inventory\TransactionController;
use App\Http\Controllers\Api\Procurement\PurchaseOrderController;
use App\Http\Controllers\Api\Sales\SalesOrderController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
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

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // The Stock Command Center Placeholder
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // The Master Data Catalog Interface
    Route::get('/catalog', function () {
        return Inertia::render('Catalog');
    })->name('catalog');

    Route::get('/inventory-center', fn () => Inertia::render('InventoryCenter'))->name('inventory-center');
    Route::get('/location-center', fn () => Inertia::render('LocationCenter'))->name('location-center');
    Route::get('/uom-center', fn () => Inertia::render('UomCenter'))->name('uom-center');
    Route::get('/category-center', fn () => Inertia::render('CategoryCenter'))->name('category-center');

    Route::get('/customer-center', fn () => Inertia::render('CustomerCenter'))->name('customer-center');
    Route::get('/vendor-center', fn () => Inertia::render('VendorCenter'))->name('vendor-center');

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

    Route::get('/movements/{transaction}/print', [TransactionController::class, 'print'])->name('movements.print');

    Route::get('/movements/{id}', function ($id) {
        return Inertia::render('Movements/Show', ['id' => $id]);
    })->name('movements.show');

    // --- Procurement (Phase 4.2) ---
    Route::get('/purchase-orders', function () {
        return Inertia::render('PurchaseOrders/Index');
    })->name('purchase-orders.index');

    Route::get('/purchase-orders/create', function () {
        return Inertia::render('PurchaseOrders/Form');
    })->name('purchase-orders.create');

    Route::get('/purchase-orders/{id}/edit', function (Request $request, $id) {
        $po = PurchaseOrder::with(['lines.product', 'lines.uom', 'status'])->find($id);
        if (! $po) {
            abort(404);
        }
        // Only draft POs are editable
        if (! $po->status->is_editable) {
            return redirect()->route('purchase-orders.show', $id)
                ->with('error', 'This purchase order is no longer editable.');
        }

        return Inertia::render('PurchaseOrders/Form', ['purchaseOrder' => $po]);
    })->name('purchase-orders.edit')->middleware('permission:manage-purchase-orders');

    Route::get('/purchase-orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])->name('purchase-orders.print');

    Route::get('/purchase-orders/{id}', function (Request $request, $id) {
        if ($id === 'create') {
            return redirect()->route('purchase-orders.create');
        }

        return Inertia::render('PurchaseOrders/Show', ['id' => $id]);
    })->name('purchase-orders.show');

    // --- Sales (Phase 5) ---
    Route::get('/sales-orders', function () {
        return Inertia::render('SalesOrders/Index');
    })->name('sales-orders.index');

    Route::get('/sales-orders/create', function () {
        return Inertia::render('SalesOrders/Form');
    })->name('sales-orders.create');

    Route::get('/sales-orders/{id}/edit', function (Request $request, $id) {
        // Fetch SO and pass as prop for edit mode
        $so = SalesOrder::with('lines')->find($id);
        if (! $so) {
            abort(404);
        }

        return Inertia::render('SalesOrders/Form', ['salesOrder' => $so]);
    })->name('sales-orders.edit');

    Route::get('/sales-orders/{salesOrder}/print', [SalesOrderController::class, 'print'])->name('sales-orders.print');

    Route::get('/sales-orders/{id}', function (Request $request, $id) {
        if ($id === 'create') {
            return redirect()->route('sales-orders.create');
        }

        return Inertia::render('SalesOrders/Show', ['id' => $id]);
    })->name('sales-orders.show');

    // --- Finance (Phase 5.5 / 5.7) ---
    Route::get('/finance', fn () => redirect()->route('finance.center'));
    Route::get('/finance-center', function () {
        return Inertia::render('Finance/FinanceCenter');
    })->name('finance.center');

    Route::prefix('finance')->name('finance.')->group(function () {
        // A/R - Invoices
        Route::get('/invoices/create', function () {
            return Inertia::render('Finance/InvoiceForm');
        })->name('invoices.create');
        Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
        Route::get('/invoices/{id}', function (Request $request, $id) {
            return Inertia::render('Finance/InvoiceDocument', ['id' => $id]);
        })->name('invoices.show');

        // A/R - Payments
        Route::get('/payments/create', function () {
            return Inertia::render('Finance/PaymentForm');
        })->name('payments.create');
        Route::get('/payments/{payment}/print', [PaymentController::class, 'print'])->name('payments.print');
        Route::get('/payments/{id}', function (Request $request, $id) {
            return Inertia::render('Finance/PaymentDocument', ['id' => $id]);
        })->name('payments.show');

        // A/P - Bills
        Route::get('/bills/create', function () {
            return Inertia::render('Finance/BillForm');
        })->name('bills.create');
        Route::get('/bills/{id}', function (Request $request, $id) {
            return Inertia::render('Finance/BillDocument', ['id' => $id]);
        })->name('bills.show');

        // A/P - Vendor Payments
        Route::get('/vendor-payments/create', function () {
            return Inertia::render('Finance/VendorPaymentForm');
        })->name('vendor-payments.create');
        Route::get('/vendor-payments/{id}', function (Request $request, $id) {
            return Inertia::render('Finance/VendorPaymentDocument', ['id' => $id]);
        })->name('vendor-payments.show');
    });
});
