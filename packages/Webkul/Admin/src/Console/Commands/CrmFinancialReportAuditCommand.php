<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Webkul\Admin\Services\FinancialReportAuditService;
use Webkul\Core\Support\BusinessUnit;

class CrmFinancialReportAuditCommand extends Command
{
    protected $signature = 'crm:financial-report-audit {--year=} {--month=all} {--business-unit=} {--event-status=} {--product=} {--invoice=}';

    protected $description = 'Read-only reconciliation of Financial Report cards, details, cash, projects, annual figures and CSV against the ledger.';

    public function handle(FinancialReportAuditService $audit): int
    {
        try {
            $data = Validator::make([
                'year' => $this->option('year') ?: now()->year,
                'month' => $this->option('month'),
                'business_unit' => $this->option('business-unit'),
                'event_status' => $this->option('event-status'),
                'product' => $this->option('product'),
                'invoice' => $this->option('invoice'),
            ], [
                'year' => ['required', 'integer', 'between:2000,2100'],
                'month' => ['required', 'regex:/^(all|[1-9]|1[0-2])$/'],
                'business_unit' => ['nullable', 'in:'.implode(',', BusinessUnit::values())],
                'event_status' => ['nullable', 'in:prospect,confirm,cancel'],
                'product' => ['nullable', 'string', 'max:255'],
                'invoice' => ['nullable', 'string', 'max:255'],
            ])->validate();
            $invoice = $data['invoice'];
            unset($data['invoice']);
            $result = $audit->audit($data, $invoice);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
