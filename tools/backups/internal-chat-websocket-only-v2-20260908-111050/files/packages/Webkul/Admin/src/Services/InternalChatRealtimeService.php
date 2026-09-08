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
        if ($conversationId < 1) {
            return;
        }

        $this->emit(
            'internal-chat.conversation.'.$conversationId,
            'conversation.changed',
            [
                'conversation_id' => $conversationId,
                'reason' => $reason,
                'actor_user_id' => $actorUserId,
            ],
        );

        $this->conversationUserIds($conversationId)
            ->each(fn (int $userId) => $this->userStateChanged(
                $userId,
                $reason,
                $conversationId,
            ));
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

        if ($userId < 1) {
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
        if ($userId < 1) {
            return;
        }

        $payload = [
            'user_id' => $userId,
            'reason' => $reason,
            'conversation_id' => $conversationId,
            'chat_unread' => $this->chatUnreadCount($userId),
            'notification_unread' => $this->notificationUnreadCount($userId),
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

    private function chatUnreadCount(int $userId): int
    {
        if (
            ! Schema::hasTable('internal_conversation_members')
            || ! Schema::hasTable('internal_messages')
        ) {
            return 0;
        }

        $query = DB::table('internal_conversation_members as member')
            ->join(
                'internal_messages as message',
                'message.conversation_id',
                '=',
                'member.conversation_id',
            )
            ->where('member.user_id', $userId)
            ->where('message.user_id', '<>', $userId)
            ->whereNull('message.deleted_at');

        if (Schema::hasColumn('internal_conversation_members', 'last_read_message_id')) {
            $query->whereRaw(
                'message.id > COALESCE(member.last_read_message_id, 0)',
            );
        } else {
            $query->where(function ($nested) {
                $nested
                    ->whereNull('member.last_read_at')
                    ->orWhereColumn(
                        'message.created_at',
                        '>',
                        'member.last_read_at',
                    );
            });
        }

        return (int) $query->count();
    }

    private function notificationUnreadCount(int $userId): int
    {
        if (! Schema::hasTable('workflow_notifications')) {
            return 0;
        }

        return (int) WorkflowNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
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
    ): void {
        if (! $this->enabled()) {
            return;
        }

        $broadcast = static function () use ($channel, $kind, $payload): void {
            try {
                event(new InternalChatRealtimeEvent(
                    $channel,
                    $kind,
                    $payload,
                    (string) Str::uuid(),
                ));
            } catch (Throwable $exception) {
                Log::warning('Internal Chat realtime broadcast failed; HTTP fallback remains active.', [
                    'channel' => $channel,
                    'kind' => $kind,
                    'exception' => $exception->getMessage(),
                ]);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($broadcast);

            return;
        }

        $broadcast();
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
