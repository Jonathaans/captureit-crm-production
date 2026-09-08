<?php

namespace Webkul\Admin\Http\Controllers\InternalCommunication;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;

class InternalChatExperienceController extends Controller
{
    public function search(
        Request $request,
        int $conversationId
    ): JsonResponse {
        $user =
            $this->user();

        $this->assertMember(
            $conversationId,
            (int) $user->id
        );

        $query =
            trim(
                (string) $request->query(
                    'q',
                    ''
                )
            );

        if (mb_strlen($query) < 2) {
            return response()->json([
                'results' => [],
            ]);
        }

        $results =
            DB::table(
                'internal_messages as m'
            )
                ->leftJoin(
                    'users as sender',
                    'sender.id',
                    '=',
                    'm.user_id'
                )
                ->where(
                    'm.conversation_id',
                    $conversationId
                )
                ->whereNull(
                    'm.deleted_at'
                )
                ->whereNotNull(
                    'm.body'
                )
                ->where(
                    'm.body',
                    'like',
                    '%'.$query.'%'
                )
                ->orderByDesc(
                    'm.id'
                )
                ->limit(
                    50
                )
                ->select([
                    'm.id',
                    'm.user_id',
                    'm.body',
                    'm.created_at',
                    'sender.name as sender_name',
                ])
                ->get()
                ->map(
                    fn ($row) => [
                        'id' =>
                            (int) $row->id,

                        'user_id' =>
                            (int) $row->user_id,

                        'sender_name' =>
                            (string) (
                                $row->sender_name
                                ?: 'User'
                            ),

                        'body' =>
                            (string) (
                                $row->body
                                ?: ''
                            ),

                        'created_at' =>
                            $row->created_at
                                ? date(
                                    'd M Y H:i',
                                    strtotime(
                                        (string) $row->created_at
                                    )
                                )
                                : '',
                    ]
                )
                ->values();

        return response()->json([
            'results' =>
                $results,
        ]);
    }

    public function typing(
        Request $request,
        int $conversationId
    ): JsonResponse {
        $user =
            $this->user();

        $this->assertMember(
            $conversationId,
            (int) $user->id
        );

        $isTyping =
            $request->boolean(
                'typing',
                true
            );

        $key =
            $this->typingKey(
                $conversationId,
                (int) $user->id
            );

        if ($isTyping) {
            Cache::put(
                $key,
                [
                    'user_id' =>
                        (int) $user->id,

                    'name' =>
                        (string) (
                            $user->name
                            ?: 'User'
                        ),
                ],
                now()->addSeconds(
                    6
                )
            );
        } else {
            Cache::forget(
                $key
            );
        }

        return response()->json([
            'ok' =>
                true,
        ]);
    }

    public function typingStatus(
        int $conversationId
    ): JsonResponse {
        $user =
            $this->user();

        $this->assertMember(
            $conversationId,
            (int) $user->id
        );

        $otherMembers =
            DB::table(
                'internal_conversation_members as cm'
            )
                ->join(
                    'users',
                    'users.id',
                    '=',
                    'cm.user_id'
                )
                ->where(
                    'cm.conversation_id',
                    $conversationId
                )
                ->where(
                    'cm.user_id',
                    '<>',
                    $user->id
                )
                ->select([
                    'users.id',
                    'users.name',
                ])
                ->get();

        $typingUsers = [];

        foreach ($otherMembers as $member) {
            $state =
                Cache::get(
                    $this->typingKey(
                        $conversationId,
                        (int) $member->id
                    )
                );

            if (! $state) {
                continue;
            }

            $typingUsers[] = [
                'id' =>
                    (int) $member->id,

                'name' =>
                    (string) (
                        $state['name']
                        ?? $member->name
                        ?? 'User'
                    ),
            ];
        }

        return response()->json([
            'typing' =>
                count(
                    $typingUsers
                ) > 0,

            'users' =>
                $typingUsers,
        ]);
    }

    public function previewAttachment(
        int $id
    ): StreamedResponse {
        $user =
            $this->user();

        $attachment =
            DB::table(
                'internal_message_attachments as a'
            )
                ->join(
                    'internal_messages as m',
                    'm.id',
                    '=',
                    'a.message_id'
                )
                ->where(
                    'a.id',
                    $id
                )
                ->select([
                    'a.id',
                    'a.original_name',
                    'a.mime_type',
                    'a.storage_path',
                    'm.conversation_id',
                ])
                ->first();

        abort_unless(
            $attachment,
            404
        );

        $this->assertMember(
            (int) $attachment
                ->conversation_id,
            (int) $user->id
        );

        $path =
            (string) $attachment
                ->storage_path;

        abort_unless(
            $path !== ''
            && Storage::disk(
                'local'
            )->exists(
                $path
            ),
            404
        );

        $mime =
            strtolower(
                trim(
                    (string) (
                        $attachment->mime_type
                        ?? ''
                    )
                )
            );

        $previewable =
            str_starts_with(
                $mime,
                'image/'
            )
            || $mime ===
                'application/pdf';

        abort_unless(
            $previewable,
            415,
            'Preview hanya tersedia untuk image dan PDF.'
        );

        return Storage::disk(
            'local'
        )->response(
            $path,
            (string) (
                $attachment
                    ->original_name
                ?: 'attachment'
            ),
            [
                'Cache-Control' =>
                    'private, no-store, max-age=0',

                'Pragma' =>
                    'no-cache',

                'X-Content-Type-Options' =>
                    'nosniff',

                'X-Robots-Tag' =>
                    'noindex, nofollow, noarchive',
            ],
            'inline'
        );
    }

    private function assertMember(
        int $conversationId,
        int $userId
    ): void {
        $isMember =
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
            $isMember,
            403
        );
    }

    private function typingKey(
        int $conversationId,
        int $userId
    ): string {
        return 'internal-chat:typing:'
            .$conversationId
            .':'
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
