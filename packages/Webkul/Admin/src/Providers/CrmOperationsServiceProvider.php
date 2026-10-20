<?php

namespace Webkul\Admin\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\Console\Commands\CrmGoogleCalendarRetryCommand;
use Webkul\Admin\Console\Commands\CrmNotificationsGenerateCommand;
use Webkul\Admin\Console\Commands\CrmVendorsBackfillCommand;
use Webkul\Admin\Http\Controllers\Notification\CrmNotificationController;
use Webkul\Admin\Http\Controllers\Vendor\VendorController;
use Webkul\Admin\Services\VendorSyncService;
use Webkul\Invoice\Models\PurchaseOrder;

class CrmOperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PurchaseOrder::saving(
            function (PurchaseOrder $purchaseOrder) {
                /* PO VENDOR MASTER SYNC V1.1
                 *
                 * vendor_name is the source of truth entered in the PO.
                 * Resolve it on every save so a stale vendor_id cannot prevent
                 * a newly typed/renamed vendor from entering Vendor Master.
                 */
                if (! empty($purchaseOrder->vendor_name)) {
                    $vendor =
                        app(
                            VendorSyncService::class
                        )->findOrCreateFromPurchaseOrder(
                            $purchaseOrder
                        );

                    if ($vendor) {
                        $purchaseOrder->vendor_id =
                            $vendor->id;
                    }
                }
            }
        );

        Route::middleware(['web', 'admin_locale', 'user'])
            ->prefix('admin')
            ->group(
                function () {
                    /* VENDOR NPWP PRIVATE IMAGE V1 */
                    Route::get(
                        'vendors/{id}/npwp-image',
                        [
                            VendorController::class,
                            'npwpImage',
                        ]
                    )->name(
                        'admin.vendors.npwp-image'
                    );

                    Route::get(
                        'vendors',
                        [
                            VendorController::class,
                            'index',
                        ]
                    )->name(
                        'admin.vendors.index'
                    );

                    Route::get(
                        'vendors/create',
                        [
                            VendorController::class,
                            'create',
                        ]
                    )->name(
                        'admin.vendors.create'
                    );

                    Route::post(
                        'vendors',
                        [
                            VendorController::class,
                            'store',
                        ]
                    )->name(
                        'admin.vendors.store'
                    );

                    Route::get(
                        'vendors/{id}/edit',
                        [
                            VendorController::class,
                            'edit',
                        ]
                    )->name(
                        'admin.vendors.edit'
                    );

                    Route::put(
                        'vendors/{id}',
                        [
                            VendorController::class,
                            'update',
                        ]
                    )->name(
                        'admin.vendors.update'
                    );

                    Route::get(
                        'notifications',
                        [
                            CrmNotificationController::class,
                            'index',
                        ]
                    )->name(
                        'admin.crm-notifications.index'
                    );

                    Route::post(
                        'notifications/{id}/read',
                        [
                            CrmNotificationController::class,
                            'read',
                        ]
                    )->name(
                        'admin.crm-notifications.read'
                    );

                    Route::post(
                        'notifications/read-all',
                        [
                            CrmNotificationController::class,
                            'readAll',
                        ]
                    )->name(
                        'admin.crm-notifications.read-all'
                    );
                }
            );

        if ($this->app->runningInConsole()) {
            $this->commands([
                CrmGoogleCalendarRetryCommand::class,
                CrmNotificationsGenerateCommand::class,
                CrmVendorsBackfillCommand::class,
            ]);
        }
    }
}
