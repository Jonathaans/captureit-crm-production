@if ($showProformaRows ?? true)
    <details class="fw-card fw-details">
        <summary>
            <span><span class="fw-title">Proforma Belum Menjadi Invoice</span><span class="fw-muted block">{{ $proformaFollowUp['count'] }} tertunda · Rp {{ number_format($proformaFollowUp['amount'], 0, ',', '.') }} · {{ $proformaFollowUp['overdue_count'] }} lewat jatuh tempo</span></span>
            <span class="fw-badge fw-badge-issued">Tindak lanjut</span>
        </summary>
        <div class="fw-section-note fw-between">
            <p>Seluruh proforma aktif lintas periode, mengikuti filter unit bisnis dan produk.<br>Nilainya tidak masuk total invoice, uang masuk, piutang, atau margin.</p>
            <a class="fw-link" href="{{ route('admin.proformas.index', ['status' => 'issued']) }}">Lihat seluruh proforma tertunda &rarr;</a>
        </div>
    <div class="fw-scroll">
        <table class="fw-table" style="min-width:900px">
            <thead><tr class="border-b border-amber-200 dark:border-gray-700">
                <th class="p-3">Proforma / Tanggal</th><th class="p-3">Proyek / Company</th><th class="p-3">Jenis / Nilai</th><th class="p-3">Umur / Jatuh tempo</th><th class="p-3">Dibuat oleh</th><th class="p-3">Tindak lanjut</th>
            </tr></thead>
            <tbody>
                @forelse ($proformaFollowUp['rows'] as $pendingProforma)
                    @php($pendingSnapshot = $pendingProforma->quote_snapshot['attributes'] ?? [])
                    <tr class="border-b border-amber-100 dark:border-gray-800">
                        <td class="p-3">{{ $pendingProforma->proforma_number }}<br><span class="text-xs text-gray-500">{{ $pendingProforma->issued_at?->format('d/m/Y') }}</span></td>
                        <td class="p-3">{{ $pendingSnapshot['subject'] ?? '-' }}<br>{{ $pendingProforma->billToIdentity()['company_name'] ?? '-' }}</td>
                        <td class="p-3 whitespace-nowrap">{{ $pendingProforma->billingLabel() }}<br>Rp {{ number_format((float) $pendingProforma->amount, 0, ',', '.') }}</td>
                        <td class="p-3">{{ max(0, (int) $pendingProforma->issued_at?->diffInDays(today(), false)) }} hari<br>{{ $pendingProforma->due_at?->format('d/m/Y') }}</td>
                        <td class="p-3">{{ $pendingProforma->creator?->name ?? '-' }}</td>
                        <td class="p-3">
                            <span class="block text-xs text-gray-500">Menunggu pemindahan oleh Admin Sales</span>
                            @if (bouncer()->hasPermission('invoices.view'))
                                <a class="fw-link" href="{{ route('admin.proformas.show', $pendingProforma->id) }}">Periksa proforma</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-4 text-center text-gray-500">Tidak ada proforma yang menunggu menjadi invoice untuk filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($proformaFollowUp['count'] > 10)
        <p class="mt-3 text-xs text-gray-500">Menampilkan 10 proforma tertua. Buka daftar tertunda untuk melihat seluruh dokumen.</p>
    @endif
    </details>
@else
    <section class="fw-metrics fw-metrics-three" aria-label="Ringkasan proforma aktif">
        <div class="fw-metric"><p class="fw-metric-label">Menunggu menjadi invoice</p><p class="fw-value">{{ $proformaFollowUp['count'] }} <span class="fw-muted">dokumen</span></p><small>Seluruh proforma aktif lintas periode</small></div>
        <div class="fw-metric"><p class="fw-metric-label">Nilai proforma tertunda</p><p class="fw-value">Rp {{ number_format($proformaFollowUp['amount'], 0, ',', '.') }}</p><small>Belum masuk angka laporan keuangan</small></div>
        <div class="fw-metric fw-highlight"><p class="fw-metric-label">Lewat jatuh tempo</p><p class="fw-value">{{ $proformaFollowUp['overdue_count'] }} <span class="fw-muted">dokumen</span></p><small>Perlu konfirmasi dan tindak lanjut</small></div>
    </section>
@endif
