<x-admin::layouts>
    <x-slot:title>
        Financial Report
    </x-slot:title>

    @php
        $rupiah = static fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');

        $periodLabel = $month
            ? \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y')
            : (string) $year;

        $businessUnitLabel = $businessUnit
            ? ($businessUnitOptions[$businessUnit] ?? '-')
            : 'All Business Units';

        $eventStatusLabel = $eventStatus
            ? ucfirst($eventStatus)
            : 'All Events';

        $productLabel = $product
            ?: 'All Products';
    @endphp

    <div class="space-y-5">
        {{-- Header --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Financial Report</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Revenue, payment, expense, estimated profit, and cash position per invoice.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        {{ $periodLabel }}
                    </span>
                    <span class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        {{ $businessUnitLabel }}
                    </span>
                    <span class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        {{ $eventStatusLabel }}
                    </span>
                    <span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                        {{ $productLabel }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Modern Filter --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Filters</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Filter by period, business unit, event status, and product.
                    </p>
                </div>

                @if (bouncer()->hasPermission('invoices.financial-report.export'))
                    <a
                        href="{{ route('admin.invoices.financial-report.export', ['year' => $year, 'month' => $month, 'business_unit' => $businessUnit, 'event_status' => $eventStatus, 'product' => $product]) }}"
                        class="secondary-button rounded-lg px-4 py-2.5 text-sm"
                    >
                        Export CSV
                    </a>

            {{-- FINANCIAL REPORT EXPORT ALL EXPENSES V1 --}}
            <a href="{{ route('admin.invoices.financial-report.expenses.export') }}" class="primary-button">
                Export All Expenses
            </a>
                @endif
            </div>

            <form method="GET" action="{{ route('admin.invoices.financial-report') }}">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Year
                        </label>
                        <select
                            name="year"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 focus:bg-white dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                            @foreach ($availableYears as $availableYear)
                                <option value="{{ $availableYear }}" @selected((int) $year === (int) $availableYear)>
                                    {{ $availableYear }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Month
                        </label>
                        <select
                            name="month"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 focus:bg-white dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                            <option value="">All Months</option>
                            @foreach (range(1, 12) as $monthNumber)
                                <option value="{{ $monthNumber }}" @selected((int) ($month ?? 0) === $monthNumber)>
                                    {{ \Carbon\Carbon::createFromDate($year, $monthNumber, 1)->format('F') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Business Unit
                        </label>
                        <select
                            name="business_unit"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 focus:bg-white dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                            <option value="">All Business Units</option>
                            @foreach ($businessUnitOptions as $value => $label)
                                <option value="{{ $value }}" @selected($businessUnit === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Event Status
                        </label>
                        <select
                            name="event_status"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 focus:bg-white dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                            <option value="">All Events</option>
                            <option value="confirm" @selected($eventStatus === 'confirm')>Confirm</option>
                            <option value="prospect" @selected($eventStatus === 'prospect')>Prospect</option>
                            <option value="cancel" @selected($eventStatus === 'cancel')>Cancel</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Product
                        </label>
                        <select
                            name="product"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 focus:bg-white dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                            <option value="">All Products</option>

                            @foreach ($productOptions as $productOption)
                                <option
                                    value="{{ $productOption }}"
                                    @selected($product === $productOption)
                                >
                                    {{ $productOption }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="submit" class="primary-button rounded-lg px-5 py-2.5 text-sm">
                        Apply Filter
                    </button>

                    <a href="{{ route('admin.invoices.financial-report') }}" class="secondary-button rounded-lg px-5 py-2.5 text-sm">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Summary cards --}}
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Revenue</div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $rupiah($financialSummary['revenue']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Confirm invoice cohort</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Payment Received</div>
                <div class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">{{ $rupiah($financialSummary['received']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cash in during period</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Outstanding</div>
                <div class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">{{ $rupiah($financialSummary['outstanding']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Current confirm balance</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Expense</div>
                <div class="mt-2 text-3xl font-bold text-orange-600 dark:text-orange-400">{{ $rupiah($financialSummary['expense']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cash out during period</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Est. Project Profit</div>
                <div class="mt-2 text-3xl font-bold {{ (float) $financialSummary['estimated_profit'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $rupiah($financialSummary['estimated_profit']) }}
                </div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cohort less all project expense</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cash Surplus</div>
                <div class="mt-2 text-3xl font-bold {{ (float) $financialSummary['cash_surplus'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $rupiah($financialSummary['cash_surplus']) }}
                </div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cash in minus cash out</div>
            </div>
        </div>

        {{-- Invoice cohort --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                <span class="font-semibold text-gray-800 dark:text-white">Invoice Cohort</span>
                <span class="text-gray-600 dark:text-gray-300">Total: <strong>{{ $invoiceStats['total'] }}</strong></span>
                <span class="text-green-600 dark:text-green-400">Paid: <strong>{{ $invoiceStats['paid'] }}</strong></span>
                <span class="text-yellow-600 dark:text-yellow-400">Partial: <strong>{{ $invoiceStats['partial'] }}</strong></span>
                <span class="text-red-600 dark:text-red-400">Unpaid: <strong>{{ $invoiceStats['unpaid'] }}</strong></span>
            </div>
        </div>

        {{-- Invoice performance --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-800 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Invoice Performance</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Project metrics plus cash activity for the selected period.
                    </p>
                </div>

                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $periodLabel }} · {{ $businessUnitLabel }}
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1450px]">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-950">
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice / Project</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Customer / Subject</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Product</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice Value</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Received</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Outstanding</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Expense</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Est. Profit</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cash Surplus</th>
                            <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($invoicePerformance as $invoice)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-6 py-5 align-top">
                                    <a href="{{ route('admin.invoices.show', $invoice['id']) }}" class="text-base font-semibold text-blue-600 hover:underline dark:text-blue-400">
                                        {{ $invoice['invoice_number'] }}
                                    </a>
                                    <div class="mt-2 space-y-1 text-sm text-gray-500 dark:text-gray-400">
                                        <div>{{ $invoice['project_code'] }}</div>
                                        <div>{{ optional($invoice['issued_at'])->format('d M Y') }}</div>
                                    </div>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <div class="text-base font-semibold text-gray-800 dark:text-white">{{ $invoice['customer'] }}</div>
                                    <div class="mt-2 max-w-[320px] text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $invoice['subject'] }}</div>
                                    @if (! empty($invoice['business_unit_label']))
                                        <div class="mt-3">
                                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                {{ $invoice['business_unit_label'] }}
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                <td class="px-6 py-5 align-top">
                                    @if (! empty($invoice['product_list']))
                                        <div class="flex max-w-[280px] flex-wrap gap-1.5">
                                            @foreach ($invoice['product_list'] as $productName)
                                                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                                                    {{ $productName }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-400">-</span>
                                    @endif
                                </td>

                                <td class="px-6 py-5 text-right align-top font-semibold text-gray-800 dark:text-white">{{ $rupiah($invoice['invoice_value']) }}</td>

                                <td class="px-6 py-5 text-right align-top">
                                    <div class="font-semibold text-green-600 dark:text-green-400">{{ $rupiah($invoice['received_in_period']) }}</div>
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">To date: {{ $rupiah($invoice['paid_to_date']) }}</div>
                                </td>

                                <td class="px-6 py-5 text-right align-top font-semibold text-red-600 dark:text-red-400">{{ $rupiah($invoice['outstanding']) }}</td>

                                <td class="px-6 py-5 text-right align-top">
                                    <div class="font-semibold text-orange-600 dark:text-orange-400">{{ $rupiah($invoice['expense_in_period']) }}</div>
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Project: {{ $rupiah($invoice['project_expense']) }}</div>
                                </td>

                                <td class="px-6 py-5 text-right align-top font-semibold {{ (float) $invoice['estimated_profit'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ $rupiah($invoice['estimated_profit']) }}</td>

                                <td class="px-6 py-5 text-right align-top font-semibold {{ (float) $invoice['cash_surplus_in_period'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ $rupiah($invoice['cash_surplus_in_period']) }}</td>

                                <td class="px-6 py-5 text-center align-top">
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                                            {{ $invoice['event_status'] }}
                                        </span>
                                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                            {{ $invoice['status'] }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-6 py-5 text-right align-top">
                                    <a href="{{ route('admin.invoices.show', $invoice['id']) }}" class="inline-flex rounded-lg bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 dark:bg-blue-950/40 dark:text-blue-300 dark:hover:bg-blue-950/60">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No invoice data for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Monthly analytics --}}
        @if (! $month)
            <details class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900" open>
                <summary class="cursor-pointer px-5 py-4 text-base font-semibold text-gray-800 dark:text-white">
                    Monthly Analytics — {{ $year }}
                </summary>

                <div class="overflow-x-auto border-t border-gray-200 dark:border-gray-800">
                    <table class="w-full min-w-[920px]">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-950">
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Month</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Revenue</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Received</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Expense</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Est. Profit</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cash Surplus</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($monthlyPerformance as $row)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-6 py-4 font-medium text-gray-800 dark:text-white">{{ $row['month_full'] }}</td>
                                    <td class="px-6 py-4 text-right text-gray-800 dark:text-gray-200">{{ $rupiah($row['revenue']) }}</td>
                                    <td class="px-6 py-4 text-right text-green-600 dark:text-green-400">{{ $rupiah($row['received']) }}</td>
                                    <td class="px-6 py-4 text-right text-orange-600 dark:text-orange-400">{{ $rupiah($row['expense']) }}</td>
                                    <td class="px-6 py-4 text-right text-gray-800 dark:text-gray-200">{{ $rupiah($row['profit']) }}</td>
                                    <td class="px-6 py-4 text-right text-gray-800 dark:text-gray-200">{{ $rupiah($row['cash_surplus']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif

        {{-- Expense breakdown --}}
        <details class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <summary class="cursor-pointer px-5 py-4 text-base font-semibold text-gray-800 dark:text-white">
                Expense Breakdown
            </summary>

            <div class="border-t border-gray-200 p-5 dark:border-gray-800">
                @forelse ($expenseByCategory as $expense)
                    <div class="flex items-center justify-between gap-4 border-b border-gray-100 py-3.5 last:border-b-0 dark:border-gray-800">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ $expense['category'] }}</span>
                        <span class="text-base font-semibold text-orange-600 dark:text-orange-400">{{ $rupiah($expense['total']) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">No expense in this selected period.</p>
                @endforelse
            </div>
        </details>

        {{-- How calculations work --}}
        <details class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <summary class="cursor-pointer px-5 py-4 text-base font-semibold text-gray-800 dark:text-white">
                Financial Calculation
            </summary>

            <div class="grid gap-4 border-t border-gray-200 p-5 sm:grid-cols-2 dark:border-gray-800">
                <div>
                    <div class="font-semibold text-gray-800 dark:text-white">Revenue</div>
                    <div class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Invoice value from confirmed invoice cohort.</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-white">Payment Received</div>
                    <div class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">All payment transactions actually received in the selected period.</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-white">Outstanding</div>
                    <div class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Remaining balance of confirmed invoices in the selected cohort.</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-white">Expense</div>
                    <div class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Actual recorded expenses in the selected period.</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-white">Estimated Project Profit</div>
                    <div class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Confirm + Prospect minus all project expenses. Cancel is excluded.</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-white">Cash Surplus</div>
                    <div class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Payment received in period minus expense in the same period.</div>
                </div>
            </div>
        </details>
    </div>
</x-admin::layouts>
