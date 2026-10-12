<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Invoice\ExpenseExportController;
use Webkul\Admin\Http\Controllers\Invoice\FinancialReportController;
use Webkul\Admin\Http\Controllers\Invoice\InvoiceController;
use Webkul\Admin\Http\Controllers\Invoice\InvoiceDeliveryOrderController;
use Webkul\Admin\Http\Controllers\Invoice\ProformaInvoiceController;
use Webkul\Admin\Http\Controllers\PurchaseOrder\PurchaseOrderController;
use Webkul\Admin\Http\Controllers\PurchaseOrder\PurchaseOrderExpenseExportController;
use Webkul\Admin\Http\Controllers\WorkOrder\WorkOrderController;

Route::controller(ProformaInvoiceController::class)->prefix('proformas')->group(function () {
    Route::get('/', 'index')->name('admin.proformas.index');
    Route::get('{id}', 'show')->whereNumber('id')->name('admin.proformas.show');
    Route::get('{id}/print', 'print')->whereNumber('id')->name('admin.proformas.print');
    Route::post('{id}/payment', 'confirmPayment')->whereNumber('id')->name('admin.proformas.payment');
    Route::post('{id}/cancel', 'cancel')->whereNumber('id')->name('admin.proformas.cancel');
});

/*
|--------------------------------------------------------------------------
| Financial Report
|--------------------------------------------------------------------------
|
| Route names stay EXACTLY the same, so existing menu + ACL continue
| working. Report logic now lives in a dedicated controller and no longer
| makes InvoiceController even larger.
|
| IMPORTANT: define these routes BEFORE invoices/{id}.
|
*/

Route::controller(FinancialReportController::class)
    ->prefix('invoices')
    ->group(function () {
        Route::get(
            'financial-report',
            'index'
        )->name(
            'admin.invoices.financial-report'
        );

        Route::get(
            'financial-report/export',
            'export'
        )->name(
            'admin.invoices.financial-report.export'
        );
    });

/*
|--------------------------------------------------------------------------
| Purchase Orders - Event Vendor / Outsource
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| PO EXPENSE CSV EXPORT V1 ROUTE
|--------------------------------------------------------------------------
*/

Route::get(
    'purchase-orders/export-expense-csv',
    [
        PurchaseOrderExpenseExportController::class,
        'export',
    ]
)->name('admin.purchase-orders.export-expense');
Route::controller(
    PurchaseOrderController::class
)
    ->prefix('purchase-orders')
    ->group(function () {
        Route::get('/', 'index')
            ->name('admin.purchase-orders.index');

        Route::get('create', 'create')
            ->name('admin.purchase-orders.create');

        Route::post('/', 'store')
            ->name('admin.purchase-orders.store');

        Route::get('{id}/edit', 'edit')
            ->name('admin.purchase-orders.edit');

        Route::put('{id}', 'update')
            ->name('admin.purchase-orders.update');

        Route::post('{id}/release', 'release')
            ->name('admin.purchase-orders.release');

        /* PURCHASE ORDER PAID WORKFLOW V1 ROUTES */
        Route::post('{id}/paid', 'paid')
            ->name('admin.purchase-orders.paid');

        Route::get('{id}/payment-proof', 'paymentProof')
            ->name('admin.purchase-orders.payment-proof');

        Route::post('{id}/complete', 'complete')
            ->name('admin.purchase-orders.complete');

        Route::post('{id}/cancel', 'cancel')
            ->name('admin.purchase-orders.cancel');

        Route::get('{id}/print', 'print')
            ->name('admin.purchase-orders.print');

        Route::get('{id}', 'show')
            ->name('admin.purchase-orders.show');
    });
/*
|--------------------------------------------------------------------------
| EXPORT ALL EXPENSES CSV V1
|--------------------------------------------------------------------------
| This static route must be declared before the dynamic invoices/{id} route.
*/

Route::controller(
    ExpenseExportController::class
)
    ->prefix('invoices')
    ->group(function () {
        Route::get('expenses/export-all', 'export')
            ->name('admin.invoices.expenses.export-all');
    });

Route::controller(InvoiceController::class)
    ->prefix('invoices')
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Listing
        |--------------------------------------------------------------------------
        */

        Route::get('/', 'index')
            ->name('admin.invoices.index');

        /*
        |--------------------------------------------------------------------------
        | Generate Invoice
        |--------------------------------------------------------------------------
        */

        Route::post('generate/{quoteId}', 'generate')
            ->name('admin.invoices.generate');

        /*
        |--------------------------------------------------------------------------
        | Edit Invoice
        |--------------------------------------------------------------------------
        */

        Route::get('{id}/edit', 'edit')
            ->name('admin.invoices.edit');

        Route::put('{id}', 'update')
            ->name('admin.invoices.update');

        /*
        |--------------------------------------------------------------------------
        | Delivery Order / Surat Jalan
        |--------------------------------------------------------------------------
        */

        Route::get(
            '{id}/delivery-orders',
            [InvoiceDeliveryOrderController::class, 'index']
        )
            ->name('admin.invoices.delivery-orders.index');

        Route::post(
            '{id}/delivery-order',
            [WorkOrderController::class, 'storeFromInvoice']
        )
            ->name('admin.invoices.delivery-order.generate');

        /*
        |--------------------------------------------------------------------------
        | Detail
        |--------------------------------------------------------------------------
        */

        Route::get('{id}', 'show')
            ->name('admin.invoices.show');

        /*
        |--------------------------------------------------------------------------
        | Print
        |--------------------------------------------------------------------------
        */

        Route::get('{id}/print', 'print')
            ->name('admin.invoices.print');

        /*
        |--------------------------------------------------------------------------
        | Payment
        |--------------------------------------------------------------------------
        */

        Route::post('{id}/payments', 'addPayment')
            ->name('admin.invoices.payments.store');

        /*
        |--------------------------------------------------------------------------
        | Expenses
        |--------------------------------------------------------------------------
        */

        Route::post('{id}/expenses', 'addExpense')
            ->name('admin.invoices.expenses.store');

        Route::put(
            '{invoiceId}/expenses/{expenseId}',
            'updateExpense'
        )->name(
            'admin.invoices.expenses.update'
        );

        Route::delete(
            '{invoiceId}/expenses/{expenseId}',
            'deleteExpense'
        )->name(
            'admin.invoices.expenses.delete'
        );

        /*
        |--------------------------------------------------------------------------
        | Event Status
        |--------------------------------------------------------------------------
        */

        Route::put(
            '{id}/event-status',
            'updateEventStatus'
        )->name(
            'admin.invoices.event-status.update'
        );
    });
