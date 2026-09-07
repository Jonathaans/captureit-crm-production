<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\Route;
use ZipArchive;

class CrmBackupStatusService
{
    /* CRM_FULL_QA_BACKUP_CENTER_V1 */

    public function summary(): array
    {
        $directory = config(
            'crm-hardening.backup.directory',
            storage_path('app/private/crm-backups')
        );

        $files = glob(
            $directory.DIRECTORY_SEPARATOR.'crm-backup-*.zip'
        ) ?: [];

        usort(
            $files,
            fn (string $left, string $right): int =>
                filemtime($right) <=> filemtime($left)
        );

        $latest = $files[0] ?? null;
        $detail = $latest ? $this->inspect($latest) : null;

        return [
            'available' => $latest !== null,
            'count' => count($files),
            'retention_days' => max(
                1,
                (int) config('crm-production-operations.backup.keep_daily_days', 14)
            ),
            'latest' => $detail,
            'directory_writable' => is_dir($directory)
                ? is_writable($directory)
                : is_writable(dirname($directory)),
        ];
    }

    private function inspect(string $path): array
    {
        $filename = basename($path);
        $size = is_file($path) ? (int) filesize($path) : 0;
        $createdAt = is_file($path) ? (int) filemtime($path) : 0;
        $valid = false;
        $includesStorage = false;
        $message = 'Archive tidak dapat diverifikasi.';

        if (! class_exists(ZipArchive::class)) {
            $message = 'PHP Zip extension belum aktif.';
        } else {
            $zip = new ZipArchive();
            $opened = $zip->open($path, ZipArchive::CHECKCONS);

            if ($opened === true) {
                $databaseIndex = $zip->locateName('database.sql');
                $metadataIndex = $zip->locateName('metadata.json');
                $databaseSize = $databaseIndex !== false
                    ? (int) ($zip->statIndex($databaseIndex)['size'] ?? 0)
                    : 0;

                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $name = (string) $zip->getNameIndex($index);

                    if (str_starts_with($name, 'storage-app/')) {
                        $includesStorage = true;
                        break;
                    }
                }

                $valid = $databaseIndex !== false
                    && $metadataIndex !== false
                    && $databaseSize > 0;

                $message = $valid
                    ? 'Archive valid dan database.sql tersedia.'
                    : 'Archive tidak lengkap atau database.sql kosong.';

                $zip->close();
            }
        }

        $ageSeconds = max(0, time() - $createdAt);

        return [
            'filename' => $filename,
            'valid' => $valid,
            'message' => $message,
            'includes_storage' => $includesStorage,
            'size_bytes' => $size,
            'size_label' => $this->formatBytes($size),
            'created_at' => $createdAt > 0
                ? date('d M Y H:i', $createdAt)
                : '-',
            'age_hours' => round($ageSeconds / 3600, 1),
            'age_label' => $this->formatAge($ageSeconds),
            'download_url' => Route::has(
                'admin.operations-dashboard.backups.download'
            )
                ? route(
                    'admin.operations-dashboard.backups.download',
                    ['filename' => $filename]
                )
                : null,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return number_format($bytes / 1024 / 1024 / 1024, 2).' GB';
        }

        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / 1024 / 1024, 2).' MB';
        }

        return number_format($bytes / 1024, 2).' KB';
    }

    private function formatAge(int $seconds): string
    {
        if ($seconds < 3600) {
            return max(1, (int) floor($seconds / 60)).' menit lalu';
        }

        if ($seconds < 86400) {
            return (int) floor($seconds / 3600).' jam lalu';
        }

        return (int) floor($seconds / 86400).' hari lalu';
    }
}
