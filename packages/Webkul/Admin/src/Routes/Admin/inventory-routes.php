<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Inventory\InventoryAlertController;
use Webkul\Admin\Http\Controllers\Inventory\InventoryAssetController;
use Webkul\Admin\Http\Controllers\Inventory\InventoryConsumableController;
use Webkul\Admin\Http\Controllers\Inventory\InventoryConsumableUsageController;
use Webkul\Admin\Http\Controllers\Inventory\InventoryDashboardController;
use Webkul\Admin\Http\Controllers\Inventory\InventoryItemController;
use Webkul\Admin\Http\Controllers\Inventory\InventoryMaintenanceController;
use Webkul\Admin\Http\Controllers\Inventory\InventoryMissingRecoveryScanController;
use Webkul\Admin\Http\Controllers\Inventory\InventoryMovementController;
use Webkul\Admin\Http\Controllers\Inventory\InventoryQaController;
use Webkul\Admin\Http\Controllers\Inventory\InventoryStockOpnameController;

Route::prefix('inventory')
    ->group(function () {
        Route::get('/', [InventoryDashboardController::class, 'index'])
            ->name('admin.inventory.dashboard');

        Route::controller(InventoryConsumableUsageController::class)
            ->prefix('consumable-usage')
            ->group(function () {
                Route::get('/', 'index')->name('admin.inventory.consumable-usage.index');
                Route::get('{id}', 'show')->whereNumber('id')->name('admin.inventory.consumable-usage.show');
                Route::put('{id}', 'update')->whereNumber('id')->name('admin.inventory.consumable-usage.update');
            });

        Route::controller(InventoryAlertController::class)
            ->prefix('alerts')
            ->group(function () {
                Route::get('/', 'index')
                    ->name('admin.inventory.alerts.index');

                Route::get('export-csv', 'exportCsv')
                    ->name('admin.inventory.alerts.export-csv');
            });

        Route::controller(InventoryQaController::class)
            ->prefix('qa')
            ->group(function () {
                Route::get('/', 'index')
                    ->name('admin.inventory.qa.index');

                Route::get('export-csv', 'exportCsv')
                    ->name('admin.inventory.qa.export-csv');
            });

        Route::controller(InventoryConsumableController::class)
            ->prefix('consumables')
            ->group(function () {
                Route::get('/', 'index')
                    ->name('admin.inventory.consumables.index');

                Route::get('create', 'create')
                    ->name('admin.inventory.consumables.create');

                Route::post('/', 'store')
                    ->name('admin.inventory.consumables.store');

                Route::get('{id}/edit', 'edit')
                    ->name('admin.inventory.consumables.edit');

                Route::put('{id}', 'update')
                    ->name('admin.inventory.consumables.update');
            });

        Route::controller(InventoryItemController::class)
            ->prefix('items')
            ->group(function () {
                Route::get('/', 'index')
                    ->name('admin.inventory.items.index');

                Route::get('create', 'create')
                    ->name('admin.inventory.items.create');

                Route::post('/', 'store')
                    ->name('admin.inventory.items.store');

                Route::get('{id}/edit', 'edit')
                    ->name('admin.inventory.items.edit');

                Route::put('{id}', 'update')
                    ->name('admin.inventory.items.update');
            });

        Route::controller(InventoryAssetController::class)
            ->prefix('assets')
            ->group(function () {
                Route::get('/', 'index')
                    ->name('admin.inventory.assets.index');

                /*
                 * Keep static barcode routes above {id} routes.
                 */
                Route::get('qr-labels', 'qrLabels')
                    ->name('admin.inventory.assets.qr-labels.index');

                Route::get('{id}/qr.svg', 'qrSvg')
                    ->name('admin.inventory.assets.qr-labels.svg');

                Route::get('bulk-create', 'bulkCreate')
                    ->name('admin.inventory.assets.bulk-create');

                Route::post('bulk-create', 'bulkStore')
                    ->name('admin.inventory.assets.bulk-store');

                Route::get('create', 'create')
                    ->name('admin.inventory.assets.create');

                Route::post('/', 'store')
                    ->name('admin.inventory.assets.store');

                Route::get('{id}/edit', 'edit')
                    ->name('admin.inventory.assets.edit');

                Route::put('{id}', 'update')
                    ->name('admin.inventory.assets.update');
            });

        /* INVENTORY_MISSING_RECOVERY_STATION_ROUTES_V2 */
        Route::controller(InventoryMissingRecoveryScanController::class)
            ->prefix('missing-recovery')
            ->group(function () {
                Route::get('/', 'station')
                    ->name('admin.inventory.missing-recovery.station');

                Route::post('verify', 'verify')
                    ->name('admin.inventory.missing-recovery.verify');

                Route::post('{id}/complete', 'complete')
                    ->name('admin.inventory.missing-recovery.complete');

                Route::delete('verified', 'clear')
                    ->name('admin.inventory.missing-recovery.clear');
            });
        /* INVENTORY_MISSING_RECOVERY_SCAN_ROUTES_V1 */
        Route::controller(InventoryMissingRecoveryScanController::class)
            ->prefix('assets')
            ->group(function () {
                Route::get('{id}/recover-missing-scan', 'create')
                    ->name('admin.inventory.assets.missing-recovery.scan');

                Route::post('{id}/recover-missing-scan', 'store')
                    ->name('admin.inventory.assets.missing-recovery.store');
            });
        Route::controller(InventoryMaintenanceController::class)
            ->prefix('maintenance')
            ->group(function () {
                Route::get('/', 'index')
                    ->name('admin.inventory.maintenance.index');

                Route::get('create', 'create')
                    ->name('admin.inventory.maintenance.create');

                Route::post('/', 'store')
                    ->name('admin.inventory.maintenance.store');

                Route::get('{id}', 'show')
                    ->name('admin.inventory.maintenance.show');

                Route::put('{id}/complete', 'complete')
                    ->name('admin.inventory.maintenance.complete');

                Route::put('{id}/retire', 'retire')
                    ->name('admin.inventory.maintenance.retire');
            });

        Route::controller(InventoryStockOpnameController::class)
            ->prefix('stock-opname')
            ->group(function () {
                Route::get('/', 'index')
                    ->name('admin.inventory.stock-opname.index');

                Route::get('create', 'create')
                    ->name('admin.inventory.stock-opname.create');

                Route::post('/', 'store')
                    ->name('admin.inventory.stock-opname.store');

                Route::get('{id}', 'show')
                    ->name('admin.inventory.stock-opname.show');

                Route::get('{id}/export-csv', 'exportCsv')
                    ->name('admin.inventory.stock-opname.export-csv');

                Route::put('{id}/start', 'start')
                    ->name('admin.inventory.stock-opname.start');

                Route::put('{id}/scan', 'scan')
                    ->name('admin.inventory.stock-opname.scan');

                Route::put('{id}/quantity/{entryId}', 'countQuantity')
                    ->name('admin.inventory.stock-opname.quantity');

                Route::put('{id}/review', 'review')
                    ->name('admin.inventory.stock-opname.review');

                Route::put('{id}/resume', 'resume')
                    ->name('admin.inventory.stock-opname.resume');

                Route::put('{id}/finalize', 'finalize')
                    ->name('admin.inventory.stock-opname.finalize');
            });

        Route::controller(InventoryMovementController::class)
            ->prefix('movements')
            ->group(function () {
                Route::get('/', 'index')
                    ->name('admin.inventory.movements.index');

                Route::get('adjust-stock', 'createStockAdjustment')
                    ->name('admin.inventory.movements.adjust-stock.create');

                Route::post('adjust-stock', 'storeStockAdjustment')
                    ->name('admin.inventory.movements.adjust-stock.store');
            });
    });
