<?php

namespace Webkul\Admin\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CrmBackupObjectStorageService
{
    public function mirror(string $localPath): ?string
    {
        $diskName = config('crm-production-operations.backup.object_disk');

        if (! is_string($diskName) || trim($diskName) === '') {
            return null;
        }

        $disk = $this->disk(trim($diskName));
        $prefix = (string) config('crm-production-operations.backup.object_prefix', 'crm-backups');
        $remotePath = trim($prefix, '/').'/'.basename($localPath);
        $stream = fopen($localPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Tidak dapat membuka backup lokal untuk object storage.');
        }

        try {
            if (! $disk->put($remotePath, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException('Upload backup ke object storage gagal.');
            }
        } finally {
            fclose($stream);
        }

        if (! $disk->exists($remotePath) || $disk->size($remotePath) !== filesize($localPath)) {
            throw new RuntimeException('Verifikasi ukuran backup object storage gagal.');
        }

        return $remotePath;
    }

    public function configured(): bool
    {
        return is_string(config('crm-production-operations.backup.object_disk'))
            && trim((string) config('crm-production-operations.backup.object_disk')) !== '';
    }

    private function disk(string $name): FilesystemAdapter
    {
        if ($name === 'crm-backup-s3') {
            return Storage::build((array) config('crm-production-operations.backup.object_store'));
        }

        return Storage::disk($name);
    }
}
