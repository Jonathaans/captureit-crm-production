<?php

namespace Webkul\Admin\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\Http\Controllers\InternalCommunication\InternalChatAttachmentController;
use Webkul\Admin\Http\Controllers\InternalCommunication\InternalChatController;
use Webkul\Admin\Http\Controllers\InternalCommunication\InternalChatExperienceController;
use Webkul\Admin\Http\Controllers\InternalCommunication\InternalChatConversationController;
use Webkul\Admin\Http\Controllers\InternalCommunication\InternalChatAuditController;
use Webkul\Admin\Http\Controllers\InternalCommunication\WorkflowNotificationController;
use Webkul\Admin\Http\Middleware\InjectInternalCommunicationUi;
use Webkul\Admin\Services\LeadWonNotificationDetector;
use Webkul\Admin\Services\WorkflowNotificationService;
use Webkul\Admin\Models\InternalMessage;
use Webkul\Admin\Observers\InternalMessageAuditObserver;

class InternalCommunicationServiceProvider extends ServiceProvider
{
    /* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/internal_chat_realtime.php',
            'internal_chat_realtime'
        );
    }
    public function boot(
        Router $router
    ): void {
        /*
         * Global admin popup/chat widget without modifying the customized
         * Admin master layout.
         */
        $router->pushMiddlewareToGroup(
            'web',
            InjectInternalCommunicationUi::class
        );

        InternalMessage::observe(
            InternalMessageAuditObserver::class
        );

        $this->registerRoutes();

        $this->registerBusinessNotifications();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Webkul\Admin\Console\Commands\CrmInternalCommunicationCheckCommand::class,
            ]);
        }
    }

    private function registerRoutes(): void
    {
        Route::middleware(
            'web'
        )
            ->prefix(
                'admin'
            )
            ->group(
                function () {
                    Route::get(
                        'internal-notifications',
                        [
                            WorkflowNotificationController::class,
                            'index',
                        ]
                    )->name(
                        'admin.internal-notifications.index'
                    );

                    Route::get(
                        'internal-notifications/poll',
                        [
                            WorkflowNotificationController::class,
                            'poll',
                        ]
                    )->name(
                        'admin.internal-notifications.poll'
                    );

                    /* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */
                    Route::post(
                        'internal-notifications/{id}/popup-ack',
                        [
                            WorkflowNotificationController::class,
                            'popupAck',
                        ]
                    )->name(
                        'admin.internal-notifications.popup-ack'
                    );
                    Route::get(
                        'internal-notifications/{id}/open',
                        [
                            WorkflowNotificationController::class,
                            'open',
                        ]
                    )->name(
                        'admin.internal-notifications.open'
                    );

                    Route::post(
                        'internal-notifications/read-all',
                        [
                            WorkflowNotificationController::class,
                            'markAllRead',
                        ]
                    )->name(
                        'admin.internal-notifications.read-all'
                    );

                    Route::get(
                        'internal-chat',
                        [
                            InternalChatController::class,
                            'index',
                        ]
                    )->name(
                        'admin.internal-chat.index'
                    );

                                        Route::get(
                        'internal-chat/unread-summary',
                        [
                            InternalChatController::class,
                            'unreadSummary',
                        ]
                    )->name(
                        'admin.internal-chat.unread-summary'
                    );

                    Route::patch(
                        'internal-chat/{conversationId}/messages/{messageId}',
                        [
                            InternalChatController::class,
                            'updateMessage',
                        ]
                    )->name(
                        'admin.internal-chat.messages.update'
                    );

                    Route::delete(
                        'internal-chat/{conversationId}/messages/{messageId}',
                        [
                            InternalChatController::class,
                            'deleteMessage',
                        ]
                    )->name(
                        'admin.internal-chat.messages.delete'
                    );

Route::post(
                        'internal-chat/direct/{userId}',
                        [
                            InternalChatController::class,
                            'startDirect',
                        ]
                    )->name(
                        'admin.internal-chat.direct'
                    );

                    Route::get(
                        'internal-chat/{conversationId}/messages',
                        [
                            InternalChatController::class,
                            'messages',
                        ]
                    )->name(
                        'admin.internal-chat.messages'
                    );

                    Route::post(
                        'internal-chat/{conversationId}/messages',
                        [
                            InternalChatController::class,
                            'send',
                        ]
                    )->name(
                        'admin.internal-chat.send'
                    );

                    Route::get(
                        'internal-chat/attachments/{id}/download',
                        [
                            InternalChatAttachmentController::class,
                            'download',
                        ]
                    )->name(
                        'admin.internal-chat.attachments.download'
                    );

                    Route::get(
                        'internal-chat/{conversationId}/search',
                        [
                            InternalChatExperienceController::class,
                            'search',
                        ]
                    )->name(
                        'admin.internal-chat.search'
                    );

                    Route::post(
                        'internal-chat/{conversationId}/typing',
                        [
                            InternalChatExperienceController::class,
                            'typing',
                        ]
                    )->name(
                        'admin.internal-chat.typing'
                    );

                    Route::get(
                        'internal-chat/{conversationId}/typing-status',
                        [
                            InternalChatExperienceController::class,
                            'typingStatus',
                        ]
                    )->name(
                        'admin.internal-chat.typing-status'
                    );

                    Route::get(
                        'internal-chat/attachments/{id}/preview',
                        [
                            InternalChatExperienceController::class,
                            'previewAttachment',
                        ]
                    )->name(
                        'admin.internal-chat.attachments.preview'
                    );

                    Route::get(
                        'internal-chat/sidebar-summary',
                        [
                            InternalChatConversationController::class,
                            'sidebarSummary',
                        ]
                    )->name(
                        'admin.internal-chat.sidebar-summary'
                    );

                    Route::post(
                        'internal-chat/{conversationId}/preference',
                        [
                            InternalChatConversationController::class,
                            'updatePreference',
                        ]
                    )->name(
                        'admin.internal-chat.preference'
                    );

                    Route::post(
                        'internal-chat/presence/heartbeat',
                        [
                            InternalChatConversationController::class,
                            'heartbeat',
                        ]
                    )->name(
                        'admin.internal-chat.presence.heartbeat'
                    );

                    Route::get(
                        'operational-dashboard/internal-chat-audit',
                        [
                            InternalChatAuditController::class,
                            'index',
                        ]
                    )->name(
                        'admin.operational-dashboard.internal-chat-audit.index'
                    );

                    Route::get(
                        'operational-dashboard/internal-chat-audit/{messageId}',
                        [
                            InternalChatAuditController::class,
                            'show',
                        ]
                    )->name(
                        'admin.operational-dashboard.internal-chat-audit.show'
                    );
                }
            );
    }

    private function registerBusinessNotifications(): void
    {
                /*
        |--------------------------------------------------------------------------
        | LEAD COMMERCIAL WORKFLOW DELEGATED V1.1
        |--------------------------------------------------------------------------
        |
        | Lead QUOTATION and WON notifications are now handled by:
        | LeadCommercialWorkflowServiceProvider.
        |
        | SPK and Surat Jalan notifications below remain unchanged.
        |
        */
/*
        |--------------------------------------------------------------------------
        | SPK Released -> Sales Owner
        |--------------------------------------------------------------------------
        */

        $workOrderClass =
            \Webkul\Invoice\Models\WorkOrder::class;

        if (class_exists($workOrderClass)) {
            $workOrderClass::updated(
                function ($workOrder) {
                    if (
                        ! $workOrder->wasChanged(
                            'status'
                        )
                        || strtolower(
                            trim(
                                (string) $workOrder
                                    ->status
                            )
                        ) !== 'released'
                    ) {
                        return;
                    }

                    $salesOwnerId =
                        (int) (
                            $workOrder->user_id
                            ?? 0
                        );

                    if ($salesOwnerId < 1) {
                        return;
                    }

                    $message =
                        $workOrder->work_order_number
                        .(
                            $workOrder->project_code
                                ? ' · '
                                    .$workOrder
                                        ->project_code
                                : ''
                        )
                        .(
                            $workOrder->project_name
                                ? ' · '
                                    .$workOrder
                                        ->project_name
                                : ''
                        );

                    app(
                        WorkflowNotificationService::class
                    )->notifyUser(
                        $salesOwnerId,
                        'spk_released',
                        'SPK Dirilis',
                        $message,
                        route(
                            'admin.work-orders.show',
                            $workOrder->id
                        ),
                        'spk-released:'
                            .$workOrder->id,
                        'work_order',
                        $workOrder->id
                    );
                }
            );
        }

                /*
        |--------------------------------------------------------------------------
        | Surat Jalan Created -> Warehouse V1.2
        |--------------------------------------------------------------------------
        |
        | As soon as a Delivery Order / Surat Jalan record is created,
        | notify all active:
        | - Head Warehouse
        | - Warehouse User
        |
        | The existing RELEASED notification below remains unchanged.
        |
        */

        $deliveryOrderClass =
            \Webkul\Invoice\Models\DeliveryOrder::class;

        if (class_exists($deliveryOrderClass)) {
            $deliveryOrderClass::created(
                function ($deliveryOrder) {
                    $service =
                        app(
                            WorkflowNotificationService::class
                        );

                    $recipientIds =
                        $service->usersByRoleNames([
                            'Head Warehouse',
                            'Warehouse User',
                        ]);

                    if ($recipientIds->isEmpty()) {
                        return;
                    }

                    $number =
                        $deliveryOrder
                            ->delivery_order_number
                        ?? (
                            'SJ #'
                            .$deliveryOrder->id
                        );

                    $message =
                        $number;

                    $meta = [
                        'work_order_id' =>
                            $deliveryOrder
                                ->work_order_id
                            ?? null,

                        'invoice_id' =>
                            $deliveryOrder
                                ->invoice_id
                            ?? null,
                    ];

                    if (
                        ! empty(
                            $deliveryOrder
                                ->work_order_id
                        )
                    ) {
                        $workOrder =
                            \Illuminate\Support\Facades\DB::table(
                                'work_orders'
                            )
                                ->where(
                                    'id',
                                    $deliveryOrder
                                        ->work_order_id
                                )
                                ->first();

                        if ($workOrder) {
                            if (
                                ! empty(
                                    $workOrder
                                        ->work_order_number
                                )
                            ) {
                                $message .=
                                    ' · '
                                    .$workOrder
                                        ->work_order_number;
                            }

                            if (
                                ! empty(
                                    $workOrder
                                        ->project_code
                                )
                            ) {
                                $message .=
                                    ' · '
                                    .$workOrder
                                        ->project_code;
                            }

                            $meta['work_order_number'] =
                                $workOrder
                                    ->work_order_number
                                ?? null;

                            $meta['project_code'] =
                                $workOrder
                                    ->project_code
                                ?? null;
                        }
                    }

                    $message .=
                        ' · Surat Jalan baru dibuat dan menunggu proses Warehouse.';

                    $service->notifyUsers(
                        $recipientIds,
                        'delivery_order_created',
                        'Surat Jalan Baru Dibuat',
                        $message,
                        route(
                            'admin.delivery-orders.show',
                            $deliveryOrder->id
                        ),
                        'delivery-order-created:'
                            .$deliveryOrder->id,
                        'delivery_order',
                        $deliveryOrder->id,
                        $meta
                    );
                }
            );
        }
/*
        |--------------------------------------------------------------------------
        | Surat Jalan Released -> Warehouse
        |--------------------------------------------------------------------------
        */

        $deliveryOrderClass =
            \Webkul\Invoice\Models\DeliveryOrder::class;

        if (class_exists($deliveryOrderClass)) {
            $deliveryOrderClass::updated(
                function ($deliveryOrder) {
                    if (
                        ! $deliveryOrder->wasChanged(
                            'status'
                        )
                    ) {
                        return;
                    }

                    $status =
                        strtolower(
                            trim(
                                (string) (
                                    $deliveryOrder->status
                                    ?? ''
                                )
                            )
                        );

                    if (
                        ! in_array(
                            $status,
                            [
                                'released',
                                'release',
                            ],
                            true
                        )
                    ) {
                        return;
                    }

                    $service =
                        app(
                            WorkflowNotificationService::class
                        );

                    $recipientIds =
                        $service->usersByRoleNames([
                            'Head Warehouse',
                            'Warehouse User',
                        ]);

                    if ($recipientIds->isEmpty()) {
                        return;
                    }

                    $number =
                        $deliveryOrder
                            ->delivery_order_number
                        ?? (
                            'SJ #'
                            .$deliveryOrder->id
                        );

                    $message =
                        $number;

                    if (
                        ! empty(
                            $deliveryOrder
                                ->work_order_id
                        )
                    ) {
                        $workOrderNumber =
                            \Illuminate\Support\Facades\DB::table(
                                'work_orders'
                            )
                                ->where(
                                    'id',
                                    $deliveryOrder
                                        ->work_order_id
                                )
                                ->value(
                                    'work_order_number'
                                );

                        if ($workOrderNumber) {
                            $message .=
                                ' · '
                                .$workOrderNumber;
                        }
                    }

                    $service->notifyUsers(
                        $recipientIds,
                        'delivery_order_released',
                        'Surat Jalan Siap Diproses',
                        $message,
                        route(
                            'admin.delivery-orders.show',
                            $deliveryOrder->id
                        ),
                        'delivery-order-released:'
                            .$deliveryOrder->id,
                        'delivery_order',
                        $deliveryOrder->id
                    );
                }
            );
        }
    }
}
