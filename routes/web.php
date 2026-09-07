<?php

use Illuminate\Support\Facades\Route;

// FINANCIAL REPORT EXPORT ALL EXPENSES V1 START
Route::get('admin/invoices/financial-report/export-expenses', \App\Http\Controllers\AllExpensesExportController::class)
    ->middleware(['web', 'admin_locale', 'user'])
    ->name('admin.invoices.financial-report.expenses.export');
// FINANCIAL REPORT EXPORT ALL EXPENSES V1 END

/*
|--------------------------------------------------------------------------
| MISSING_ASSET_RECOVERY_V1
|--------------------------------------------------------------------------
| Recover a single serialized MISSING asset without running a full
| warehouse stock opname. Uses the same authenticated admin middleware.
*/
\Illuminate\Support\Facades\Route::post(
    'admin/inventory/assets/{id}/recover',
    [
        \Webkul\Admin\Http\Controllers\Inventory\InventoryAssetController::class,
        'recover',
    ]
)
    ->middleware([
        'web',
        'admin_locale',
        'user',
    ])
    ->name(
        'admin.inventory.assets.recover'
    );
