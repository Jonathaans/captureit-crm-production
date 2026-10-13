<div class="rounded-xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-gray-900">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Proforma Belum Menjadi Invoice</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Pemantauan tindak lanjut saja. Nilai ini tidak masuk total invoice, uang masuk, piutang, atau margin.
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Seluruh proforma aktif lintas periode{{ ($showProformaRows ?? true) ? '; mengikuti filter unit bisnis dan produk' : '' }}. Jatuh tempo di sini adalah jatuh tempo proforma.
            </p>
        </div>
        <a class="secondary-button" href="{{ route('admin.proformas.index', ['status' => 'issued']) }}">Lihat seluruh proforma tertunda</a>
    </div>
    <div class="my-4 flex flex-wrap gap-x-8 gap-y-2 text-sm text-gray-800 dark:text-white">
        <span><strong>{{ $proformaFollowUp['count'] }}</strong> proforma tertunda</span>
        <span>Nilai proforma: <strong>Rp {{ number_format($proformaFollowUp['amount'], 0, ',', '.') }}</strong></span>
        <span><strong>{{ $proformaFollowUp['overdue_count'] }}</strong> melewati jatuh tempo proforma</span>
    </div>
    @if ($showProformaRows ?? true)
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-800 dark:text-white">
            <thead><tr class="border-b border-amber-200 dark:border-gray-700">
                <th class="p-3">Proforma / Tanggal</th><th class="p-3">Proyek / Company</th><th class="p-3">Jenis / Nilai</th><th class="p-3">Umur / Jatuh tempo</th><th class="p-3">Dibuat oleh</th><th class="p-3">Tindak lanjut</th>
            </tr></thead>
            <tbody>
                @forelse ($proformaFollowUp['rows'] as $pendingProforma)
                    @php($pendingSnapshot = $pendingProforma->quote_snapshot['attributes'] ?? [])
                    <tr class="border-b border-amber-100 dark:border-gray-800">
                        <td class="p-3">{{ $pendingProforma->proforma_number }}<br><span class="text-xs text-gray-500">{{ $pendingProforma->issued_at?->format('d/m/Y') }}</span></td>
                        <td class="p-3">{{ $pendingSnapshot['subject'] ?? '-' }}<br>{{ $pendingSnapshot['bill_to_company_name'] ?? '-' }}</td>
                        <td class="p-3 whitespace-nowrap">{{ $pendingProforma->billingLabel() }}<br>Rp {{ number_format((float) $pendingProforma->amount, 0, ',', '.') }}</td>
                        <td class="p-3">{{ max(0, (int) $pendingProforma->issued_at?->diffInDays(today(), false)) }} hari<br>{{ $pendingProforma->due_at?->format('d/m/Y') }}</td>
                        <td class="p-3">{{ $pendingProforma->creator?->name ?? '-' }}</td>
                        <td class="p-3">
                            <span class="block text-xs text-gray-500">Menunggu pemindahan oleh Admin Sales</span>
                            @if (bouncer()->hasPermission('invoices.view'))
                                <a class="mt-1 inline-block text-brandColor underline" href="{{ route('admin.proformas.show', $pendingProforma->id) }}">Periksa proforma</a>
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
    @endif
</div>
