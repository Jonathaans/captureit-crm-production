<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CrmAttachmentMigrateCommand extends Command
{
    protected $signature = 'crm:attachments-migrate {target} {--from=local} {--execute}';
    protected $description = 'Copy referenced CRM attachments to another filesystem disk without deleting the source.';

    /** @var array<string, array<int, string>> */
    private array $locations = [
        'user_email_attachments' => ['storage_path'],
        'internal_message_attachments' => ['storage_path'],
        'purchase_orders' => ['payment_proof_path'],
        'expenses' => ['receipt_path', 'image_path'],
        'vendors' => ['npwp_image_path', 'npwp_path'],
        'persons' => ['identity_document_path'],
    ];

    public function handle(): int
    {
        $fromName = (string) $this->option('from');
        $targetName = (string) $this->argument('target');
        $execute = (bool) $this->option('execute');

        if ($fromName === $targetName) {
            $this->error('Disk sumber dan target harus berbeda.');
            return self::FAILURE;
        }

        $from = Storage::disk($fromName);
        $target = Storage::disk($targetName);
        $paths = $this->referencedPaths();
        $copied = 0;
        $missing = 0;
        $skipped = 0;

        $this->line(($execute ? 'EXECUTE' : 'DRY RUN').' '.$fromName.' -> '.$targetName.'; referenced='.count($paths));

        foreach ($paths as $path) {
            if (! $from->exists($path)) {
                $missing++;
                $this->warn('[MISSING] '.$path);
                continue;
            }

            if ($target->exists($path) && $target->size($path) === $from->size($path)) {
                $skipped++;
                continue;
            }

            if (! $execute) {
                $this->line('[COPY] '.$path);
                $copied++;
                continue;
            }

            $stream = $from->readStream($path);
            if ($stream === false) {
                $missing++;
                $this->warn('[READ FAIL] '.$path);
                continue;
            }

            try {
                if (! $target->put($path, $stream, ['visibility' => 'private'])) {
                    throw new \RuntimeException('Penulisan target gagal: '.$path);
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (! $target->exists($path) || $target->size($path) !== $from->size($path)) {
                $this->error('[VERIFY FAIL] '.$path);
                return self::FAILURE;
            }

            $copied++;
        }

        $this->info("Selesai: copied={$copied} skipped={$skipped} missing={$missing}");
        $this->comment('File sumber tidak dihapus. Ubah CRM_ATTACHMENT_DISK hanya setelah missing=0 dan verifikasi aplikasi lulus.');

        return $missing === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<int, string> */
    private function referencedPaths(): array
    {
        $paths = [];

        foreach ($this->locations as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                foreach (DB::table($table)->whereNotNull($column)->pluck($column) as $value) {
                    $path = ltrim(str_replace('\\', '/', trim((string) $value)), '/');

                    if ($path === '' || str_contains($path, '..') || preg_match('~^https?://~i', $path)) {
                        continue;
                    }

                    $paths[$path] = true;
                }
            }
        }

        return array_keys($paths);
    }
}
