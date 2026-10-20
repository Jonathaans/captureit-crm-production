<?php

namespace Webkul\Admin\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\Console\Commands\CrmLeadCommercialWorkflowCheckCommand;
use Webkul\Admin\Http\Controllers\Lead\LeadCommercialWorkflowController;
use Webkul\Admin\Http\Middleware\InjectLeadCommercialActionUi;
use Webkul\Admin\Services\LeadCommercialStageService;
use Webkul\Admin\Services\LeadCommercialWorkflowService;
use Webkul\Admin\Services\WorkflowNotificationService;
use Webkul\Lead\Models\Lead;

class LeadCommercialWorkflowServiceProvider extends ServiceProvider
{
    public function boot(
        Router $router
    ): void {
        $router->pushMiddlewareToGroup(
            'web',
            InjectLeadCommercialActionUi::class
        );

        Route::middleware(['web', 'admin_locale', 'user'])
            ->prefix(
                'admin'
            )
            ->group(
                function () {
                    Route::get(
                        'leads/{leadId}/generate-quotation',
                        [
                            LeadCommercialWorkflowController::class,
                            'quotationForm',
                        ]
                    )->name(
                        'admin.leads.generate-quotation'
                    );
                }
            );

        $this->registerLeadCommercialNotifications();

        if (
            $this->app
                ->runningInConsole()
        ) {
            $this->commands([
                CrmLeadCommercialWorkflowCheckCommand::class,
            ]);
        }
    }

    private function registerLeadCommercialNotifications(): void
    {
        $leadClass =
            Lead::class;

        if (! class_exists($leadClass)) {
            return;
        }

        $leadClass::updated(
            function ($lead) {
                $stageService =
                    app(
                        LeadCommercialStageService::class
                    );

                $workflow =
                    app(
                        LeadCommercialWorkflowService::class
                    );

                $notifications =
                    app(
                        WorkflowNotificationService::class
                    );

                $salesAdminIds =
                    $notifications
                        ->usersByRoleNames([
                            'Sales Admin',
                        ]);

                if (
                    $salesAdminIds
                        ->isEmpty()
                ) {
                    return;
                }

                $leadLabel =
                    trim(
                        (string) (
                            $lead->title
                            ?? $lead->subject
                            ?? ''
                        )
                    );

                if ($leadLabel === '') {
                    $leadLabel =
                        'Lead #'
                        .$lead->id;
                }

                $salesOwnerName =
                    null;

                if (
                    ! empty(
                        $lead->user_id
                    )
                ) {
                    $salesOwnerName =
                        DB::table(
                            'users'
                        )
                            ->where(
                                'id',
                                $lead->user_id
                            )
                            ->value(
                                'name'
                            );
                }

                /*
                |--------------------------------------------------------------------------
                | QUOTATION stage -> Sales Admin -> Create Quotation
                |--------------------------------------------------------------------------
                */
                if (
                    $stageService
                        ->becameQuotation(
                            $lead
                        )
                ) {
                    $message =
                        $leadLabel
                        .(
                            $salesOwnerName
                                ? ' · Sales Owner: '
                                    .$salesOwnerName
                                : ''
                        )
                        .' · Lead masuk stage Quotation. Buat Quotation dari data Lead.';

                    $notifications
                        ->notifyUsers(
                            $salesAdminIds,
                            'quotation_required',
                            'Quotation Required',
                            $message,
                            route(
                                'admin.leads.view',
                                $lead->id
                            ),
                            'lead-quotation-stage:'
                                .$lead->id,
                            'lead',
                            $lead->id,
                            [
                                'sales_owner_id' => $lead->user_id
                                    ?? null,

                                'commercial_action' => 'quotation',
                            ]
                        );

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | WON -> Sales Admin -> Generate Invoice from linked Quotation
                |--------------------------------------------------------------------------
                */
                if (
                    ! $stageService
                        ->becameWon(
                            $lead
                        )
                ) {
                    return;
                }

                $quoteNumber =
                    $workflow
                        ->linkedQuoteNumber(
                            $lead->id
                        );

                $message =
                    $leadLabel
                    .(
                        $salesOwnerName
                            ? ' · Sales Owner: '
                                .$salesOwnerName
                            : ''
                    );

                if ($quoteNumber) {
                    $message .=
                        ' · '
                        .$quoteNumber
                        .' siap diproses ke Invoice.';
                } else {
                    $message .=
                        ' · Lead WON tetapi Quotation belum terhubung. Periksa Lead sebelum membuat Invoice.';
                }

                $notifications
                    ->notifyUsers(
                        $salesAdminIds,
                        'invoice_required',
                        'Lead WON - Buat Invoice',
                        $message,
                        $workflow
                            ->invoiceActionUrl(
                                $lead->id
                            ),
                        'lead-won-invoice:'
                            .$lead->id,
                        'lead',
                        $lead->id,
                        [
                            'sales_owner_id' => $lead->user_id
                                ?? null,

                            'quote_number' => $quoteNumber,

                            'commercial_action' => 'invoice',
                        ]
                    );
            }
        );
    }
}
