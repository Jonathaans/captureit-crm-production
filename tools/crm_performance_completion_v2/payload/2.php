<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use Webkul\Admin\Events\InternalChatRealtimeEvent;
use Webkul\Admin\Models\WorkflowNotification;

class InternalChatRealtimeService
{
    public function conversationChanged(
        int $conversationId,
        string $reason,
        int $actorUserId = 0,
    ): void {
        if ($conversationId < 1 || ! $this->enabled()) {
            return;
        }

        if (! $this->emit(
            'internal-chat.conversation.'.$conversationId,
            'conversation.changed',
            [
                'conversation_id' => $conversationId,
                'reason' => $reason,
                'actor_user_id' => $actorUserId,
            ],
        )) {
            // A failed synchronous transport must not be retried for every
            // member during this call. The next request can try again.
            return;
        }

        // CRM_CHAT_BACKEND_PERFORMANCE_V2: calculate totals once for all recipients.
        $userIds = $this->conversationUserIds($conversationId)->all();
        $counts = $this->unreadCountsForUsers($userIds);

        foreach ($userIds as $userId) {
            if (! $this->emit('internal-chat.user.'.$userId, 'user.state', [
                'user_id' => $userId,
                'reason' => $reason,
                'conversation_id' => $conversationId,
                'chat_unread' => $counts[$userId]['chat_unread'],
                'notification_unread' => $counts[$userId]['notification_unread'],
            ])) {
                break;
            }
        }
    }

    public function typingChanged(
        int $conversationId,
        int $userId,
        string $name,
        bool $typing,
    ): void {
        if ($conversationId < 1 || $userId < 1) {
            return;
        }

        $this->emit(
            'internal-chat.conversation.'.$conversationId,
            'conversation.typing',
            [
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'name' => $name !== '' ? $name : 'User',
                'typing' => $typing,
            ],
        );
    }

    public function workflowNotificationCreated(
        WorkflowNotification $notification,
    ): void {
        $userId = (int) $notification->user_id;

        if ($userId < 1 || ! $this->enabled()) {
            return;
        }

        $this->userStateChanged(
            $userId,
            'workflow.notification.created',
            null,
            [
                'id' => (int) $notification->id,
                'type' => (string) $notification->type,
                'title' => (string) $notification->title,
                'message' => (string) ($notification->message ?? ''),
                'open_url' => route(
                    'admin.internal-notifications.open',
                    $notification->id,
                ),
                'ack_url' => route(
                    'admin.internal-notifications.popup-ack',
                    $notification->id,
                ),
                'created_at' => $notification->created_at?->toIso8601String(),
            ],
        );
    }

    public function userStateChanged(
        int $userId,
        string $reason,
        ?int $conversationId = null,
        ?array $notification = null,
    ): void {
        if ($userId < 1 || ! $this->enabled()) {
            return;
        }

        $counts = $this->unreadCountsForUsers([$userId]);
        $payload = [
            'user_id' => $userId,
            'reason' => $reason,
            'conversation_id' => $conversationId,
            'chat_unread' => $counts[$userId]['chat_unread'],
            'notification_unread' => $counts[$userId]['notification_unread'],
        ];

        if ($notification !== null) {
            $payload['notification'] = $notification;
        }

        $this->emit(
            'internal-chat.user.'.$userId,
            'user.state',
            $payload,
        );
    }

    /** CRM_CHAT_BACKEND_PERFORMANCE_V2: query count is independent of recipient count. */
    private function unreadCountsForUsers(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            fn (int $id) => $id > 0,
        )));
        $counts = array_fill_keys($userIds, ['chat_unread' => 0, 'notification_unread' => 0]);

        if ($userIds === []) {
            return $counts;
        }

        if (Schema::hasTable('internal_conversation_members') && Schema::hasTable('internal_messages')) {
            $query = DB::table('internal_conversation_members as member')
                ->join('internal_messages as message', 'message.conversation_id', '=', 'member.conversation_id')
                ->whereIn('member.user_id', $userIds)
                ->whereColumn('message.user_id', '<>', 'member.user_id')
                ->whereNull('message.deleted_at');

            if (Schema::hasColumn('internal_conversation_members', 'last_read_message_id')) {
                $query->whereRaw('message.id > COALESCE(member.last_read_message_id, 0)');
            } else {
                $query->where(function ($nested) {
                    $nested->whereNull('member.last_read_at')
                        ->orWhereColumn('message.created_at', '>', 'member.last_read_at');
                });
            }

            foreach ($query->groupBy('member.user_id')
                ->selectRaw('member.user_id, COUNT(*) as unread_count')->get() as $row) {
                $counts[(int) $row->user_id]['chat_unread'] = (int) $row->unread_count;
            }
        }

        if (Schema::hasTable((new WorkflowNotification)->getTable())) {
            foreach (WorkflowNotification::query()->whereIn('user_id', $userIds)
                ->whereNull('read_at')->groupBy('user_id')
                ->selectRaw('user_id, COUNT(*) as unread_count')->get() as $row) {
                $counts[(int) $row->user_id]['notification_unread'] = (int) $row->unread_count;
            }
        }

        return $counts;
    }

    private function conversationUserIds(int $conversationId)
    {
        if (! Schema::hasTable('internal_conversation_members')) {
            return collect();
        }

        return DB::table('internal_conversation_members')
            ->where('conversation_id', $conversationId)
            ->pluck('user_id')
            ->map(fn ($userId) => (int) $userId)
            ->filter()
            ->unique()
            ->values();
    }

    private function emit(
        string $channel,
        string $kind,
        array $payload,
    ): bool {
        if (! $this->enabled()) {
            return false;
        }

        $broadcast = static function () use ($channel, $kind, $payload): bool {
            try {
                event(new InternalChatRealtimeEvent(
                    $channel,
                    $kind,
                    $payload,
                    (string) Str::uuid(),
                ));
                return true;
            } catch (Throwable $exception) {
                Log::warning('Internal Chat realtime broadcast failed; clients will resync after WebSocket reconnect.', [
                    'channel' => $channel,
                    'kind' => $kind,
                    'exception' => $exception->getMessage(),
                ]);
                return false;
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($broadcast);

            return true;
        }

        return $broadcast();
    }

    private function enabled(): bool
    {
        if (! (bool) config('internal_chat_realtime.enabled', true)) {
            return false;
        }

        $connection = (string) config('broadcasting.default', 'null');

        return in_array($connection, ['reverb', 'pusher'], true)
            && filled(config('broadcasting.connections.'.$connection.'.key'));
    }
}
