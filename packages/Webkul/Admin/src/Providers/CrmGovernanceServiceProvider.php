<?php

namespace Webkul\Admin\Providers;

use Carbon\Carbon;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\Console\Commands\CrmFinancialPeriodStatusCommand;
use Webkul\Admin\Http\Controllers\Dashboard\OperationsDashboardController;
use Webkul\Admin\Http\Controllers\Financial\FinancialPeriodController;
use Webkul\Admin\Services\FinancialPeriodLockService;

class CrmGovernanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')
            ->prefix('admin')
            ->group(
                function () {
                    Route::get(
                        'operations-dashboard',
                        [
                            OperationsDashboardController::class,
                            'index',
                        ]
                    )->name(
                        'admin.operations-dashboard.index'
                    );

                    Route::get('operations-dashboard/financial-audit/export', [OperationsDashboardController::class, 'financialAuditExport'])
                        ->name('admin.operations-dashboard.financial-audit.export');

                    Route::get(
                        'financial-periods',
                        [
                            FinancialPeriodController::class,
                            'index',
                        ]
                    )->name(
                        'admin.financial-periods.index'
                    );

                    Route::post(
                        'financial-periods',
                        [
                            FinancialPeriodController::class,
                            'store',
                        ]
                    )->name(
                        'admin.financial-periods.store'
                    );

                    Route::delete(
                        'financial-periods/{id}',
                        [
                            FinancialPeriodController::class,
                            'destroy',
                        ]
                    )->name(
                        'admin.financial-periods.destroy'
                    );
                }
            );

        Event::listen(
            RouteMatched::class,
            function (RouteMatched $event) {
                $request =
                    request();

                if (
                    in_array(
                        strtoupper(
                            $request->method()
                        ),
                        [
                            'GET',
                            'HEAD',
                            'OPTIONS',
                        ],
                        true
                    )
                ) {
                    return;
                }

                $routeName =
                    strtolower(
                        (string) (
                            $event->route->getName()
                            ?? ''
                        )
                    );

                if ($routeName === '') {
                    return;
                }

                $financialWrite =
                    str_contains(
                        $routeName,
                        'payment'
                    )
                    || str_contains(
                        $routeName,
                        'expense'
                    )
                    || str_contains(
                        $routeName,
                        'purchase-orders.release'
                    )
                    || str_contains(
                        $routeName,
                        'purchase-orders.cancel'
                    );

                if (! $financialWrite) {
                    return;
                }

                $date =
                    $request->input(
                        'paid_at'
                    )
                    ?: $request->input(
                        'payment_date'
                    )
                    ?: $request->input(
                        'expense_date'
                    )
                    ?: $request->input(
                        'date'
                    )
                    ?: now();

                try {
                    $date =
                        Carbon::parse(
                            $date
                        );
                } catch (\Throwable) {
                    $date =
                        now();
                }

                $lock =
                    app(
                        FinancialPeriodLockService::class
                    )->findLock(
                        $date
                    );

                if ($lock) {
                    abort(
                        423,
                        'Financial period '
                        .$lock->period
                        .' is CLOSED. Reopen the period before changing payment/expense/PO financial transactions.'
                    );
                }
            }
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                CrmFinancialPeriodStatusCommand::class,
            ]);
        }
    }
}
