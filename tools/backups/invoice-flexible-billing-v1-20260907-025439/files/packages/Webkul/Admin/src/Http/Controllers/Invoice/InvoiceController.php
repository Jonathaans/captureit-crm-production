<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Webkul\Contact\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Core\Traits\PDFHandler;
use Webkul\Invoice\Services\DeliveryOrderService;
use Webkul\Invoice\Models\Expense;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\Payment;
use Webkul\Invoice\Services\ExpenseService;
use Webkul\Invoice\Services\InvoiceService;
use Webkul\Invoice\Services\PaymentService;
use Webkul\Quote\Models\Quote;
use Webkul\Admin\DataGrids\Invoice\InvoiceDataGrid;


class InvoiceController extends Controller
{
    use PDFHandler;

        public function __construct(
            protected InvoiceService $invoiceService,
            protected PaymentService $paymentService,
            protected ExpenseService $expenseService,
            protected DeliveryOrderService $deliveryOrderService
        ) {
        }

    /**
     * ============================================================
     * INVOICE LIST
     * ============================================================
     *
     * Halaman invoice hanya berisi daftar invoice.
     *
     * Filter:
     * - tanggal
     * - payment status
     * - event status
     */
public function index()
{
    if (request()->ajax() || request()->expectsJson()) {
        return app(InvoiceDataGrid::class)->toJson();
    }

    return view('admin::invoices.index');
}

    /**
     * ============================================================
     * FINANCIAL REPORT
     * ============================================================
     */
    public function financialReport(Request $request): View
    {
        $year = $this->normalizeYear(
            $request->input('year')
        );

        $eventStatus = $this->normalizeEventStatus(
            $request->input('event_status')
        );

        /**
         * Financial Summary.
         */
        $financialSummary = $this->buildFinancialSummary(
            $year,
            $eventStatus
        );

        /**
         * ========================================================
         * MONTHLY CONFIRMED REVENUE
         * ========================================================
         *
         * Revenue hanya invoice CONFIRM.
         */
        $monthlyRevenue = Invoice::query()
            ->selectRaw(
                'MONTH(issued_at) as month, SUM(grand_total) as total'
            )
            ->whereNotNull('issued_at')
            ->whereYear(
                'issued_at',
                $year
            )
            ->where(
                'event_status',
                'confirm'
            )
            ->when(
                $eventStatus,
                function ($query) use ($eventStatus) {
                    $query->where(
                        'event_status',
                        $eventStatus
                    );
                }
            )
            ->groupByRaw(
                'MONTH(issued_at)'
            )
            ->pluck(
                'total',
                'month'
            );

        /**
         * ========================================================
         * MONTHLY PAYMENT RECEIVED
         * ========================================================
         *
         * Menghitung semua payment aktual.
         *
         * PARTIAL ikut dihitung.
         * PAID ikut dihitung.
         */
        $monthlyReceived = Payment::query()
            ->selectRaw(
                'MONTH(paid_at) as month, SUM(amount) as total'
            )
            ->whereNotNull('paid_at')
            ->whereYear(
                'paid_at',
                $year
            )
            ->when(
                $eventStatus,
                function ($query) use ($eventStatus) {
                    $query->whereHas(
                        'invoice',
                        function ($invoiceQuery) use ($eventStatus) {
                            $invoiceQuery->where(
                                'event_status',
                                $eventStatus
                            );
                        }
                    );
                }
            )
            ->groupByRaw(
                'MONTH(paid_at)'
            )
            ->pluck(
                'total',
                'month'
            );

        /**
         * ========================================================
         * MONTHLY ACTUAL EXPENSE
         * ========================================================
         */
        $monthlyExpense = Expense::query()
            ->selectRaw(
                'MONTH(expense_date) as month, SUM(amount) as total'
            )
            ->whereNotNull('expense_date')
            ->whereYear(
                'expense_date',
                $year
            )
            ->when(
                $eventStatus,
                function ($query) use ($eventStatus) {
                    $query->whereHas(
                        'invoice',
                        function ($invoiceQuery) use ($eventStatus) {
                            $invoiceQuery->where(
                                'event_status',
                                $eventStatus
                            );
                        }
                    );
                }
            )
            ->groupByRaw(
                'MONTH(expense_date)'
            )
            ->pluck(
                'total',
                'month'
            );

        /**
         * ========================================================
         * MONTHLY PROJECTED REVENUE
         * ========================================================
         *
         * CONFIRM + PROSPECT
         *
         * CANCEL tidak masuk Estimated Profit.
         */
        $projectedStatuses = $this
            ->projectedEventStatuses(
                $eventStatus
            );

        if (empty($projectedStatuses)) {
            $monthlyProjectedRevenue = collect();

            $monthlyProjectedExpense = collect();
        } else {
            $monthlyProjectedRevenue = Invoice::query()
                ->selectRaw(
                    'MONTH(issued_at) as month, SUM(grand_total) as total'
                )
                ->whereNotNull('issued_at')
                ->whereYear(
                    'issued_at',
                    $year
                )
                ->whereIn(
                    'event_status',
                    $projectedStatuses
                )
                ->groupByRaw(
                    'MONTH(issued_at)'
                )
                ->pluck(
                    'total',
                    'month'
                );

            $monthlyProjectedExpense = Expense::query()
                ->selectRaw(
                    'MONTH(expense_date) as month, SUM(amount) as total'
                )
                ->whereNotNull('expense_date')
                ->whereYear(
                    'expense_date',
                    $year
                )
                ->whereHas(
                    'invoice',
                    function ($invoiceQuery) use ($projectedStatuses) {
                        $invoiceQuery->whereIn(
                            'event_status',
                            $projectedStatuses
                        );
                    }
                )
                ->groupByRaw(
                    'MONTH(expense_date)'
                )
                ->pluck(
                    'total',
                    'month'
                );
        }

        /**
         * ========================================================
         * BUILD JANUARY - DECEMBER
         * ========================================================
         */
        $monthlyPerformance = collect(
            range(1, 12)
        )->map(function ($month) use (
            $year,
            $monthlyRevenue,
            $monthlyReceived,
            $monthlyExpense,
            $monthlyProjectedRevenue,
            $monthlyProjectedExpense
        ) {
            $revenue = (float) $monthlyRevenue
                ->get(
                    $month,
                    0
                );

            $received = (float) $monthlyReceived
                ->get(
                    $month,
                    0
                );

            $expense = (float) $monthlyExpense
                ->get(
                    $month,
                    0
                );

            $projectedRevenue = (float) $monthlyProjectedRevenue
                ->get(
                    $month,
                    0
                );

            $projectedExpense = (float) $monthlyProjectedExpense
                ->get(
                    $month,
                    0
                );

            return [
                'month_number' =>
                    $month,

                'month' =>
                    Carbon::createFromDate(
                        $year,
                        $month,
                        1
                    )->format('M'),

                'month_full' =>
                    Carbon::createFromDate(
                        $year,
                        $month,
                        1
                    )->format('F'),

                /**
                 * Confirm only.
                 */
                'revenue' =>
                    $revenue,

                /**
                 * Partial + Paid actual payment.
                 */
                'received' =>
                    $received,

                /**
                 * Actual expense.
                 */
                'expense' =>
                    $expense,

                /**
                 * Confirm + Prospect.
                 */
                'profit' =>
                    $projectedRevenue
                    - $projectedExpense,

                /**
                 * Uang aktual masuk - keluar.
                 */
                'cash_surplus' =>
                    $received
                    - $expense,
            ];
        });

        /**
         * ========================================================
         * EXPENSE BY CATEGORY
         * ========================================================
         */
        $expenseByCategory = Expense::query()
            ->selectRaw(
                'category, SUM(amount) as total'
            )
            ->whereNotNull(
                'expense_date'
            )
            ->whereYear(
                'expense_date',
                $year
            )
            ->when(
                $eventStatus,
                function ($query) use ($eventStatus) {
                    $query->whereHas(
                        'invoice',
                        function ($invoiceQuery) use ($eventStatus) {
                            $invoiceQuery->where(
                                'event_status',
                                $eventStatus
                            );
                        }
                    );
                }
            )
            ->groupBy(
                'category'
            )
            ->orderByDesc(
                'total'
            )
            ->get()
            ->map(function ($item) {
                return [
                    'category' =>
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                $item->category
                            )
                        ),

                    'total' =>
                        (float) $item->total,
                ];
            });

            

        /**
         * ========================================================
         * PROJECT / INVOICE PERFORMANCE
         * ========================================================
         */
        $invoicePerformance = Invoice::query()
            ->with([
                'person',
            ])
            ->withSum(
                'expenses',
                'amount'
            )
            ->whereNotNull(
                'issued_at'
            )
            ->whereYear(
                'issued_at',
                $year
            )
            ->when(
                $eventStatus,
                function ($query) use ($eventStatus) {
                    $query->where(
                        'event_status',
                        $eventStatus
                    );
                }
            )
            ->latest(
                'issued_at'
            )
            ->get()
            ->map(function ($invoice) {
                $invoiceExpense = (float) (
                    $invoice->expenses_sum_amount
                    ?? 0
                );

                $invoiceRevenue =
                    (float) $invoice->grand_total;

                $invoicePaid =
                    (float) $invoice->paid_amount;

                $invoiceOutstanding =
                    (float) $invoice->balance_due;

                /**
                 * CANCEL tidak mempunyai
                 * Estimated Profit.
                 */
                $invoiceProfit =
                    $invoice->event_status === 'cancel'
                        ? 0
                        : $invoiceRevenue
                            - $invoiceExpense;

                return [
                    'id' =>
                        $invoice->id,

                    'invoice_number' =>
                        $invoice->invoice_number,

                    'customer' =>
                        $invoice->person?->name
                        ?? '-',

                    'subject' =>
                        $invoice->subject,

                    'issued_at' =>
                        $invoice->issued_at,

                    'revenue' =>
                        $invoiceRevenue,

                    'paid' =>
                        $invoicePaid,

                    'outstanding' =>
                        $invoiceOutstanding,

                    'expense' =>
                        $invoiceExpense,

                    'profit' =>
                        $invoiceProfit,

                    'cash_surplus' =>
                        $invoicePaid
                        - $invoiceExpense,

                    'event_status' =>
                        $invoice->event_status,

                    /**
                     * Payment status.
                     */
                    'status' =>
                        $invoice->status,
                ];
            });

        /**
         * ========================================================
         * AVAILABLE YEARS
         * ========================================================
         */
        $invoiceYears = Invoice::query()
            ->whereNotNull(
                'issued_at'
            )
            ->selectRaw(
                'YEAR(issued_at) as year'
            )
            ->distinct()
            ->pluck(
                'year'
            );

        $paymentYears = Payment::query()
            ->whereNotNull(
                'paid_at'
            )
            ->selectRaw(
                'YEAR(paid_at) as year'
            )
            ->distinct()
            ->pluck(
                'year'
            );

        $expenseYears = Expense::query()
            ->whereNotNull(
                'expense_date'
            )
            ->selectRaw(
                'YEAR(expense_date) as year'
            )
            ->distinct()
            ->pluck(
                'year'
            );

        $availableYears = $invoiceYears
            ->merge(
                $paymentYears
            )
            ->merge(
                $expenseYears
            )
            ->push(
                now()->year
            )
            ->map(function ($item) {
                return (int) $item;
            })
            ->unique()
            ->sortDesc()
            ->values();

        /**
         * ========================================================
         * INVOICE PAYMENT STATISTICS
         * ========================================================
         */
        $invoiceStatsQuery = Invoice::query()
            ->whereNotNull(
                'issued_at'
            )
            ->whereYear(
                'issued_at',
                $year
            )
            ->when(
                $eventStatus,
                function ($query) use ($eventStatus) {
                    $query->where(
                        'event_status',
                        $eventStatus
                    );
                }
            );

        $invoiceStats = [
            'total' =>
                (clone $invoiceStatsQuery)
                    ->count(),

            'paid' =>
                (clone $invoiceStatsQuery)
                    ->where(
                        'status',
                        'paid'
                    )
                    ->count(),

            'partial' =>
                (clone $invoiceStatsQuery)
                    ->where(
                        'status',
                        'partial'
                    )
                    ->count(),

            'unpaid' =>
                (clone $invoiceStatsQuery)
                    ->where(
                        'status',
                        'unpaid'
                    )
                    ->count(),
        ];

        return view(
            'admin::invoices.financial-report',
            compact(
                'year',
                'eventStatus',
                'availableYears',
                'financialSummary',
                'monthlyPerformance',
                'expenseByCategory',
                'invoicePerformance',
                'invoiceStats'
            )
        );
    }

    /**
     * ============================================================
     * EXPORT FINANCIAL REPORT
     * ============================================================
     */
    public function exportFinancialReport(
        Request $request
    ): StreamedResponse {
        $year = $this->normalizeYear(
            $request->input('year')
        );

        $eventStatus = $this->normalizeEventStatus(
            $request->input('event_status')
        );

        $financialSummary = $this->buildFinancialSummary(
            $year,
            $eventStatus
        );

        $invoices = Invoice::query()
            ->with([
                'person',
            ])
            ->withSum(
                'expenses',
                'amount'
            )
            ->whereNotNull(
                'issued_at'
            )
            ->whereYear(
                'issued_at',
                $year
            )
            ->when(
                $eventStatus,
                function ($query) use ($eventStatus) {
                    $query->where(
                        'event_status',
                        $eventStatus
                    );
                }
            )
            ->orderBy(
                'issued_at'
            )
            ->get();

        $fileName =
            'financial-report-'.$year;

        if ($eventStatus) {
            $fileName .=
                '-'.$eventStatus;
        }

        $fileName .= '.csv';

        return response()->streamDownload(
            function () use (
                $year,
                $eventStatus,
                $invoices,
                $financialSummary
            ) {
                $handle = fopen(
                    'php://output',
                    'w'
                );

                if ($handle === false) {
                    return;
                }

                /**
                 * UTF-8 BOM.
                 */
                fwrite(
                    $handle,
                    "\xEF\xBB\xBF"
                );

                $writeRow = static function (
                    $stream,
                    array $row
                ): void {
                    fputcsv(
                        $stream,
                        $row,
                        ';',
                        '"',
                        ''
                    );
                };

                /**
                 * Report title.
                 */
                $writeRow(
                    $handle,
                    [
                        'FINANCIAL REPORT',
                        $year,
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'Event Status',
                        $eventStatus
                            ? strtoupper($eventStatus)
                            : 'ALL',
                    ]
                );

                $writeRow(
                    $handle,
                    []
                );

                /**
                 * Summary.
                 */
                $writeRow(
                    $handle,
                    [
                        'Metric',
                        'Amount',
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'Revenue',
                        $financialSummary['revenue'],
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'Payment Received',
                        $financialSummary['received'],
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'Outstanding',
                        $financialSummary['outstanding'],
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'Expense',
                        $financialSummary['expense'],
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'Estimated Profit',
                        $financialSummary['estimated_profit'],
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'Cash Surplus',
                        $financialSummary['cash_surplus'],
                    ]
                );

                $writeRow(
                    $handle,
                    []
                );

                $writeRow(
                    $handle,
                    []
                );

                /**
                 * Invoice header.
                 */
                $writeRow(
                    $handle,
                    [
                        'Invoice',
                        'Date',
                        'Customer',
                        'Subject',
                        'Event Status',
                        'Payment',
                        'Invoice Value',
                        'Paid',
                        'Outstanding',
                        'Expense',
                        'Estimated Profit',
                        'Cash Surplus',
                    ]
                );

                foreach ($invoices as $invoice) {
                    $invoiceRevenue =
                        (float) $invoice->grand_total;

                    $invoicePaid =
                        (float) $invoice->paid_amount;

                    $invoiceOutstanding =
                        (float) $invoice->balance_due;

                    $invoiceExpense =
                        (float) (
                            $invoice->expenses_sum_amount
                            ?? 0
                        );

                    $invoiceProfit =
                        $invoice->event_status === 'cancel'
                            ? 0
                            : $invoiceRevenue
                                - $invoiceExpense;

                    $invoiceCashSurplus =
                        $invoicePaid
                        - $invoiceExpense;

                    $writeRow(
                        $handle,
                        [
                            $invoice->invoice_number,

                            $invoice->issued_at
                                ?->format('Y-m-d'),

                            $invoice->person?->name
                                ?? '-',

                            $invoice->subject
                                ?? '-',

                            strtoupper(
                                $invoice->event_status
                                ?? 'confirm'
                            ),

                            strtoupper(
                                $invoice->status
                            ),

                            $invoiceRevenue,

                            $invoicePaid,

                            $invoiceOutstanding,

                            $invoiceExpense,

                            $invoiceProfit,

                            $invoiceCashSurplus,
                        ]
                    );
                }

                fclose(
                    $handle
                );
            },
            $fileName,
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
            ]
        );
    }

    /**
 * ============================================================
 * EDIT INVOICE
 * ============================================================
 */
public function edit(int $id): View
{
    $invoice = Invoice::with([
        'items',
        'payments.creator',
        'expenses.creator',
        'quote',
        'person',
        'user',
    ])->findOrFail($id);

    return view(
        'admin::invoices.edit',
        compact('invoice')
    );
}

/**
 * ============================================================
 * UPDATE INVOICE
 * ============================================================
 */
public function update(
    Request $request,
    int $id
): RedirectResponse {
    $invoice = Invoice::with('items')
        ->findOrFail($id);
    /*
|--------------------------------------------------------------------------
| PATCH: CREATE NEW PERSON FROM EDIT INVOICE
|--------------------------------------------------------------------------
|
| Put this block INSIDE InvoiceController::update(),
| BEFORE:
|
|     $validated = $request->validate([
|
| This bypasses the broken lookup "Add as New".
|
*/

/*
|--------------------------------------------------------------------------
| REPLACE BLOCK NEW PERSON DI InvoiceController::update()
|--------------------------------------------------------------------------
|
| Ganti block lama:
|
| if ($request->input('person_id') === '__new__') {
|     ...
| }
|
| dengan block ini.
|
*/

if ($request->input('person_id') === '__new__') {
    $newPersonData = $request->validate([
        'new_person_name' => [
            'required',
            'string',
            'min:2',
            'max:255',
        ],

        'new_person_email' => [
            'nullable',
            'email',
            'max:255',
        ],

        'new_person_phone' => [
            'nullable',
            'string',
            'max:50',
        ],

        'new_person_organization_id' => [
            'nullable',
        ],

        'new_person_organization_name' => [
            'nullable',
            'string',
            'max:255',
        ],
    ]);

    $name = trim(
        $newPersonData['new_person_name']
    );

    $email = strtolower(
        trim(
            $newPersonData['new_person_email']
            ?? ''
        )
    );

    $phone = trim(
        $newPersonData['new_person_phone']
        ?? ''
    );

    $normalizedPhone = preg_replace(
        '/\D+/',
        '',
        $phone
    );

    /*
    |--------------------------------------------------------------------------
    | ORGANIZATION
    |--------------------------------------------------------------------------
    |
    | Existing:
    | new_person_organization_id = numeric ID
    |
    | Quick Add:
    | new_person_organization_id = "__new__"
    | new_person_organization_name = nama organization
    |
    */

    $organizationChoice = (string) (
        $newPersonData['new_person_organization_id']
        ?? ''
    );

    $organizationId = null;
    $organizationName = null;

    if (
        $organizationChoice !== ''
        && $organizationChoice !== '__new__'
    ) {
        if (! ctype_digit($organizationChoice)) {
            return back()
                ->withInput()
                ->withErrors([
                    'new_person_organization_id' =>
                        'Organization tidak valid.',
                ]);
        }

        $organization = \Webkul\Contact\Models\Organization::find(
            (int) $organizationChoice
        );

        if (! $organization) {
            return back()
                ->withInput()
                ->withErrors([
                    'new_person_organization_id' =>
                        'Organization tidak ditemukan.',
                ]);
        }

        $organizationId = $organization->id;
    }

    if ($organizationChoice === '__new__') {
        $requestedOrganizationName = trim(
            $newPersonData['new_person_organization_name']
            ?? ''
        );

        if ($requestedOrganizationName === '') {
            return back()
                ->withInput()
                ->withErrors([
                    'new_person_organization_name' =>
                        'Nama organization wajib diisi jika memilih Add New Organization.',
                ]);
        }

        /*
         * Hindari duplicate organization karena beda kapitalisasi.
         * Contoh:
         * "BCA" dan "bca" akan dianggap organization yang sama.
         */
        $existingOrganization = \Webkul\Contact\Models\Organization::query()
            ->get([
                'id',
                'name',
            ])
            ->first(
                fn ($organization) =>
                    mb_strtolower(
                        trim($organization->name)
                    )
                    === mb_strtolower(
                        $requestedOrganizationName
                    )
            );

        if ($existingOrganization) {
            $organizationId =
                $existingOrganization->id;
        } else {
            $organizationName =
                $requestedOrganizationName;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DUPLICATE PERSON CHECK
    |--------------------------------------------------------------------------
    |
    | Strong duplicate:
    | - Email sama, ATAU
    | - Phone sama.
    |
    | Jika Email dan Phone kosong:
    | - Name + Organization yang sama dianggap duplicate.
    |
    */

    $existingPersons = \Webkul\Contact\Models\Person::query()
        ->with('organization')
        ->get([
            'id',
            'name',
            'emails',
            'contact_numbers',
            'organization_id',
        ]);

    $duplicatePerson = $existingPersons->first(
        function ($person) use (
            $name,
            $email,
            $normalizedPhone,
            $organizationId,
            $organizationName
        ) {
            $emailMatch = false;

            if ($email !== '') {
                $emailMatch = collect(
                    $person->emails ?? []
                )->contains(
                    fn ($item) =>
                        strtolower(
                            trim(
                                $item['value']
                                ?? ''
                            )
                        ) === $email
                );
            }

            if ($emailMatch) {
                return true;
            }

            $phoneMatch = false;

            if ($normalizedPhone !== '') {
                $phoneMatch = collect(
                    $person->contact_numbers ?? []
                )->contains(
                    fn ($item) =>
                        preg_replace(
                            '/\D+/',
                            '',
                            (string) (
                                $item['value']
                                ?? ''
                            )
                        ) === $normalizedPhone
                );
            }

            if ($phoneMatch) {
                return true;
            }

            /*
             * Kalau tidak ada Email/Phone,
             * gunakan Name + Organization untuk mencegah duplicate.
             */
            if (
                $email === ''
                && $normalizedPhone === ''
            ) {
                $sameName =
                    mb_strtolower(
                        trim($person->name)
                    )
                    === mb_strtolower($name);

                if (! $sameName) {
                    return false;
                }

                if ($organizationId) {
                    return
                        (int) $person->organization_id
                        === (int) $organizationId;
                }

                if ($organizationName) {
                    return mb_strtolower(
                        trim(
                            $person->organization?->name
                            ?? ''
                        )
                    ) === mb_strtolower(
                        trim($organizationName)
                    );
                }

                return empty(
                    $person->organization_id
                );
            }

            return false;
        }
    );

    if ($duplicatePerson) {
        return back()
            ->withInput()
            ->withErrors([
                'person_id' =>
                    'Data client sudah ada: '
                    .$duplicatePerson->name
                    .'. Silakan pilih client tersebut pada Bill To, jangan membuat data baru.',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE PERSON
    |--------------------------------------------------------------------------
    */

    $personPayload = [
        'name' => $name,

        'emails' => $email !== ''
            ? [
                [
                    'value' => $email,
                    'label' => 'work',
                ],
            ]
            : [],

        'user_id' => auth()
            ->guard('user')
            ->id(),

        'organization_id' =>
            $organizationId,

        'quick_add' => 1,

        'entity_type' =>
            'persons',
    ];

    /*
     * Jangan kirim contact_numbers kosong karena
     * PersonRepository melakukan akses ke index pertama.
     */
    if ($phone !== '') {
        $personPayload['contact_numbers'] = [
            [
                'value' => $phone,
                'label' => 'work',
            ],
        ];
    }

    /*
     * PersonRepository akan otomatis fetch/create
     * organization dari organization_name.
     */
    if ($organizationName) {
        $personPayload['organization_name'] =
            $organizationName;
    }

    $person = app(
        \Webkul\Contact\Repositories\PersonRepository::class
    )->create(
        $personPayload
    );

    /*
     * Lanjut ke validasi/update Invoice normal
     * menggunakan Person ID yang baru.
     */
    $request->merge([
        'person_id' => $person->id,
    ]);
}



/*
|--------------------------------------------------------------------------
| KEEP YOUR EXISTING MAIN VALIDATION BELOW
|--------------------------------------------------------------------------
|
| person_id should remain:
|
| 'person_id' => [
|     'required',
|     'integer',
|     'exists:persons,id',
| ],
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| KEEP THIS IN $invoice->update([...])
|--------------------------------------------------------------------------
|
| 'person_id' => $validated['person_id'],
|
|--------------------------------------------------------------------------
*/
    $validated = $request->validate([

        'person_id' => [
        'required',
        'integer',
        'exists:persons,id',
        ],
        'subject' => [
            'required',
            'string',
            'max:255',
        ],

        'description' => [
            'nullable',
            'string',
        ],

        'event_date' => [
            'nullable',
            'date',
        ],

        'location' => [
            'nullable',
            'string',
        ],

        'payment_term' => [
            'nullable',
            'string',
        ],

        'due_at' => [
            'nullable',
            'date',
        ],

        'billing_address' => [
            'nullable',
        ],

        'shipping_address' => [
            'nullable',
        ],

        'status' => [
            'required',
            'in:unpaid,partial,paid',
        ],

        'items' => [
            'required',
            'array',
            'min:1',
        ],

        'items.*.name' => [
            'required',
            'string',
            'max:255',
        ],

        'items.*.description' => [
            'nullable',
            'string',
        ],

        'items.*.day' => [
            'required',
            'integer',
            'min:1',
        ],

        'items.*.quantity' => [
            'required',
            'numeric',
            'min:1',
        ],

        'items.*.price' => [
            'required',
            'numeric',
            'min:0',
        ],

        'items.*.discount_percent' => [
            'nullable',
            'numeric',
            'min:0',
            'max:100',
        ],

        'items.*.tax_percent' => [
            'nullable',
            'numeric',
            'min:0',
            'max:100',
        ],
    ]);

    DB::transaction(function () use (
        $invoice,
        $validated
    ) {
        /*
        |--------------------------------------------------------------------------
        | UPDATE INVOICE HEADER
        |--------------------------------------------------------------------------
        */

        $invoice->update([
            'subject' => $validated['subject'],

            'person_id' => $validated['person_id'],

            'description' =>
                $validated['description'] ?? null,

            'event_date' =>
                $validated['event_date'] ?? null,

            'location' =>
                $validated['location'] ?? null,

            'payment_term' =>
                $validated['payment_term'] ?? null,

            'billing_address' =>
                $validated['billing_address'] ?? null,

            'shipping_address' =>
                $validated['shipping_address'] ?? null,

            'due_at' =>
                $validated['due_at'] ?? null,

            'status' =>
                $validated['status'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | DELETE OLD INVOICE ITEMS
        |--------------------------------------------------------------------------
        |
        | Payment dan Expense TIDAK disentuh.
        |
        */

        $invoice->items()->delete();

        /*
        |--------------------------------------------------------------------------
        | CREATE UPDATED ITEMS
        |--------------------------------------------------------------------------
        */

        $subTotal = 0;
        $discount = 0;
        $tax = 0;

        foreach ($validated['items'] as $item) {

            $day = (float) $item['day'];
            $quantity = (float) $item['quantity'];
            $price = (float) $item['price'];

            $discountPercent =
                (float) ($item['discount_percent'] ?? 0);

            $taxPercent =
                (float) ($item['tax_percent'] ?? 0);

            /*
            |--------------------------------------------------------------------------
            | BASE AMOUNT
            |--------------------------------------------------------------------------
            */

            $amount =
                $day
                * $quantity
                * $price;

            /*
            |--------------------------------------------------------------------------
            | DISCOUNT
            |--------------------------------------------------------------------------
            */

            $discountAmount =
                $amount
                * ($discountPercent / 100);

            /*
            |--------------------------------------------------------------------------
            | TAX
            |--------------------------------------------------------------------------
            */

            $taxable =
                max(
                    $amount - $discountAmount,
                    0
                );

            $taxAmount =
                $taxable
                * ($taxPercent / 100);

            /*
            |--------------------------------------------------------------------------
            | ITEM TOTAL
            |--------------------------------------------------------------------------
            */

            $total =
                $taxable
                + $taxAmount;

            /*
            |--------------------------------------------------------------------------
            | CREATE ITEM
            |--------------------------------------------------------------------------
            */

            $invoice->items()->create([
                'name' =>
                    $item['name'],

                'description' =>
                    $item['description'] ?? null,

                'day' =>
                    $day,

                'quantity' =>
                    $quantity,

                'price' =>
                    $price,

                'discount_percent' =>
                    $discountPercent,

                'discount_amount' =>
                    $discountAmount,

                'tax_percent' =>
                    $taxPercent,

                'tax_amount' =>
                    $taxAmount,

                'total' =>
                    $total,
            ]);

            $subTotal += $amount;
            $discount += $discountAmount;
            $tax += $taxAmount;
        }

        /*
        |--------------------------------------------------------------------------
        | GRAND TOTAL
        |--------------------------------------------------------------------------
        */

        $adjustment =
            (float) (
                $invoice->adjustment_amount ?? 0
            );

        $grandTotal =
            $subTotal
            - $discount
            + $tax
            + $adjustment;

        $grandTotal =
            max($grandTotal, 0);

        /*
        |--------------------------------------------------------------------------
        | PAYMENT
        |--------------------------------------------------------------------------
        |
        | Jangan ubah paid_amount.
        | Payment history tetap aman.
        |
        */

        $paidAmount =
            (float) (
                $invoice->paid_amount ?? 0
            );

        $balanceDue =
            max(
                $grandTotal - $paidAmount,
                0
            );

        /*
        |--------------------------------------------------------------------------
        | UPDATE TOTALS
        |--------------------------------------------------------------------------
        */

        $invoice->update([
            'sub_total' =>
                $subTotal,

            'discount_amount' =>
                $discount,

            'tax_amount' =>
                $tax,

            'grand_total' =>
                $grandTotal,

            'paid_amount' =>
                $paidAmount,

            'balance_due' =>
                $balanceDue,
        ]);
    });

    return redirect()
        ->route(
            'admin.invoices.show',
            $invoice->id
        )
        ->with(
            'success',
            'Invoice berhasil diperbarui.'
        );
}

    /**
     * ============================================================
     * SHOW INVOICE
     * ============================================================
     */
        public function show(int $id): View
        {
            $invoice = Invoice::with([
                'items',
                'payments.creator',
                'expenses.creator',
                'quote',
                'person',
                'user',
                'deliveryOrders',
            ])->findOrFail(
                $id
            );

            return view(
                'admin::invoices.show',
                compact(
                    'invoice'
                )
            );
        }
    /**
     * ============================================================
     * UPDATE EVENT STATUS
     * ============================================================
     */
    public function updateEventStatus(
        Request $request,
        int $id
    ): RedirectResponse {
        $validated = $request->validate([
            'event_status' => [
                'required',
                'in:prospect,confirm,cancel',
            ],
        ]);

        $invoice = Invoice::findOrFail(
            $id
        );

        $invoice->update([
            'event_status' =>
                $validated['event_status'],
        ]);

        session()->flash(
            'success',
            'Event status berhasil diperbarui.'
        );

        return redirect()->route(
            'admin.invoices.show',
            $invoice->id
        );
    }

    /**
     * ============================================================
     * GENERATE INVOICE FROM QUOTE
     * ============================================================
     */
    public function generate(
        int $quoteId
    ): RedirectResponse {
        $quote = Quote::with(
            'items'
        )->findOrFail(
            $quoteId
        );

        $invoice = $this->invoiceService
            ->createFromQuote(
                $quote
            );

        session()->flash(
            'success',
            'Invoice berhasil dibuat: '
            .$invoice->invoice_number
        );

        return redirect()->route(
            'admin.invoices.show',
            $invoice->id
        );
    }
/**
 * ============================================================
 * GENERATE DELIVERY ORDER FROM INVOICE
 * ============================================================
 */
public function generateDeliveryOrder(
    int $id
): RedirectResponse {
    $invoice = Invoice::with([
        'quote',
        'person',
        'user',
    ])->findOrFail(
        $id
    );

    $deliveryOrder = $this
        ->deliveryOrderService
        ->createFromInvoice(
            $invoice,
            auth()
                ->guard('user')
                ->id()
        );

    if ($deliveryOrder->wasRecentlyCreated) {
        session()->flash(
            'success',
            'Surat Jalan berhasil dibuat: '
            .$deliveryOrder->delivery_order_number
        );
    } else {
        session()->flash(
            'success',
            'Surat Jalan untuk invoice ini sudah tersedia: '
            .$deliveryOrder->delivery_order_number
        );
    }

    return redirect()->route(
        'admin.invoices.show',
        $invoice->id
    );
}
    /**
     * ============================================================
     * ADD PAYMENT
     * ============================================================
     */
    public function addPayment(
        Request $request,
        int $id
    ): RedirectResponse {
        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'payment_method' => [
                'nullable',
                'string',
                'max:255',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'paid_date' => [
                'required',
                'date',
            ],

            'paid_time' => [
                'required',
                'date_format:H:i',
            ],
        ]);

        $invoice = Invoice::findOrFail(
            $id
        );

        $paidAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['paid_date']
            .' '
            .$validated['paid_time']
        );

        try {
            $this->paymentService->addPayment(
                $invoice,
                [
                    'amount' =>
                        $validated['amount'],

                    'payment_method' =>
                        $validated['payment_method']
                        ?? null,

                    'reference_number' =>
                        $validated['reference_number']
                        ?? null,

                    'notes' =>
                        $validated['notes']
                        ?? null,

                    'created_by' =>
                        auth()
                            ->guard('user')
                            ->id(),

                    'paid_at' =>
                        $paidAt,
                ]
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'amount' =>
                        $exception->getMessage(),
                ]);
        }

        session()->flash(
            'success',
            'Pembayaran berhasil ditambahkan.'
        );

        return redirect()->route(
            'admin.invoices.show',
            $invoice->id
        );
    }

    /**
     * ============================================================
     * ADD EXPENSE
     * ============================================================
     */
    public function addExpense(
        Request $request,
        int $id
    ): RedirectResponse {
        $invoice = Invoice::findOrFail(
            $id
        );

        /**
         * Event CANCEL tidak diperbolehkan
         * mempunyai expense baru.
         */
        if ($invoice->event_status === 'cancel') {
            return back()
                ->withErrors([
                    'expense' =>
                        'Expense tidak dapat ditambahkan pada event yang sudah cancel.',
                ]);
        }

        $validated = $request->validate([
            'category' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
                'max:255',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'expense_date' => [
                'required',
                'date',
            ],

            'vendor_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'receipt' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ]);

        $receiptPath = null;

        /**
         * Upload Bon.
         */
        if ($request->hasFile('receipt')) {
            $receiptPath = $request
                ->file('receipt')
                ->store(
                    'expense-receipts',
                    'public'
                );
        }

        unset(
            $validated['receipt']
        );

        try {
            $this->expenseService->addExpense(
                $invoice,
                [
                    ...$validated,

                    'receipt_path' =>
                        $receiptPath,

                    'created_by' =>
                        auth()
                            ->guard('user')
                            ->id(),
                ]
            );
        } catch (InvalidArgumentException $exception) {
            if ($receiptPath) {
                Storage::disk(
                    'public'
                )->delete(
                    $receiptPath
                );
            }

            return back()
                ->withInput()
                ->withErrors([
                    'amount' =>
                        $exception->getMessage(),
                ]);
        }

        session()->flash(
            'success',
            'Pengeluaran berhasil ditambahkan.'
        );

        return redirect()->route(
            'admin.invoices.show',
            $invoice->id
        );
    }

    /**
     * ============================================================
     * UPDATE EXPENSE
     * ============================================================
     */
    public function updateExpense(
        Request $request,
        int $invoiceId,
        int $expenseId
    ): RedirectResponse {

        /*
         * PO-GENERATED EXPENSE GUARD
         *
         * Expense dari PAID/legacy PO dikunci agar PO dan
         * Financial Report tidak berbeda nominal.
         */
        $linkedPurchaseOrder =
            \Webkul\Invoice\Models\PurchaseOrder::query()
                ->where('expense_id', $expenseId)
                ->whereIn(
                    'status',
                    [
                        'paid',
                        'released', // legacy rows posted by the old flow
                        'completed', // legacy rows
                    ]
                )
                ->first();

        if ($linkedPurchaseOrder) {
            session()->flash(
                'warning',
                'Expense ini berasal dari '
                    .$linkedPurchaseOrder->po_number
                    .' dan dikunci. Kelola koreksi dari Purchase Order.'
            );

            return back();
        }
        $invoice = Invoice::findOrFail(
            $invoiceId
        );

        $expense = Expense::query()
            ->where(
                'invoice_id',
                $invoice->id
            )
            ->findOrFail(
                $expenseId
            );

        $validated = $request->validate([
            'category' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
                'max:255',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'expense_date' => [
                'required',
                'date',
            ],

            'vendor_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'receipt' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ]);

        $oldReceiptPath =
            $expense->receipt_path;

        $newReceiptPath = null;

        if ($request->hasFile('receipt')) {
            $newReceiptPath = $request
                ->file('receipt')
                ->store(
                    'expense-receipts',
                    'public'
                );
        }

        unset(
            $validated['receipt']
        );

        if ($newReceiptPath) {
            $validated['receipt_path'] =
                $newReceiptPath;
        }

        try {
            $this->expenseService->updateExpense(
                $expense,
                $validated
            );
        } catch (InvalidArgumentException $exception) {
            if ($newReceiptPath) {
                Storage::disk(
                    'public'
                )->delete(
                    $newReceiptPath
                );
            }

            return back()
                ->withInput()
                ->withErrors([
                    'amount' =>
                        $exception->getMessage(),
                ]);
        }

        /**
         * Hapus bon lama kalau diganti.
         */
        if (
            $newReceiptPath
            && $oldReceiptPath
            && $oldReceiptPath !== $newReceiptPath
        ) {
            Storage::disk(
                'public'
            )->delete(
                $oldReceiptPath
            );
        }

        session()->flash(
            'success',
            'Pengeluaran berhasil diperbarui.'
        );

        return redirect()->route(
            'admin.invoices.show',
            $invoice->id
        );
    }

    /**
     * ============================================================
     * DELETE EXPENSE
     * ============================================================
     */
    public function deleteExpense(
        int $invoiceId,
        int $expenseId
    ): RedirectResponse {

        /*
         * PO-GENERATED EXPENSE GUARD
         *
         * Expense dari PAID/legacy PO dikunci agar PO dan
         * Financial Report tidak berbeda nominal.
         */
        $linkedPurchaseOrder =
            \Webkul\Invoice\Models\PurchaseOrder::query()
                ->where('expense_id', $expenseId)
                ->whereIn(
                    'status',
                    [
                        'paid',
                        'released', // legacy rows posted by the old flow
                        'completed', // legacy rows
                    ]
                )
                ->first();

        if ($linkedPurchaseOrder) {
            session()->flash(
                'warning',
                'Expense ini berasal dari '
                    .$linkedPurchaseOrder->po_number
                    .' dan dikunci. Kelola koreksi dari Purchase Order.'
            );

            return back();
        }
        $invoice = Invoice::findOrFail(
            $invoiceId
        );

        $expense = Expense::query()
            ->where(
                'invoice_id',
                $invoice->id
            )
            ->findOrFail(
                $expenseId
            );

        $receiptPath =
            $expense->receipt_path;

        $this->expenseService
            ->deleteExpense(
                $expense
            );

        if ($receiptPath) {
            Storage::disk(
                'public'
            )->delete(
                $receiptPath
            );
        }

        session()->flash(
            'success',
            'Pengeluaran berhasil dihapus.'
        );

        return redirect()->route(
            'admin.invoices.show',
            $invoice->id
        );
    }

    /**
     * ============================================================
     * PRINT INVOICE
     * ============================================================
     */
    public function print(
        int $id
    ): Response|StreamedResponse {
        $invoice = Invoice::with([
            'items',
            'payments',
            'expenses',
            'quote',
            'person',
            'user',
        ])->findOrFail(
            $id
        );

        return $this->downloadPDF(
            view(
                'admin::invoices.pdf',
                compact(
                    'invoice'
                )
            )->render(),

            'Invoice_'
            .$invoice->invoice_number
        );
    }

    /**
     * ============================================================
     * FINANCIAL SUMMARY HELPER
     * ============================================================
     */
    private function buildFinancialSummary(
        int $year,
        ?string $eventStatus
    ): array {
        /**
         * ========================================================
         * REVENUE
         * ========================================================
         *
         * Hanya event CONFIRM.
         *
         * Prospect belum dianggap actual revenue.
         * Cancel tidak dianggap revenue.
         */
        $revenueQuery = Invoice::query()
            ->whereNotNull(
                'issued_at'
            )
            ->whereYear(
                'issued_at',
                $year
            )
            ->where(
                'event_status',
                'confirm'
            );

        /**
         * Kalau filter bukan Confirm,
         * confirmed Revenue akan menjadi 0.
         */
        if ($eventStatus) {
            $revenueQuery->where(
                'event_status',
                $eventStatus
            );
        }

        $revenue = (float) $revenueQuery
            ->sum(
                'grand_total'
            );

        /**
         * ========================================================
         * PAYMENT RECEIVED
         * ========================================================
         *
         * Actual payment history.
         *
         * Termasuk:
         * - PARTIAL
         * - PAID
         */
        $paymentQuery = Payment::query()
            ->whereNotNull(
                'paid_at'
            )
            ->whereYear(
                'paid_at',
                $year
            );

        if ($eventStatus) {
            $paymentQuery->whereHas(
                'invoice',
                function ($invoiceQuery) use ($eventStatus) {
                    $invoiceQuery->where(
                        'event_status',
                        $eventStatus
                    );
                }
            );
        }

        $received = (float) $paymentQuery
            ->sum(
                'amount'
            );

        /**
         * ========================================================
         * OUTSTANDING
         * ========================================================
         *
         * Hanya event CONFIRM.
         *
         * Prospect belum menjadi actual receivable.
         * Cancel tidak mempunyai outstanding aktif.
         */
        $outstandingQuery = Invoice::query()
            ->whereNotNull(
                'issued_at'
            )
            ->whereYear(
                'issued_at',
                $year
            )
            ->where(
                'event_status',
                'confirm'
            );

        if ($eventStatus) {
            $outstandingQuery->where(
                'event_status',
                $eventStatus
            );
        }

        $outstanding = (float) $outstandingQuery
            ->sum(
                'balance_due'
            );

        /**
         * ========================================================
         * ACTUAL EXPENSE
         * ========================================================
         */
        $expenseQuery = Expense::query()
            ->whereNotNull(
                'expense_date'
            )
            ->whereYear(
                'expense_date',
                $year
            );

        if ($eventStatus) {
            $expenseQuery->whereHas(
                'invoice',
                function ($invoiceQuery) use ($eventStatus) {
                    $invoiceQuery->where(
                        'event_status',
                        $eventStatus
                    );
                }
            );
        }

        $expense = (float) $expenseQuery
            ->sum(
                'amount'
            );

        /**
         * ========================================================
         * ESTIMATED PROFIT
         * ========================================================
         *
         * CONFIRM + PROSPECT
         *
         * CANCEL tidak masuk.
         */
        $projectedStatuses = $this
            ->projectedEventStatuses(
                $eventStatus
            );

        if (empty($projectedStatuses)) {
            $projectedRevenue = 0;

            $projectedExpense = 0;
        } else {
            $projectedRevenue =
                (float) Invoice::query()
                    ->whereNotNull(
                        'issued_at'
                    )
                    ->whereYear(
                        'issued_at',
                        $year
                    )
                    ->whereIn(
                        'event_status',
                        $projectedStatuses
                    )
                    ->sum(
                        'grand_total'
                    );

            $projectedExpense =
                (float) Expense::query()
                    ->whereNotNull(
                        'expense_date'
                    )
                    ->whereYear(
                        'expense_date',
                        $year
                    )
                    ->whereHas(
                        'invoice',
                        function ($invoiceQuery) use ($projectedStatuses) {
                            $invoiceQuery->whereIn(
                                'event_status',
                                $projectedStatuses
                            );
                        }
                    )
                    ->sum(
                        'amount'
                    );
        }

        /**
         * Est. Profit
         *
         * Invoice potential - Expense.
         */
        $estimatedProfit =
            $projectedRevenue
            - $projectedExpense;

        /**
         * Cash Surplus
         *
         * Actual money received
         * -
         * Actual money spent.
         */
        $cashSurplus =
            $received
            - $expense;

        return [
            'revenue' =>
                $revenue,

            'received' =>
                $received,

            'outstanding' =>
                $outstanding,

            'expense' =>
                $expense,

            'estimated_profit' =>
                $estimatedProfit,

            'cash_surplus' =>
                $cashSurplus,
        ];
    }

    /**
     * ============================================================
     * PROJECTED EVENT STATUSES
     * ============================================================
     */
    private function projectedEventStatuses(
        ?string $eventStatus
    ): array {
        return match ($eventStatus) {
            /**
             * Confirm filter:
             * hanya Confirm.
             */
            'confirm' => [
                'confirm',
            ],

            /**
             * Prospect filter:
             * hanya Prospect.
             */
            'prospect' => [
                'prospect',
            ],

            /**
             * Cancel:
             * tidak mempunyai Estimated Profit.
             */
            'cancel' => [],

            /**
             * All:
             * Confirm + Prospect.
             */
            default => [
                'confirm',
                'prospect',
            ],
        };
    }

    /**
     * ============================================================
     * NORMALIZE EVENT STATUS
     * ============================================================
     */
    private function normalizeEventStatus(
        ?string $status
    ): ?string {
        return in_array(
            $status,
            [
                'prospect',
                'confirm',
                'cancel',
            ],
            true
        )
            ? $status
            : null;
    }

    /**
     * ============================================================
     * NORMALIZE PAYMENT STATUS
     * ============================================================
     */
    private function normalizePaymentStatus(
        ?string $status
    ): ?string {
        return in_array(
            $status,
            [
                'unpaid',
                'partial',
                'paid',
            ],
            true
        )
            ? $status
            : null;
    }

    /**
     * ============================================================
     * NORMALIZE YEAR
     * ============================================================
     */
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
