<?php

namespace Webkul\Admin\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Webkul\Core\Support\BusinessUnit;
use Webkul\Invoice\Models\Invoice;
use Webkul\User\Models\User;

class FinanceSalesDashboardService
{
    public const MARKER = 'CRM_FINANCE_SALES_DASHBOARD_V1';

    private const FOCUS_OPTIONS = [
        'all',
        'unpaid',
        'partial',
        'overdue',
        'due_soon',
        'down_payment',
        'settlement',
    ];

    public function normalizeFilters(array $input): array
    {
        $focus = strtolower(trim((string) ($input['focus'] ?? 'all')));

        if (! in_array($focus, self::FOCUS_OPTIONS, true)) {
            $focus = 'all';
        }

        return [
            'search' => mb_substr(trim((string) ($input['search'] ?? '')), 0, 120),
            'sales_user_id' => max(0, (int) ($input['sales_user_id'] ?? 0)),
            'business_unit' => mb_substr(trim((string) ($input['business_unit'] ?? '')), 0, 80),
            'focus' => $focus,
        ];
    }

    public function build(array $filters, int $perPage = 25): array
    {
        $filters = $this->normalizeFilters($filters);
        $metricInvoices = $this->metricInvoices($filters);
        $readySettlements = $this->readySettlements($filters);
        $actionInvoices = $this->actionInvoices(
            $filters,
            min(100, max(10, $perPage))
        );

        return [
            'filters' => $filters,
            'metrics' => $this->metrics($metricInvoices, $readySettlements),
            'aging' => $this->aging($metricInvoices),
            'actionInvoices' => $actionInvoices,
            'readySettlements' => $readySettlements,
            'salesPortfolio' => $this->salesPortfolio($metricInvoices),
            'salesUsers' => User::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'businessUnits' => $this->businessUnitOptions(),
        ];
    }

    public function exportRows(array $filters): Collection
    {
        $filters = $this->normalizeFilters($filters);
        $query = $this->baseInvoiceQuery();

        $this->applyDimensions($query, $filters);
        $this->applyFocus($query, $filters['focus']);
        $this->applyOutstandingConstraint($query);

        return $query
            ->orderByRaw('CASE WHEN invoices.due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('invoices.due_at')
            ->orderBy('invoices.id')
            ->get()
            ->map(fn (Invoice $invoice) => $this->decorateInvoice($invoice));
    }

    /* CRM_FINANCE_SALES_DASHBOARD_UI_HOTFIX_V1_1 */
    private function businessUnitOptions(): Collection
    {
        $masterOptions = collect(BusinessUnit::options())
            ->map(fn ($label, $value) => [
                'value' => (string) $value,
                'label' => (string) $label,
            ])
            ->values();

        $knownValues = $masterOptions->pluck('value')->all();

        $historicalOptions = Invoice::query()
            ->whereNotNull('business_unit')
            ->where('business_unit', '!=', '')
            ->distinct()
            ->pluck('business_unit')
            ->map(fn ($value) => (string) $value)
            ->filter(fn ($value) => ! in_array($value, $knownValues, true))
            ->map(fn ($value) => [
                'value' => $value,
                'label' => BusinessUnit::label($value) ?: $value,
            ]);

        return $masterOptions
            ->concat($historicalOptions)
            ->unique('value')
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
    private function metricInvoices(array $filters): Collection
    {
        $query = $this->baseInvoiceQuery();

        $this->applyDimensions($query, $filters);
        $this->applyOutstandingConstraint($query);

        return $query
            ->orderBy('invoices.due_at')
            ->get()
            ->map(fn (Invoice $invoice) => $this->decorateInvoice($invoice));
    }

    private function actionInvoices(
        array $filters,
        int $perPage
    ): LengthAwarePaginator {
        $query = $this->baseInvoiceQuery();

        $this->applyDimensions($query, $filters);
        $this->applyFocus($query, $filters['focus']);
        $this->applyOutstandingConstraint($query);

        $paginator = $query
            ->orderByRaw('CASE WHEN invoices.due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('invoices.due_at')
            ->orderBy('invoices.id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(fn (Invoice $invoice) => $this->decorateInvoice($invoice))
        );

        return $paginator;
    }

    private function baseInvoiceQuery(): Builder
    {
        return Invoice::query()
            ->select('invoices.*')
            ->with([
                'person:id,name',
                'user:id,name',
                'quote:id,quote_number,grand_total',
            ])
            ->withSum('payments as dashboard_paid_total', 'amount')
            ->where('invoices.event_status', 'confirm');
    }

    private function applyDimensions(Builder $query, array $filters): void
    {
        if ($filters['sales_user_id'] > 0) {
            $query->where('invoices.user_id', $filters['sales_user_id']);
        }

        if ($filters['business_unit'] !== '') {
            $query->where('invoices.business_unit', $filters['business_unit']);
        }

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';

            $query->where(function (Builder $search) use ($term) {
                $search
                    ->where('invoices.invoice_number', 'like', $term)
                    ->orWhere('invoices.project_code', 'like', $term)
                    ->orWhere('invoices.subject', 'like', $term)
                    ->orWhereHas('person', fn (Builder $person) =>
                        $person->where('name', 'like', $term)
                    )
                    ->orWhereHas('user', fn (Builder $user) =>
                        $user->where('name', 'like', $term)
                    );
            });
        }
    }

    private function applyOutstandingConstraint(Builder $query): void
    {
        $query->whereRaw(
            '(SELECT COALESCE(SUM(crm_dashboard_payments.amount), 0) '
            .'FROM payments AS crm_dashboard_payments '
            .'WHERE crm_dashboard_payments.invoice_id = invoices.id) '
            .'< COALESCE(invoices.grand_total, 0)'
        );
    }

    private function applyFocus(Builder $query, string $focus): void
    {
        $paidSql = '(SELECT COALESCE(SUM(crm_focus_payments.amount), 0) '
            .'FROM payments AS crm_focus_payments '
            .'WHERE crm_focus_payments.invoice_id = invoices.id)';

        match ($focus) {
            'unpaid' => $query->whereRaw($paidSql.' <= 0'),
            'partial' => $query
                ->whereRaw($paidSql.' > 0')
                ->whereRaw($paidSql.' < COALESCE(invoices.grand_total, 0)'),
            'overdue' => $query
                ->whereNotNull('invoices.due_at')
                ->whereDate('invoices.due_at', '<', today()),
            'due_soon' => $query
                ->whereNotNull('invoices.due_at')
                ->whereDate('invoices.due_at', '>=', today())
                ->whereDate('invoices.due_at', '<=', today()->addDays(7)),
            'down_payment' => $query->where('invoices.billing_type', 'down_payment'),
            'settlement' => $query->where('invoices.billing_type', 'settlement'),
            default => null,
        };
    }

    private function readySettlements(array $filters): Collection
    {
        $query = $this->baseInvoiceQuery()
            ->where('invoices.billing_type', 'down_payment')
            ->whereNotNull('invoices.quote_id');

        $this->applyDimensions($query, $filters);

        $downPayments = $query->get();

        if ($downPayments->isEmpty()) {
            return collect();
        }

        $quoteIds = $downPayments
            ->pluck('quote_id')
            ->filter()
            ->unique()
            ->values();

        $blockedQuoteIds = Invoice::query()
            ->whereIn('quote_id', $quoteIds)
            ->whereIn('billing_type', ['settlement', 'full_payment', 'full'])
            ->where(function (Builder $active) {
                $active
                    ->whereNull('event_status')
                    ->orWhereNotIn('event_status', ['cancel', 'cancelled', 'canceled']);
            })
            ->pluck('quote_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $downPayments
            ->filter(function (Invoice $invoice) use ($blockedQuoteIds) {
                $paid = (float) ($invoice->dashboard_paid_total ?? 0);
                $outstanding = max(0, (float) $invoice->grand_total - $paid);
                $remaining = (float) ($invoice->remaining_amount_snapshot ?? 0);

                return $outstanding <= 0.009
                    && $remaining > 0.009
                    && ! in_array((int) $invoice->quote_id, $blockedQuoteIds, true);
            })
            ->map(function (Invoice $invoice) {
                return [
                    'invoice_id' => (int) $invoice->id,
                    'invoice_number' => (string) $invoice->invoice_number,
                    'quote_id' => (int) $invoice->quote_id,
                    'quote_number' => (string) ($invoice->quote?->quote_number ?: '-'),
                    'project_code' => (string) ($invoice->project_code ?: '-'),
                    'subject' => (string) ($invoice->subject ?: '-'),
                    'customer' => (string) ($invoice->person?->name ?: '-'),
                    'salesperson' => (string) ($invoice->user?->name ?: 'Belum ditentukan'),
                    'dp_value' => round((float) $invoice->grand_total, 2),
                    'remaining' => round(
                        (float) $invoice->remaining_amount_snapshot,
                        2
                    ),
                    'paid_at_label' => $invoice->updated_at?->format('d M Y') ?: '-',
                ];
            })
            ->sortByDesc('remaining')
            ->values();
    }

    private function decorateInvoice(Invoice $invoice): array
    {
        $invoiceValue = round((float) $invoice->grand_total, 2);
        $paid = round((float) ($invoice->dashboard_paid_total ?? 0), 2);
        $outstanding = round(max(0, $invoiceValue - $paid), 2);
        $dueAt = $invoice->due_at ? Carbon::parse($invoice->due_at)->startOfDay() : null;
        $today = today()->startOfDay();
        $daysOverdue = $dueAt && $dueAt->lt($today)
            ? $dueAt->diffInDays($today)
            : 0;
        $dueInDays = $dueAt && $dueAt->gte($today)
            ? $today->diffInDays($dueAt)
            : null;
        $billingType = $invoice->billing_type ?: 'full_payment';

        return [
            'id' => (int) $invoice->id,
            'invoice_number' => (string) ($invoice->invoice_number ?: '#'.$invoice->id),
            'project_code' => (string) ($invoice->project_code ?: '-'),
            'subject' => (string) ($invoice->subject ?: '-'),
            'customer' => (string) ($invoice->person?->name ?: '-'),
            'salesperson' => (string) ($invoice->user?->name ?: 'Belum ditentukan'),
            'business_unit' => (string) ($invoice->business_unit ?: '-'),
            'business_unit_label' => $invoice->business_unit
                ? BusinessUnit::label((string) $invoice->business_unit)
                : '-',
            'billing_type' => $billingType,
            'billing_label' => match ($billingType) {
                'down_payment' => 'DP',
                'settlement' => 'PELUNASAN',
                default => 'FULL PAYMENT',
            },
            'invoice_value' => $invoiceValue,
            'paid' => $paid,
            'outstanding' => $outstanding,
            'payment_status' => $paid <= 0.009 ? 'unpaid' : 'partial',
            'payment_status_label' => $paid <= 0.009 ? 'UNPAID' : 'PARTIAL',
            'issued_at_label' => $invoice->issued_at?->format('d M Y') ?: '-',
            'due_at_label' => $dueAt?->format('d M Y') ?: 'Tanpa jatuh tempo',
            'days_overdue' => $daysOverdue,
            'due_in_days' => $dueInDays,
            'is_overdue' => $daysOverdue > 0,
            'is_due_soon' => $dueInDays !== null && $dueInDays <= 7,
        ];
    }

    private function metrics(
        Collection $invoices,
        Collection $readySettlements
    ): array {
        $overdue = $invoices->where('is_overdue', true);
        $dueSoon = $invoices->where('is_due_soon', true);
        $dpWaiting = $invoices->where('billing_type', 'down_payment');
        $settlement = $invoices->where('billing_type', 'settlement');

        return [
            'outstanding_total' => round((float) $invoices->sum('outstanding'), 2),
            'open_count' => $invoices->count(),
            'unpaid_count' => $invoices->where('payment_status', 'unpaid')->count(),
            'unpaid_total' => round((float) $invoices
                ->where('payment_status', 'unpaid')
                ->sum('outstanding'), 2),
            'partial_count' => $invoices->where('payment_status', 'partial')->count(),
            'partial_total' => round((float) $invoices
                ->where('payment_status', 'partial')
                ->sum('outstanding'), 2),
            'overdue_count' => $overdue->count(),
            'overdue_total' => round((float) $overdue->sum('outstanding'), 2),
            'due_soon_count' => $dueSoon->count(),
            'due_soon_total' => round((float) $dueSoon->sum('outstanding'), 2),
            'dp_waiting_count' => $dpWaiting->count(),
            'dp_waiting_total' => round((float) $dpWaiting->sum('outstanding'), 2),
            'settlement_outstanding_count' => $settlement->count(),
            'settlement_outstanding_total' => round(
                (float) $settlement->sum('outstanding'),
                2
            ),
            'ready_settlement_count' => $readySettlements->count(),
            'ready_settlement_total' => round(
                (float) $readySettlements->sum('remaining'),
                2
            ),
        ];
    }

    private function aging(Collection $invoices): array
    {
        $buckets = [
            'current' => ['label' => 'Belum Jatuh Tempo', 'count' => 0, 'amount' => 0.0],
            '1_7' => ['label' => 'Terlambat 1–7 Hari', 'count' => 0, 'amount' => 0.0],
            '8_30' => ['label' => 'Terlambat 8–30 Hari', 'count' => 0, 'amount' => 0.0],
            '31_60' => ['label' => 'Terlambat 31–60 Hari', 'count' => 0, 'amount' => 0.0],
            'over_60' => ['label' => 'Terlambat >60 Hari', 'count' => 0, 'amount' => 0.0],
        ];

        foreach ($invoices as $invoice) {
            $days = (int) $invoice['days_overdue'];
            $key = match (true) {
                $days <= 0 => 'current',
                $days <= 7 => '1_7',
                $days <= 30 => '8_30',
                $days <= 60 => '31_60',
                default => 'over_60',
            };

            $buckets[$key]['count']++;
            $buckets[$key]['amount'] = round(
                $buckets[$key]['amount'] + (float) $invoice['outstanding'],
                2
            );
        }

        return $buckets;
    }

    private function salesPortfolio(Collection $invoices): Collection
    {
        return $invoices
            ->groupBy('salesperson')
            ->map(function (Collection $items, string $salesperson) {
                return [
                    'salesperson' => $salesperson,
                    'invoice_count' => $items->count(),
                    'unpaid_count' => $items->where('payment_status', 'unpaid')->count(),
                    'partial_count' => $items->where('payment_status', 'partial')->count(),
                    'overdue_count' => $items->where('is_overdue', true)->count(),
                    'outstanding' => round((float) $items->sum('outstanding'), 2),
                ];
            })
            ->sortByDesc('outstanding')
            ->values();
    }
}
