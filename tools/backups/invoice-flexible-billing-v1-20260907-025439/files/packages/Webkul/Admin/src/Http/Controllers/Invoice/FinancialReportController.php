<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Core\Support\BusinessUnit;
use Webkul\Invoice\Models\Expense;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\Payment;

class FinancialReportController extends Controller
{
    /**
     * Financial Report page.
     *
     * Filter semantics:
     *
     * - Revenue:
     *   Confirmed invoices whose issued_at is inside the selected period.
     *
     * - Payment Received:
     *   Actual payment transactions whose paid_at is inside the selected period.
     *
     * - Expense:
     *   Actual expense transactions whose expense_date is inside the selected period.
     *
     * - Outstanding:
     *   Current outstanding of confirmed invoices issued inside the selected period.
     *
     * - Estimated Profit:
     *   Confirm + Prospect invoice value issued inside the selected period,
     *   minus ALL project expenses linked to those invoices.
     *
     * - Cash Surplus:
     *   Payment Received in period - Expense in period.
     */
    public function index(Request $request): View
    {
        $filters = $this->normalizeFilters($request);

        $financialSummary = $this->buildFinancialSummary($filters);

        $invoicePerformance = $this->buildInvoicePerformance($filters);

        $invoiceStats = $this->buildInvoiceStats($filters);

        $expenseByCategory = $this->buildExpenseByCategory($filters);

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
            ]
        );
    }

    /**
     * Export exactly what the current filters are showing.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->normalizeFilters($request);

        $financialSummary = $this->buildFinancialSummary($filters);

        $invoicePerformance = $this->buildInvoicePerformance($filters);

        $fileName = $this->buildExportFileName($filters);

        return response()->streamDownload(
            function () use (
                $filters,
                $financialSummary,
                $invoicePerformance
            ) {
                $handle = fopen('php://output', 'w');

                if ($handle === false) {
                    return;
                }

                /*
                 * UTF-8 BOM for Excel.
                 */
                fwrite($handle, "\xEF\xBB\xBF");

                $writeRow = static function ($stream, array $row): void {
                    fputcsv(
                        $stream,
                        $row,
                        ';',
                        '"',
                        ''
                    );
                };

                /*
                |--------------------------------------------------------------------------
                | REPORT META
                |--------------------------------------------------------------------------
                */

                $writeRow($handle, [
                    'FINANCIAL REPORT',
                ]);

                $writeRow($handle, [
                    'Year',
                    $filters['year'],
                ]);

                $writeRow($handle, [
                    'Month',
                    $filters['month']
                        ? Carbon::createFromDate(
                            $filters['year'],
                            $filters['month'],
                            1
                        )->format('F')
                        : 'ALL MONTHS',
                ]);

                $writeRow($handle, [
                    'Business Unit',
                    $filters['business_unit']
                        ? BusinessUnit::label($filters['business_unit'])
                        : 'ALL BUSINESS UNITS',
                ]);

                $writeRow($handle, [
                    'Event Status',
                    $filters['event_status']
                        ? strtoupper($filters['event_status'])
                        : 'ALL EVENT STATUSES',
                ]);

                $writeRow($handle, [
                    'Product',
                    $filters['product']
                        ?: 'ALL PRODUCTS',
                ]);

                $writeRow($handle, []);

                /*
                |--------------------------------------------------------------------------
                | SUMMARY
                |--------------------------------------------------------------------------
                |
                | Labels are intentionally explicit to avoid mixing:
                | project/invoice performance vs period cash-flow.
                |
                */

                $writeRow($handle, [
                    'SUMMARY',
                ]);

                $writeRow($handle, [
                    'Metric',
                    'Amount',
                ]);

                $writeRow($handle, [
                    'Revenue - Confirmed Invoice Value in Period',
                    $financialSummary['revenue'],
                ]);

                $writeRow($handle, [
                    'Payment Received - Cash In During Period',
                    $financialSummary['received'],
                ]);

                $writeRow($handle, [
                    'Outstanding - Current Balance of Confirmed Invoice Cohort',
                    $financialSummary['outstanding'],
                ]);

                $writeRow($handle, [
                    'Expense - Cash Out During Period',
                    $financialSummary['expense'],
                ]);

                $writeRow($handle, [
                    'Estimated Project Profit - Invoice Cohort minus All Project Expenses',
                    $financialSummary['estimated_profit'],
                ]);

                $writeRow($handle, [
                    'Cash Surplus - Cash In minus Cash Out During Period',
                    $financialSummary['cash_surplus'],
                ]);

                $writeRow($handle, []);
                $writeRow($handle, []);

                /*
                |--------------------------------------------------------------------------
                | INVOICE DETAIL
                |--------------------------------------------------------------------------
                */

                $writeRow($handle, [
                    'Project Code',
                    'Business Unit',
                    'Invoice',
                    'Invoice Date',
                    'Customer',
                    'Project / Subject',
                    'Product',
                    'Event Status',
                    'Payment Status',
                    'Invoice Value',
                    'Paid To Date',
                    'Payment Received In Period',
                    'Outstanding Current',
                    'Project Expense Total',
                    'Expense In Period',
                    'Estimated Project Profit',
                    'Cash Surplus In Period',
                ]);

                foreach ($invoicePerformance as $invoice) {
                    $writeRow($handle, [
                        $invoice['project_code'],
                        $invoice['business_unit_label'],
                        $invoice['invoice_number'],
                        $invoice['issued_at']
                            ?->format('Y-m-d'),
                        $invoice['customer'],
                        $invoice['subject'],
                        $invoice['products'],
                        strtoupper($invoice['event_status']),
                        strtoupper($invoice['status']),
                        $invoice['invoice_value'],
                        $invoice['paid_to_date'],
                        $invoice['received_in_period'],
                        $invoice['outstanding'],
                        $invoice['project_expense'],
                        $invoice['expense_in_period'],
                        $invoice['estimated_profit'],
                        $invoice['cash_surplus_in_period'],
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
     * Summary values shown in top cards.
     */
    private function buildFinancialSummary(array $filters): array
    {
        /*
        |--------------------------------------------------------------------------
        | REVENUE
        |--------------------------------------------------------------------------
        |
        | Confirm only.
        | Period = invoice issued_at.
        |
        */

        $revenueQuery = Invoice::query()
            ->whereNotNull('issued_at')
            ->where('event_status', 'confirm');

        $this->applyInvoicePeriod(
            $revenueQuery,
            $filters
        );

        $this->applyInvoiceDimensions(
            $revenueQuery,
            $filters
        );

        /*
         * When user explicitly filters Prospect/Cancel,
         * confirmed revenue should correctly become zero.
         */
        if (
            $filters['event_status']
            && $filters['event_status'] !== 'confirm'
        ) {
            $revenue = 0.0;
        } else {
            $revenue = (float) $revenueQuery
                ->sum('grand_total');
        }

        /*
        |--------------------------------------------------------------------------
        | PAYMENT RECEIVED
        |--------------------------------------------------------------------------
        |
        | Actual cash-in.
        | Period = payments.paid_at.
        |
        */

        $paymentQuery = Payment::query()
            ->whereNotNull('paid_at');

        $this->applyTransactionPeriod(
            $paymentQuery,
            'paid_at',
            $filters
        );

        $this->applyTransactionInvoiceDimensions(
            $paymentQuery,
            $filters
        );

        $received = (float) $paymentQuery
            ->sum('amount');

        /*
        |--------------------------------------------------------------------------
        | EXPENSE
        |--------------------------------------------------------------------------
        |
        | Actual cash-out.
        | Period = expenses.expense_date.
        |
        */

        $expenseQuery = Expense::query()
            ->whereNotNull('expense_date');

        $this->applyTransactionPeriod(
            $expenseQuery,
            'expense_date',
            $filters
        );

        $this->applyTransactionInvoiceDimensions(
            $expenseQuery,
            $filters
        );

        $expense = (float) $expenseQuery
            ->sum('amount');

        /*
        |--------------------------------------------------------------------------
        | OUTSTANDING
        |--------------------------------------------------------------------------
        |
        | Current outstanding for CONFIRMED invoices
        | issued in selected period.
        |
        | Derived from actual payment rows instead of trusting
        | invoice.balance_due blindly, so calculation cannot drift
        | if a cached aggregate column becomes stale.
        |
        */

        $outstandingQuery = Invoice::query()
            ->withSum('payments', 'amount')
            ->whereNotNull('issued_at')
            ->where('event_status', 'confirm');

        $this->applyInvoicePeriod(
            $outstandingQuery,
            $filters
        );

        $this->applyInvoiceDimensions(
            $outstandingQuery,
            $filters
        );

        if (
            $filters['event_status']
            && $filters['event_status'] !== 'confirm'
        ) {
            $outstanding = 0.0;
        } else {
            $outstanding = (float) $outstandingQuery
                ->get()
                ->sum(function (Invoice $invoice) {
                    $paid = (float) (
                        $invoice->payments_sum_amount
                        ?? 0
                    );

                    return max(
                        0,
                        (float) $invoice->grand_total
                            - $paid
                    );
                });
        }

        /*
        |--------------------------------------------------------------------------
        | ESTIMATED PROJECT PROFIT
        |--------------------------------------------------------------------------
        |
        | Project cohort = invoices issued inside selected period.
        | Confirm + Prospect are included.
        | Cancel is excluded.
        |
        | IMPORTANT:
        | Project expense here is ALL expense rows linked to each
        | selected invoice, regardless of when the expense was entered.
        | This prevents project profit from being overstated simply
        | because a project expense was posted in another month.
        |
        */

        $projectedStatuses = $this->projectedEventStatuses(
            $filters['event_status']
        );

        if (empty($projectedStatuses)) {
            $estimatedProfit = 0.0;
        } else {
            $projectQuery = Invoice::query()
                ->withSum('expenses', 'amount')
                ->whereNotNull('issued_at')
                ->whereIn(
                    'event_status',
                    $projectedStatuses
                );

            $this->applyInvoicePeriod(
                $projectQuery,
                $filters
            );

            /*
             * Event status has already been represented by
             * $projectedStatuses, so only Business Unit is applied here.
             */
            if ($filters['business_unit']) {
                $projectQuery->where(
                    'business_unit',
                    $filters['business_unit']
                );
            }

            $this->applyInvoiceProductDimension(
                $projectQuery,
                $filters
            );

            $estimatedProfit = (float) $projectQuery
                ->get()
                ->sum(function (Invoice $invoice) {
                    $projectExpense = (float) (
                        $invoice->expenses_sum_amount
                        ?? 0
                    );

                    return (float) $invoice->grand_total
                        - $projectExpense;
                });
        }

        return [
            'revenue' => $revenue,
            'received' => $received,
            'outstanding' => $outstanding,
            'expense' => $expense,
            'estimated_profit' => $estimatedProfit,
            'cash_surplus' => $received - $expense,
        ];
    }

    /**
     * Per-invoice performance.
     *
     * Invoice cohort is based on issued_at.
     * Cash in/out "in period" uses transaction dates.
     */
    private function buildInvoicePerformance(array $filters)
    {
        $query = Invoice::query()
            ->with([
                'person',
                'payments',
                'expenses',
                'items',
            ])
            ->whereNotNull('issued_at');

        $this->applyInvoicePeriod(
            $query,
            $filters
        );

        $this->applyInvoiceDimensions(
            $query,
            $filters
        );

        return $query
            ->latest('issued_at')
            ->get()
            ->map(function (Invoice $invoice) use ($filters) {
                $invoiceValue = (float) $invoice->grand_total;

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
                    ->payments
                    ->sum('amount');

                $projectExpense = (float) $invoice
                    ->expenses
                    ->sum('amount');

                $receivedInPeriod = (float) $invoice
                    ->payments
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
                        $invoiceValue - $paidToDate
                    )
                    : 0.0;

                $estimatedProfit = $invoice->event_status === 'cancel'
                    ? 0.0
                    : $invoiceValue - $projectExpense;

                return [
                    'id' => $invoice->id,
                    'project_code' => $invoice->project_code ?? '-',
                    'business_unit' => $invoice->business_unit,
                    'business_unit_label' => BusinessUnit::label(
                        $invoice->business_unit
                    ),
                    'invoice_number' => $invoice->invoice_number,
                    'customer' => $invoice->person?->name ?? '-',
                    'subject' => $invoice->subject ?? '-',
                    'products' => $productNames->implode('; '),
                    'product_list' => $productNames->all(),
                    'issued_at' => $invoice->issued_at,
                    'event_status' => $invoice->event_status ?? 'confirm',
                    'status' => $invoice->status ?? 'unpaid',

                    /*
                     * Project / invoice metrics.
                     */
                    'invoice_value' => $invoiceValue,
                    'paid_to_date' => $paidToDate,
                    'outstanding' => $outstanding,
                    'project_expense' => $projectExpense,
                    'estimated_profit' => $estimatedProfit,

                    /*
                     * Period cash-flow metrics.
                     */
                    'received_in_period' => $receivedInPeriod,
                    'expense_in_period' => $expenseInPeriod,
                    'cash_surplus_in_period' =>
                        $receivedInPeriod - $expenseInPeriod,
                ];
            });
    }

    /**
     * Payment-status counts for invoice cohort.
     */
    private function buildInvoiceStats(array $filters): array
    {
        $query = Invoice::query()
            ->whereNotNull('issued_at');

        $this->applyInvoicePeriod(
            $query,
            $filters
        );

        $this->applyInvoiceDimensions(
            $query,
            $filters
        );

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
            ->map(function ($item) {
                return [
                    'category' => ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $item->category ?: 'uncategorized'
                        )
                    ),
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

        $revenueQuery = Invoice::query()
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

        $receivedQuery = Payment::query()
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

        /*
        |--------------------------------------------------------------------------
        | PROJECT PROFIT BY INVOICE MONTH
        |--------------------------------------------------------------------------
        |
        | Use invoice cohort month + all project expenses.
        |
        */

        $projectedStatuses = $this->projectedEventStatuses(
            $filters['event_status']
        );

        if (empty($projectedStatuses)) {
            $monthlyProfit = collect();
        } else {
            $projectQuery = Invoice::query()
                ->withSum('expenses', 'amount')
                ->whereNotNull('issued_at')
                ->whereYear('issued_at', $filters['year'])
                ->whereIn(
                    'event_status',
                    $projectedStatuses
                );

            if ($filters['business_unit']) {
                $projectQuery->where(
                    'business_unit',
                    $filters['business_unit']
                );
            }

            $this->applyInvoiceProductDimension(
                $projectQuery,
                $filters
            );

            $monthlyProfit = $projectQuery
                ->get()
                ->groupBy(function (Invoice $invoice) {
                    return (int) $invoice
                        ->issued_at
                        ->format('n');
                })
                ->map(function ($invoices) {
                    return (float) $invoices
                        ->sum(function (Invoice $invoice) {
                            return (float) $invoice->grand_total
                                - (float) (
                                    $invoice->expenses_sum_amount
                                    ?? 0
                                );
                        });
                });
        }

        return collect(range(1, 12))
            ->map(function ($month) use (
                $filters,
                $monthlyRevenue,
                $monthlyReceived,
                $monthlyExpense,
                $monthlyProfit
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

                    'profit' => (float) $monthlyProfit
                        ->get($month, 0),

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
        if (
            ! $filters['business_unit']
            && ! $filters['event_status']
            && ! $filters['product']
        ) {
            return;
        }

        $query->whereHas(
            'invoice',
            function (Builder $invoiceQuery) use ($filters) {
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
        $invoiceYears = Invoice::query()
            ->whereNotNull('issued_at')
            ->selectRaw('YEAR(issued_at) as year')
            ->distinct()
            ->pluck('year');

        $paymentYears = Payment::query()
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

    private function projectedEventStatuses(
        ?string $eventStatus
    ): array {
        return match ($eventStatus) {
            'confirm' => [
                'confirm',
            ],

            'prospect' => [
                'prospect',
            ],

            'cancel' => [],

            default => [
                'confirm',
                'prospect',
            ],
        };
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
