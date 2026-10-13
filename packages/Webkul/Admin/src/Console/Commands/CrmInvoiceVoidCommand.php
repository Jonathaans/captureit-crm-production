<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Admin\Services\InvoiceVoidService;

class CrmInvoiceVoidCommand extends Command
{
    protected $signature = 'crm:invoice-void {invoice} {--apply} {--expect=} {--actor=} {--reason=} {--no-money-received}';

    protected $description = 'Preview or void a wrong invoice/payment entry while preserving completed SPK, DO and stock history.';

    public function handle(InvoiceVoidService $service): int
    {
        try {
            $number = trim((string) $this->argument('invoice'));
            if (! $this->option('apply')) {
                $this->line(json_encode($service->preview($number), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                $this->info('PREVIEW: belum ada perubahan. Jangan apply jika blockers tidak kosong.');

                return self::SUCCESS;
            }

            $result = $service->apply($number, (string) $this->option('expect'), (string) $this->option('reason'), (int) $this->option('actor'), (bool) $this->option('no-money-received'));
            $this->info(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
