<x-admin::layouts>
    <x-slot:title>
        Finance & Sales Dashboard
    </x-slot>

    {{-- CRM_FINANCE_SALES_DASHBOARD_UI_HOTFIX_V1_1 --}}
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

    <style>
        .fsd-v11 {
            --fsd-bg: #ffffff;
            --fsd-soft: #f8fafc;
            --fsd-border: #e2e8f0;
            --fsd-text: #0f172a;
            --fsd-muted: #64748b;
            display: grid;
            gap: 16px;
            color: var(--fsd-text);
        }

        .dark .fsd-v11 {
            --fsd-bg: #111827;
            --fsd-soft: #0f172a;
            --fsd-border: #334155;
            --fsd-text: #f8fafc;
            --fsd-muted: #94a3b8;
        }

        .fsd-panel {
            border: 1px solid var(--fsd-border);
            border-radius: 14px;
            background: var(--fsd-bg);
            box-shadow: 0 1px 3px rgba(15, 23, 42, .06);
        }

        .fsd-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 18px 20px;
        }

        .fsd-eyebrow {
            margin: 0 0 4px;
            color: #d97706;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .fsd-title {
            margin: 0;
            color: var(--fsd-text);
            font-size: 22px;
            font-weight: 750;
            line-height: 1.25;
        }

        .fsd-subtitle,
        .fsd-muted {
            color: var(--fsd-muted);
        }

        .fsd-subtitle {
            margin: 4px 0 0;
            font-size: 13px;
        }

        .fsd-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 8px;
        }

        .fsd-filters {
            padding: 16px;
        }

        .fsd-filter-grid {
            display: grid;
            grid-template-columns: minmax(280px, 2fr) repeat(3, minmax(170px, 1fr));
            align-items: end;
            gap: 12px;
        }

        .fsd-field {
            display: grid;
            min-width: 0;
            gap: 6px;
        }

        .fsd-field label,
        .fsd-label {
            color: var(--fsd-muted);
            font-size: 11px;
            font-weight: 750;
            letter-spacing: .02em;
        }

        .fsd-control {
            box-sizing: border-box;
            width: 100%;
            min-height: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            background: var(--fsd-bg);
            padding: 9px 11px;
            color: var(--fsd-text);
            font-size: 13px;
            outline: none;
        }

        .fsd-control:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, .14);
        }

        .dark .fsd-control {
            border-color: #475569;
        }

        .fsd-filter-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--fsd-border);
        }

        .fsd-filter-note {
            margin: 0;
            color: var(--fsd-muted);
            font-size: 11px;
        }

        .fsd-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .fsd-kpi {
            display: block;
            min-height: 118px;
            box-sizing: border-box;
            border: 1px solid var(--fsd-border);
            border-radius: 13px;
            background: var(--fsd-bg);
            padding: 15px;
            color: var(--fsd-text);
            text-decoration: none;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .05);
            transition: transform .15s ease, box-shadow .15s ease;
        }

        a.fsd-kpi:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(15, 23, 42, .08);
        }

        .fsd-kpi--primary {
            border-color: #0f172a;
            background: #0f172a;
            color: #ffffff;
        }

        .fsd-kpi--danger { border-color: #fecaca; background: #fff7f7; }
        .fsd-kpi--warning { border-color: #fde68a; background: #fffbeb; }
        .fsd-kpi--blue { border-color: #bfdbfe; background: #eff6ff; }
        .fsd-kpi--violet { border-color: #ddd6fe; background: #f5f3ff; }
        .fsd-kpi--success { border-color: #a7f3d0; background: #ecfdf5; }
        .fsd-kpi--cyan { border-color: #a5f3fc; background: #ecfeff; }

        .dark .fsd-kpi--danger,
        .dark .fsd-kpi--warning,
        .dark .fsd-kpi--blue,
        .dark .fsd-kpi--violet,
        .dark .fsd-kpi--success,
        .dark .fsd-kpi--cyan {
            background: #111827;
        }

        .fsd-kpi-label {
            display: block;
            margin: 0;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .07em;
            text-transform: uppercase;
            opacity: .8;
        }

        .fsd-kpi-value {
            display: block;
            margin-top: 15px;
            font-size: 24px;
            font-weight: 800;
            line-height: 1.1;
        }

        .fsd-kpi-meta {
            display: block;
            margin-top: 5px;
            font-size: 11px;
            opacity: .75;
        }

        .fsd-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 15px 18px;
            border-bottom: 1px solid var(--fsd-border);
        }

        .fsd-section-head h2 {
            margin: 0;
            color: var(--fsd-text);
            font-size: 15px;
            font-weight: 750;
        }

        .fsd-section-head p {
            margin: 3px 0 0;
            color: var(--fsd-muted);
            font-size: 11px;
        }

        .fsd-aging-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
            padding: 14px;
        }

        .fsd-aging-item {
            min-width: 0;
            border: 1px solid var(--fsd-border);
            border-radius: 10px;
            padding: 12px;
        }

        .fsd-aging-item p { margin: 0; }
        .fsd-aging-value { margin-top: 8px !important; font-size: 16px; font-weight: 800; }
        .fsd-aging-count { margin-top: 3px !important; color: var(--fsd-muted); font-size: 11px; }

        .fsd-table-wrap {
            overflow-x: auto;
        }

        .fsd-table {
            width: 100%;
            border-collapse: collapse;
            color: var(--fsd-text);
            font-size: 12px;
        }

        .fsd-table th {
            padding: 10px 12px;
            background: var(--fsd-soft);
            color: var(--fsd-muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .04em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .fsd-table td {
            padding: 12px;
            border-top: 1px solid var(--fsd-border);
            vertical-align: middle;
        }

        .fsd-table tbody tr:hover {
            background: var(--fsd-soft);
        }

        .fsd-right { text-align: right !important; }
        .fsd-strong { font-weight: 750; }
        .fsd-link { color: #2563eb; font-weight: 750; text-decoration: none; }
        .fsd-link:hover { text-decoration: underline; }
        .fsd-small { margin: 3px 0 0; color: var(--fsd-muted); font-size: 10px; }
        .fsd-danger-text { color: #dc2626; }
        .fsd-warning-text { color: #d97706; }
        .fsd-success-text { color: #059669; }

        .fsd-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 9px;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
        }

        .fsd-badge--stage { background: #ede9fe; color: #6d28d9; }
        .fsd-badge--unpaid { background: #fee2e2; color: #b91c1c; }
        .fsd-badge--partial { background: #fef3c7; color: #b45309; }

        .fsd-lower-grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
            gap: 16px;
        }

        .fsd-ready-head {
            background: #ecfdf5;
        }

        .dark .fsd-ready-head {
            background: rgba(6, 78, 59, .25);
        }

        .fsd-portfolio-list > div {
            padding: 13px 16px;
            border-top: 1px solid var(--fsd-border);
        }

        .fsd-portfolio-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .fsd-portfolio-row p { margin: 0; }

        .fsd-empty {
            padding: 36px 18px !important;
            color: var(--fsd-muted);
            text-align: center;
        }

        .fsd-pagination {
            padding: 13px 16px;
            border-top: 1px solid var(--fsd-border);
        }

        @media (max-width: 1180px) {
            .fsd-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .fsd-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .fsd-aging-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .fsd-lower-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 680px) {
            .fsd-header,
            .fsd-filter-footer,
            .fsd-section-head {
                align-items: stretch;
                flex-direction: column;
            }

            .fsd-actions { justify-content: flex-start; }
            .fsd-filter-grid,
            .fsd-kpi-grid,
            .fsd-aging-grid { grid-template-columns: 1fr; }
            .fsd-actions > a,
            .fsd-actions > button { justify-content: center; width: 100%; }
        }
    </style>

    <div class="fsd-v11">
        <section class="fsd-panel fsd-header">
            <div>
                <p class="fsd-eyebrow">Collection Control</p>
                <h1 class="fsd-title">Finance & Sales Dashboard</h1>
                <p class="fsd-subtitle">Prioritas penagihan, DP, pelunasan, jatuh tempo, dan tanggung jawab Sales dalam satu halaman.</p>
            </div>

            <div class="fsd-actions">
                <a href="{{ route('admin.invoices.index') }}" class="secondary-button">Daftar Invoice</a>
                <a href="{{ route('admin.invoices.financial-report') }}" class="secondary-button">Financial Report</a>
                <a href="{{ route('admin.invoices.billing.create') }}" class="primary-button">+ Generate Invoice</a>
            </div>
        </section>

        <form method="GET" action="{{ route('admin.finance-sales-dashboard.index') }}" class="fsd-panel fsd-filters">
            <div class="fsd-filter-grid">
                <div class="fsd-field">
                    <label for="fsd-search">Cari</label>
                    <input id="fsd-search" type="text" name="search" value="{{ $filters['search'] }}" placeholder="Invoice, project, customer, atau sales..." class="fsd-control">
                </div>

                <div class="fsd-field">
                    <label for="fsd-sales">Sales Owner</label>
                    <select id="fsd-sales" name="sales_user_id" class="fsd-control">
                        <option value="0">Semua Sales</option>
                        @foreach ($salesUsers as $salesUser)
                            <option value="{{ $salesUser->id }}" @selected((int) $filters['sales_user_id'] === (int) $salesUser->id)>{{ $salesUser->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="fsd-field">
                    <label for="fsd-unit">Business Unit</label>
                    <select id="fsd-unit" name="business_unit" class="fsd-control">
                        <option value="">Semua Business Unit</option>
                        @foreach ($businessUnits as $unit)
                            <option value="{{ $unit['value'] }}" @selected($filters['business_unit'] === $unit['value'])>{{ $unit['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="fsd-field">
                    <label for="fsd-focus">Fokus Collection</label>
                    <select id="fsd-focus" name="focus" class="fsd-control">
                        @foreach ($focusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['focus'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="fsd-filter-footer">
                <p class="fsd-filter-note">Hanya Invoice berstatus event CONFIRM yang masuk dalam collection. Business Unit berasal dari master dan tetap mencakup nilai historis Invoice.</p>
                <div class="fsd-actions">
                    <a href="{{ route('admin.finance-sales-dashboard.index') }}" class="secondary-button">Reset</a>
                    <button type="submit" class="primary-button">Terapkan Filter</button>
                    <a href="{{ route('admin.finance-sales-dashboard.export', $filters) }}" class="secondary-button">Export CSV</a>
                </div>
            </div>
        </form>

        <section class="fsd-kpi-grid">
            <a href="{{ route('admin.finance-sales-dashboard.index', array_merge($filters, ['focus' => 'all'])) }}" class="fsd-kpi fsd-kpi--primary">
                <span class="fsd-kpi-label">Total Outstanding</span>
                <span class="fsd-kpi-value">{{ $rupiah($metrics['outstanding_total']) }}</span>
                <span class="fsd-kpi-meta">{{ $metrics['open_count'] }} invoice perlu tindak lanjut</span>
            </a>

            <a href="{{ route('admin.finance-sales-dashboard.index', array_merge($filters, ['focus' => 'unpaid'])) }}" class="fsd-kpi fsd-kpi--danger">
                <span class="fsd-kpi-label fsd-danger-text">Belum Dibayar</span>
                <span class="fsd-kpi-value">{{ $metrics['unpaid_count'] }}</span>
                <span class="fsd-kpi-meta">{{ $rupiah($metrics['unpaid_total']) }}</span>
            </a>

            <a href="{{ route('admin.finance-sales-dashboard.index', array_merge($filters, ['focus' => 'partial'])) }}" class="fsd-kpi fsd-kpi--warning">
                <span class="fsd-kpi-label fsd-warning-text">Partial</span>
                <span class="fsd-kpi-value">{{ $metrics['partial_count'] }}</span>
                <span class="fsd-kpi-meta">Sisa {{ $rupiah($metrics['partial_total']) }}</span>
            </a>

            <a href="{{ route('admin.finance-sales-dashboard.index', array_merge($filters, ['focus' => 'overdue'])) }}" class="fsd-kpi fsd-kpi--danger">
                <span class="fsd-kpi-label fsd-danger-text">Terlambat</span>
                <span class="fsd-kpi-value">{{ $metrics['overdue_count'] }}</span>
                <span class="fsd-kpi-meta">{{ $rupiah($metrics['overdue_total']) }}</span>
            </a>

            <a href="{{ route('admin.finance-sales-dashboard.index', array_merge($filters, ['focus' => 'due_soon'])) }}" class="fsd-kpi fsd-kpi--blue">
                <span class="fsd-kpi-label">Jatuh Tempo 7 Hari</span>
                <span class="fsd-kpi-value">{{ $metrics['due_soon_count'] }}</span>
                <span class="fsd-kpi-meta">{{ $rupiah($metrics['due_soon_total']) }}</span>
            </a>

            <a href="{{ route('admin.finance-sales-dashboard.index', array_merge($filters, ['focus' => 'down_payment'])) }}" class="fsd-kpi fsd-kpi--violet">
                <span class="fsd-kpi-label">DP Belum Lunas</span>
                <span class="fsd-kpi-value">{{ $metrics['dp_waiting_count'] }}</span>
                <span class="fsd-kpi-meta">{{ $rupiah($metrics['dp_waiting_total']) }}</span>
            </a>

            <div class="fsd-kpi fsd-kpi--success">
                <span class="fsd-kpi-label fsd-success-text">Siap Pelunasan</span>
                <span class="fsd-kpi-value">{{ $metrics['ready_settlement_count'] }}</span>
                <span class="fsd-kpi-meta">Potensi {{ $rupiah($metrics['ready_settlement_total']) }}</span>
            </div>

            <a href="{{ route('admin.finance-sales-dashboard.index', array_merge($filters, ['focus' => 'settlement'])) }}" class="fsd-kpi fsd-kpi--cyan">
                <span class="fsd-kpi-label">Pelunasan Outstanding</span>
                <span class="fsd-kpi-value">{{ $metrics['settlement_outstanding_count'] }}</span>
                <span class="fsd-kpi-meta">{{ $rupiah($metrics['settlement_outstanding_total']) }}</span>
            </a>
        </section>

        <section class="fsd-panel">
            <div class="fsd-section-head">
                <div>
                    <h2>Aging Outstanding</h2>
                    <p>Umur piutang berdasarkan tanggal jatuh tempo.</p>
                </div>
                <span class="fsd-muted fsd-label">REAL-TIME DARI PAYMENT HISTORY</span>
            </div>
            <div class="fsd-aging-grid">
                @foreach ($aging as $bucket)
                    <div class="fsd-aging-item">
                        <p class="fsd-muted fsd-label">{{ $bucket['label'] }}</p>
                        <p class="fsd-aging-value">{{ $rupiah($bucket['amount']) }}</p>
                        <p class="fsd-aging-count">{{ $bucket['count'] }} invoice</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="fsd-panel">
            <div class="fsd-section-head">
                <div>
                    <h2>Priority Collection</h2>
                    <p>{{ $focusLabels[$filters['focus']] ?? 'Semua Tagihan Aktif' }} · urut dari due date terdekat.</p>
                </div>
                <span class="fsd-muted fsd-label">{{ $actionInvoices->total() }} INVOICE</span>
            </div>

            <div class="fsd-table-wrap">
                <table class="fsd-table" style="min-width: 1120px">
                    <thead>
                        <tr>
                            <th>Invoice / Project</th>
                            <th>Customer</th>
                            <th>Sales</th>
                            <th>Tahap</th>
                            <th class="fsd-right">Nilai</th>
                            <th class="fsd-right">Terbayar</th>
                            <th class="fsd-right">Outstanding</th>
                            <th>Jatuh Tempo</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($actionInvoices as $invoice)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.invoices.show', $invoice['id']) }}" class="fsd-link">{{ $invoice['invoice_number'] }}</a>
                                    <p class="fsd-small">{{ $invoice['project_code'] }} · {{ $invoice['subject'] }}</p>
                                </td>
                                <td class="fsd-strong">{{ $invoice['customer'] }}</td>
                                <td>{{ $invoice['salesperson'] }}</td>
                                <td>
                                    <span class="fsd-badge fsd-badge--stage">{{ $invoice['billing_label'] }}</span>
                                    <span class="fsd-badge {{ $invoice['payment_status'] === 'partial' ? 'fsd-badge--partial' : 'fsd-badge--unpaid' }}">{{ $invoice['payment_status_label'] }}</span>
                                </td>
                                <td class="fsd-right">{{ $rupiah($invoice['invoice_value']) }}</td>
                                <td class="fsd-right fsd-success-text">{{ $rupiah($invoice['paid']) }}</td>
                                <td class="fsd-right fsd-strong">{{ $rupiah($invoice['outstanding']) }}</td>
                                <td>
                                    <span class="fsd-strong {{ $invoice['is_overdue'] ? 'fsd-danger-text' : ($invoice['is_due_soon'] ? 'fsd-warning-text' : '') }}">{{ $invoice['due_at_label'] }}</span>
                                    @if ($invoice['is_overdue'])
                                        <p class="fsd-small fsd-danger-text">Terlambat {{ $invoice['days_overdue'] }} hari</p>
                                    @elseif ($invoice['due_in_days'] !== null)
                                        <p class="fsd-small">{{ $invoice['due_in_days'] === 0 ? 'Jatuh tempo hari ini' : $invoice['due_in_days'].' hari lagi' }}</p>
                                    @endif
                                </td>
                                <td><a href="{{ route('admin.invoices.show', $invoice['id']) }}" class="secondary-button">Buka Invoice</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="fsd-empty">Tidak ada invoice yang cocok dengan filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($actionInvoices->hasPages())
                <div class="fsd-pagination">{{ $actionInvoices->links() }}</div>
            @endif
        </section>

        <div class="fsd-lower-grid">
            <section class="fsd-panel">
                <div class="fsd-section-head fsd-ready-head">
                    <div>
                        <h2>DP Lunas — Siap Generate Pelunasan</h2>
                        <p>DP telah lunas dari transaksi Payment dan belum mempunyai Invoice Pelunasan aktif.</p>
                    </div>
                </div>
                <div class="fsd-table-wrap">
                    <table class="fsd-table" style="min-width: 760px">
                        <thead>
                            <tr>
                                <th>DP / Quote</th>
                                <th>Customer & Sales</th>
                                <th class="fsd-right">DP Dibayar</th>
                                <th class="fsd-right">Nilai Pelunasan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($readySettlements as $settlement)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.invoices.show', $settlement['invoice_id']) }}" class="fsd-link">{{ $settlement['invoice_number'] }}</a>
                                        <p class="fsd-small">{{ $settlement['quote_number'] }} · {{ $settlement['project_code'] }}</p>
                                    </td>
                                    <td>
                                        <span class="fsd-strong">{{ $settlement['customer'] }}</span>
                                        <p class="fsd-small">Sales: {{ $settlement['salesperson'] }}</p>
                                    </td>
                                    <td class="fsd-right fsd-success-text">{{ $rupiah($settlement['dp_value']) }}</td>
                                    <td class="fsd-right fsd-strong">{{ $rupiah($settlement['remaining']) }}</td>
                                    <td><a href="{{ route('admin.invoices.billing.create', ['quote_id' => $settlement['quote_id']]) }}" class="primary-button">Generate Pelunasan</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="fsd-empty">Belum ada DP lunas yang siap dibuatkan pelunasan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="fsd-panel">
                <div class="fsd-section-head">
                    <div>
                        <h2>Portfolio Sales</h2>
                        <p>Outstanding per Sales Owner.</p>
                    </div>
                </div>
                <div class="fsd-portfolio-list">
                    @forelse ($salesPortfolio as $portfolio)
                        <div>
                            <div class="fsd-portfolio-row">
                                <div>
                                    <p class="fsd-strong">{{ $portfolio['salesperson'] }}</p>
                                    <p class="fsd-small">{{ $portfolio['invoice_count'] }} invoice · {{ $portfolio['overdue_count'] }} terlambat</p>
                                </div>
                                <p class="fsd-strong fsd-right">{{ $rupiah($portfolio['outstanding']) }}</p>
                            </div>
                            <p class="fsd-small">{{ $portfolio['unpaid_count'] }} unpaid · {{ $portfolio['partial_count'] }} partial</p>
                        </div>
                    @empty
                        <div class="fsd-empty">Belum ada outstanding per Sales.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-admin::layouts>
