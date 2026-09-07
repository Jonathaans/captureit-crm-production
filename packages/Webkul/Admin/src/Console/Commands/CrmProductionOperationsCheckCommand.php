<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Webkul\Admin\Services\CrmOperationalHealthService;

class CrmProductionOperationsCheckCommand extends Command
{
    protected $signature = 'crm:production-operations-check';
    protected $description = 'Validate production scheduler, queue, backup, storage, upload, indexes, and HTTPS posture.';

    public function handle(CrmOperationalHealthService $health): int
    {
        $failures = 0;
        $warnings = 0;

        $this->check(config('app.env') === 'production', 'APP_ENV=production', $failures);
        $this->check(config('app.debug') === false, 'APP_DEBUG=false', $failures);
        $this->check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL menggunakan HTTPS', $failures);
        $this->check(config('crm-production-operations.production.force_https', false), 'CRM_FORCE_HTTPS=true', $warnings, true);
        $this->check(config('queue.default') !== 'sync', 'QUEUE_CONNECTION bukan sync', $failures);
        $this->check(Schema::hasTable('crm_operational_heartbeats'), 'Tabel operational heartbeat tersedia', $failures);

        foreach ($health->summary() as $item) {
            $this->check($item['status'] === 'healthy', $item['label'].' sehat ('.$item['status'].')', $failures);
        }

        $indexNames = [
            'crm_quotes_document_lookup_idx',
            'crm_quotes_project_event_idx',
            'crm_invoices_document_lookup_idx',
            'crm_invoices_project_event_idx',
            'crm_invoices_status_due_idx',
            'crm_invoices_quote_relation_idx',
            'crm_work_orders_invoice_status_idx',
            'crm_delivery_orders_invoice_status_idx',
            'crm_purchase_orders_invoice_status_idx',
            'crm_expenses_invoice_date_idx',
            'crm_movements_item_created_idx',
        ];
        $present = $this->indexNames();

        foreach ($indexNames as $name) {
            $this->check(in_array($name, $present, true), 'Index '.$name, $warnings, true);
        }

        $attachmentDisk = (string) config('crm-production-operations.attachments.disk', 'local');
        $this->check($attachmentDisk !== '', 'Attachment disk terkonfigurasi: '.$attachmentDisk, $failures);
        $this->check(config('crm-production-operations.backup.object_disk') !== null, 'Backup object storage terkonfigurasi', $warnings, true);
        $this->check(config('crm-production-operations.antivirus.enabled', false), 'Antivirus upload aktif', $warnings, true);

        if (config('crm-production-operations.antivirus.enabled', false)) {
            $this->check($this->clamAvReady(), 'ClamAV dapat dijalankan', $failures);
        }

        $appKey = (string) config('filesystems.disks.s3.key');
        $backupKey = (string) config('crm-production-operations.backup.object_store.key');
        if ($appKey !== '' && $backupKey !== '') {
            $this->check(! hash_equals($appKey, $backupKey), 'Kredensial attachment dan backup terpisah', $warnings, true);
        }

        $this->newLine();
        $this->line("Hasil: failures={$failures} warnings={$warnings}");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function check(bool $condition, string $label, int &$counter, bool $warning = false): void
    {
        if ($condition) {
            $this->info('[OK] '.$label);
            return;
        }

        $counter++;
        $warning ? $this->warn('[WARN] '.$label) : $this->error('[FAIL] '.$label);
    }

    private function indexNames(): array
    {
        try {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::connection()->getDatabaseName())
                ->pluck('index_name')
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function clamAvReady(): bool
    {
        if (! class_exists(Process::class)) {
            return false;
        }

        try {
            $process = new Process([(string) config('crm-production-operations.antivirus.binary', 'clamscan'), '--version']);
            $process->setTimeout(10);
            $process->run();
            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }
}
