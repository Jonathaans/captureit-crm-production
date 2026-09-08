<?php

namespace Webkul\Admin\Http\Controllers\InternalCommunication;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\WorkflowNotification;
use Webkul\Admin\Services\InternalChatService;

class WorkflowNotificationController extends Controller
{
    public function index(): View
    {
        $user =
            $this->user();

        $notifications =
            WorkflowNotification::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->latest(
                    'id'
                )
                ->paginate(
                    50
                );

        return view(
            'admin::internal-communication.notifications',
            compact(
                'notifications'
            )
        );
    }

    public function poll(
        InternalChatService $chat
    ): JsonResponse {
        $user =
            $this->user();

        $claimed =
            DB::transaction(
                function () use ($user) {
                    $items =
                        WorkflowNotification::query()
                            ->where(
                                'user_id',
                                $user->id
                            )
                            ->whereNull(
                                'read_at'
                            )
                            ->whereNull(
                                'popup_at'
                            )
                            ->orderBy(
                                'id'
                            )
                            ->limit(
                                5
                            )
                            ->lockForUpdate()
                            ->get();

                    if ($items->isNotEmpty()) {
                        WorkflowNotification::query()
                            ->whereIn(
                                'id',
                                $items->pluck(
                                    'id'
                                )
                            )
                            ->update([
                                'popup_at' =>
                                    now(),
                            ]);
                    }

                    return $items;
                }
            );

        $notifications =
            $claimed
                ->map(
                    function ($notification) {
                        return [
                            'id' =>
                                $notification->id,

                            'type' =>
                                $notification->type,

                            'title' =>
                                $notification->title,

                            'message' =>
                                $notification->message,

                            'open_url' =>
                                route(
                                    'admin.internal-notifications.open',
                                    $notification->id
                                ),

                            'created_at' =>
                                $notification
                                    ->created_at
                                    ?->format(
                                        'Y-m-d H:i:s'
                                    ),
                        ];
                    }
                )
                ->values();

        $notificationUnread =
            WorkflowNotification::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNull(
                    'read_at'
                )
                ->count();

        return response()->json([
            'notifications' =>
                $notifications,

            'notification_unread' =>
                $notificationUnread,

            'chat_unread' =>
                $chat->unreadCount(
                    $user->id
                ),
        ]);
    }

    public function open(
        int $id
    ): RedirectResponse {
        $user =
            $this->user();

        $notification =
            WorkflowNotification::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->findOrFail(
                    $id
                );

        if (! $notification->read_at) {
            $notification->update([
                'read_at' =>
                    now(),
            ]);
        }

        $url =
            trim(
                (string) (
                    $notification
                        ->action_url
                    ?? ''
                )
            );

        /*
         * Prevent open redirects. Workflow actions must remain inside CRM admin.
         */
        $adminRoot =
            rtrim(
                url('/admin'),
                '/'
            );

        if (
            $url !== ''
            && (
                str_starts_with(
                    $url,
                    $adminRoot
                )
                || str_starts_with(
                    $url,
                    '/admin'
                )
            )
        ) {
            return redirect(
                $url
            );
        }

        return redirect()->route(
            'admin.internal-notifications.index'
        );
    }

    public function markAllRead(): RedirectResponse
    {
        $user =
            $this->user();

        WorkflowNotification::query()
            ->where(
                'user_id',
                $user->id
            )
            ->whereNull(
                'read_at'
            )
            ->update([
                'read_at' =>
                    now(),
            ]);

        session()->flash(
            'success',
            'Semua notifikasi ditandai sudah dibaca.'
        );

        return back();
    }

    private function user()
    {
        $user =
            auth()
                ->guard('user')
                ->user();

        abort_unless(
            $user,
            403
        );

        return $user;
    }
}
