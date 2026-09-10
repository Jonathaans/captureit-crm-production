<?php

namespace Webkul\Admin\Http\Controllers\InternalCommunication;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Admin\Http\Controllers\Controller;

class InternalChatConversationController extends Controller
{
    public function sidebarSummary(): JsonResponse
    {
        $user =
            $this->user();

        $hasCursor =
            Schema::hasColumn(
                'internal_conversation_members',
                'last_read_message_id'
            );

        $select = [
            'conversation.id',
            'conversation.updated_at',
            'member.last_read_at',
            'member.pinned_at',
            'member.muted_until',
            'member.mute_forever',
        ];

        if ($hasCursor) {
            $select[] =
                'member.last_read_message_id';
        }

        $rows =
            DB::table(
                'internal_conversation_members as member'
            )
                ->join(
                    'internal_conversations as conversation',
                    'conversation.id',
                    '=',
                    'member.conversation_id'
                )
                ->where(
                    'member.user_id',
                    $user->id
                )
                ->where(
                    'conversation.type',
                    'direct'
                )
                ->select(
                    $select
                )
                ->get();

        $hasPresenceTable =
            Schema::hasTable(
                'internal_chat_user_states'
            );

        $conversations =
            $rows
                ->map(
                    function ($row) use (
                        $user,
                        $hasPresenceTable,
                        $hasCursor
                    ) {
                        $other =
                            DB::table(
                                'internal_conversation_members as member'
                            )
                                ->join(
                                    'users',
                                    'users.id',
                                    '=',
                                    'member.user_id'
                                )
                                ->leftJoin(
                                    'roles',
                                    'roles.id',
                                    '=',
                                    'users.role_id'
                                )
                                ->where(
                                    'member.conversation_id',
                                    $row->id
                                )
                                ->where(
                                    'member.user_id',
                                    '<>',
                                    $user->id
                                )
                                ->select([
                                    'users.id',
                                    'users.name',
                                    'users.email',
                                    'roles.name as role_name',
                                ])
                                ->first();

                        $lastMessage =
                            DB::table(
                                'internal_messages'
                            )
                                ->where(
                                    'conversation_id',
                                    $row->id
                                )
                                ->whereNull(
                                    'deleted_at'
                                )
                                ->orderByDesc(
                                    'id'
                                )
                                ->first([
                                    'id',
                                    'user_id',
                                    'body',
                                    'created_at',
                                ]);

                        /*
                         * V3.3.9 unread cursor.
                         *
                         * Timestamp-only unread tracking is ambiguous when
                         * last_read_at is null and can make old messages appear
                         * unread in every conversation. Message-id cursor makes
                         * unread state conversation-specific and deterministic.
                         */
                        $readCursor =
                            $hasCursor
                                ? max(
                                    0,
                                    (int) (
                                        $row->last_read_message_id
                                        ?? 0
                                    )
                                )
                                : 0;

                        $unreadQuery =
                            DB::table(
                                'internal_messages'
                            )
                                ->where(
                                    'conversation_id',
                                    $row->id
                                )
                                ->where(
                                    'user_id',
                                    '<>',
                                    $user->id
                                )
                                ->whereNull(
                                    'deleted_at'
                                );

                        if ($hasCursor) {
                            $unreadQuery->where(
                                'id',
                                '>',
                                $readCursor
                            );
                        } elseif (
                            ! empty(
                                $row->last_read_at
                            )
                        ) {
                            $unreadQuery->where(
                                'created_at',
                                '>',
                                $row->last_read_at
                            );
                        }

                        $unread =
                            (int) $unreadQuery
                                ->count();

                        $preview =
                            trim(
                                (string) (
                                    $lastMessage?->body
                                    ?? ''
                                )
                            );

                        if (
                            $preview === ''
                            && $lastMessage
                        ) {
                            $hasAttachment =
                                DB::table(
                                    'internal_message_attachments'
                                )
                                    ->where(
                                        'message_id',
                                        $lastMessage->id
                                    )
                                    ->exists();

                            $preview =
                                $hasAttachment
                                    ? '📎 Attachment'
                                    : 'Pesan';
                        }

                        if ($preview === '') {
                            $preview =
                                'Belum ada pesan.';
                        }

                        $lastAt =
                            $lastMessage?->created_at
                            ?? $row->updated_at;

                        $timeLabel =
                            '';

                        if ($lastAt) {
                            $time =
                                Carbon::parse(
                                    $lastAt
                                );

                            $timeLabel =
                                $time->isToday()
                                    ? $time->format(
                                        'H:i'
                                    )
                                    : $time->format(
                                        'd M'
                                    );
                        }

                        $muted =
                            $this->isMuted(
                                $row->muted_until,
                                (bool) $row->mute_forever
                            );

                        $otherUserId =
                            (int) (
                                $other?->id
                                ?? 0
                            );

                        $presence =
                            $this->presenceState(
                                $otherUserId,
                                $hasPresenceTable
                            );

                        return [
                            'id' =>
                                (int) $row->id,

                            'name' =>
                                (string) (
                                    $other?->name
                                    ?: 'User'
                                ),

                            'email' =>
                                (string) (
                                    $other?->email
                                    ?: ''
                                ),

                            'role' =>
                                (string) (
                                    $other?->role_name
                                    ?: 'Internal User'
                                ),

                            'initials' =>
                                $this->initials(
                                    (string) (
                                        $other?->name
                                        ?: 'User'
                                    )
                                ),

                            'preview' =>
                                mb_strimwidth(
                                    preg_replace(
                                        '/\s+/',
                                        ' ',
                                        $preview
                                    ),
                                    0,
                                    56,
                                    '…'
                                ),

                            'time' =>
                                $timeLabel,

                            'unread' =>
                                $unread,

                            'read_cursor' =>
                                $readCursor,

                            'pinned' =>
                                ! empty(
                                    $row->pinned_at
                                ),

                            'muted' =>
                                $muted,

                            'mute_label' =>
                                $this->muteLabel(
                                    $row->muted_until,
                                    (bool) $row->mute_forever
                                ),

                            'online' =>
                                $presence['state']
                                === 'online',

                            'idle' =>
                                $presence['state']
                                === 'idle',

                            'in_chat' =>
                                $presence['in_chat'],

                            'presence_state' =>
                                $presence['state'],

                            'presence' =>
                                $presence['label'],

                            'sort_at' =>
                                $lastAt
                                    ? Carbon::parse(
                                        $lastAt
                                    )->format(
                                        'Y-m-d H:i:s.u'
                                    )
                                    : '',
                        ];
                    }
                )
                ->sort(
                    function (
                        array $a,
                        array $b
                    ) {
                        if (
                            $a['pinned']
                            !== $b['pinned']
                        ) {
                            return $a['pinned']
                                ? -1
                                : 1;
                        }

                        return strcmp(
                            $b['sort_at'],
                            $a['sort_at']
                        );
                    }
                )
                ->values();

        return response()->json([
            'conversations' =>
                $conversations,

            'total_unread' =>
                (int) $conversations
                    ->sum(
                        'unread'
                    ),
        ]);
    }

    public function updatePreference(
        Request $request,
        int $conversationId
    ): JsonResponse|RedirectResponse {
        $user =
            $this->user();

        $this->assertMember(
            $conversationId,
            (int) $user->id
        );

        $action =
            strtolower(
                trim(
                    (string) $request->input(
                        'action',
                        ''
                    )
                )
            );

        $allowed = [
            'pin',
            'unpin',
            'mute_1_hour',
            'mute_today',
            'mute_forever',
            'unmute',
        ];

        abort_unless(
            in_array(
                $action,
                $allowed,
                true
            ),
            422,
            'Conversation preference tidak valid.'
        );

        $values = [];

        if ($action === 'pin') {
            $values['pinned_at'] =
                now();
        }

        if ($action === 'unpin') {
            $values['pinned_at'] =
                null;
        }

        if ($action === 'mute_1_hour') {
            $values['muted_until'] =
                now()->addHour();

            $values['mute_forever'] =
                false;
        }

        if ($action === 'mute_today') {
            $values['muted_until'] =
                now()->endOfDay();

            $values['mute_forever'] =
                false;
        }

        if ($action === 'mute_forever') {
            $values['muted_until'] =
                null;

            $values['mute_forever'] =
                true;
        }

        if ($action === 'unmute') {
            $values['muted_until'] =
                null;

            $values['mute_forever'] =
                false;
        }

        $updated =
            DB::table(
                'internal_conversation_members'
            )
                ->where(
                    'conversation_id',
                    $conversationId
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->update(
                    $values
                );

        if ($request->expectsJson()) {
            return response()->json([
                'ok' =>
                    true,

                'updated' =>
                    $updated,

                'action' =>
                    $action,
            ]);
        }

        /* INTERNAL CHAT V3.3.7 PREFERENCE NOTICE */
        $notice =
            match ($action) {
                'pin' =>
                    'Conversation berhasil dipin.',

                'unpin' =>
                    'Pin conversation berhasil dilepas.',

                'mute_1_hour' =>
                    'Conversation dimute selama 1 jam.',

                'mute_today' =>
                    'Conversation dimute sampai akhir hari.',

                'mute_forever' =>
                    'Conversation dimute sampai diaktifkan kembali.',

                'unmute' =>
                    'Mute conversation berhasil dinonaktifkan.',

                default =>
                    'Conversation preference diperbarui.',
            };

        return redirect()
            ->back()
            ->with(
                'internal_chat_preference_notice',
                $notice
            );
    }

    public function heartbeat(
        Request $request
    ): JsonResponse {
        $user =
            $this->user();

        if (
            ! $request->has(
                'idle_seconds'
            )
        ) {
            return response()->json([
                'ok' =>
                    true,

                'ignored_legacy_heartbeat' =>
                    true,
            ]);
        }

        $idleSeconds =
            max(
                0,
                min(
                    86400,
                    (int) $request->input(
                        'idle_seconds',
                        0
                    )
                )
            );

        $inChat =
            $request->boolean(
                'in_chat',
                false
            );

        Cache::put(
            $this->presenceKey(
                (int) $user->id
            ),
            [
                'user_id' =>
                    (int) $user->id,

                'name' =>
                    (string) (
                        $user->name
                        ?: 'User'
                    ),

                'heartbeat_at' =>
                    now()->toIso8601String(),

                'idle_seconds' =>
                    $idleSeconds,

                'in_chat' =>
                    $inChat,
            ],
            now()->addMinutes(
                6
            )
        );

        if (
            $idleSeconds <= 60
            && Schema::hasTable(
                'internal_chat_user_states'
            )
        ) {
            $activityAt =
                now()->subSeconds(
                    $idleSeconds
                );

            $exists =
                DB::table(
                    'internal_chat_user_states'
                )
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->exists();

            if ($exists) {
                DB::table(
                    'internal_chat_user_states'
                )
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->update([
                        'last_seen_at' =>
                            $activityAt,

                        'updated_at' =>
                            now(),
                    ]);
            } else {
                DB::table(
                    'internal_chat_user_states'
                )
                    ->insert([
                        'user_id' =>
                            $user->id,

                        'last_seen_at' =>
                            $activityAt,

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);
            }
        }

        return response()->json([
            'ok' =>
                true,

            'idle_seconds' =>
                $idleSeconds,

            'in_chat' =>
                $inChat,
        ]);
    }

    private function presenceState(
        int $userId,
        bool $hasPresenceTable
    ): array {
        if ($userId < 1) {
            return [
                'state' =>
                    'offline',

                'in_chat' =>
                    false,

                'label' =>
                    'Last active belum tercatat',
            ];
        }

        $cached =
            Cache::get(
                $this->presenceKey(
                    $userId
                )
            );

        if (
            is_array(
                $cached
            )
            && ! empty(
                $cached['heartbeat_at']
            )
        ) {
            $heartbeatAge =
                max(
                    0,
                    Carbon::parse(
                        $cached['heartbeat_at']
                    )->diffInSeconds(
                        now()
                    )
                );

            $effectiveIdle =
                max(
                    0,
                    (int) (
                        $cached['idle_seconds']
                        ?? 0
                    )
                    + $heartbeatAge
                );

            $inChat =
                (bool) (
                    $cached['in_chat']
                    ?? false
                );

            if ($effectiveIdle < 60) {
                return [
                    'state' =>
                        'online',

                    'in_chat' =>
                        $inChat,

                    'label' =>
                        $inChat
                            ? 'Online · In Chat'
                            : 'Online',
                ];
            }

            if ($effectiveIdle <= 300) {
                return [
                    'state' =>
                        'idle',

                    'in_chat' =>
                        $inChat,

                    'label' =>
                        $inChat
                            ? 'Idle · In Chat'
                            : 'Idle',
                ];
            }
        }

        $lastSeen =
            null;

        if ($hasPresenceTable) {
            $lastSeen =
                DB::table(
                    'internal_chat_user_states'
                )
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->value(
                        'last_seen_at'
                    );
        }

        return [
            'state' =>
                'offline',

            'in_chat' =>
                false,

            'label' =>
                $this->lastActiveLabel(
                    $lastSeen
                ),
        ];
    }

    private function lastActiveLabel(
        mixed $lastSeen
    ): string {
        if (! $lastSeen) {
            return 'Last active belum tercatat';
        }

        $time =
            Carbon::parse(
                $lastSeen
            );

        if ($time->isToday()) {
            return 'Last active '
                .$time->format(
                    'H:i'
                );
        }

        return 'Last active '
            .$time->format(
                'd M H:i'
            );
    }

    private function isMuted(
        mixed $mutedUntil,
        bool $muteForever
    ): bool {
        if ($muteForever) {
            return true;
        }

        if (! $mutedUntil) {
            return false;
        }

        return Carbon::parse(
            $mutedUntil
        )->isFuture();
    }

    private function muteLabel(
        mixed $mutedUntil,
        bool $muteForever
    ): string {
        if ($muteForever) {
            return 'Muted';
        }

        if (! $mutedUntil) {
            return '';
        }

        $until =
            Carbon::parse(
                $mutedUntil
            );

        if (! $until->isFuture()) {
            return '';
        }

        if ($until->isToday()) {
            return 'Muted sampai '
                .$until->format(
                    'H:i'
                );
        }

        return 'Muted sampai '
            .$until->format(
                'd M H:i'
            );
    }

    private function initials(
        string $name
    ): string {
        $parts =
            preg_split(
                '/\s+/',
                trim(
                    $name
                )
            )
            ?: [];

        $initials = '';

        foreach (
            array_slice(
                $parts,
                0,
                2
            )
            as $part
        ) {
            $initials .=
                strtoupper(
                    mb_substr(
                        $part,
                        0,
                        1
                    )
                );
        }

        return $initials
            ?: 'U';
    }

    private function assertMember(
        int $conversationId,
        int $userId
    ): void {
        $member =
            DB::table(
                'internal_conversation_members'
            )
                ->where(
                    'conversation_id',
                    $conversationId
                )
                ->where(
                    'user_id',
                    $userId
                )
                ->exists();

        abort_unless(
            $member,
            403
        );
    }

    private function presenceKey(
        int $userId
    ): string {
        return 'internal-chat:presence:'
            .$userId;
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
