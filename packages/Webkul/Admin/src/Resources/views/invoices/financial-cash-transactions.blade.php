<details class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900" open>
    <summary class="cursor-pointer px-5 py-4 text-lg font-semibold text-gray-800 dark:text-white">Rincian Uang Masuk dan Keluar — {{ $periodLabel }}</summary>
    <p class="px-5 pb-4 text-sm text-gray-500">Mengikuti tanggal transaksi, termasuk pembayaran dan biaya untuk invoice dari bulan lain. Jumlah uang masuk/keluar di bagian ini sesuai ringkasan Cash Received dan Expense.</p>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-800 dark:text-white">
            <thead class="bg-gray-50 dark:bg-gray-950"><tr><th class="p-3">Tanggal / Jenis</th><th class="p-3">Invoice / Proyek</th><th class="p-3">Company / Referensi</th><th class="p-3 text-right">Uang masuk</th><th class="p-3 text-right">Uang keluar</th></tr></thead>
            <tbody>
                @forelse ($cashTransactions->take(50) as $transaction)
                    <tr class="border-t dark:border-gray-800">
                        <td class="p-3">{{ $transaction['date']->format('d/m/Y') }}<br>{{ $transaction['type'] }}</td>
                        <td class="p-3">
                            @if (bouncer()->hasPermission('invoices.view'))
                                <a class="text-brandColor underline" href="{{ route('admin.invoices.show', $transaction['invoice_id']) }}">{{ $transaction['invoice_number'] }}</a>
                            @else
                                {{ $transaction['invoice_number'] }}
                            @endif
                            <br>{{ $transaction['project_code'] }}
                        </td>
                        <td class="p-3">{{ $transaction['company'] }}<br>{{ $transaction['reference'] }}</td>
                        <td class="p-3 text-right">{{ $rupiah($transaction['cash_in']) }}</td>
                        <td class="p-3 text-right">{{ $rupiah($transaction['cash_out']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4 text-center text-gray-500">Tidak ada transaksi pada periode ini.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="border-t font-semibold dark:border-gray-800"><tr><td colspan="3" class="p-3">Total seluruh {{ $cashTransactions->count() }} transaksi</td><td class="p-3 text-right">{{ $rupiah($cashTransactions->sum('cash_in')) }}</td><td class="p-3 text-right">{{ $rupiah($cashTransactions->sum('cash_out')) }}</td></tr></tfoot>
        </table>
    </div>
    @if ($cashTransactions->count() > 50)
        <p class="px-5 py-3 text-xs text-gray-500">Menampilkan 50 transaksi pertama. Ekspor Financial Report memuat seluruh transaksi sesuai izin export Anda.</p>
    @endif
</details>
