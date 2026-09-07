<?php

namespace Webkul\Admin\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Webkul\Admin\Models\UserEmailAttachment;
use Webkul\Admin\Models\UserEmailMessage;

class UserEmailAttachmentService
{
    public function storeUploads(
        UserEmailMessage $message,
        array $uploads
    ): array {
        $stored = [];

        foreach ($uploads as $upload) {
            if (! $upload instanceof UploadedFile) {
                continue;
            }

            $stored[] =
                $this->storeBinary(
                    $message,
                    $upload->getClientOriginalName(),
                    $upload->getMimeType()
                        ?: $upload->getClientMimeType(),
                    (string) file_get_contents(
                        $upload->getRealPath()
                    ),
                    'attachment',
                    null,
                    $message->direction
                );
        }

        return $stored;
    }

    public function storeIncomingExtracted(
        UserEmailMessage $message,
        array $attachments
    ): void {
        if (
            UserEmailAttachment::query()
                ->where(
                    'message_id',
                    $message->id
                )
                ->exists()
        ) {
            return;
        }

        foreach ($attachments as $attachment) {
            $this->storeBinary(
                $message,
                (string) (
                    $attachment['filename']
                    ?? 'attachment.bin'
                ),
                (string) (
                    $attachment['mime_type']
                    ?? 'application/octet-stream'
                ),
                (string) (
                    $attachment['data']
                    ?? ''
                ),
                $attachment['disposition']
                    ?? 'attachment',
                $attachment['content_id']
                    ?? null,
                'incoming'
            );
        }
    }

    public function copyAttachments(
        UserEmailMessage $from,
        UserEmailMessage $to
    ): void {
        $attachments =
            UserEmailAttachment::query()
                ->where(
                    'message_id',
                    $from->id
                )
                ->get();

        foreach ($attachments as $attachment) {
            if (
                ! Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))
                    ->exists(
                        $attachment->storage_path
                    )
            ) {
                continue;
            }

            $data =
                Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))
                    ->get(
                        $attachment->storage_path
                    );

            $this->storeBinary(
                $to,
                $attachment->original_name,
                $attachment->mime_type
                    ?: 'application/octet-stream',
                $data,
                $attachment->disposition
                    ?: 'attachment',
                $attachment->content_id,
                $to->direction
            );
        }
    }

    public function deleteMessageFiles(
        UserEmailMessage $message
    ): void {
        $attachments =
            UserEmailAttachment::query()
                ->where(
                    'message_id',
                    $message->id
                )
                ->get();

        foreach ($attachments as $attachment) {
            Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))
                ->delete(
                    $attachment->storage_path
                );

            $attachment->delete();
        }

        Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))
            ->deleteDirectory(
                $this->messageDirectory(
                    $message
                )
            );
    }

    private function storeBinary(
        UserEmailMessage $message,
        string $originalName,
        ?string $mimeType,
        string $data,
        ?string $disposition,
        ?string $contentId,
        string $direction
    ): UserEmailAttachment {
        if ($data === '') {
            throw new RuntimeException(
                'Attachment kosong tidak disimpan.'
            );
        }

        if (strlen($data) > 10485760) {
            throw new RuntimeException(
                'Attachment melebihi 10 MB.'
            );
        }

        $safeOriginal =
            $this->safeFilename(
                $originalName
            );

        $extension =
            pathinfo(
                $safeOriginal,
                PATHINFO_EXTENSION
            );

        $storedName =
            (string) Str::uuid()
            .(
                $extension !== ''
                    ? '.'
                        .strtolower(
                            preg_replace(
                                '/[^A-Za-z0-9]/',
                                '',
                                $extension
                            )
                            ?? ''
                        )
                    : ''
            );

        $directory =
            $this->messageDirectory(
                $message
            );

        $path =
            $directory
            .'/'
            .$storedName;

        Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))
            ->put(
                $path,
                $data
            );

        return UserEmailAttachment::query()
            ->create([
                'user_id' =>
                    $message->user_id,

                'message_id' =>
                    $message->id,

                'direction' =>
                    $direction,

                'original_name' =>
                    $safeOriginal,

                'mime_type' =>
                    $mimeType
                    ?: 'application/octet-stream',

                'size' =>
                    strlen($data),

                'storage_path' =>
                    $path,

                'disposition' =>
                    $disposition,

                'content_id' =>
                    $contentId,
            ]);
    }

    private function messageDirectory(
        UserEmailMessage $message
    ): string {
        return 'private/user-email/'
            .$message->user_id
            .'/'
            .$message->id;
    }

    private function safeFilename(
        string $name
    ): string {
        $name =
            str_replace(
                ['\\', '/', "\0"],
                '_',
                trim($name)
            );

        $name =
            preg_replace(
                '/[\x00-\x1F\x7F]/u',
                '',
                $name
            )
            ?? $name;

        return $name !== ''
            ? mb_substr(
                $name,
                0,
                240
            )
            : 'attachment.bin';
    }
}
