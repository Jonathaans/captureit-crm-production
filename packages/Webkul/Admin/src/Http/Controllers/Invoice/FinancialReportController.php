<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\ProformaFollowUpService;
use Webkul\Admin\Services\TopProductReportService;
use Webkul\Core\Support\BusinessUnit;
use Webkul\Invoice\Models\Expense;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\Payment;
use Webkul\Invoice\Repositories\ExpenseCategoryRepository;

class FinancialReportController extends Controller
{
    public function __construct(
        private readonly TopProductReportService $topProductReport,
    ) {}

    /**
     * Official values come from issued invoices and their payment/expense rows.
     * Invoice totals use issued_at; cash movements use the transaction date.
     * Receivable is the current balance of invoices issued in the chosen period.
     * Project margin is current, grouped across actual invoices of selected projects.
     * Pending proformas are operational follow-up only and never enter exports/totals.
     */
    public function index(Request $request): View
    {
        $filters = $this->normalizeFilters($request);

        $financialSummary = $this->buildFinancialSummary($filters);

        $invoicePerformance = $this->buildInvoicePerformance($filters);

        $invoiceStats = $this->buildInvoiceStats($filters);

        $expenseByCategory = $this->buildExpenseByCategory($filters);

        $topProducts = $this->topProductReport->rows($filters);

        /*
         * Monthly analytics only matters when Month = All.
         * The view hides it when a specific month is selected.
         */
        $monthlyPerformance = $filters['month'] === null
            ? $this->buildMonthlyPerformance($filters)
            : collect();

        $availableYears = $this->availableYears();

        $businessUnitOptions = BusinessUnit::options();

        /*
         * Product is sourced from invoice_items.name because invoice items
         * are the immutable commercial snapshot stored on each invoice.
         * This means historical reports keep showing what was actually
         * invoiced even if the Product master is renamed later.
         */
        $productOptions = $this->availableProducts();

        return view(
            'admin::invoices.financial-report',
            [
                'year' => $filters['year'],
                'month' => $filters['month'],
                'businessUnit' => $filters['business_unit'],
                'eventStatus' => $filters['event_status'],
                'product' => $filters['product'],
                'availableYears' => $availableYears,
                'businessUnitOptions' => $businessUnitOptions,
                'productOptions' => $productOptions,
                'financialSummary' => $financialSummary,
                'invoicePerformance' => $invoicePerformance,
                'invoiceStats' => $invoiceStats,
                'expenseByCategory' => $expenseByCategory,
                'monthlyPerformance' => $monthlyPerformance,
                'topProducts' => $topProducts->take(10)->values(),
                'topProductTotal' => $topProducts->count(),
                'cashTransactions' => $this->buildCashTransactions($filters),
                'projectPerformance' => $this->buildProjectPerformance($filters),
                'proformaFollowUp' => bouncer()->hasPermission('invoices')
                    ? app(ProformaFollowUpService::class)->summary($filters) : null,
            ]
        );
    }

    /**
     * Export the finance team's invoice list for the current filters.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->normalizeFilters($request);

        if ($request->input('section') === 'top-products') {
            return $this->exportTopProducts($filters);
        }

        $invoicePerformance = $this->buildInvoicePerformance($filters)
            ->sortBy([['issued_at', 'asc'], ['id', 'asc']]);
        $fileName = $this->buildExportFileName($filters);

        return response()->streamDownload(
            function () use ($invoicePerformance): void {
                $handle = fopen('php://output', 'w');

                if ($handle === false) {
                    return;
                }

                // UTF-8 BOM for Excel; the first row is the table header.
                fwrite($handle, "\xEF\xBB\xBF");

                $writeRow = static function ($stream, array $row): void {
                    fputcsv($stream, $row, ';', '"', '');
                };

                $writeRow($handle, [
                    'Project Code',
                    'Business Unit',
                    'No Invoice',
                    'Jenis Invoice',
                    'Tanggal Inv',
                    'Nama Customer',
                    'Company',
                    'No. Telepon Pemesan',
                    'Nama Event',
                    'Tanggal Event',
                    'Produk',
                    'Status Project',
                    'Status Pembayaran',
                    'Nilai Invoice',
                    'Nilai Pembayaran',
                    'Nilai Pembayaran Periode',
                    'Sisa Hutang',
                    'Sales',
                ]);

                foreach ($invoicePerformance as $invoice) {
                    $writeRow($handle, [
                        $invoice['project_code'],
                        $invoice['business_unit_label'],
                        $invoice['invoice_number'],
                        strtoupper(str_replace('_', ' ', $invoice['billing_type'])),
                        $invoice['issued_at']?->format('j M Y'),
                        $invoice['recipient_name'],
                        $invoice['company'],
                        // Keep leading zeroes and +country codes as text in spreadsheets.
                        $invoice['customer_phone'] !== '' ? "'".$invoice['customer_phone'] : '',
                        $invoice['subject'],
                        $invoice['event_date']?->format('j M Y'),
                        $invoice['products'],
                        strtoupper($invoice['event_status']),
                        strtoupper($invoice['status']),
                        $invoice['invoice_value'],
                        $invoice['paid_to_date'],
                        $invoice['received_in_period'],
                        $invoice['outstanding'],
                        $invoice['sales'],
                    ]);
                }

                fclose($handle);
            },
            $fileName,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }

    /**
     * Export product ranking for the same filters shown on screen.
     */
    private function exportTopProducts(array $filters): StreamedResponse
    {
        $topProducts = $this->topProductReport->rows($filters);
        $fileName = $this->buildTopProductExportFileName($filters);

        return response()->streamDownload(
            function () use ($filters, $topProducts): void {
                $handle = fopen('php://output', 'w');

                if ($handle === false) {
                    return;
                }

                fwrite($handle, "\xEF\xBB\xBF");

                $safeCell = static function (mixed $value): string|int|float {
                    if (is_int($value) || is_float($value)) {
                        return $value;
                    }

                    $value = str_replace(["\r\n", "\r", "\n"], ' ', trim((string) $value));

                    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
                        return "'".$value;
                    }

                    return $value;
                };

                $writeRow = static function ($stream, array $row) use ($safeCell): void {
                    fputcsv(
                        $stream,
                        array_map($safeCell, $row),
                        ';',
                        '"',
                        ''
                    );
                };

                $writeRow($handle, ['TOP PRODUCT REPORT']);
                $writeRow($handle, ['Year', $filters['year']]);
                $writeRow($handle, [
                    'Month',
                    $filters['month']
                        ? Carbon::createFromDate($filters['year'], $filters['month'], 1)->format('F')
                        : 'ALL MONTHS',
                ]);
                $writeRow($handle, [
                    'Business Unit',
                    $filters['business_unit']
                        ? BusinessUnit::label($filters['business_unit'])
                        : 'ALL BUSINESS UNITS',
                ]);
                $writeRow($handle, ['Product', $filters['product'] ?: 'ALL PRODUCTS']);
                $writeRow($handle, [
                    'Rule',
                    'One project/product is counted once within the selected period. Values use actual confirmed invoices only; quote and proforma values are excluded.',
                ]);
                $writeRow($handle, [
                    'Period Basis',
                    'Issue date of each confirmed invoice. DP and settlement contribute their own billed values in their respective periods.',
                ]);
                $writeRow($handle, [
                    'Payment Allocation',
                    'Payments received to date on selected invoices, allocated using each invoice item snapshot. This is not period cash flow.',
                ]);
                $writeRow($handle, []);
                $writeRow($handle, [
                    'Rank',
                    'Product',
                    'SKU',
                    'Confirmed Projects',
                    'Quantity',
                    'Allocated Invoice Value',
                    'Allocated Payment Received',
                    'Collection Rate (%)',
                ]);

                foreach ($topProducts as $row) {
                    $writeRow($handle, [
                        $row['rank'],
                        $row['product_name'],
                        $row['sku'],
                        $row['deal_count'],
                        $row['quantity_label'],
                        $row['sales_value'],
                        $row['received_allocated'],
                        $row['collection_rate'],
                    ]);
                }

                fclose($handle);
            },
            $fileName,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]
        );
    }

    /**
     * Normalize every report filter in one place.
     */
    private function normalizeFilters(Request $request): array
    {
        return [
            'year' => $this->normalizeYear(
                $request->input('year')
            ),

            'month' => $this->normalizeMonth(
                $request->input('month')
            ),

            'business_unit' => $this->normalizeBusinessUnit(
                $request->input('business_unit')
            ),

            'event_status' => $this->normalizeEventStatus(
                $request->input('event_status')
            ),

            'product' => $this->normalizeProduct(
                $request->input('product')
            ),
        ];
    }

    /**
     * Billing rows and counts use the same cohort as the invoice export.
     * Cancelled events retain their documents and actual cash history, but no
     * longer contribute an active invoice value, even with no status filter.
     */
    private function invoiceReportQuery(array $filters): Builder
    {
        $query = Invoice::query()->notVoided()->whereNotNull('issued_at')
            ->where(fn (Builder $query) => $query->whereNull('event_status')
                ->orWhereNotIn('event_status', ['cancel', 'cancelled', 'canceled']));

        $this->applyInvoicePeriod($query, $filters);
        $this->applyInvoiceDimensions($query, $filters);

        return $query;
    }

    /**
     * Summary values shown in top cards.
     */
    private function buildFinancialSummary(array $filters): array
    {
        $invoices = $this->invoiceReportQuery($filters)->where('event_status', 'confirm');
        $invoiced = (float) (clone $invoices)->sum('grand_total');
        $outstanding = (float) (clone $invoices)->withSum('activePayments as payments_sum_amount', 'amount')->get()->sum(
            fn (Invoice $invoice): float => max(0, $invoice->cashPayable() - (float) $invoice->payments_sum_amount)
        );

        $payments = Payment::query()->notVoided()->whereNotNull('paid_at');
        $this->applyTransactionPeriod($payments, 'paid_at', $filters);
        $this->applyTransactionInvoiceDimensions($payments, $filters);
        $received = (float) $payments->sum('amount');

        $expenses = Expense::query()->whereNotNull('expense_date');
        $this->applyTransactionPeriod($expenses, 'expense_date', $filters);
        $this->applyTransactionInvoiceDimensions($expenses, $filters);
        $expense = (float) $expenses->sum('amount');

        return [
            'invoiced' => $invoiced,
            'received' => $received,
            'outstanding' => $outstanding,
            'expense' => $expense,
            'cash_surplus' => $received - $expense,
        ];
    }

    /** Every receipt/expense in the period, even if its invoice was issued earlier. */
    private function buildCashTransactions(array $filters): Collection
    {
        $rows = collect();
        foreach ([Payment::class => 'paid_at', Expense::class => 'expense_date'] as $model => $dateColumn) {
            $query = $model::query()->with(['invoice.person.organization', 'invoice.quote.person.organization'])->whereNotNull($dateColumn);
            if ($model === Payment::class) {
                $query->notVoided();
            }
            $this->applyTransactionPeriod($query, $dateColumn, $filters);
            $this->applyTransactionInvoiceDimensions($query, $filters);
            foreach ($query->get() as $transaction) {
                $invoice = $transaction->invoice;
                $identity = $invoice->billToIdentity();
                $rows->push([
                    'type' => $model === Payment::class ? 'Payment' : 'Expense',
                    'date' => $transaction->{$dateColumn},
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'project_code' => $invoice->project_code ?: '-',
                    'business_unit' => BusinessUnit::label($invoice->business_unit),
                    'customer' => $identity['person_name'] ?? '-',
                    'company' => $identity['company_name'] ?? '-',
                    'reference' => $transaction->reference_number ?: '-',
                    'description' => $transaction->description ?? $transaction->notes ?? '-',
                    'cash_in' => $model === Payment::class ? (float) $transaction->amount : 0.0,
                    'cash_out' => $model === Expense::class ? (float) $transaction->amount : 0.0,
                ]);
            }
        }

        return $rows->sortBy('date')->values();
    }

    /** Current project position for projects with a confirmed invoice in the chosen period. */
    private function buildProjectPerformance(array $filters): Collection
    {
        $cohort = $this->invoiceReportQuery($filters)->where('event_status', 'confirm');
        $references = $cohort->get(['id', 'quote_id']);
        if ($references->isEmpty()) {
            return collect();
        }
        $quoteIds = $references->pluck('quote_id')->filter()->unique()->values();
        $standaloneIds = $references->whereNull('quote_id')->pluck('id');

        // Historical costs still belong to this project after a billing document is voided.
        return Invoice::query()
            ->with(['person.organization', 'quote.person.organization'])
            ->withSum('activePayments as payments_sum_amount', 'amount')->withSum('expenses', 'amount')
            ->whereNotNull('issued_at')
            ->where(fn (Builder $query) => $query->whereIn('quote_id', $quoteIds)->orWhereIn('id', $standaloneIds))
            ->orderBy('issued_at')->orderBy('id')->get()
            ->groupBy(fn (Invoice $invoice): string => $invoice->quote_id ? 'quote:'.$invoice->quote_id : 'invoice:'.$invoice->id)
            ->map(function (Collection $invoices): array {
                $confirmed = $invoices->where('event_status', 'confirm')->where('status', '!=', 'void');
                $first = $confirmed->first();
                $invoiced = (float) $confirmed->sum('grand_total');
                $expense = (float) $invoices->sum('expenses_sum_amount');

                return [
                    'project_code' => $first->project_code ?: '-',
                    'subject' => $first->subject ?: '-',
                    'company' => $first->billToIdentity()['company_name'] ?? '-',
                    'invoice_numbers' => $confirmed->pluck('invoice_number')->implode('; '),
                    'invoice_count' => $confirmed->count(),
                    'invoiced' => $invoiced,
                    'received' => (float) $confirmed->sum('payments_sum_amount'),
                    'outstanding' => (float) $confirmed->sum(fn (Invoice $invoice): float => max(0, $invoice->cashPayable() - (float) $invoice->payments_sum_amount)),
                    'expense' => $expense,
                    'margin' => $invoiced - $expense,
                    'pph' => (float) $confirmed->sum('pph_amount'),
                    'net_margin' => $invoiced - $expense - (float) $confirmed->sum('pph_amount'),
                ];
            })->values();
    }

    /**
     * Per-invoice performance.
     *
     * Invoice cohort is based on issued_at.
     * Cash in/out "in period" uses transaction dates.
     */
    private function buildInvoicePerformance(array $filters)
    {
        $query = $this->invoiceReportQuery($filters)
            ->with([
                'person.organization',
                'quote.person.organization',
                'user',
                'quote.user',
                'activePayments',
                'expenses',
                'items',
            ]);

        return $query
            ->latest('issued_at')
            ->get()
            ->map(function (Invoice $invoice) use ($filters) {
                $invoiceValue = (float) $invoice->grand_total;
                $identity = $invoice->billToIdentity();
                $hasRecipientSnapshot = filled($invoice->bill_to_person_name) || filled($invoice->bill_to_company_name);
                $customer = $invoice->person_id
                    ? $invoice->person
                    : (! $hasRecipientSnapshot ? $invoice->quote?->person : null);
                $customerPhone = collect($customer?->contact_numbers ?? [])
                    ->map(fn ($number) => trim((string) (is_array($number) ? ($number['value'] ?? '') : $number)))
                    ->filter(fn (string $number): bool => $number !== '')
                    ->unique()
                    ->implode('; ');

                $productNames = $invoice
                    ->items
                    ->pluck('name')
                    ->map(
                        fn ($name) => trim(
                            (string) $name
                        )
                    )
                    ->filter()
                    ->unique()
                    ->values();

                $paidToDate = (float) $invoice
                    ->activePayments
                    ->sum('amount');

                $projectExpense = (float) $invoice
                    ->expenses
                    ->sum('amount');

                $receivedInPeriod = (float) $invoice
                    ->activePayments
                    ->filter(function (Payment $payment) use ($filters) {
                        return $this->dateIsInPeriod(
                            $payment->paid_at,
                            $filters
                        );
                    })
                    ->sum('amount');

                $expenseInPeriod = (float) $invoice
                    ->expenses
                    ->filter(function (Expense $expense) use ($filters) {
                        return $this->dateIsInPeriod(
                            $expense->expense_date,
                            $filters
                        );
                    })
                    ->sum('amount');

                $outstanding = $invoice->event_status === 'confirm'
                    ? max(
                        0,
                        $invoice->cashPayable() - $paidToDate
                    )
                    : 0.0;

                $estimatedProfit = $invoice->event_status === 'confirm'
                    ? $invoiceValue - $projectExpense
                    : 0.0;

                return [
                    'id' => $invoice->id,
                    'project_code' => $invoice->project_code ?? '-',
                    'business_unit' => $invoice->business_unit,
                    'business_unit_label' => BusinessUnit::label(
                        $invoice->business_unit
                    ),
                    'invoice_number' => $invoice->invoice_number,
                    'billing_type' => $invoice->billing_type ?: 'full_payment',
                    'customer' => $invoice->person?->name ?? '-',
                    'recipient_name' => $identity['person_name'],
                    'company' => $identity['company_name'] ?? '-',
                    'customer_phone' => $customerPhone,
                    'subject' => $invoice->subject ?? '-',
                    'products' => $productNames->implode('; '),
                    'product_list' => $productNames->all(),
                    'issued_at' => $invoice->issued_at,
                    'event_date' => $invoice->event_date ?? $invoice->quote?->event_date,
                    'sales' => $invoice->user?->name ?? $invoice->quote?->user?->name ?? '',
                    'event_status' => $invoice->event_status ?? 'confirm',
                    'status' => $invoice->status ?? 'unpaid',

                    /*
                     * Project / invoice metrics.
                     */
                    'invoice_value' => $invoiceValue,
                    'pph_amount' => (float) $invoice->pph_amount,
                    'net_payable' => $invoice->cashPayable(),
                    'net_margin' => $invoice->event_status === 'confirm' ? $estimatedProfit - (float) $invoice->pph_amount : 0.0,
                    'paid_to_date' => $paidToDate,
                    'outstanding' => $outstanding,
                    'project_expense' => $projectExpense,
                    'estimated_profit' => $estimatedProfit,

                    /*
                     * Period cash-flow metrics.
                     */
                    'received_in_period' => $receivedInPeriod,
                    'expense_in_period' => $expenseInPeriod,
                    'cash_surplus_in_period' => $receivedInPeriod - $expenseInPeriod,
                ];
            });
    }

    /**
     * Payment-status counts for invoice cohort.
     */
    private function buildInvoiceStats(array $filters): array
    {
        $query = $this->invoiceReportQuery($filters);

        return [
            'total' => (clone $query)->count(),

            'paid' => (clone $query)
                ->where('status', 'paid')
                ->count(),

            'partial' => (clone $query)
                ->where('status', 'partial')
                ->count(),

            'unpaid' => (clone $query)
                ->where('status', 'unpaid')
                ->count(),
        ];
    }

    /**
     * Actual expense transactions in selected period, grouped by category.
     */
    private function buildExpenseByCategory(array $filters)
    {
        $categoryLabels = app(ExpenseCategoryRepository::class)->labels();
        $query = Expense::query()
            ->selectRaw(
                'category, SUM(amount) as total'
            )
            ->whereNotNull('expense_date');

        $this->applyTransactionPeriod(
            $query,
            'expense_date',
            $filters
        );

        $this->applyTransactionInvoiceDimensions(
            $query,
            $filters
        );

        return $query
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) use ($categoryLabels) {
                return [
                    'category' => ExpenseCategoryRepository::label($item->category, $categoryLabels),
                    'total' => (float) $item->total,
                ];
            });
    }

    /**
     * January - December analytics.
     *
     * Only built when user selected Month = All.
     */
    private function buildMonthlyPerformance(array $filters)
    {
        /*
        |--------------------------------------------------------------------------
        | CONFIRMED REVENUE BY INVOICE MONTH
        |--------------------------------------------------------------------------
        */

        $revenueQuery = Invoice::query()->notVoided()
            ->selectRaw(
                'MONTH(issued_at) as month, SUM(grand_total) as total'
            )
            ->whereNotNull('issued_at')
            ->whereYear('issued_at', $filters['year'])
            ->where('event_status', 'confirm');

        if ($filters['business_unit']) {
            $revenueQuery->where(
                'business_unit',
                $filters['business_unit']
            );
        }

        $this->applyInvoiceProductDimension(
            $revenueQuery,
            $filters
        );

        if (
            $filters['event_status']
            && $filters['event_status'] !== 'confirm'
        ) {
            $monthlyRevenue = collect();
        } else {
            $monthlyRevenue = $revenueQuery
                ->groupByRaw('MONTH(issued_at)')
                ->pluck('total', 'month');
        }

        /*
        |--------------------------------------------------------------------------
        | CASH RECEIVED BY PAYMENT MONTH
        |--------------------------------------------------------------------------
        */

        $receivedQuery = Payment::query()->notVoided()
            ->selectRaw(
                'MONTH(paid_at) as month, SUM(amount) as total'
            )
            ->whereNotNull('paid_at')
            ->whereYear('paid_at', $filters['year']);

        $this->applyTransactionInvoiceDimensions(
            $receivedQuery,
            $filters
        );

        $monthlyReceived = $receivedQuery
            ->groupByRaw('MONTH(paid_at)')
            ->pluck('total', 'month');

        /*
        |--------------------------------------------------------------------------
        | CASH EXPENSE BY EXPENSE MONTH
        |--------------------------------------------------------------------------
        */

        $expenseQuery = Expense::query()
            ->selectRaw(
                'MONTH(expense_date) as month, SUM(amount) as total'
            )
            ->whereNotNull('expense_date')
            ->whereYear('expense_date', $filters['year']);

        $this->applyTransactionInvoiceDimensions(
            $expenseQuery,
            $filters
        );

        $monthlyExpense = $expenseQuery
            ->groupByRaw('MONTH(expense_date)')
            ->pluck('total', 'month');

        return collect(range(1, 12))
            ->map(function ($month) use (
                $filters,
                $monthlyRevenue,
                $monthlyReceived,
                $monthlyExpense
            ) {
                $received = (float) $monthlyReceived
                    ->get($month, 0);

                $expense = (float) $monthlyExpense
                    ->get($month, 0);

                return [
                    'month_number' => $month,

                    'month' => Carbon::createFromDate(
                        $filters['year'],
                        $month,
                        1
                    )->format('M'),

                    'month_full' => Carbon::createFromDate(
                        $filters['year'],
                        $month,
                        1
                    )->format('F'),

                    'revenue' => (float) $monthlyRevenue
                        ->get($month, 0),

                    'received' => $received,

                    'expense' => $expense,

                    'cash_surplus' => $received - $expense,
                ];
            });
    }

    /**
     * Apply Year / Month to an invoice issued_at query.
     */
    private function applyInvoicePeriod(
        Builder $query,
        array $filters
    ): void {
        $query->whereYear(
            'issued_at',
            $filters['year']
        );

        if ($filters['month']) {
            $query->whereMonth(
                'issued_at',
                $filters['month']
            );
        }
    }

    /**
     * Apply Business Unit + Event Status directly to Invoice query.
     */
    private function applyInvoiceDimensions(
        Builder $query,
        array $filters
    ): void {
        if ($filters['business_unit']) {
            $query->where(
                'business_unit',
                $filters['business_unit']
            );
        }

        if ($filters['event_status']) {
            $query->where(
                'event_status',
                $filters['event_status']
            );
        }

        $this->applyInvoiceProductDimension(
            $query,
            $filters
        );
    }

    /**
     * Apply Year / Month to Payment or Expense query.
     */
    private function applyTransactionPeriod(
        Builder $query,
        string $column,
        array $filters
    ): void {
        $query->whereYear(
            $column,
            $filters['year']
        );

        if ($filters['month']) {
            $query->whereMonth(
                $column,
                $filters['month']
            );
        }
    }

    /**
     * Filter Payment / Expense through its related Invoice.
     */
    private function applyTransactionInvoiceDimensions(
        Builder $query,
        array $filters
    ): void {
        $query->whereHas(
            'invoice',
            function (Builder $invoiceQuery) use ($filters) {
                $invoiceQuery->whereNotNull('issued_at');

                if ($filters['business_unit']) {
                    $invoiceQuery->where(
                        'business_unit',
                        $filters['business_unit']
                    );
                }

                if ($filters['event_status']) {
                    $invoiceQuery->where(
                        'event_status',
                        $filters['event_status']
                    );
                }

                $this->applyInvoiceProductDimension(
                    $invoiceQuery,
                    $filters
                );
            }
        );
    }

    /**
     * Check if an already-loaded Payment/Expense date belongs
     * to the selected period.
     */
    private function dateIsInPeriod(
        mixed $date,
        array $filters
    ): bool {
        if (! $date) {
            return false;
        }

        $date = $date instanceof Carbon
            ? $date
            : Carbon::parse($date);

        if ((int) $date->year !== $filters['year']) {
            return false;
        }

        if (
            $filters['month']
            && (int) $date->month !== $filters['month']
        ) {
            return false;
        }

        return true;
    }

    /**
     * Available Year dropdown values.
     */
    private function availableYears()
    {
        $invoiceYears = Invoice::query()->notVoided()
            ->whereNotNull('issued_at')
            ->selectRaw('YEAR(issued_at) as year')
            ->distinct()
            ->pluck('year');

        $paymentYears = Payment::query()->notVoided()
            ->whereNotNull('paid_at')
            ->selectRaw('YEAR(paid_at) as year')
            ->distinct()
            ->pluck('year');

        $expenseYears = Expense::query()
            ->whereNotNull('expense_date')
            ->selectRaw('YEAR(expense_date) as year')
            ->distinct()
            ->pluck('year');

        return $invoiceYears
            ->merge($paymentYears)
            ->merge($expenseYears)
            ->push(now()->year)
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sortDesc()
            ->values();
    }

    private function buildTopProductExportFileName(array $filters): string
    {
        return str_replace(
            'financial-report-',
            'top-products-',
            $this->buildExportFileName($filters)
        );
    }

    private function buildExportFileName(array $filters): string
    {
        $parts = [
            'financial-report',
            (string) $filters['year'],
        ];

        if ($filters['month']) {
            $parts[] = str_pad(
                (string) $filters['month'],
                2,
                '0',
                STR_PAD_LEFT
            );
        } else {
            $parts[] = 'all-months';
        }

        $parts[] = $filters['business_unit']
            ?: 'all-business-units';

        if ($filters['event_status']) {
            $parts[] = $filters['event_status'];
        }

        if ($filters['product']) {
            $productSlug = strtolower(
                preg_replace(
                    '/[^A-Za-z0-9]+/',
                    '-',
                    $filters['product']
                )
            );

            $productSlug = trim(
                (string) $productSlug,
                '-'
            );

            if ($productSlug !== '') {
                $parts[] = $productSlug;
            }
        }

        return implode('-', $parts).'.csv';
    }

    /**
     * Product dimension.
     *
     * "Product" deliberately means the invoice-item snapshot name.
     * It is safer for historical reporting than joining the current
     * Product master, whose name may change after the invoice was issued.
     */
    private function applyInvoiceProductDimension(
        Builder $query,
        array $filters
    ): void {
        if (! $filters['product']) {
            return;
        }

        $query->whereHas(
            'items',
            function (Builder $itemQuery) use ($filters) {
                $itemQuery->where(
                    'name',
                    $filters['product']
                );
            }
        );
    }

    /**
     * Product dropdown values from historical invoice line snapshots.
     */
    private function availableProducts()
    {
        return DB::table('invoice_items')
            ->whereNotNull('name')
            ->where('name', '<>', '')
            ->select('name')
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->map(
                fn ($name) => trim(
                    (string) $name
                )
            )
            ->filter()
            ->unique()
            ->values();
    }

    private function normalizeProduct(
        mixed $product
    ): ?string {
        $product = trim(
            (string) ($product ?? '')
        );

        return $product !== ''
            ? $product
            : null;
    }

    private function normalizeBusinessUnit(
        ?string $businessUnit
    ): ?string {
        return in_array(
            $businessUnit,
            BusinessUnit::values(),
            true
        )
            ? $businessUnit
            : null;
    }

    private function normalizeEventStatus(
        ?string $eventStatus
    ): ?string {
        return in_array(
            $eventStatus,
            [
                'prospect',
                'confirm',
                'cancel',
            ],
            true
        )
            ? $eventStatus
            : null;
    }

    private function normalizeMonth(
        mixed $month
    ): ?int {
        if (
            $month === null
            || $month === ''
            || $month === 'all'
        ) {
            return null;
        }

        $month = (int) $month;

        return $month >= 1 && $month <= 12
            ? $month
            : null;
    }

    private function normalizeYear(
        mixed $year
    ): int {
        $year = (int) (
            $year
            ?: now()->year
        );

        if (
            $year < 2000
            || $year > 2100
        ) {
            return now()->year;
        }

        return $year;
    }
}
