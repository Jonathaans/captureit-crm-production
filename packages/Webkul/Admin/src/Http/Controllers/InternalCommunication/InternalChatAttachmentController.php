<?php

namespace Webkul\Admin\Http\Controllers\InternalCommunication;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\InternalMessageAttachment;

class InternalChatAttachmentController extends Controller
{
    public function download(
        int $id
    ): StreamedResponse {
        $user =
            auth()
                ->guard('user')
                ->user();

        abort_unless(
            $user,
            403
        );

        $attachment =
            InternalMessageAttachment::query()
                ->findOrFail(
                    $id
                );

        $conversationId =
            DB::table(
                'internal_messages'
            )
                ->where(
                    'id',
                    $attachment->message_id
                )
                ->value(
                    'conversation_id'
                );

        abort_unless(
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
                ->exists(),
            403
        );

        abort_unless(
            Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))
                ->exists(
                    $attachment
                        ->storage_path
                ),
            404
        );

        return Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))
            ->download(
                $attachment->storage_path,
                $attachment->original_name,
                [
                    'Content-Type' =>
                        $attachment->mime_type
                        ?: 'application/octet-stream',

                    'X-Content-Type-Options' =>
                        'nosniff',
                ]
            );
    }
}
