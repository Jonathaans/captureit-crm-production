<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\File;
use ZipArchive;

class CrmBackupRetentionService
{
    public function apply(): array
    {
        $directory = (string) config(
            'crm-hardening.backup.directory',
            storage_path('app/private/crm-backups')
        );

        if (! is_dir($directory)) {
            return ['kept' => 0, 'deleted' => 0, 'bytes_freed' => 0];
        }

        $files = glob($directory.DIRECTORY_SEPARATOR.'crm-backup-*.zip') ?: [];
        usort($files, fn (string $a, string $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        $now = now();
        $dailyCutoff = $now->copy()->subDays((int) config('crm-production-operations.backup.keep_daily_days', 14));
        $weeklyCutoff = $now->copy()->subWeeks((int) config('crm-production-operations.backup.keep_weekly_weeks', 8));
        $monthlyCutoff = $now->copy()->subMonths((int) config('crm-production-operations.backup.keep_monthly_months', 12));
        $yearlyCutoff = $now->copy()->subYears((int) config('crm-production-operations.backup.keep_yearly_years', 7));

        $keep = [];
        $weekly = [];
        $monthly = [];
        $yearly = [];
        $newestByType = [];

        foreach ($files as $path) {
            $time = \Illuminate\Support\Carbon::createFromTimestamp(filemtime($path) ?: 0);
            $type = $this->isDatabaseOnly($path) ? 'database' : 'full';

            if (! isset($newestByType[$type])) {
                $newestByType[$type] = true;
                $keep[$path] = true;
            }

            if ($time->gte($dailyCutoff)) {
                $keep[$path] = true;
            }

            if ($type !== 'full') {
                continue;
            }

            $week = $time->format('o-W');
            if ($time->gte($weeklyCutoff) && ! isset($weekly[$week])) {
                $weekly[$week] = true;
                $keep[$path] = true;
            }

            $month = $time->format('Y-m');
            if ($time->gte($monthlyCutoff) && ! isset($monthly[$month])) {
                $monthly[$month] = true;
                $keep[$path] = true;
            }

            $year = $time->format('Y');
            if ($time->gte($yearlyCutoff) && ! isset($yearly[$year])) {
                $yearly[$year] = true;
                $keep[$path] = true;
            }
        }

        $deleted = 0;
        $freed = 0;

        foreach ($files as $path) {
            if (isset($keep[$path])) {
                continue;
            }

            $size = filesize($path) ?: 0;
            if (File::delete($path)) {
                $deleted++;
                $freed += $size;
            }
        }

        return [
            'kept' => count($files) - $deleted,
            'deleted' => $deleted,
            'bytes_freed' => $freed,
        ];
    }

    public function isDatabaseOnly(string $path): bool
    {
        if (! class_exists(ZipArchive::class)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }

        try {
            $json = $zip->getFromName('metadata.json');
            $metadata = is_string($json) ? json_decode($json, true) : null;

            return (bool) ($metadata['database_only'] ?? false);
        } finally {
            $zip->close();
        }
    }
}
