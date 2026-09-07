<?php

namespace Webkul\Admin\Http\Controllers\UserEmail;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\UserEmailAttachment;

class MyEmailAttachmentController extends Controller
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
            UserEmailAttachment::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->findOrFail(
                    $id
                );

        abort_unless(
            Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))
                ->exists(
                    $attachment->storage_path
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
