<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CrmAttachmentStorageAuditCommand extends Command
{
    protected $signature = 'crm:storage:audit {--write-probe}';
    protected $description = 'Check the configured attachment and backup object storage.';

    public function handle(): int
    {
        $attachmentDisk = (string) config('crm-production-operations.attachments.disk', 'local');
        $this->line('Attachment disk: '.$attachmentDisk);

        try {
            $disk = Storage::disk($attachmentDisk);

            if ($this->option('write-probe')) {
                $path = 'private/health-check/'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.txt';
                $payload = 'crm-storage-probe:'.now()->toIso8601String();
                $disk->put($path, $payload, ['visibility' => 'private']);

                if (! $disk->exists($path) || $disk->get($path) !== $payload) {
                    throw new \RuntimeException('Read-after-write probe gagal.');
                }

                $disk->delete($path);
                $this->info('Attachment disk read/write/delete probe: PASS');
            } else {
                $this->comment('Gunakan --write-probe untuk menguji read/write/delete.');
            }

            $this->line('Backup object disk: '.((string) config('crm-production-operations.backup.object_disk') ?: '(belum dikonfigurasi)'));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Storage audit FAIL: '.$exception->getMessage());
            return self::FAILURE;
        }
    }
}
