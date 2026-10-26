<?php

namespace Webkul\Admin\Services;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webkul\Core\Support\BusinessUnit;

/** Presentation only: the web dashboard and CLI use the same reconciliation. */
class FinancialReportAuditDashboardService
{
    public function __construct(private FinancialReportAuditService $audit) {}

    public function filters(Request $request): array
    {
        $data = Validator::make([
            'year' => $request->input('year', now()->year),
            'month' => $request->input('month') ?: 'all',
            'business_unit' => $request->input('business_unit'),
            'event_status' => $request->input('event_status'),
            'product' => $request->input('product'),
            'invoice' => $request->input('invoice'),
            'history_search' => $request->input('history_search'),
            'history_page' => $request->input('history_page', 1),
        ], [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'regex:/^(all|[1-9]|1[0-2])$/'],
            'business_unit' => ['nullable', 'in:'.implode(',', BusinessUnit::values())],
            'event_status' => ['nullable', 'in:prospect,confirm,cancel'],
            'product' => ['nullable', 'string', 'max:255'],
            'invoice' => ['nullable', 'string', 'max:255'],
            'history_search' => ['nullable', 'string', 'max:255'],
            'history_page' => ['required', 'integer', 'between:1,1000000'],
        ])->validate();
        foreach (['business_unit', 'event_status', 'product', 'invoice', 'history_search'] as $key) {
            $data[$key] = isset($data[$key]) ? trim($data[$key]) : null;
            $data[$key] = $data[$key] === '' ? null : $data[$key];
        }

        return $data;
    }

    public function run(array $filters): array
    {
        $result = $this->audit->audit(array_intersect_key($filters, array_flip(['year', 'month', 'business_unit', 'event_status', 'product'])), $filters['invoice']);

        return ['checked_at' => now()->toIso8601String()] + $result;
    }

    public function build(Request $request): array
    {
        $result = null;
        $error = null;
        try {
            $filters = $this->filters($request);
            if ($request->boolean('run_financial_audit')) {
                $result = $this->run($filters);
            }
        } catch (ValidationException $exception) {
            $filters ??= $this->filters(new Request);
            $error = collect($exception->errors())->flatten()->implode(' ');
        } catch (\Throwable $exception) {
            report($exception);
            $error = 'Audit belum berhasil dijalankan. Periksa log aplikasi, lalu jalankan ulang. Hasil belum tersedia.';
        }

        return [
            'filters' => $filters,
            'result' => $result,
            'error' => $error,
            'groups' => $result ? $this->groups($result['checks']) : [],
            'passed' => $result ? collect($result['checks'])->where('ok', true)->count() : 0,
            'failed' => $result ? collect($result['checks'])->where('ok', false)->count() : 0,
            'history' => $this->history($filters, $request),
            'businessUnitOptions' => BusinessUnit::options(),
            'productOptions' => DB::table('invoice_items')->whereNotNull('name')->where('name', '!=', '')->distinct()->orderBy('name')->pluck('name'),
        ];
    }

    private function history(array $filters, Request $request): LengthAwarePaginator
    {
        $query = DB::table('crm_data_corrections as corrections')
            ->leftJoin('users', 'users.id', '=', 'corrections.actor_id')
            ->leftJoin('invoices', 'invoices.invoice_number', '=', 'corrections.invoice_number')
            ->whereIn('corrections.kind', ['invoice_cancel', 'invoice_void']);
        if ($search = $filters['history_search']) {
            $query->where(fn ($query) => $query->where('corrections.invoice_number', 'like', '%'.$search.'%')
                ->orWhere('corrections.reason', 'like', '%'.$search.'%')->orWhere('users.name', 'like', '%'.$search.'%'));
        }
        $history = $query->orderByDesc('corrections.id')->select([
            'corrections.*', 'users.name as actor_name', 'invoices.id as invoice_id',
            'invoices.status as current_status', 'invoices.balance_due as current_balance_due',
        ])->paginate(15, ['*'], 'history_page', (int) $filters['history_page']);
        $history->withPath(route('admin.operations-dashboard.index'))->appends($request->except('history_page'))->fragment('financial-audit-history');
        $history->through(function ($row) {
            $snapshot = json_decode($row->snapshot, true);
            $result = json_decode($row->result, true);
            $row->before = is_array($snapshot) && is_array($snapshot['invoice'] ?? null) ? $snapshot['invoice'] : null;
            $row->outcome = is_array($result) ? $result : null;

            return $row;
        });

        return $history;
    }

    private function groups(array $checks): array
    {
        $labels = [
            'summary.invoiced' => 'Total tagihan Confirm', 'summary.outstanding' => 'Piutang saat ini',
            'summary.received' => 'Penerimaan dalam periode', 'summary.expense' => 'Pengeluaran dalam periode',
            'summary.cash_surplus' => 'Surplus kas', 'invoice.count' => 'Jumlah invoice',
            'invoice.paid' => 'Invoice Paid', 'invoice.partial' => 'Invoice Partial', 'invoice.unpaid' => 'Invoice Unpaid',
            'invoice.ids' => 'Daftar ID invoice yang masuk laporan', 'details.confirmed_invoiced' => 'Total tagihan Confirm pada rincian',
            'details.outstanding' => 'Total piutang pada rincian', 'cash.received' => 'Penerimaan pada rincian kas',
            'cash.expense' => 'Pengeluaran pada rincian kas', 'expense.categories' => 'Total kategori biaya',
            'annual.revenue' => 'Tagihan tahunan', 'annual.received' => 'Penerimaan tahunan',
            'annual.expense' => 'Pengeluaran tahunan', 'annual.cash_surplus' => 'Surplus kas tahunan',
            'project.invoiced' => 'Total tagihan proyek', 'project.outstanding' => 'Piutang proyek saat ini',
            'project.expense' => 'Total biaya proyek', 'project.margin' => 'Margin proyek',
            'csv.invoice_numbers' => 'Daftar nomor invoice pada CSV', 'csv.invoice_value' => 'Total nominal invoice pada CSV',
            'csv.outstanding' => 'Total piutang pada CSV', 'cancelled_invoice.included_in_details' => 'Invoice VOID masuk rincian',
            'cancelled_invoice.included_in_csv' => 'Invoice VOID masuk CSV', 'cancelled_invoice.balance_due' => 'Saldo invoice VOID',
        ];
        $titles = [
            'summary' => 'Ringkasan Financial Report', 'invoice' => 'Jumlah dan daftar invoice',
            'details' => 'Rincian invoice', 'cash' => 'Arus kas', 'expense' => 'Kategori biaya',
            'annual' => 'Rekap tahunan', 'project' => 'Posisi proyek saat ini', 'csv' => 'Ekspor CSV',
            'cancelled_invoice' => 'Invoice yang diperiksa',
        ];
        $groups = [];
        foreach ($checks as $key => $check) {
            $prefix = explode('.', $key)[0];
            $money = ! in_array($prefix, ['invoice', 'cancelled_invoice'], true) && $key !== 'csv.invoice_numbers'
                || $key === 'cancelled_invoice.balance_due';
            $groups[$prefix]['title'] = $titles[$prefix] ?? $prefix;
            $groups[$prefix]['checks'][$key] = $check + [
                'label' => $labels[$key] ?? $key, 'money' => $money,
                'difference' => is_numeric($check['expected']) && is_numeric($check['actual'])
                    ? round((float) $check['actual'] - (float) $check['expected'], 4) : null,
            ];
        }

        return $groups;
    }
}
