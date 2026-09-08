<?php

namespace Webkul\Admin\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\Console\Commands\CrmBackupCommand;
use Webkul\Admin\Console\Commands\CrmBackupVerifyCommand;
use Webkul\Admin\Console\Commands\CrmIncidentListCommand;
use Webkul\Admin\Console\Commands\CrmProductionReadinessCommand;
use Webkul\Admin\Console\Commands\CrmSecurityAuditCommand;
use Webkul\Admin\Http\Controllers\System\SystemControlController;
use Webkul\Admin\Http\Controllers\System\CrmBackupController;
use Webkul\Admin\Services\CrmAuditService;
use Webkul\Admin\Services\CrmIncidentService;
use Webkul\Admin\Services\CrmReadOnlyArchivePolicyService;

class CrmHardeningCoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /* CRM_PRODUCTION_OPERATIONS_V2 */
        $this->app['router']->pushMiddlewareToGroup(
            'web',
            \Webkul\Admin\Http\Middleware\CrmSecureUploadMiddleware::class
        );

        if (
            $this->app->environment('production')
            && config('crm-production-operations.production.force_https', false)
        ) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
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
            ]
            as $action
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
        \Illuminate\Support\Facades\Route::middleware('web')
            ->prefix('admin/invoice-billing')
            ->controller(
                \Webkul\Admin\Http\Controllers\Invoice\FlexibleQuoteBillingController::class
            )
            ->group(function () {
                \Illuminate\Support\Facades\Route::get('create', 'create')
                    ->name('admin.invoices.billing.create');

                \Illuminate\Support\Facades\Route::get('quotes/{quoteId}', 'summary')
                    ->whereNumber('quoteId')
                    ->name('admin.invoices.billing.summary');

                \Illuminate\Support\Facades\Route::post('/', 'store')
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
                            \Webkul\Admin\Services\FlexibleQuoteBillingService::class
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
        if ($this->app->runningInConsole()) {
            $this->commands([
                CrmSecurityAuditCommand::class,
                CrmBackupCommand::class,
                CrmBackupVerifyCommand::class,
                CrmProductionReadinessCommand::class,
                CrmIncidentListCommand::class,
                            \Webkul\Admin\Console\Commands\CrmSchedulerHeartbeatCommand::class,
                \Webkul\Admin\Console\Commands\CrmQueueHeartbeatDispatchCommand::class,
                \Webkul\Admin\Console\Commands\CrmManagedEmailSyncCommand::class,
                \Webkul\Admin\Console\Commands\CrmManagedBackupCommand::class,
                \Webkul\Admin\Console\Commands\CrmBackupRetentionCommand::class,
                \Webkul\Admin\Console\Commands\CrmOperationalAlertsCommand::class,
                \Webkul\Admin\Console\Commands\CrmAttachmentStorageAuditCommand::class,
                \Webkul\Admin\Console\Commands\CrmAttachmentMigrateCommand::class,
                \Webkul\Admin\Console\Commands\CrmProductionOperationsCheckCommand::class,
]);
        }
    }
}
