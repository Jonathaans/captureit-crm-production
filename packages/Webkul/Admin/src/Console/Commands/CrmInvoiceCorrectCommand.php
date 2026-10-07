<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Admin\Services\InvoiceCorrectionService;

class CrmInvoiceCorrectCommand extends Command
{
    protected $signature = 'crm:invoice-correct {invoice} {--apply} {--expect=} {--actor=} {--reason=}';

    protected $description = 'Preview or archive and remove an erroneous invoice with its payments.';

    public function handle(InvoiceCorrectionService $service): int
    {
        try {
            $number = trim((string) $this->argument('invoice'));
            if (! $this->option('apply')) {
                $preview = $service->preview($number);
                // Show only information needed for review, not billing addresses.
                $preview['invoice'] = array_intersect_key($preview['invoice'], array_flip([
                    'id', 'invoice_number', 'quote_id', 'project_code', 'subject', 'grand_total', 'paid_amount', 'status', 'event_status',
                ]));
                $this->line(json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                $this->info('PREVIEW: belum ada perubahan. Apply memerlukan --expect, --actor, dan --reason.');

                return self::SUCCESS;
            }

            $result = $service->apply($number, (string) $this->option('expect'), (string) $this->option('reason'), (int) $this->option('actor'));
            $this->info(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
