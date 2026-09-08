<x-admin::layouts>
    <x-slot:title>
        Finance & Sales Dashboard
    </x-slot>

    {{-- CRM_FINANCE_SALES_DASHBOARD_V1 --}}
    @php
        $rupiah = static fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
        $focusLabels = [
            'all' => 'Semua Tagihan Aktif',
            'unpaid' => 'Belum Dibayar',
            'partial' => 'Dibayar Sebagian',
            'overdue' => 'Terlambat',
            'due_soon' => 'Jatuh Tempo 7 Hari',
            'down_payment' => 'DP Belum Lunas',
            'settlement' => 'Pelunasan Belum Lunas',
        ];
    @endphp

    <div class="grid gap-4">
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-amber-600">
                    Collection Control
                </p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                    Finance & Sales Dashboard
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Prioritas penagihan, DP, pelunasan, jatuh tempo, dan tanggung jawab Sales dalam satu halaman.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.invoices.index') }}" class="secondary-button">
                    Daftar Invoice
                </a>
                <a href="{{ route('admin.invoices.financial-report') }}" class="secondary-button">
                    Financial Report
                </a>
                <a href="{{ route('admin.invoices.billing.create') }}" class="primary-button">
                    + Generate Invoice
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.finance-sales-dashboard.index') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="grid gap-3 lg:grid-cols-5">
                <label class="grid gap-1 lg:col-span-2">
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">Cari</span>
                    <input
                        type="text"
                        name="search"
                        value="{{ $filters['search'] }}"
                        placeholder="Invoice, project, customer, atau sales..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                </label>

                <label class="grid gap-1">
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">Sales Owner</span>
                    <select name="sales_user_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <option value="0">Semua Sales</option>
                        @foreach ($salesUsers as $salesUser)
                            <option value="{{ $salesUser->id }}" @selected((int) $filters['sales_user_id'] === (int) $salesUser->id)>
                                {{ $salesUser->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-1">
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">Business Unit</span>
                    <select name="business_unit" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <option value="">Semua Unit</option>
                        @foreach ($businessUnits as $unit)
                            <option value="{{ $unit['value'] }}" @selected($filters['business_unit'] === $unit['value'])>
                                {{ $unit['label'] }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-1">
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">Fokus</span>
                    <select name="focus" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        @foreach ($focusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['focus'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Hanya Invoice berstatus event CONFIRM yang masuk dalam collection.
                </p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.finance-sales-dashboard.index') }}" class="secondary-button">Reset</a>
                    <button type="submit" class="primary-button">Terapkan Filter</button>
                    <a href="{{ route('admin.finance-sales-dashboard.export', $filters) }}" class="secondary-button">Export CSV</a>
                </div>
            </div>
        </form>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('admin.finance-sales-dashboard.index', array_merge($filters, ['focus' => 'all'])) }}" class="rounded-xl border border-slate-200 bg-slate-950 p-4 text-white shadow-sm transition hover:-translate-y-0.5">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-300">Total Outstanding</p>
                <p class="mt-3 text-2xl font-bold">{{ $rupiah($metrics['outstanding_total']) }}</p>
                <p class="mt-1 text-xs text-slate-300">{{ $metrics['open_count'] }} invoice perlu tindak lanjut</p>
            </a>

            <a href="{{ route('admin.finance-sales-dashboard.index', array_merge($filters, ['focus' => 'unpaid'])) }}" class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm transition hover:-translate-y-0.5 dark:border-red-900/50 dark:bg-red-950/30">
                <p class="text-xs font-semibold uppercase tracking-wider text-red-600">Belum Dibayar</p>
                <p class="mt-3 text-2xl font-bold text-red-700 dark:text-red-300">{{ $metrics['unpaid_count'] }}</p>
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $rupiah($metrics['unpaid_total']) }}</p>
            </a>

            <a href="{{ route('admin.finance-sales-dashboard.index', array_merge($filters, ['focus' => 'partial'])) }}" class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm transition hover:-translate-y-0.5 dark:border-amber-900/50 dark:bg-amber-950/30">
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Partial</p>
                <p class="mt-3 text-2xl font-bold text-amber-700 dark:text-amber-300">{{ $metrics['partial_count'] }}</p>
                <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">Sisa {{ $rupiah($metrics['partial_total']) }}</p>
            </a>

            <a href="{{ route('admin.finance-sales-dashboard.index', array_merge($filters, ['focus' => 'overdue'])) }}" class="rounded-xl border border-rose-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 dark:border-rose-900/50 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase tracking-wider text-rose-600">Terlambat</p>
                <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">{{ $metrics['overdue_count'] }}</p>
                <p class="mt-1 text-xs text-rose-600">{{ $rupiah($metrics['overdue_total']) }}</p>
            </a>

            <a href="{{ route('admin.finance-sales-dashboard.index', array_merge($filters, ['focus' => 'due_soon'])) }}" class="rounded-xl border border-blue-200 bg-blue-50 p-4 shadow-sm transition hover:-translate-y-0.5 dark:border-blue-900/50 dark:bg-blue-950/30">
                <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">Jatuh Tempo 7 Hari</p>
                <p class="mt-3 text-2xl font-bold text-blue-700 dark:text-blue-300">{{ $metrics['due_soon_count'] }}</p>
                <p class="mt-1 text-xs text-blue-600 dark:text-blue-400">{{ $rupiah($metrics['due_soon_total']) }}</p>
            </a>

            <a href="{{ route('admin.finance-sales-dashboard.index', array_merge($filters, ['focus' => 'down_payment'])) }}" class="rounded-xl border border-violet-200 bg-violet-50 p-4 shadow-sm transition hover:-translate-y-0.5 dark:border-violet-900/50 dark:bg-violet-950/30">
                <p class="text-xs font-semibold uppercase tracking-wider text-violet-600">DP Belum Lunas</p>
                <p class="mt-3 text-2xl font-bold text-violet-700 dark:text-violet-300">{{ $metrics['dp_waiting_count'] }}</p>
                <p class="mt-1 text-xs text-violet-600 dark:text-violet-400">{{ $rupiah($metrics['dp_waiting_total']) }}</p>
            </a>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-900/50 dark:bg-emerald-950/30">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Siap Pelunasan</p>
                <p class="mt-3 text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ $metrics['ready_settlement_count'] }}</p>
                <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">Potensi {{ $rupiah($metrics['ready_settlement_total']) }}</p>
            </div>

            <a href="{{ route('admin.finance-sales-dashboard.index', array_merge($filters, ['focus' => 'settlement'])) }}" class="rounded-xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm transition hover:-translate-y-0.5 dark:border-cyan-900/50 dark:bg-cyan-950/30">
                <p class="text-xs font-semibold uppercase tracking-wider text-cyan-600">Pelunasan Outstanding</p>
                <p class="mt-3 text-2xl font-bold text-cyan-700 dark:text-cyan-300">{{ $metrics['settlement_outstanding_count'] }}</p>
                <p class="mt-1 text-xs text-cyan-600 dark:text-cyan-400">{{ $rupiah($metrics['settlement_outstanding_total']) }}</p>
            </a>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Aging Outstanding</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Umur piutang berdasarkan tanggal jatuh tempo.</p>
                </div>
                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">Real-time dari payment history</span>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ($aging as $bucket)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $bucket['label'] }}</p>
                        <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $rupiah($bucket['amount']) }}</p>
                        <p class="mt-1 text-xs font-medium text-gray-500">{{ $bucket['count'] }} invoice</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Priority Collection</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $focusLabels[$filters['focus']] ?? 'Semua Tagihan Aktif' }} · urut dari due date terdekat.</p>
                </div>
                <span class="text-xs font-semibold text-gray-500">{{ $actionInvoices->total() }} invoice</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" style="min-width: 1120px">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-950/40 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Invoice / Project</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Sales</th>
                            <th class="px-4 py-3">Tahap</th>
                            <th class="px-4 py-3 text-right">Nilai</th>
                            <th class="px-4 py-3 text-right">Terbayar</th>
                            <th class="px-4 py-3 text-right">Outstanding</th>
                            <th class="px-4 py-3">Jatuh Tempo</th>
                            <th class="px-4 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($actionInvoices as $invoice)
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.invoices.show', $invoice['id']) }}" class="font-semibold text-blue-600 hover:underline dark:text-blue-400">{{ $invoice['invoice_number'] }}</a>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $invoice['project_code'] }} · {{ $invoice['subject'] }}</p>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $invoice['customer'] }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $invoice['salesperson'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">{{ $invoice['billing_label'] }}</span>
                                    <span class="ml-1 rounded-full px-2.5 py-1 text-xs font-semibold {{ $invoice['payment_status'] === 'partial' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' }}">{{ $invoice['payment_status_label'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ $rupiah($invoice['invoice_value']) }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600">{{ $rupiah($invoice['paid']) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ $rupiah($invoice['outstanding']) }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium {{ $invoice['is_overdue'] ? 'text-red-600' : ($invoice['is_due_soon'] ? 'text-amber-600' : 'text-gray-700 dark:text-gray-300') }}">{{ $invoice['due_at_label'] }}</p>
                                    @if ($invoice['is_overdue'])
                                        <p class="mt-0.5 text-xs font-semibold text-red-500">Terlambat {{ $invoice['days_overdue'] }} hari</p>
                                    @elseif ($invoice['due_in_days'] !== null)
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $invoice['due_in_days'] === 0 ? 'Jatuh tempo hari ini' : $invoice['due_in_days'].' hari lagi' }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.invoices.show', $invoice['id']) }}" class="secondary-button whitespace-nowrap">Buka & Catat Payment</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-12 text-center text-sm text-gray-500">Tidak ada invoice yang cocok dengan filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($actionInvoices->hasPages())
                <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                    {{ $actionInvoices->links() }}
                </div>
            @endif
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="overflow-hidden rounded-xl border border-emerald-200 bg-white shadow-sm dark:border-emerald-900/50 dark:bg-gray-900 xl:col-span-2">
                <div class="border-b border-emerald-100 bg-emerald-50 px-5 py-4 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                    <h2 class="text-base font-bold text-emerald-800 dark:text-emerald-300">DP Lunas — Siap Generate Pelunasan</h2>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">Hanya DP yang pembayaran transaksinya sudah penuh dan belum mempunyai Invoice Pelunasan aktif.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" style="min-width: 760px">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-950/40">
                            <tr>
                                <th class="px-4 py-3">DP / Quote</th>
                                <th class="px-4 py-3">Customer & Sales</th>
                                <th class="px-4 py-3 text-right">DP Dibayar</th>
                                <th class="px-4 py-3 text-right">Nilai Pelunasan</th>
                                <th class="px-4 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($readySettlements as $settlement)
                                <tr>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.invoices.show', $settlement['invoice_id']) }}" class="font-semibold text-blue-600 hover:underline">{{ $settlement['invoice_number'] }}</a>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $settlement['quote_number'] }} · {{ $settlement['project_code'] }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ $settlement['customer'] }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">Sales: {{ $settlement['salesperson'] }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right text-emerald-600">{{ $rupiah($settlement['dp_value']) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ $rupiah($settlement['remaining']) }}</td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.invoices.billing.create', ['quote_id' => $settlement['quote_id']]) }}" class="primary-button whitespace-nowrap">Generate Pelunasan</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500">Belum ada DP lunas yang siap dibuatkan pelunasan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Portfolio Sales</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Outstanding yang perlu ditindaklanjuti per Sales Owner.</p>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($salesPortfolio as $portfolio)
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $portfolio['salesperson'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $portfolio['invoice_count'] }} invoice · {{ $portfolio['overdue_count'] }} terlambat</p>
                                </div>
                                <p class="text-right text-sm font-bold text-gray-900 dark:text-white">{{ $rupiah($portfolio['outstanding']) }}</p>
                            </div>
                            <div class="mt-3 flex gap-2 text-xs">
                                <span class="rounded-full bg-red-50 px-2 py-1 text-red-600 dark:bg-red-950/30">{{ $portfolio['unpaid_count'] }} unpaid</span>
                                <span class="rounded-full bg-amber-50 px-2 py-1 text-amber-600 dark:bg-amber-950/30">{{ $portfolio['partial_count'] }} partial</span>
                            </div>
                        </div>
                    @empty
                        <p class="p-8 text-center text-sm text-gray-500">Tidak ada outstanding.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-300">
            <strong>Definisi angka:</strong> outstanding dihitung dari Grand Total Invoice dikurangi total riwayat Payment. Quote tidak dijumlahkan kembali. DP yang sudah lunas baru masuk “Siap Pelunasan” jika belum ada Invoice Pelunasan/Full Payment aktif.
        </div>
    </div>
</x-admin::layouts>
