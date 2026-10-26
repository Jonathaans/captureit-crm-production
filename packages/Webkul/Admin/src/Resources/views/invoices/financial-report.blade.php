<x-admin::layouts>
    <x-slot:title>Financial Report</x-slot:title>
    @include('admin::invoices.partials.workspace-styles')
    @php
        $rupiah = static fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
        $periodLabel = $month ? \Carbon\Carbon::createFromDate($year, $month, 1)->locale('id')->translatedFormat('F Y') : (string) $year;
        $businessUnitLabel = $businessUnit ? ($businessUnitOptions[$businessUnit] ?? '-') : 'Semua unit bisnis';
        $eventStatusLabel = $eventStatus ? ucfirst($eventStatus) : 'Semua status event';
        $productLabel = $product ?: 'Semua produk';
        $exportFilters = ['year' => $year, 'month' => $month, 'business_unit' => $businessUnit, 'event_status' => $eventStatus, 'product' => $product];
    @endphp
    <div class="crm-finance-workspace">
        <header class="fw-header">
            <div>
                <p class="fw-eyebrow">Finance / Laporan</p>
                <h1>Financial Report</h1>
                <p class="fw-subtitle">Gambaran tagihan, penerimaan, dan pengeluaran dalam satu tampilan.</p>
            </div>
            <div class="fw-actions">
                @if (bouncer()->hasPermission('invoices') || bouncer()->hasPermission('invoices.view') || bouncer()->hasPermission('invoices.financial-report'))
                    <a href="{{ route('admin.finance-sales-dashboard.index') }}" class="fw-link">Dashboard penjualan &rarr;</a>
                @endif
                @if (bouncer()->hasPermission('invoices.financial-report.export') || bouncer()->hasPermission('invoices.expense.export-all'))
                    <details class="fw-export">
                        <summary>Ekspor <span aria-hidden="true">⌄</span></summary>
                        <div class="fw-export-menu">
                            @if (bouncer()->hasPermission('invoices.financial-report.export'))
                                <a href="{{ route('admin.invoices.financial-report.export', $exportFilters) }}">Financial Report<small>Daftar invoice sesuai filter aktif</small></a>
                                <a href="{{ route('admin.invoices.financial-report.export', array_merge($exportFilters, ['section' => 'top-products'])) }}">Produk teratas<small>Seluruh produk sesuai filter aktif</small></a>
                            @endif
                            @if (bouncer()->hasPermission('invoices.expense.export-all'))
                                <a href="{{ route('admin.invoices.expenses.export-all') }}">Seluruh pengeluaran<small>Semua periode, tanpa filter laporan</small></a>
                            @endif
                        </div>
                    </details>
                @endif
            </div>
        </header>
        <details class="fw-card fw-details">
            <summary>
                <span><span class="fw-title">{{ $periodLabel }}</span><span class="fw-muted block">{{ $businessUnitLabel }} · {{ $eventStatusLabel }} · {{ $productLabel }}</span></span>
                <span class="fw-badge" style="margin-left:auto">Ubah filter</span>
            </summary>
            <div class="fw-pad">
            <form method="GET" action="{{ route('admin.invoices.financial-report') }}">
                <div class="fw-filter-grid">
                    <div>
                        <label for="report-year" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Tahun
                        </label>
                        <select
                            id="report-year" name="year"
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
                        <label for="report-month" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Bulan
                        </label>
                        <select
                            id="report-month" name="month"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 focus:bg-white dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                            <option value="">Semua bulan</option>
                            @foreach (range(1, 12) as $monthNumber)
                                <option value="{{ $monthNumber }}" @selected((int) ($month ?? 0) === $monthNumber)>
                                    {{ \Carbon\Carbon::createFromDate($year, $monthNumber, 1)->format('F') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="report-business_unit" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Unit bisnis
                        </label>
                        <select
                            id="report-business_unit" name="business_unit"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 focus:bg-white dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                            <option value="">Semua unit bisnis</option>
                            @foreach ($businessUnitOptions as $value => $label)
                                <option value="{{ $value }}" @selected($businessUnit === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="report-event_status" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Status event
                        </label>
                        <select
                            id="report-event_status" name="event_status"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 focus:bg-white dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                            <option value="">Semua status event</option>
                            <option value="confirm" @selected($eventStatus === 'confirm')>Confirm</option>
                            <option value="prospect" @selected($eventStatus === 'prospect')>Prospect</option>
                            <option value="cancel" @selected($eventStatus === 'cancel')>Cancel</option>
                        </select>
                    </div>

                    <div>
                        <label for="report-product" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Produk
                        </label>
                        <select
                            id="report-product" name="product"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 focus:bg-white dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                            <option value="">Semua produk</option>

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
                        Terapkan filter
                    </button>

                    <a href="{{ route('admin.invoices.financial-report') }}" class="secondary-button rounded-lg px-5 py-2.5 text-sm">
                        Reset
                    </a>
                </div>
            </form>
            </div>
        </details>
        <section aria-label="Ringkasan keuangan" class="fw-metrics">
            @foreach ([
                ['Total invoice', 'invoiced', 'Invoice confirmed terbit pada periode ini', ''],
                ['Uang masuk', 'received', 'Pembayaran aktual pada periode ini', 'fw-positive'],
                ['Piutang saat ini', 'outstanding', 'Saldo invoice terbit pada periode ini', 'fw-negative'],
                ['Pengeluaran', 'expense', 'Uang keluar aktual pada periode ini', ''],
                ['Surplus kas', 'cash_surplus', 'Uang masuk dikurangi pengeluaran', (float) $financialSummary['cash_surplus'] >= 0 ? 'fw-positive' : 'fw-negative'],
            ] as [$label, $key, $description, $tone])
                <div class="fw-metric {{ $key === 'cash_surplus' ? 'fw-highlight' : '' }}">
                    <p class="fw-metric-label">{{ $label }}</p>
                    <p class="fw-value {{ $tone }}">{{ $rupiah($financialSummary[$key]) }}</p>
                    <small>{{ $description }}</small>
                </div>
            @endforeach
        </section>
        <div class="fw-between">
            <div class="fw-inline-stats" aria-label="Status invoice periode terpilih">
                <span><strong>{{ $invoiceStats['total'] }}</strong> invoice</span>
                <span><strong>{{ $invoiceStats['paid'] }}</strong> lunas</span>
                <span><strong>{{ $invoiceStats['partial'] }}</strong> dibayar sebagian</span>
                <span><strong>{{ $invoiceStats['unpaid'] }}</strong> belum dibayar</span>
            </div>
            <span class="fw-muted">Proforma, invoice VOID, dan event Cancel tidak masuk daftar maupun total invoice.</span>
        </div>
        <p class="fw-muted">Pembayaran dan biaya aktual tetap tercatat pada rincian kas meskipun event dibatalkan. Filter Cancel menampilkan arus kas event tersebut.</p>
        @if ($proformaFollowUp !== null)
            @include('admin::invoices.proformas.follow-up')
        @endif
        <div>
            <p class="fw-eyebrow">Rincian laporan</p>
            <p class="fw-muted">Buka bagian yang ingin diperiksa.</p>
        </div>
        @include('admin::invoices.financial-cash-transactions')
        @include('admin::invoices.financial-project-position')
        {{-- CRM_TOP_PRODUCT_REPORT_V1 --}}
        <details class="fw-card fw-details">
            <summary><span><span class="fw-title">Produk teratas</span><span class="fw-muted block">{{ $topProductTotal }} produk · berdasarkan proyek confirmed</span></span></summary>
            <p class="fw-section-note">Invoice DP dan pelunasan dalam proyek yang sama dihitung satu kali. Nilai mengikuti invoice terbit pada periode ini; penerimaan sampai saat ini dialokasikan per produk. Menampilkan maksimal 10 produk, seluruhnya tersedia di menu Ekspor.</p>
            <div class="fw-scroll">
                <table class="fw-table" style="min-width:900px">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-950">
                            <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Urutan</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Produk</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Proyek confirmed</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Qty</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nilai invoice</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Diterima</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tertagih</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($topProducts as $topProduct)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-6 py-4 text-center align-top">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full {{ $topProduct['rank'] <= 3 ? 'bg-blue-50 font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' : 'bg-gray-100 font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">
                                        {{ $topProduct['rank'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="font-semibold text-gray-800 dark:text-white">{{ $topProduct['product_name'] }}</div>
                                    @if ($topProduct['sku'])
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">SKU: {{ $topProduct['sku'] }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right align-top font-semibold text-gray-800 dark:text-white">{{ $topProduct['deal_count'] }}</td>
                                <td class="px-6 py-4 text-right align-top text-gray-700 dark:text-gray-300">
                                    {{ $topProduct['quantity_label'] }}
                                </td>
                                <td class="px-6 py-4 text-right align-top font-semibold text-gray-800 dark:text-white">{{ $rupiah($topProduct['sales_value']) }}</td>
                                <td class="px-6 py-4 text-right align-top font-semibold text-green-600 dark:text-green-400">{{ $rupiah($topProduct['received_allocated']) }}</td>
                                <td class="px-6 py-4 text-right align-top">
                                    <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-950/40 dark:text-green-300">
                                        {{ number_format((float) $topProduct['collection_rate'], 2, ',', '.') }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada produk dari proyek confirmed untuk filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>

        {{-- Invoice performance --}}
        <details class="fw-card fw-details">
            <summary><span><span class="fw-title">Rincian invoice</span><span class="fw-muted block">{{ $invoiceStats['total'] }} invoice terbit pada periode terpilih</span></span></summary>
            <p class="fw-section-note">Penerimaan dan biaya periode ini ditampilkan bersama posisi invoice saat ini. Transaksi untuk invoice bulan lain ada pada Rincian Uang Masuk dan Keluar.</p>
            <div class="fw-scroll">
                <table class="fw-table" style="min-width:1280px">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-950">
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice / Proyek</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pelanggan / Acara</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Produk</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nilai invoice</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Diterima periode ini</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Piutang saat ini</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pengeluaran</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Margin invoice sebelum PPh</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Surplus kas</th>
                            <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($invoicePerformance as $invoice)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-6 py-5 align-top">
                                    @if (bouncer()->hasPermission('invoices.view'))
                                        <a href="{{ route('admin.invoices.show', $invoice['id']) }}" class="fw-link">{{ $invoice['invoice_number'] }}</a>
                                    @else
                                        {{ $invoice['invoice_number'] }}
                                    @endif
                                    <div class="mt-2">
                                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold uppercase text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                                            {{ str_replace('_', ' ', $invoice['billing_type']) }}
                                        </span>
                                    </div>
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

                                <td class="px-6 py-5 text-right align-top font-semibold text-gray-800 dark:text-white">{{ $rupiah($invoice['invoice_value']) }} @if ($invoice['pph_amount'] > 0)<div class="mt-2 text-xs font-normal">PPh: {{ $rupiah($invoice['pph_amount']) }}<br>Netto: {{ $rupiah($invoice['net_payable']) }}</div>@endif</td>

                                <td class="px-6 py-5 text-right align-top">
                                    <div class="font-semibold text-green-600 dark:text-green-400">{{ $rupiah($invoice['received_in_period']) }}</div>
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Hingga kini: {{ $rupiah($invoice['paid_to_date']) }}</div>
                                </td>

                                <td class="px-6 py-5 text-right align-top font-semibold text-red-600 dark:text-red-400">{{ $rupiah($invoice['outstanding']) }}</td>

                                <td class="px-6 py-5 text-right align-top">
                                    <div class="font-semibold text-orange-600 dark:text-orange-400">{{ $rupiah($invoice['expense_in_period']) }}</div>
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Invoice: {{ $rupiah($invoice['project_expense']) }}</div>
                                </td>

                                <td class="px-6 py-5 text-right align-top font-semibold {{ (float) $invoice['estimated_profit'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ $rupiah($invoice['estimated_profit']) }} @if ($invoice['pph_amount'] > 0)<div class="mt-2 text-xs font-normal">Setelah PPh: {{ $rupiah($invoice['net_margin']) }}</div>@endif</td>

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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada invoice yang sesuai dengan filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>

        {{-- Monthly analytics --}}
        @if (! $month)
            <details class="fw-card fw-details">
                <summary class="cursor-pointer px-5 py-4 text-base font-semibold text-gray-800 dark:text-white">
                    Ringkasan bulanan · {{ $year }}
                </summary>

                <div class="overflow-x-auto border-t border-gray-200 dark:border-gray-800">
                    <table class="fw-table" style="min-width:800px">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-950">
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bulan</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total invoice</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Diterima periode ini</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pengeluaran</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Surplus kas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($monthlyPerformance as $row)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-6 py-4 font-medium text-gray-800 dark:text-white">{{ $row['month_full'] }}</td>
                                    <td class="px-6 py-4 text-right text-gray-800 dark:text-gray-200">{{ $rupiah($row['revenue']) }}</td>
                                    <td class="px-6 py-4 text-right text-green-600 dark:text-green-400">{{ $rupiah($row['received']) }}</td>
                                    <td class="px-6 py-4 text-right text-orange-600 dark:text-orange-400">{{ $rupiah($row['expense']) }}</td>
                                    <td class="px-6 py-4 text-right text-gray-800 dark:text-gray-200">{{ $rupiah($row['cash_surplus']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif

        {{-- Expense breakdown --}}
        <details class="fw-card fw-details">
            <summary class="cursor-pointer px-5 py-4 text-base font-semibold text-gray-800 dark:text-white">
                Pengeluaran per kategori
            </summary>

            <div class="border-t border-gray-200 p-5 dark:border-gray-800">
                @forelse ($expenseByCategory as $expense)
                    <div class="flex items-center justify-between gap-4 border-b border-gray-100 py-3.5 last:border-b-0 dark:border-gray-800">
                        <span v-pre class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ $expense['category'] }}</span>
                        <span class="text-base font-semibold text-orange-600 dark:text-orange-400">{{ $rupiah($expense['total']) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada pengeluaran pada periode ini.</p>
                @endforelse
            </div>
        </details>

        {{-- How calculations work --}}
        <details class="fw-card fw-details">
            <summary class="cursor-pointer px-5 py-4 text-base font-semibold text-gray-800 dark:text-white">
                Cara membaca angka
            </summary>

            <div class="grid gap-4 border-t border-gray-200 p-5 sm:grid-cols-2 dark:border-gray-800">
                <div>
                    <div class="font-semibold text-gray-800 dark:text-white">Total invoice</div>
                    <div class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Nilai invoice confirmed berdasarkan tanggal terbit. Proforma dan sisa penawaran tidak disertakan.</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-white">Diterima</div>
                    <div class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Pembayaran aktual berdasarkan tanggal transaksi pada periode terpilih.</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-white">Piutang saat ini</div>
                    <div class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Saldo saat ini dari invoice confirmed yang terbit pada periode terpilih; bukan saldo pada akhir periode tersebut.</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-white">Pengeluaran</div>
                    <div class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Pengeluaran aktual berdasarkan tanggal transaksi pada periode terpilih.</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-white">Margin Proyek dari Invoice</div>
                    <div class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Seluruh invoice confirmed pada proyek dikurangi biaya yang tercatat pada invoice proyek. DP/pelunasan satu proyek digabung. Proforma dan nilai penawaran tidak diakui.</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-white">Surplus kas</div>
                    <div class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Uang masuk dikurangi pengeluaran pada periode yang sama.</div>
                </div>
            </div>
        </details>
    </div>
</x-admin::layouts>
