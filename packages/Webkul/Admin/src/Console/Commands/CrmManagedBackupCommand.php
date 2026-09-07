<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Webkul\Admin\Services\CrmBackupObjectStorageService;
use Webkul\Admin\Services\CrmBackupRetentionService;
use Webkul\Admin\Services\CrmOperationalHealthService;

class CrmManagedBackupCommand extends Command
{
    protected $signature = 'crm:backup-managed {--database-only}';
    protected $description = 'Create, verify, mirror, retain, and record a CRM backup.';

    public function handle(
        CrmOperationalHealthService $health,
        CrmBackupRetentionService $retention,
        CrmBackupObjectStorageService $objectStorage
    ): int {
        $databaseOnly = (bool) $this->option('database-only');
        $key = CrmOperationalHealthService::BACKUP;
        $started = hrtime(true);
        $directory = (string) config('crm-hardening.backup.directory', storage_path('app/private/crm-backups'));
        $before = glob($directory.DIRECTORY_SEPARATOR.'crm-backup-*.zip') ?: [];

        $health->started($key, $databaseOnly ? 'Backup database dimulai.' : 'Backup database + storage dimulai.');

        try {
            $code = Artisan::call('crm:backup', ['--database-only' => $databaseOnly]);
            $output = trim(Artisan::output());

            if ($code !== self::SUCCESS) {
                throw new \RuntimeException($output ?: 'Perintah crm:backup gagal.');
            }

            $after = glob($directory.DIRECTORY_SEPARATOR.'crm-backup-*.zip') ?: [];
            $created = array_values(array_diff($after, $before));
            usort($created, fn (string $a, string $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
            $archive = $created[0] ?? null;

            if (! $archive || ! is_file($archive) || filesize($archive) === 0) {
                throw new \RuntimeException('Archive backup baru tidak ditemukan atau kosong.');
            }

            $remote = $objectStorage->mirror($archive);
            $retentionResult = $retention->apply();
            $duration = (int) ((hrtime(true) - $started) / 1_000_000);

            $health->success($key, 'Backup berhasil: '.basename($archive), [
                'archive' => basename($archive),
                'bytes' => filesize($archive),
                'database_only' => $databaseOnly,
                'object_path' => $remote,
                'retention' => $retentionResult,
            ], $duration);
            $this->info('Backup PASS: '.$archive);
            if ($remote) {
                $this->line('Object storage: '.$remote);
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $health->failure($key, $exception->getMessage(), [
                'database_only' => $databaseOnly,
                'exception' => $exception::class,
            ], (int) ((hrtime(true) - $started) / 1_000_000));
            report($exception);
            $this->error('Backup FAIL: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
