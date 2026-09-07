<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrmOperationalHealthService
{
    public const SCHEDULER = 'scheduler';
    public const QUEUE = 'queue';
    public const EMAIL_SYNC = 'email_sync';
    public const BACKUP = 'backup';

    public function started(string $key, ?string $message = null, array $meta = []): void
    {
        $this->write($key, [
            'status' => 'running',
            'last_started_at' => now(),
            'last_seen_at' => now(),
            'message' => $message,
            'meta' => $meta,
        ]);
    }

    public function success(string $key, ?string $message = null, array $meta = [], ?int $durationMs = null): void
    {
        $this->write($key, [
            'status' => 'healthy',
            'last_success_at' => now(),
            'last_seen_at' => now(),
            'duration_ms' => $durationMs,
            'consecutive_failures' => 0,
            'message' => $message,
            'meta' => $meta,
        ]);
    }

    public function failure(string $key, string $message, array $meta = [], ?int $durationMs = null): void
    {
        if (! $this->available()) {
            return;
        }

        $current = DB::table('crm_operational_heartbeats')->where('key', $key)->first();

        $this->write($key, [
            'status' => 'failed',
            'last_failed_at' => now(),
            'last_seen_at' => now(),
            'duration_ms' => $durationMs,
            'consecutive_failures' => ((int) ($current->consecutive_failures ?? 0)) + 1,
            'message' => mb_substr($message, 0, 2000),
            'meta' => $meta,
        ]);
    }

    public function summary(): array
    {
        $definitions = [
            self::SCHEDULER => ['label' => 'Scheduler', 'limit' => now()->subMinutes((int) config('crm-production-operations.health.scheduler_stale_minutes', 3))],
            self::BACKUP => ['label' => 'Backup Terakhir', 'limit' => now()->subHours((int) config('crm-production-operations.health.backup_stale_hours', 26))],
            self::QUEUE => ['label' => 'Queue Worker', 'limit' => now()->subMinutes((int) config('crm-production-operations.health.queue_stale_minutes', 5))],
            self::EMAIL_SYNC => ['label' => 'Email Sync', 'limit' => now()->subMinutes((int) config('crm-production-operations.health.email_sync_stale_minutes', 15))],
        ];

        if (! $this->available()) {
            return array_map(fn ($key, $definition) => $this->unknown($key, $definition['label'], 'Jalankan migration.'), array_keys($definitions), $definitions);
        }

        $rows = DB::table('crm_operational_heartbeats')
            ->whereIn('key', array_keys($definitions))
            ->get()
            ->keyBy('key');

        $result = [];

        foreach ($definitions as $key => $definition) {
            $row = $rows->get($key);

            if (! $row) {
                $result[$key] = $this->unknown($key, $definition['label'], 'Belum pernah berjalan.');
                continue;
            }

            $seen = $row->last_seen_at ? Carbon::parse($row->last_seen_at) : null;
            $status = (string) $row->status;

            if ($key === self::QUEUE && config('queue.default') === 'sync') {
                $status = 'warning';
            } elseif ($status !== 'failed' && (! $seen || $seen->lt($definition['limit']))) {
                $status = 'stale';
            }

            $result[$key] = [
                'key' => $key,
                'label' => $definition['label'],
                'status' => $status,
                'last_seen_at' => $seen?->toIso8601String(),
                'last_seen_human' => $seen?->diffForHumans() ?? '-',
                'last_success_at' => $row->last_success_at ? Carbon::parse($row->last_success_at)->toIso8601String() : null,
                'last_failed_at' => $row->last_failed_at ? Carbon::parse($row->last_failed_at)->toIso8601String() : null,
                'message' => $key === self::QUEUE && config('queue.default') === 'sync'
                    ? 'QUEUE_CONNECTION=sync; gunakan database/redis dan jalankan queue:work.'
                    : ($row->message ?: null),
                'duration_ms' => $row->duration_ms,
                'consecutive_failures' => (int) $row->consecutive_failures,
            ];
        }

        return $result;
    }

    public function get(string $key): ?object
    {
        return $this->available()
            ? DB::table('crm_operational_heartbeats')->where('key', $key)->first()
            : null;
    }

    private function write(string $key, array $values): void
    {
        if (! $this->available()) {
            return;
        }

        if (isset($values['meta'])) {
            $values['meta'] = json_encode($values['meta'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $values['updated_at'] = now();
        $query = DB::table('crm_operational_heartbeats')->where('key', $key);

        if ($query->exists()) {
            $query->update($values);
            return;
        }

        DB::table('crm_operational_heartbeats')->insert(array_merge([
            'key' => $key,
            'created_at' => now(),
        ], $values));
    }

    private function available(): bool
    {
        try {
            return Schema::hasTable('crm_operational_heartbeats');
        } catch (\Throwable) {
            return false;
        }
    }

    private function unknown(string $key, string $label, string $message): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => 'unknown',
            'last_seen_at' => null,
            'last_seen_human' => '-',
            'last_success_at' => null,
            'last_failed_at' => null,
            'message' => $message,
            'duration_ms' => null,
            'consecutive_failures' => 0,
        ];
    }
}
