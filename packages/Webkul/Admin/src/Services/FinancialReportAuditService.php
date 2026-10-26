<?php

namespace Webkul\Admin\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Admin\Http\Controllers\Invoice\FinancialReportController;

/** Read-only reconciliation against ledger rows, independent of Eloquent report scopes. */
class FinancialReportAuditService
{
    public function __construct(private FinancialReportController $report) {}

    public function audit(array $input, ?string $invoiceNumber = null): array
    {
        return DB::transaction(function () use ($input, $invoiceNumber) {
            $request = Request::create('/', 'GET', $input);
            $data = $this->report->index($request)->getData();
            $filters = [
                'year' => $data['year'], 'month' => $data['month'],
                'business_unit' => $data['businessUnit'], 'event_status' => $data['eventStatus'], 'product' => $data['product'],
            ];
            $query = $this->invoiceQuery($filters);
            $this->period($query, 'invoices.issued_at', $filters);
            $rows = $query->get(['invoices.id', 'invoices.invoice_number', 'invoices.quote_id', 'invoices.status', 'invoices.event_status', 'invoices.grand_total', 'invoices.pph_amount']);
            $eligible = $rows->filter(fn ($row) => $row->status !== 'void'
                && ! in_array($row->event_status, ['cancel', 'cancelled', 'canceled'], true));
            $confirmed = $eligible->where('event_status', 'confirm');
            $paid = $this->paidTotals($eligible->pluck('id')->all());
            $invoiced = (float) $confirmed->sum('grand_total');
            $outstanding = $this->outstanding($confirmed, $paid);
            $received = $this->transactionTotal('payments', 'paid_at', $filters);
            $expense = $this->transactionTotal('expenses', 'expense_date', $filters);
            $checks = [];
            foreach (['invoiced' => $invoiced, 'outstanding' => $outstanding, 'received' => $received, 'expense' => $expense, 'cash_surplus' => $received - $expense] as $key => $expected) {
                $this->check($checks, 'summary.'.$key, $expected, $data['financialSummary'][$key]);
            }
            $this->check($checks, 'invoice.count', $eligible->count(), $data['invoiceStats']['total']);
            foreach (['paid', 'partial', 'unpaid'] as $status) {
                $this->check($checks, 'invoice.'.$status, $eligible->where('status', $status)->count(), $data['invoiceStats'][$status]);
            }
            $this->check($checks, 'invoice.ids', $eligible->pluck('id')->sort()->values()->all(), $data['invoicePerformance']->pluck('id')->sort()->values()->all());
            $this->check($checks, 'details.confirmed_invoiced', $invoiced, $data['invoicePerformance']->where('event_status', 'confirm')->sum('invoice_value'));
            $this->check($checks, 'details.outstanding', $outstanding, $data['invoicePerformance']->sum('outstanding'));
            $this->check($checks, 'cash.received', $received, $data['cashTransactions']->sum('cash_in'));
            $this->check($checks, 'cash.expense', $expense, $data['cashTransactions']->sum('cash_out'));
            $this->check($checks, 'expense.categories', $expense, $data['expenseByCategory']->sum('total'));
            if ($filters['month'] === null) {
                foreach (['revenue' => $invoiced, 'received' => $received, 'expense' => $expense, 'cash_surplus' => $received - $expense] as $key => $expected) {
                    $this->check($checks, 'annual.'.$key, $expected, $data['monthlyPerformance']->sum($key));
                }
            }

            $projects = DB::table('invoices')->whereNotNull('issued_at')->where(function (Builder $query) use ($confirmed) {
                $query->whereIn('quote_id', $confirmed->pluck('quote_id')->filter()->unique()->all())
                    ->orWhereIn('id', $confirmed->whereNull('quote_id')->pluck('id')->all());
            })->get(['id', 'status', 'event_status', 'grand_total', 'pph_amount']);
            $activeProjectInvoices = $projects->where('event_status', 'confirm')->where('status', '!=', 'void');
            $projectPaid = $this->paidTotals($activeProjectInvoices->pluck('id')->all());
            $projectExpense = (float) DB::table('expenses')->whereIn('invoice_id', $projects->pluck('id')->all())->sum('amount');
            foreach ([
                'invoiced' => (float) $activeProjectInvoices->sum('grand_total'),
                'outstanding' => $this->outstanding($activeProjectInvoices, $projectPaid),
                'expense' => $projectExpense,
                'margin' => (float) $activeProjectInvoices->sum('grand_total') - $projectExpense,
            ] as $key => $expected) {
                $this->check($checks, 'project.'.$key, $expected, $data['projectPerformance']->sum($key));
            }

            $csvRows = $this->csvRows($request);
            $this->check($checks, 'csv.invoice_numbers', $eligible->pluck('invoice_number')->sort()->values()->all(), $csvRows->pluck('No Invoice')->sort()->values()->all());
            $this->check($checks, 'csv.invoice_value', (float) $eligible->sum('grand_total'), $csvRows->sum('Nilai Invoice'));
            $this->check($checks, 'csv.outstanding', $outstanding, $csvRows->sum('Sisa Hutang'));

            $target = $invoiceNumber !== null ? $this->invoiceContext($invoiceNumber, $eligible) : null;
            if (($target['status'] ?? null) === 'void') {
                $this->check($checks, 'cancelled_invoice.included_in_details', false, $data['invoicePerformance']->contains('invoice_number', $invoiceNumber));
                $this->check($checks, 'cancelled_invoice.included_in_csv', false, $csvRows->contains('No Invoice', $invoiceNumber));
                $this->check($checks, 'cancelled_invoice.balance_due', 0, $target['balance_due']);
            }

            return [
                'ok' => ! collect($checks)->contains('ok', false),
                'read_only' => true,
                'filters' => $filters,
                'checks' => $checks,
                'cancelled_invoices_excluded' => $rows->where('status', 'void')->pluck('invoice_number')->values()->all(),
                'invoice' => $target,
            ];
        });
    }

    private function invoiceQuery(array $filters): Builder
    {
        $query = DB::table('invoices')->whereNotNull('invoices.issued_at');
        if ($filters['business_unit']) {
            $query->where('invoices.business_unit', $filters['business_unit']);
        }
        if ($filters['event_status']) {
            $query->where('invoices.event_status', $filters['event_status']);
        }
        if ($filters['product']) {
            $query->whereExists(fn (Builder $items) => $items->selectRaw('1')->from('invoice_items')
                ->whereColumn('invoice_items.invoice_id', 'invoices.id')->where('invoice_items.name', $filters['product']));
        }

        return $query;
    }

    private function transactionTotal(string $table, string $date, array $filters): float
    {
        $query = DB::table($table)->whereIn($table.'.invoice_id', $this->invoiceQuery($filters)->select('invoices.id'));
        $this->period($query, $table.'.'.$date, $filters);
        if ($table === 'payments') {
            $query->whereNotExists(fn (Builder $voids) => $voids->selectRaw('1')->from('payment_voids')->whereColumn('payment_voids.payment_id', 'payments.id'))
                ->whereExists(fn (Builder $invoices) => $invoices->selectRaw('1')->from('invoices')->whereColumn('invoices.id', 'payments.invoice_id')
                    ->where(fn (Builder $status) => $status->whereNull('invoices.status')->orWhere('invoices.status', '!=', 'void')));
        }

        return (float) $query->sum($table.'.amount');
    }

    private function paidTotals(array $invoiceIds): Collection
    {
        return DB::table('payments')->whereIn('invoice_id', $invoiceIds)
            ->whereNotExists(fn (Builder $voids) => $voids->selectRaw('1')->from('payment_voids')->whereColumn('payment_voids.payment_id', 'payments.id'))
            ->selectRaw('invoice_id, SUM(amount) AS amount')->groupBy('invoice_id')->pluck('amount', 'invoice_id');
    }

    private function outstanding(Collection $invoices, Collection $paid): float
    {
        return (float) $invoices->sum(fn ($invoice) => max(0, (float) $invoice->grand_total - (float) $invoice->pph_amount - (float) $paid->get($invoice->id, 0)));
    }

    private function period(Builder $query, string $column, array $filters): void
    {
        $query->whereYear($column, $filters['year']);
        if ($filters['month'] !== null) {
            $query->whereMonth($column, $filters['month']);
        }
    }

    private function csvRows(Request $request): Collection
    {
        ob_start();
        try {
            $this->report->export($request)->sendContent();
            $csv = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $stream = fopen('php://temp', 'w+');
        try {
            fwrite($stream, substr($csv, 3));
            rewind($stream);
            $header = fgetcsv($stream, 0, ';', '"', '');
            $rows = collect();
            while (($row = fgetcsv($stream, 0, ';', '"', '')) !== false) {
                $rows->push(array_combine($header, $row));
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    private function invoiceContext(string $number, Collection $eligible): array
    {
        $invoice = DB::table('invoices')->where('invoice_number', $number)->first();
        if (! $invoice) {
            throw ValidationException::withMessages(['invoice' => 'Nomor invoice tidak ditemukan.']);
        }
        $audit = DB::table('crm_data_corrections')->where('invoice_number', $number)->whereIn('kind', ['invoice_cancel', 'invoice_void'])->first();
        $before = $audit ? json_decode($audit->snapshot, true, 512, JSON_THROW_ON_ERROR)['invoice'] : null;

        return [
            'invoice_number' => $number, 'status' => $invoice->status, 'event_status' => $invoice->event_status,
            'original_value' => (float) $invoice->grand_total, 'balance_due' => (float) $invoice->balance_due,
            'included_in_invoice_report' => $eligible->contains('id', $invoice->id),
            'cancellation_audit_id' => $audit?->id,
            'audit_kind' => $audit?->kind,
            'confirmed_value_before_cancellation' => ($before['event_status'] ?? null) === 'confirm' ? (float) $before['grand_total'] : null,
            'receivable_before_cancellation' => ($before['event_status'] ?? null) === 'confirm'
                ? max(0, (float) $before['grand_total'] - (float) ($before['pph_amount'] ?? 0) - (float) $before['paid_amount']) : null,
        ];
    }

    private function check(array &$checks, string $name, mixed $expected, mixed $actual): void
    {
        $numeric = is_numeric($expected) && is_numeric($actual);
        $checks[$name] = [
            'expected' => $numeric ? round((float) $expected, 4) : $expected,
            'actual' => $numeric ? round((float) $actual, 4) : $actual,
            'ok' => $numeric ? abs((float) $expected - (float) $actual) < 0.0001 : $expected === $actual,
        ];
    }
}
