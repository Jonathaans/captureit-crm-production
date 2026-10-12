<x-admin::layouts>
    <x-slot:title>Proforma Invoice</x-slot>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold dark:text-white">Proforma Invoice</h1>
            <p class="mt-2 text-sm text-gray-500">Permintaan DP. Invoice DP diterbitkan setelah uang masuk dikonfirmasi.</p>
        </div>
        @if (bouncer()->hasPermission('invoices.generate'))
            <a class="primary-button" href="{{ route('admin.invoices.billing.create') }}">+ Buat dari penawaran</a>
        @endif
    </div>
    <div class="mt-5 overflow-x-auto rounded-xl border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <table class="w-full text-left text-sm dark:text-white">
            <thead><tr class="border-b"><th class="p-3">Proforma</th><th class="p-3">Penawaran / Proyek</th><th class="p-3">Company</th><th class="p-3">DP</th><th class="p-3">Jatuh tempo</th><th class="p-3">Status</th></tr></thead>
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
                        </td>
                        <td class="p-3">{{ $snapshot['quote_number'] ?? '-' }}<br>{{ $snapshot['subject'] ?? '-' }}</td>
                        <td class="p-3">{{ $snapshot['bill_to_company_name'] ?? '-' }}</td>
                        <td class="p-3 whitespace-nowrap">Rp {{ number_format((float) $proforma->amount, 2, ',', '.') }}</td>
                        <td class="p-3">{{ $proforma->due_at->format('d/m/Y') }}</td>
                        <td class="p-3">{{ ['issued' => 'Menunggu DP', 'converted' => 'Invoice DP terbit', 'cancelled' => 'Dibatalkan'][$proforma->status] ?? $proforma->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-gray-500">Belum ada proforma.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $proformas->links() }}</div>
    </div>
</x-admin::layouts>
