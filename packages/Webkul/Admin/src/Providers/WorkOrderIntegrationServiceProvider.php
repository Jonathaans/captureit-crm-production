<?php

namespace Webkul\Admin\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\Console\Commands\CrmWorkOrdersBackfillCommand;
use Webkul\Admin\Http\Controllers\WorkOrder\WorkOrderController;

class WorkOrderIntegrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'admin_locale', 'user'])
            ->prefix(
                'admin'
            )
            ->group(
                function () {
                    Route::get(
                        'work-orders',
                        [
                            WorkOrderController::class,
                            'index',
                        ]
                    )->name(
                        'admin.work-orders.index'
                    );

                    Route::post(
                        'invoices/{id}/work-order',
                        [
                            WorkOrderController::class,
                            'storeFromInvoice',
                        ]
                    )->name(
                        'admin.invoices.work-orders.store'
                    );

                    Route::get(
                        'invoices/{id}/work-order',
                        [
                            WorkOrderController::class,
                            'openForInvoice',
                        ]
                    )->name(
                        'admin.invoices.work-orders.open'
                    );

                    Route::get(
                        'work-orders/{id}/edit',
                        [
                            WorkOrderController::class,
                            'edit',
                        ]
                    )->name(
                        'admin.work-orders.edit'
                    );

                    Route::put(
                        'work-orders/{id}',
                        [
                            WorkOrderController::class,
                            'update',
                        ]
                    )->name(
                        'admin.work-orders.update'
                    );

                    Route::get(
                        'work-orders/{id}/print',
                        [
                            WorkOrderController::class,
                            'print',
                        ]
                    )->name(
                        'admin.work-orders.print'
                    );

                    Route::post(
                        'work-orders/{id}/delivery-orders',
                        [
                            WorkOrderController::class,
                            'generateDeliveryOrder',
                        ]
                    )->name(
                        'admin.work-orders.delivery-orders.generate'
                    );

                    Route::put(
                        'work-orders/{id}/release',
                        [
                            WorkOrderController::class,
                            'release',
                        ]
                    )->name(
                        'admin.work-orders.release'
                    );

                    Route::put(
                        'work-orders/{id}/complete',
                        [
                            WorkOrderController::class,
                            'complete',
                        ]
                    )->name(
                        'admin.work-orders.complete'
                    );

                    Route::put(
                        'work-orders/{id}/cancel',
                        [
                            WorkOrderController::class,
                            'cancel',
                        ]
                    )->name(
                        'admin.work-orders.cancel'
                    );

                    Route::get(
                        'work-orders/{id}',
                        [
                            WorkOrderController::class,
                            'show',
                        ]
                    )->name(
                        'admin.work-orders.show'
                    );
                }
            );

        if (
            $this->app
                ->runningInConsole()
        ) {
            $this->commands([
                CrmWorkOrdersBackfillCommand::class,
            ]);
        }
    }
}
