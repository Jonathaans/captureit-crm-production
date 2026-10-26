@php
    $auditFilters = $auditDashboard['filters'];
    $auditResult = $auditDashboard['result'];
    $auditQuery = array_intersect_key($auditFilters, array_flip(['year', 'month', 'business_unit', 'event_status', 'product', 'invoice']));
    $auditHistory = $auditDashboard['history'];
@endphp

<section id="financial-audit" class="financial-audit rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <style>
        .financial-audit .audit-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,210px),1fr)); gap:12px; }
        .financial-audit label { display:block; font-size:13px; font-weight:600; }
        .financial-audit input:not([type=hidden]), .financial-audit select { display:block; width:100%; min-width:0; padding:9px 11px; margin-top:6px; border:1px solid #cbd5e1; border-radius:8px; background:transparent; font:inherit; }
        .financial-audit table { width:100%; border-collapse:collapse; font-size:13px; }
        .financial-audit th, .financial-audit td { padding:12px; text-align:left; vertical-align:top; border-bottom:1px solid #e2e8f0; }
        .financial-audit th { font-weight:600; background:rgba(148,163,184,.1); }
        .financial-audit .audit-table { overflow-x:auto; margin-top:12px; }
        .financial-audit .audit-pass { color:#166534; background:#f0fdf4; border:1px solid #bbf7d0; }
        .financial-audit .audit-fail { color:#991b1b; background:#fef2f2; border:1px solid #fecaca; }
        .financial-audit .audit-note { color:#92400e; background:#fffbeb; border:1px solid #fde68a; }
        .financial-audit .audit-badge { display:inline-block; padding:3px 8px; border-radius:20px; font-size:11px; font-weight:700; white-space:nowrap; }
        .financial-audit .audit-value { font-variant-numeric:tabular-nums; white-space:nowrap; }
        .financial-audit .audit-actions { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:14px; }
        .financial-audit .audit-message { padding:14px; border-radius:10px; margin-top:16px; }
        .financial-audit .audit-heading { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; }
        .financial-audit .audit-failed-row { background:rgba(239,68,68,.08); }
        .financial-audit .audit-history td { min-width:130px; }
        .financial-audit .audit-history .audit-reason { min-width:240px; max-width:400px; white-space:pre-wrap; overflow-wrap:anywhere; }
    </style>

    <div class="audit-heading">
        <div>
            <div class="text-xs font-bold uppercase text-gray-500">Kontrol keuangan</div>
            <h2 class="mt-1 text-xl font-bold">Audit Financial Report</h2>
            <p class="mt-1 text-sm text-gray-500">Bandingkan laporan dengan transaksi sumber dan periksa invoice yang dibatalkan.</p>
        </div>
        <span class="audit-badge audit-pass">Hanya baca</span>
    </div>

    <form method="GET" action="{{ route('admin.operations-dashboard.index') }}#financial-audit" class="mt-4">
        <input type="hidden" name="run_financial_audit" value="1">
        <div class="audit-grid">
            <label for="audit-year">Tahun terbit invoice
                <input id="audit-year" name="year" type="number" min="2000" max="2100" value="{{ $auditFilters['year'] }}" required>
            </label>
            <label for="audit-month">Bulan
                <select id="audit-month" name="month">
                    <option value="all" @selected($auditFilters['month'] === 'all')>Semua bulan</option>
                    @foreach ([1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $number => $name)
                        <option value="{{ $number }}" @selected((string) $auditFilters['month'] === (string) $number)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label for="audit-unit">Unit usaha
                <select id="audit-unit" name="business_unit">
                    <option value="">Semua unit</option>
                    @foreach ($auditDashboard['businessUnitOptions'] as $value => $label)
                        <option value="{{ $value }}" @selected($auditFilters['business_unit'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label for="audit-event">Status event
                <select id="audit-event" name="event_status">
                    <option value="">Semua status</option>
                    @foreach (['prospect' => 'Prospect', 'confirm' => 'Confirm', 'cancel' => 'Cancel'] as $value => $label)
                        <option value="{{ $value }}" @selected($auditFilters['event_status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label for="audit-product">Produk
                <input id="audit-product" name="product" list="audit-products" maxlength="255" placeholder="Semua produk" value="{{ $auditFilters['product'] }}">
                <datalist id="audit-products">
                    @foreach ($auditDashboard['productOptions'] as $productName)
                        <option value="{{ $productName }}"></option>
                    @endforeach
                </datalist>
            </label>
            <label for="audit-invoice">Nomor invoice (opsional)
                <input id="audit-invoice" name="invoice" maxlength="255" placeholder="Contoh: INV 2610-0078" value="{{ $auditFilters['invoice'] }}">
            </label>
        </div>
        <div class="audit-actions">
            <button type="submit" class="primary-button">Jalankan Audit</button>
            <a href="{{ route('admin.operations-dashboard.index') }}#financial-audit" class="secondary-button">Reset</a>
            @if (bouncer()->hasPermission('invoices.financial-report'))
                <a href="{{ route('admin.invoices.financial-report', array_diff_key($auditQuery, ['invoice' => true])) }}" class="secondary-button">Buka Financial Report</a>
            @endif
        </div>
    </form>

    <p class="mt-3 text-xs text-gray-500">Tagihan mengikuti tanggal terbit; kas mengikuti tanggal pembayaran/biaya. Piutang menunjukkan saldo saat ini. Produk harus sama persis dengan nama pada invoice.</p>

    @if ($auditDashboard['error'])
        <div role="alert" class="audit-message audit-fail">{{ $auditDashboard['error'] }}</div>
    @elseif (! $auditResult)
        <div class="audit-message rounded-lg border text-sm dark:border-gray-800">Audit belum dijalankan. Pilih filter lalu klik Jalankan Audit untuk melihat seluruh pemeriksaan terbaru.</div>
    @endif

    @if ($auditResult)
        <div role="status" class="audit-message {{ $auditResult['ok'] ? 'audit-pass' : 'audit-fail' }}">
            <div class="audit-heading">
                <div>
                    <p class="font-bold">{{ $auditResult['ok'] ? 'Semua pemeriksaan cocok' : 'Ada selisih yang perlu diperiksa' }}</p>
                    <p class="mt-1 text-sm">{{ $auditDashboard['passed'] }} cocok · {{ $auditDashboard['failed'] }} selisih · {{ count($auditResult['checks']) }} pemeriksaan</p>
                    <p class="mt-1 text-xs">Diperiksa {{ $auditResult['checked_at'] }}. Hasil ini berlaku untuk filter di atas.</p>
                </div>
                <a href="{{ route('admin.operations-dashboard.financial-audit.export', $auditQuery) }}" class="secondary-button">Unduh Audit JSON Terbaru</a>
            </div>
        </div>

        <div class="audit-grid mt-4">
            @foreach (['invoiced' => 'Total tagihan Confirm', 'outstanding' => 'Piutang saat ini', 'received' => 'Penerimaan periode', 'expense' => 'Pengeluaran periode', 'cash_surplus' => 'Surplus kas'] as $key => $label)
                @php($cardCheck = $auditResult['checks']['summary.'.$key])
                <div class="rounded-lg border p-4 dark:border-gray-800">
                    <p class="text-xs text-gray-500">{{ $label }}</p>
                    <p class="mt-2 font-bold">Rp {{ number_format((float) $cardCheck['actual'], 4, ',', '.') }}</p>
                    <span class="audit-badge mt-2 {{ $cardCheck['ok'] ? 'audit-pass' : 'audit-fail' }}">{{ $cardCheck['ok'] ? 'Cocok' : 'Selisih' }}</span>
                </div>
            @endforeach
        </div>

        @if ($target = $auditResult['invoice'])
            <div class="mt-4 rounded-lg border p-4 dark:border-gray-800">
                <h3 class="font-bold">Invoice yang diperiksa: {{ $target['invoice_number'] }}</h3>
                <dl class="audit-grid mt-3 text-sm">
                    <div><dt class="text-gray-500">Status invoice / event</dt><dd>{{ strtoupper($target['status'] ?? '-') }} / {{ ucfirst($target['event_status'] ?? '-') }}</dd></div>
                    <div><dt class="text-gray-500">Nominal asli</dt><dd>Rp {{ number_format($target['original_value'], 4, ',', '.') }}</dd></div>
                    <div><dt class="text-gray-500">Saldo sekarang</dt><dd>Rp {{ number_format($target['balance_due'], 4, ',', '.') }}</dd></div>
                    <div><dt class="text-gray-500">Masuk rincian pada filter ini</dt><dd>{{ $target['included_in_invoice_report'] ? 'Ya' : 'Tidak' }}</dd></div>
                    <div><dt class="text-gray-500">ID / jenis audit pembatalan</dt><dd>{{ $target['cancellation_audit_id'] ?? '—' }} / {{ $target['audit_kind'] ?? '—' }}</dd></div>
                </dl>
                @if ($target['cancellation_audit_id'])
                    @if ($target['confirmed_value_before_cancellation'] !== null)
                        <p class="mt-3 text-sm">Snapshot sebelum pembatalan: tagihan Confirm Rp {{ number_format($target['confirmed_value_before_cancellation'], 4, ',', '.') }}; piutang Rp {{ number_format($target['receivable_before_cancellation'], 4, ',', '.') }}.</p>
                    @else
                        <p class="audit-message audit-note text-sm">Snapshot sebelum pembatalan tidak mencatat event Confirm. Nominal asli tidak dapat dianggap sebagai pengurangan baru dari total tagihan Confirm.</p>
                    @endif
                @endif
            </div>
        @endif

        <div class="mt-4 rounded-lg border p-4 dark:border-gray-800">
            <h3 class="font-bold">Invoice VOID yang dikeluarkan dari laporan ({{ count($auditResult['cancelled_invoices_excluded']) }})</h3>
            <div class="audit-actions">
                @forelse ($auditResult['cancelled_invoices_excluded'] as $invoiceNumber)
                    <a class="secondary-button" href="{{ route('admin.operations-dashboard.index', array_replace($auditQuery, ['invoice' => $invoiceNumber, 'run_financial_audit' => 1])) }}#financial-audit">{{ $invoiceNumber }}</a>
                @empty
                    <p class="text-sm text-gray-500">Tidak ada invoice VOID pada filter ini.</p>
                @endforelse
            </div>
        </div>

        <p class="mt-4 text-xs text-gray-500">Selisih = nilai laporan − transaksi sumber. Nominal ditampilkan sampai 4 desimal agar perbedaan kecil terlihat. Rekap tahunan diperiksa saat Semua bulan dipilih.</p>
        @foreach ($auditDashboard['groups'] as $group)
            <details open class="mt-3 rounded-lg border dark:border-gray-800">
                <summary class="cursor-pointer p-4 font-bold">{{ $group['title'] }} ({{ count($group['checks']) }})</summary>
                <div class="audit-table" style="margin-top:0;">
                    <table>
                        <thead><tr><th scope="col">Pemeriksaan</th><th scope="col">Transaksi sumber</th><th scope="col">Nilai laporan</th><th scope="col">Selisih</th><th scope="col">Hasil</th></tr></thead>
                        <tbody>
                            @foreach ($group['checks'] as $checkKey => $check)
                                <tr @class(['audit-failed-row' => ! $check['ok']])>
                                    <th scope="row" style="min-width:230px;background:transparent;">{{ $check['label'] }}<span class="mt-1 block text-xs font-normal text-gray-500">{{ $checkKey }}</span></th>
                                    @foreach (['expected', 'actual'] as $side)
                                        <td class="audit-value">@include('admin::operations-dashboard.financial-audit-value', ['value' => $check[$side], 'money' => $check['money']])</td>
                                    @endforeach
                                    <td class="audit-value">@include('admin::operations-dashboard.financial-audit-value', ['value' => $check['difference'], 'money' => $check['money']])</td>
                                    <td><span class="audit-badge {{ $check['ok'] ? 'audit-pass' : 'audit-fail' }}">{{ $check['ok'] ? 'Cocok' : 'Selisih' }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endforeach
        <p class="mt-4 text-sm text-gray-500">Hasil cocok menunjukkan perhitungan laporan sesuai transaksi yang tercatat. Invoice duplikat dengan nomor berbeda tetap perlu ditinjau berdasarkan quote/proyek dan jenis tagihannya.</p>
    @endif

    <div id="financial-audit-history" class="mt-6 border-t pt-5 dark:border-gray-800">
        <h3 class="text-lg font-bold">Riwayat Pembatalan &amp; Void Invoice</h3>
        <p class="mt-1 text-sm text-gray-500">Seluruh tanggal tindakan, terpisah dari filter periode laporan di atas. Menampilkan {{ $auditHistory->total() }} catatan; nominal dan piutang sebelum berasal dari snapshot saat tindakan.</p>
        <form method="GET" action="{{ route('admin.operations-dashboard.index') }}#financial-audit-history" class="audit-actions">
            @foreach ($auditQuery as $key => $value)
                @if ($value !== null)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
            @endforeach
            @if ($auditResult)<input type="hidden" name="run_financial_audit" value="1">@endif
            <label for="audit-history-search" style="flex:1;min-width:200px;max-width:480px;">Cari nomor invoice, alasan, atau pelaku
                <input id="audit-history-search" name="history_search" maxlength="255" value="{{ $auditFilters['history_search'] }}" placeholder="Cari seluruh riwayat">
            </label>
            <button type="submit" class="secondary-button">Cari Riwayat</button>
        </form>
        <div class="audit-table">
            <table class="audit-history">
                <thead><tr><th scope="col">ID / waktu tindakan ({{ config('app.timezone') }})</th><th scope="col">Invoice / jenis</th><th scope="col">Pelaku</th><th scope="col">Alasan</th><th scope="col">Nominal asli / status sebelum</th><th scope="col">Piutang sebelum → sekarang</th><th scope="col">Periksa</th></tr></thead>
                <tbody>
                    @forelse ($auditHistory as $entry)
                        <tr>
                            <td>#{{ $entry->id }}<div class="mt-1 text-xs">{{ $entry->created_at }}</div></td>
                            <td><strong>{{ $entry->invoice_number }}</strong><div class="mt-1 text-xs">{{ $entry->kind === 'invoice_cancel' ? 'Batalkan Invoice' : 'Void invoice & pembayaran salah input' }}</div><div class="mt-1 text-xs">Sekarang: {{ strtoupper($entry->current_status ?? 'dokumen tidak tersedia') }}</div></td>
                            <td>{{ $entry->actor_name ?? 'Pengguna tidak tersedia' }}<div class="mt-1 text-xs text-gray-500">ID {{ $entry->actor_id }}</div></td>
                            <td class="audit-reason">{{ $entry->reason }}</td>
                            <td>
                                @if ($entry->before)
                                    <div class="audit-value">@include('admin::operations-dashboard.financial-audit-value', ['value' => $entry->before['grand_total'] ?? null, 'money' => true])</div>
                                    <div class="mt-1 text-xs">{{ strtoupper($entry->before['status'] ?? '-') }} / {{ ucfirst($entry->before['event_status'] ?? '-') }}</div>
                                @else
                                    <span class="audit-badge audit-fail">Snapshot tidak terbaca</span>
                                @endif
                                @if (isset($entry->outcome['payment_total_voided']))
                                    <div class="mt-2 text-xs">Pembayaran salah input di-void: Rp {{ number_format($entry->outcome['payment_total_voided'], 4, ',', '.') }}</div>
                                @endif
                            </td>
                            <td class="audit-value">
                                @include('admin::operations-dashboard.financial-audit-value', ['value' => $entry->before['balance_due'] ?? null, 'money' => true])<br>→ @include('admin::operations-dashboard.financial-audit-value', ['value' => $entry->current_balance_due, 'money' => true])
                            </td>
                            <td><a class="secondary-button" href="{{ route('admin.operations-dashboard.index', array_replace($auditQuery, ['invoice' => $entry->invoice_number, 'run_financial_audit' => 1])) }}#financial-audit">Audit Invoice</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ $auditFilters['history_search'] ? 'Tidak ada riwayat yang cocok dengan pencarian.' : 'Belum ada riwayat pembatalan atau void invoice.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($auditHistory->hasPages())
            <nav aria-label="Halaman riwayat pembatalan invoice" class="audit-actions">
                @if ($auditHistory->previousPageUrl())<a href="{{ $auditHistory->previousPageUrl() }}" class="secondary-button">← Sebelumnya</a>@endif
                <span class="text-sm">Halaman {{ $auditHistory->currentPage() }} dari {{ $auditHistory->lastPage() }}</span>
                @if ($auditHistory->nextPageUrl())<a href="{{ $auditHistory->nextPageUrl() }}" class="secondary-button">Berikutnya →</a>@endif
            </nav>
        @endif
    </div>
</section>
