<x-admin::layouts>
    <x-slot:title>Proforma Invoice</x-slot>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold dark:text-white">Proforma Invoice</h1>
            <p class="mt-2 text-sm text-gray-500">Semua tagihan dari penawaran masuk di sini. Admin Sales memindahkannya ke Invoice setelah event deal/oke atau pembayaran diterima.</p>
        </div>
        @if (bouncer()->hasPermission('invoices.generate'))
            <a class="primary-button" href="{{ route('admin.invoices.billing.create') }}">+ Buat dari penawaran</a>
        @endif
    </div>
    <div class="mt-5">
        @include('admin::invoices.proformas.follow-up', ['showProformaRows' => false])
    </div>
    <div class="mt-5 overflow-x-auto rounded-xl border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <form class="mb-4 flex flex-wrap items-center gap-3" method="GET" action="{{ route('admin.proformas.index') }}">
            <label for="proforma-status" class="text-sm dark:text-white">Status dokumen</label>
            <select id="proforma-status" name="status" class="rounded border px-3 py-2 dark:bg-gray-900 dark:text-white">
                <option value="">Semua status</option>
                <option value="issued" @selected($status === 'issued')>Belum menjadi Invoice</option>
                <option value="converted" @selected($status === 'converted')>Sudah menjadi Invoice</option>
                <option value="cancelled" @selected($status === 'cancelled')>Dibatalkan</option>
            </select>
            <button class="secondary-button" type="submit">Tampilkan</button>
        </form>
        <table class="w-full text-left text-sm dark:text-white">
            <thead><tr class="border-b"><th class="p-3">Proforma / Tanggal</th><th class="p-3">Penawaran / Proyek</th><th class="p-3">Company</th><th class="p-3">Jenis / Nominal</th><th class="p-3">Umur / Jatuh tempo</th><th class="p-3">Dibuat oleh</th><th class="p-3">Status</th></tr></thead>
            <tbody>
                @forelse ($proformas as $proforma)
                    @php($snapshot = $proforma->quote_snapshot['attributes'])
                    <tr class="border-b dark:border-gray-800">
                        <td class="p-3">
                            @if (bouncer()->hasPermission('invoices.view'))
                                <a class="text-brandColor underline" href="{{ route('admin.proformas.show', $proforma->id) }}">{{ $proforma->proforma_number }}</a>
                            @else
                                {{ $proforma->proforma_number }}
                            @endif
                            <div class="mt-1 text-xs text-gray-500">{{ $proforma->issued_at?->format('d/m/Y') }}</div>
                        </td>
                        <td class="p-3">{{ $snapshot['quote_number'] ?? '-' }}<br>{{ $snapshot['subject'] ?? '-' }}</td>
                        <td class="p-3">{{ $snapshot['bill_to_company_name'] ?? '-' }}</td>
                        <td class="p-3 whitespace-nowrap">{{ $proforma->billingLabel() }}<br>Rp {{ number_format((float) $proforma->amount, 2, ',', '.') }}</td>
                        <td class="p-3">
                            @if ($proforma->status === 'issued')
                                {{ max(0, (int) $proforma->issued_at?->diffInDays(today(), false)) }} hari<br>
                            @endif
                            {{ $proforma->due_at?->format('d/m/Y') }}
                        </td>
                        <td class="p-3">{{ $proforma->creator?->name ?? '-' }}</td>
                        <td class="p-3">{{ ['issued' => 'Menunggu Admin Sales', 'converted' => 'Sudah masuk Invoice', 'cancelled' => 'Dibatalkan'][$proforma->status] ?? $proforma->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-center text-gray-500">Belum ada proforma.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $proformas->links() }}</div>
    </div>
</x-admin::layouts>
