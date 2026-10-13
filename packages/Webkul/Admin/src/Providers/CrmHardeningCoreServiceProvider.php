<?php

namespace Webkul\Admin\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\Console\Commands\CrmAttachmentMigrateCommand;
use Webkul\Admin\Console\Commands\CrmAttachmentStorageAuditCommand;
use Webkul\Admin\Console\Commands\CrmBackupCommand;
use Webkul\Admin\Console\Commands\CrmBackupRetentionCommand;
use Webkul\Admin\Console\Commands\CrmBackupVerifyCommand;
use Webkul\Admin\Console\Commands\CrmIncidentListCommand;
use Webkul\Admin\Console\Commands\CrmManagedBackupCommand;
use Webkul\Admin\Console\Commands\CrmManagedEmailSyncCommand;
use Webkul\Admin\Console\Commands\CrmOperationalAlertsCommand;
use Webkul\Admin\Console\Commands\CrmProductionOperationsCheckCommand;
use Webkul\Admin\Console\Commands\CrmProductionReadinessCommand;
use Webkul\Admin\Console\Commands\CrmQueueHeartbeatDispatchCommand;
use Webkul\Admin\Console\Commands\CrmSchedulerHeartbeatCommand;
use Webkul\Admin\Console\Commands\CrmSecurityAuditCommand;
use Webkul\Admin\Http\Controllers\Invoice\FinanceSalesDashboardController;
use Webkul\Admin\Http\Controllers\Invoice\FlexibleQuoteBillingController;
use Webkul\Admin\Http\Controllers\Invoice\SalesCommissionExportController;
use Webkul\Admin\Http\Controllers\System\CrmBackupController;
use Webkul\Admin\Http\Controllers\System\SystemControlController;
use Webkul\Admin\Http\Middleware\CrmSecureUploadMiddleware;
use Webkul\Admin\Services\CrmAuditService;
use Webkul\Admin\Services\CrmIncidentService;
use Webkul\Admin\Services\CrmReadOnlyArchivePolicyService;
use Webkul\Admin\Services\FlexibleQuoteBillingService;

class CrmHardeningCoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /* CRM_PRODUCTION_OPERATIONS_V2 */
        $this->app['router']->pushMiddlewareToGroup(
            'web',
            CrmSecureUploadMiddleware::class
        );

        if (
            $this->app->environment('production')
            && config('crm-production-operations.production.force_https', false)
        ) {
            URL::forceScheme('https');
        }
        /* CRM_READ_ONLY_ARCHIVE_POLICY_V1
         * Final documents are readable/printable but cannot be modified or
         * deleted. Inventory movements are permanently append-only.
         */
        Event::listen(
            'eloquent.creating: *',
            function (string $eventName, array $data) {
                $model = $data[0] ?? null;

                if ($model instanceof Model) {
                    app(CrmReadOnlyArchivePolicyService::class)
                        ->assertMutable($model, 'create');
                }
            }
        );

        Event::listen(
            'eloquent.updating: *',
            function (string $eventName, array $data) {
                $model = $data[0] ?? null;

                if ($model instanceof Model) {
                    app(CrmReadOnlyArchivePolicyService::class)
                        ->assertMutable($model, 'update');
                }
            }
        );

        Event::listen(
            'eloquent.deleting: *',
            function (string $eventName, array $data) {
                $model = $data[0] ?? null;

                if ($model instanceof Model) {
                    app(CrmReadOnlyArchivePolicyService::class)
                        ->assertMutable($model, 'delete');
                }
            }
        );
        foreach (
            [
                'created',
                'updated',
                'deleted',
                'restored',
            ] as $action
        ) {
            Event::listen(
                'eloquent.'.$action.': *',
                function (
                    string $eventName,
                    array $data
                ) use ($action) {
                    $model = $data[0] ?? null;

                    if ($model instanceof Model) {
                        app(CrmAuditService::class)
                            ->record(
                                $action,
                                $model
                            );
                    }
                }
            );
        }

        Log::listen(
            function (MessageLogged $event) {
                app(CrmIncidentService::class)
                    ->captureLog($event);
            }
        );

        Queue::failing(
            function (JobFailed $event) {
                app(CrmIncidentService::class)
                    ->captureException(
                        $event->exception,
                        'error',
                        [
                            'queue' => $event->job->getQueue(),
                            'connection' => $event->connectionName,
                            'job' => $event->job->resolveName(),
                        ]
                    );
            }
        );

        Route::middleware('web')
            ->prefix('admin')
            ->group(
                function () {
                    Route::get(
                        'system-control',
                        [
                            SystemControlController::class,
                            'index',
                        ]
                    )->name(
                        'admin.system-control.index'
                    );

                    Route::get(
                        'system-control/audit-logs',
                        [
                            SystemControlController::class,
                            'auditLogs',
                        ]
                    )->name(
                        'admin.system-control.audit-logs'
                    );

                    Route::get(
                        'system-control/incidents',
                        [
                            SystemControlController::class,
                            'incidents',
                        ]
                    )->name(
                        'admin.system-control.incidents'
                    );

                    Route::post(
                        'system-control/incidents/{id}/resolve',
                        [
                            SystemControlController::class,
                            'resolveIncident',
                        ]
                    )->name(
                        'admin.system-control.incidents.resolve'
                    );
                }
            );

        /*
         * CRM_FULL_QA_BACKUP_CENTER_V1
         * Backup is POST-only, CSRF protected by web middleware, and the
         * controller applies an additional Administrator + ACL hard lock.
         */
        Route::middleware('web')
            ->prefix('admin')
            ->group(
                function () {
                    Route::post(
                        'operations-dashboard/backups',
                        [
                            CrmBackupController::class,
                            'store',
                        ]
                    )->name(
                        'admin.operations-dashboard.backups.store'
                    );

                    Route::get(
                        'operations-dashboard/backups/{filename}',
                        [
                            CrmBackupController::class,
                            'download',
                        ]
                    )
                        ->where(
                            'filename',
                            'crm-backup-[0-9]{8}-[0-9]{6}\.zip'
                        )
                        ->name(
                            'admin.operations-dashboard.backups.download'
                        );
                }
            );

        /* CRM_INVOICE_FLEXIBLE_BILLING_V1 */
        // Repeated middleware() calls on a route group replace the previous list.
        // Load the session and CSRF protection before checking the user's access.
        Route::middleware(['web', 'admin_locale', 'user'])
            ->prefix('admin/invoice-billing')
            ->controller(
                FlexibleQuoteBillingController::class
            )
            ->group(function () {
                Route::get('create', 'create')
                    ->name('admin.invoices.billing.create');

                Route::get('quotes/{quoteId}', 'summary')
                    ->whereNumber('quoteId')
                    ->name('admin.invoices.billing.summary');

                Route::post('/', 'store')
                    ->name('admin.invoices.billing.store');
            });

        foreach (['creating', 'updating', 'deleting'] as $billingOperation) {
            Event::listen(
                'eloquent.'.$billingOperation.': *',
                function (
                    string $eventName,
                    array $data
                ) use ($billingOperation) {
                    $model = $data[0] ?? null;

                    if ($model instanceof Model) {
                        app(
                            FlexibleQuoteBillingService::class
                        )->assertMutable(
                            $model,
                            $billingOperation === 'deleting'
                                ? 'delete'
                                : ($billingOperation === 'creating'
                                    ? 'create'
                                    : 'update')
                        );
                    }
                }
            );
        }

        /* CRM_FINANCE_SALES_DASHBOARD_V1 */
        Route::middleware('web')
            ->prefix('admin/finance-sales-dashboard')
            ->controller(
                FinanceSalesDashboardController::class
            )
            ->group(function () {
                Route::get('/', 'index')
                    ->name('admin.finance-sales-dashboard.index');

                Route::get('export', 'export')
                    ->name('admin.finance-sales-dashboard.export');
            });
        /* CRM_SALES_COMMISSION_PAID_DEALS_EXPORT_V1 */
        Route::middleware('web')
            ->get(
                'admin/finance-sales-dashboard/commission-export',
                [
                    SalesCommissionExportController::class,
                    'export',
                ]
            )
            ->name('admin.finance-sales-dashboard.commission-export');
        if ($this->app->runningInConsole()) {
            $this->commands([
                CrmSecurityAuditCommand::class,
                CrmBackupCommand::class,
                CrmBackupVerifyCommand::class,
                CrmProductionReadinessCommand::class,
                CrmIncidentListCommand::class,
                CrmSchedulerHeartbeatCommand::class,
                CrmQueueHeartbeatDispatchCommand::class,
                CrmManagedEmailSyncCommand::class,
                CrmManagedBackupCommand::class,
                CrmBackupRetentionCommand::class,
                CrmOperationalAlertsCommand::class,
                CrmAttachmentStorageAuditCommand::class,
                CrmAttachmentMigrateCommand::class,
                CrmProductionOperationsCheckCommand::class,
            ]);
        }
    }
}
