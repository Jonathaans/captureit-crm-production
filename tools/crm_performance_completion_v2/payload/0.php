<?php

namespace Webkul\Admin\Http\Controllers\InternalCommunication;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\InternalConversation;
use Webkul\Admin\Models\InternalConversationMember;
use Webkul\Admin\Models\InternalMessage;
use Webkul\Admin\Services\InternalChatService;
use Webkul\Admin\Services\InternalChatRealtimeService;
use Webkul\Admin\Services\WorkflowNotificationService;

class InternalChatController extends Controller
{
    public function index(
        Request $request,
        InternalChatService $chat
    ): View {
        $user = $this->user();

        $conversationId = $request->integer('conversation');

        $conversation = null;
                    /*
             * INTERNAL CHAT LATEST 50 V1.5
             * Initial room memuat 50 pesan terbaru saja.
             */
$messages = collect();

        if ($conversationId > 0) {
            $chat->assertMember($conversationId, $user->id);

            $conversation = InternalConversation::query()
                ->findOrFail($conversationId);

                        /*
             * INTERNAL CHAT OPEN LATEST V1.1
             * Initial render mengambil 300 pesan terbaru lalu menampilkannya
             * kembali secara ascending.
             */
$messages =
                InternalMessage::query()
                    ->with(
                        'attachments'
                    )
                    ->where(
                        'conversation_id',
                        $conversationId
                    )
                    ->whereNull(
                        'deleted_at'
                    )
                    ->orderByDesc(
                        'id'
                    )
                    ->limit(
                        50
                    )
                    ->get()
                    ->sortBy(
                        'id'
                    )
                    ->values();

            $this->markConversationRead(
                $chat,
                $conversationId,
                (int) $user->id
            );
        }

        $users = DB::table('users')
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.id', '<>', $user->id)
            ->when(
                Schema::hasColumn('users', 'status'),
                fn ($query) => $query->where('users.status', 1)
            )
            ->orderBy('users.name')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'roles.name as role_name',
            ])
            ->get();

        $conversationRows = DB::table('internal_conversation_members as m')
            ->join(
                'internal_conversations as c',
                'c.id',
                '=',
                'm.conversation_id'
            )
            ->where('m.user_id', $user->id)
            ->where('c.type', 'direct')
            ->orderByDesc('c.updated_at')
            ->select([
                'c.id',
                'c.updated_at',
                'm.last_read_at',
            ])
            ->get();

        /* CRM_TARGETED_PERFORMANCE_OPTIMIZATION_V1: batched initial chat list */
        $conversationIds = $conversationRows
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $otherUsersByConversation = $conversationIds->isEmpty()
            ? collect()
            : DB::table('internal_conversation_members as member')
                ->join('users', 'users.id', '=', 'member.user_id')
                ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->whereIn('member.conversation_id', $conversationIds->all())
                ->where('member.user_id', '<>', $user->id)
                ->select([
                    'member.conversation_id',
                    'users.id',
                    'users.name',
                    'users.email',
                    'roles.name as role_name',
                ])
                ->get()
                ->keyBy('conversation_id');

        $lastMessageIds = $conversationIds->isEmpty()
            ? collect()
            : DB::table('internal_messages')
                ->whereIn('conversation_id', $conversationIds->all())
                ->whereNull('deleted_at')
                ->groupBy('conversation_id')
                ->selectRaw('conversation_id, MAX(id) as last_message_id')
                ->pluck('last_message_id');

        $lastMessagesByConversation = $lastMessageIds->isEmpty()
            ? collect()
            : DB::table('internal_messages')
                ->whereIn('id', $lastMessageIds->map(fn ($id) => (int) $id)->all())
                ->get()
                ->keyBy('conversation_id');

        $hasReadCursor = Schema::hasColumn(
            'internal_conversation_members',
            'last_read_message_id'
        );

        $unreadCountsByConversation = collect();

        if ($conversationIds->isNotEmpty()) {
            $unreadQuery = DB::table('internal_messages as message')
                ->join('internal_conversation_members as self_member', function ($join) use ($user) {
                    $join->on('self_member.conversation_id', '=', 'message.conversation_id')
                        ->where('self_member.user_id', '=', (int) $user->id);
                })
                ->whereIn('message.conversation_id', $conversationIds->all())
                ->where('message.user_id', '<>', $user->id)
                ->whereNull('message.deleted_at');

            if ($hasReadCursor) {
                $unreadQuery->whereRaw(
                    'message.id > COALESCE(self_member.last_read_message_id, 0)'
                );
            } else {
                $unreadQuery->where(function ($query) {
                    $query->whereNull('self_member.last_read_at')
                        ->orWhereColumn('message.created_at', '>', 'self_member.last_read_at');
                });
            }

            $unreadCountsByConversation = $unreadQuery
                ->groupBy('message.conversation_id')
                ->selectRaw('message.conversation_id, COUNT(*) as unread_count')
                ->get()
                ->mapWithKeys(fn ($row) => [
                    (int) $row->conversation_id => (int) $row->unread_count,
                ]);
        }

        $conversationList = $conversationRows->map(
            fn ($row) => (object) [
                'id' => (int) $row->id,
                'other' => $otherUsersByConversation->get((int) $row->id),
                'last_message' => $lastMessagesByConversation->get((int) $row->id),
                'unread_count' => (int) $unreadCountsByConversation->get((int) $row->id, 0),
            ]
        );
        $senderIds = $messages
            ->pluck('user_id')
            ->unique()
            ->values();

        $senderNames = $senderIds->isEmpty()
            ? collect()
            : DB::table('users')
                ->whereIn('id', $senderIds)
                ->pluck('name', 'id');

        $replyIds = $messages
            ->pluck('reply_to_message_id')
            ->filter()
            ->unique()
            ->values();

        $replyMessages = $replyIds->isEmpty()
            ? collect()
            : InternalMessage::query()
                ->where('conversation_id', $conversationId)
                ->whereIn('id', $replyIds)
                ->get()
                ->keyBy('id');

        $replySenderIds = $replyMessages
            ->pluck('user_id')
            ->unique()
            ->values();

        $replySenderNames = $replySenderIds->isEmpty()
            ? collect()
            : DB::table('users')
                ->whereIn('id', $replySenderIds)
                ->pluck('name', 'id');

        $activeOtherUser = null;
        $activeReadUpToId = 0;

        if ($conversation) {
            $activeOtherUser = DB::table('internal_conversation_members as m')
                ->join('users', 'users.id', '=', 'm.user_id')
                ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->where('m.conversation_id', $conversation->id)
                ->where('m.user_id', '<>', $user->id)
                ->select([
                    'users.id',
                    'users.name',
                    'users.email',
                    'roles.name as role_name',
                    'm.last_read_at',
                ])
                ->first();

            $activeReadUpToId = $this->readUpToMessageId(
                (int) $conversation->id,
                (int) $user->id,
                $activeOtherUser?->last_read_at
            );
        }

        return view(
            'admin::internal-communication.chat',
            compact(
                'conversation',
                'conversationList',
                'messages',
                'senderNames',
                'replyMessages',
                'replySenderNames',
                'activeOtherUser',
                'activeReadUpToId',
                'users'
            )
        );
    }

    public function startDirect(
        int $userId,
        InternalChatService $chat
    ): RedirectResponse {
        $user = $this->user();

        $conversation = $chat->directConversation(
            $user->id,
            $userId
        );

        return redirect()->route(
            'admin.internal-chat.index',
            [
                'conversation' => $conversation->id,
            ]
        );
    }

    public function send(
        Request $request,
        int $conversationId,
        InternalChatService $chat,
        WorkflowNotificationService $notifications
    ): JsonResponse|RedirectResponse {
        $user = $this->user();

        $validated = $request->validate([
            'body' => [
                'nullable',
                'string',
                'max:20000',
            ],
            'attachments' => [
                'nullable',
                'array',
                'max:5',
            ],
            'attachments.*' => [
                'file',
                'max:10240',
            ],
            'reply_to_message_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $replyToMessageId =
            (int) (
                $validated['reply_to_message_id']
                ?? 0
            );

        if ($replyToMessageId > 0) {
            InternalMessage::query()
                ->where('id', $replyToMessageId)
                ->where('conversation_id', $conversationId)
                ->whereNull('deleted_at')
                ->firstOrFail();
        }

        $message = $chat->sendMessage(
            $conversationId,
            $user->id,
            $validated['body'] ?? null,
            $request->file('attachments', [])
        );

        if ($replyToMessageId > 0) {
            $message->reply_to_message_id =
                $replyToMessageId;

            $message->save();
        }

        $recipientIds = InternalConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', '<>', $user->id)
            ->pluck('user_id');

        foreach ($recipientIds as $recipientId) {
            /* INTERNAL CHAT V3.3 MUTE NOTIFICATION GUARD */
            if (
                \Illuminate\Support\Facades\Schema::hasColumn(
                    'internal_conversation_members',
                    'mute_forever'
                )
                && \Illuminate\Support\Facades\Schema::hasColumn(
                    'internal_conversation_members',
                    'muted_until'
                )
            ) {
                $memberPreference =
                    \Illuminate\Support\Facades\DB::table(
                        'internal_conversation_members'
                    )
                        ->where(
                            'conversation_id',
                            $conversationId
                        )
                        ->where(
                            'user_id',
                            $recipientId
                        )
                        ->first([
                            'mute_forever',
                            'muted_until',
                        ]);

                $muted =
                    $memberPreference
                    && (
                        (bool) $memberPreference
                            ->mute_forever
                        || (
                            $memberPreference
                                ->muted_until
                            && now()->lt(
                                \Illuminate\Support\Carbon::parse(
                                    $memberPreference
                                        ->muted_until
                                )
                            )
                        )
                    );

                if ($muted) {
                    continue;
                }
            }
            $notifications->notifyUser(
                (int) $recipientId,
                'internal_chat',
                'Pesan Internal Baru',
                $user->name.' mengirim pesan internal.',
                route(
                    'admin.internal-chat.index',
                    [
                        'conversation' => $conversationId,
                    ]
                ),
                'internal-chat-message:'.$message->id,
                'internal_message',
                $message->id,
                [
                    'sender_user_id' => $user->id,
                    'conversation_id' => $conversationId,
                ]
            );
        }

        /* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */
        app(InternalChatRealtimeService::class)->conversationChanged(
            $conversationId,
            'message.created',
            (int) $user->id
        );

        if ($request->expectsJson()) {
            return response()->json(
                $this->messagePayload(
                    $message->fresh('attachments'),
                    $user->name
                )
            );
        }

        return redirect()->route(
            'admin.internal-chat.index',
            [
                'conversation' => $conversationId,
            ]
        );
    }

    public function messages(
        Request $request,
        int $conversationId,
        InternalChatService $chat
    ): JsonResponse {
        $user = $this->user();

        $chat->assertMember($conversationId, $user->id);

        $after = max(0, $request->integer('after'));

        $syncAfter =
            trim(
                (string) $request->query(
                    'sync_after',
                    ''
                )
            );

        $messages = InternalMessage::query()
            ->with('attachments')
            ->where('conversation_id', $conversationId)
            ->where('id', '>', $after)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->limit(100)
            ->get();

        $senderNames = DB::table('users')
            ->whereIn(
                'id',
                $messages
                    ->pluck('user_id')
                    ->unique()
                    ->all()
            )
            ->pluck('name', 'id');

        $changedMessages = collect();
        $deletedMessageIds = collect();

        if ($syncAfter !== '') {
            try {
                $syncAfterDate =
                    \Illuminate\Support\Carbon::parse(
                        $syncAfter
                    );

                $changedMessages =
                    InternalMessage::query()
                        ->with('attachments')
                        ->where('conversation_id', $conversationId)
                        ->whereNull('deleted_at')
                        ->whereNotNull('edited_at')
                        ->where('edited_at', '>', $syncAfterDate)
                        ->orderBy('id')
                        ->limit(100)
                        ->get();

                $deletedMessageIds =
                    InternalMessage::query()
                        ->where('conversation_id', $conversationId)
                        ->whereNotNull('deleted_at')
                        ->where('deleted_at', '>', $syncAfterDate)
                        ->orderBy('id')
                        ->limit(100)
                        ->pluck('id');
            } catch (\Throwable) {
                $changedMessages = collect();
                $deletedMessageIds = collect();
            }
        }

        // CRM_CHAT_BACKEND_PERFORMANCE_V2: one reply lookup for both result sets.
        $replyPayloads = $this->replyPayloads($messages->concat($changedMessages));

        $changedSenderNames = $changedMessages->isEmpty()
            ? collect()
            : DB::table('users')
                ->whereIn(
                    'id',
                    $changedMessages
                        ->pluck('user_id')
                        ->unique()
                        ->all()
                )
                ->pluck('name', 'id');

        $this->markConversationRead(
                $chat,
                $conversationId,
                (int) $user->id
            );

        $otherLastReadAt = InternalConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', '<>', $user->id)
            ->value('last_read_at');

        $readUpToId = $this->readUpToMessageId(
            $conversationId,
            (int) $user->id,
            $otherLastReadAt
        );

        return response()->json([
            'messages' => $messages
                ->map(
                    fn ($message) => $this->messagePayload(
                        $message,
                        (string) (
                            $senderNames[$message->user_id]
                            ?? 'User'
                        ),
                        $replyPayloads
                    )
                )
                ->values(),

            'read_up_to_id' => $readUpToId,

            'changed_messages' => $changedMessages
                ->map(
                    fn ($message) => $this->messagePayload(
                        $message,
                        (string) (
                            $changedSenderNames[$message->user_id]
                            ?? 'User'
                        ),
                        $replyPayloads
                    )
                )
                ->values(),

            'deleted_message_ids' => $deletedMessageIds
                ->values(),

            'sync_at' => now()->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function updateMessage(
        Request $request,
        int $conversationId,
        int $messageId,
        InternalChatService $chat
    ): JsonResponse {
        $user = $this->user();

        $chat->assertMember($conversationId, $user->id);

        $validated = $request->validate([
            'body' => [
                'required',
                'string',
                'max:20000',
            ],
        ]);

        $message = InternalMessage::query()
            ->with('attachments')
            ->where('id', $messageId)
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $message->body =
            trim(
                (string) $validated['body']
            );

        $message->edited_at = now();
        $message->save();

        app(InternalChatRealtimeService::class)->conversationChanged(
            $conversationId,
            'message.updated',
            (int) $user->id
        );

        return response()->json(
            $this->messagePayload(
                $message->fresh('attachments'),
                $user->name
            )
        );
    }

    public function deleteMessage(
        int $conversationId,
        int $messageId,
        InternalChatService $chat
    ): JsonResponse {
        $user = $this->user();

        $chat->assertMember($conversationId, $user->id);

        $message = InternalMessage::query()
            ->where('id', $messageId)
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        /*
         * Soft delete only. Content stays in DB/storage for audit.
         */
        $message->deleted_at = now();
        $message->save();

        app(InternalChatRealtimeService::class)->conversationChanged(
            $conversationId,
            'message.deleted',
            (int) $user->id
        );

        return response()->json([
            'deleted' => true,
            'message_id' => $message->id,
        ]);
    }

    public function unreadSummary(): JsonResponse
    {
        $user = $this->user();

        $rows = DB::table('internal_conversation_members as cm')
            ->join(
                'internal_messages as im',
                'im.conversation_id',
                '=',
                'cm.conversation_id'
            )
            ->where('cm.user_id', $user->id)
            ->where('im.user_id', '<>', $user->id)
            ->whereNull('im.deleted_at')
            ->where(
                function ($query) {
                    $query
                        ->whereNull('cm.last_read_at')
                        ->orWhereColumn(
                            'im.created_at',
                            '>',
                            'cm.last_read_at'
                        );
                }
            )
            ->select([
                'cm.conversation_id',
                DB::raw('COUNT(im.id) as unread_count'),
            ])
            ->groupBy('cm.conversation_id')
            ->get();

        return response()->json([
            'total' => (int) $rows->sum('unread_count'),
            'conversations' => $rows
                ->mapWithKeys(
                    fn ($row) => [
                        (string) $row->conversation_id =>
                            (int) $row->unread_count,
                    ]
                ),
        ]);
    }

    private function readUpToMessageId(
        int $conversationId,
        int $senderUserId,
        mixed $otherLastReadAt
    ): int {
        if (! $otherLastReadAt) {
            return 0;
        }

        return (int) (
            InternalMessage::query()
                ->where('conversation_id', $conversationId)
                ->where('user_id', $senderUserId)
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $otherLastReadAt)
                ->max('id')
            ?? 0
        );
    }

    /** CRM_CHAT_BACKEND_PERFORMANCE_V2: zero queries without replies, at most two otherwise. */
    private function replyPayloads(Collection $messages): array
    {
        $replyIds = $messages->pluck('reply_to_message_id')->filter()->unique()->values();

        if ($replyIds->isEmpty()) {
            return [];
        }

        $replies = InternalMessage::query()
            ->whereIn('conversation_id', $messages->pluck('conversation_id')->unique()->all())
            ->whereIn('id', $replyIds->all())
            ->get(['id', 'conversation_id', 'user_id', 'body', 'deleted_at']);

        $names = $replies->isEmpty()
            ? collect()
            : DB::table('users')->whereIn('id', $replies->pluck('user_id')->unique()->all())
                ->pluck('name', 'id');

        return $replies->mapWithKeys(fn ($reply) => [(int) $reply->id => [
            'id' => $reply->id,
            'sender_name' => $names->get($reply->user_id) ?: 'User',
            'body' => $reply->deleted_at
                ? 'Pesan telah dihapus'
                : (trim((string) $reply->body) ?: 'Attachment'),
        ]])->all();
    }

    private function messagePayload(
        InternalMessage $message,
        string $senderName,
        ?array $replyPayloads = null
    ): array {
        // Single-message send/edit responses use the same formatter. An empty
        // supplied map deliberately does not fall back to a query per message.
        $replyPayloads ??= $this->replyPayloads(collect([$message]));
        $reply = $replyPayloads[(int) $message->reply_to_message_id] ?? null;

        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'sender_name' => $senderName,
            'body' => $message->body,
            'reply_to_message_id' => $message->reply_to_message_id,
            'reply' => $reply,
            'edited_at' => $message
                ->edited_at
                ?->format('Y-m-d H:i:s'),
            'created_at' => $message
                ->created_at
                ?->format('Y-m-d H:i:s'),
            'attachments' => $message->attachments
                ->map(
                    fn ($attachment) => [
                        'id' => $attachment->id,
                        'name' => $attachment->original_name,
                        'size' => $attachment->size,
                        'download_url' => route(
                            'admin.internal-chat.attachments.download',
                            $attachment->id
                        ),
                    ]
                )
                ->values(),
        ];
    }

    /** INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */
    private function markConversationRead(
        InternalChatService $chat,
        int $conversationId,
        int $userId
    ): void {
        $previousCursor = $this->readMessageCursor($conversationId, $userId);

        $chat->markRead($conversationId, $userId);
        $this->syncReadMessageCursor($conversationId, $userId);

        $currentCursor = $this->readMessageCursor($conversationId, $userId);

        if ($currentCursor > $previousCursor) {
            app(InternalChatRealtimeService::class)->conversationChanged(
                $conversationId,
                'conversation.read',
                $userId
            );
        }
    }

    private function readMessageCursor(int $conversationId, int $userId): int
    {
        if (! Schema::hasColumn('internal_conversation_members', 'last_read_message_id')) {
            return 0;
        }

        return (int) (DB::table('internal_conversation_members')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->value('last_read_message_id') ?? 0);
    }
    /**
     * Keep unread state deterministic per conversation.
     *
     * last_read_at remains for read receipts; last_read_message_id is the
     * sidebar unread cursor.
     */
    private function syncReadMessageCursor(
        int $conversationId,
        int $userId
    ): void {
        if (
            ! \Illuminate\Support\Facades\Schema::hasColumn(
                'internal_conversation_members',
                'last_read_message_id'
            )
        ) {
            return;
        }

        $maxMessageId =
            (int) (
                \Illuminate\Support\Facades\DB::table(
                    'internal_messages'
                )
                    ->where(
                        'conversation_id',
                        $conversationId
                    )
                    ->whereNull(
                        'deleted_at'
                    )
                    ->max(
                        'id'
                    )
                ?? 0
            );

        \Illuminate\Support\Facades\DB::table(
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
            ->update([
                'last_read_message_id' =>
                    $maxMessageId,
            ]);
    }

    private function user()
    {
        $user = auth()
            ->guard('user')
            ->user();

        abort_unless($user, 403);

        return $user;
    }
}
