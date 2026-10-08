<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\DeliveryOrder\DeliveryOrderController;
use Webkul\Admin\Http\Controllers\DeliveryOrder\DeliveryOrderInventoryController;
use Webkul\Admin\Http\Controllers\DeliveryOrder\DeliveryOrderReturnController;
use Webkul\Admin\Http\Middleware\InjectInternalCommunicationUi;

Route::controller(DeliveryOrderController::class)
    ->prefix('delivery-orders')
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Listing / View
        |--------------------------------------------------------------------------
        */

        Route::get('/', 'index')
            ->name('admin.delivery-orders.index');

        /*
        |--------------------------------------------------------------------------
        | Print
        |--------------------------------------------------------------------------
        */

        Route::get('{id}/print', 'print')
            ->withoutMiddleware(InjectInternalCommunicationUi::class)
            ->name('admin.delivery-orders.print');

        /*
        |--------------------------------------------------------------------------
        | Status Workflow
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | Setiap aksi mempunyai route name sendiri supaya ACL Roles
        | bisa membedakan Head Warehouse, Operations, Staff Warehouse, dll.
        |
        */

        Route::put('{id}/issue', 'issue')
            ->name('admin.delivery-orders.issue');

        Route::put('{id}/delivered', 'markDelivered')
            ->name('admin.delivery-orders.delivered');

        Route::put('{id}/returned', 'markReturned')
            ->name('admin.delivery-orders.returned');

        Route::put('{id}/cancel', 'cancel')
            ->name('admin.delivery-orders.cancel');

        /*
        |--------------------------------------------------------------------------
        | Edit
        |--------------------------------------------------------------------------
        */

        Route::get('{id}/edit', 'edit')
            ->name('admin.delivery-orders.edit');

        Route::put('{id}', 'update')
            ->name('admin.delivery-orders.update');

        /*
        |--------------------------------------------------------------------------
        | Detail
        |--------------------------------------------------------------------------
        */

        Route::get('{id}', 'show')
            ->name('admin.delivery-orders.show');
    });

/*
|--------------------------------------------------------------------------
| Delivery Order > Inventory Allocation
|--------------------------------------------------------------------------
*/
Route::controller(DeliveryOrderInventoryController::class)
    ->prefix('delivery-orders')
    ->group(function () {
        Route::get('{id}/inventory-allocation', 'edit')
            ->name('admin.delivery-orders.inventory-allocation.edit');

        Route::put('{id}/inventory-allocation/scan', 'scanAllocate')
            ->name('admin.delivery-orders.inventory-allocation.scan');

        Route::put('{id}/inventory-allocation/{itemId}', 'update')
            ->name('admin.delivery-orders.inventory-allocation.update');

        Route::delete('{id}/inventory-allocation/{itemId}', 'release')
            ->name('admin.delivery-orders.inventory-allocation.release');
    });

/*
|--------------------------------------------------------------------------
| Delivery Order > Return / Check-In
|--------------------------------------------------------------------------
*/
Route::controller(DeliveryOrderReturnController::class)
    ->prefix('delivery-orders')
    ->group(function () {
        Route::get('{id}/return', 'show')
            ->name('admin.delivery-orders.return.show');

        Route::put('{id}/return/scan-check-in', 'scanCheckIn')
            ->name('admin.delivery-orders.return.scan-check-in');

        Route::put('{id}/return/finalize', 'finalize')
            ->name('admin.delivery-orders.return.finalize');

        Route::put('{id}/return/{allocationId}/check-in', 'checkIn')
            ->name('admin.delivery-orders.return.check-in');
    });
