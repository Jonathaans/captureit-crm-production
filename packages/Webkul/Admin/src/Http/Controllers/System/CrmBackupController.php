<?php

namespace Webkul\Admin\Http\Controllers\System;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\CrmBackupStatusService;

class CrmBackupController extends Controller
{
    /* CRM_FULL_QA_BACKUP_CENTER_V1 */

    public function store(
        CrmBackupStatusService $statusService
    ): RedirectResponse {
        $this->authorizeAccess();

        $lock = Cache::lock(
            'crm-full-backup-running',
            3600
        );

        if (! $lock->get()) {
            session()->flash(
                'warning',
                'Backup lain masih berjalan. Tunggu proses tersebut selesai.'
            );

            return back();
        }

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $exitCode = Artisan::call('crm:backup-managed');
            $output = trim(Artisan::output());

            if ($exitCode !== 0) {
                throw new \RuntimeException(
                    $output !== ''
                        ? mb_substr($output, 0, 1000)
                        : 'Perintah backup mengembalikan status gagal.'
                );
            }

            $latest = $statusService->summary()['latest'] ?? null;

            if (! $latest || ! $latest['valid']) {
                throw new \RuntimeException(
                    $latest['message'] ?? 'File backup baru tidak lolos verifikasi.'
                );
            }

            session()->flash(
                'success',
                'Backup semua data selesai: '
                .$latest['filename']
                .sprintf(' (%s).', $latest['size_label'])
            );
        } catch (\Throwable $exception) {
            report($exception);

            session()->flash(
                'error',
                'Backup gagal: '.mb_substr($exception->getMessage(), 0, 1000)
            );
        } finally {
            $lock->release();
        }

        return redirect()->route(
            'admin.operations-dashboard.index'
        );
    }

    public function download(string $filename): BinaryFileResponse
    {
        $this->authorizeAccess();

        $safeName = basename($filename);

        abort_unless(
            preg_match(
                '/^crm-backup-\d{8}-\d{6}\.zip$/',
                $safeName
            ) === 1,
            404
        );

        $directory = config(
            'crm-hardening.backup.directory',
            storage_path('app/private/crm-backups')
        );

        $realDirectory = realpath($directory);
        $realPath = realpath(
            $directory.DIRECTORY_SEPARATOR.$safeName
        );

        abort_unless(
            $realDirectory !== false
            && $realPath !== false
            && is_file($realPath)
            && str_starts_with(
                $realPath,
                $realDirectory.DIRECTORY_SEPARATOR
            ),
            404
        );

        return response()->download(
            $realPath,
            $safeName,
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function authorizeAccess(): void
    {
        abort_unless(
            auth()->guard('user')->check(),
            403
        );

        $user = auth()->guard('user')->user();
        $user->loadMissing('role');

        abort_unless(
            strtolower(trim((string) ($user->role?->name ?? '')))
                === 'administrator',
            403
        );

        if (
            function_exists('bouncer')
            && ! bouncer()->hasPermission('operations-dashboard')
        ) {
            abort(403);
        }
    }
}
