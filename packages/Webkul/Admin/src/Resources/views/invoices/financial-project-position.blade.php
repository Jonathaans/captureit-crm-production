<details class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900" open>
    <summary class="cursor-pointer px-5 py-4 text-lg font-semibold text-gray-800 dark:text-white">Posisi Proyek dari Invoice — Saat Ini</summary>
    <p class="px-5 pb-4 text-sm text-gray-500">Proyek dengan invoice confirmed pada periode/filter yang dipilih. DP dan pelunasan digabung per proyek, termasuk invoice proyek pada bulan lain. Margin = seluruh invoice confirmed yang telah terbit dikurangi seluruh biaya pada invoice proyek. Nilai proforma dan sisa penawaran tidak dihitung. Ini posisi saat ini, bukan saldo penutupan periode.</p>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-sm text-gray-800 dark:text-white">
            <thead class="bg-gray-50 dark:bg-gray-950"><tr><th class="p-3">Proyek / Company</th><th class="p-3">Invoice confirmed</th><th class="p-3 text-right">Total invoice</th><th class="p-3 text-right">Diterima</th><th class="p-3 text-right">Piutang saat ini</th><th class="p-3 text-right">Biaya proyek</th><th class="p-3 text-right">Margin dari invoice</th></tr></thead>
            <tbody>
                @forelse ($projectPerformance->take(50) as $project)
                    <tr class="border-t dark:border-gray-800">
                        <td class="p-3">{{ $project['project_code'] }}<br>{{ $project['subject'] }}<br>{{ $project['company'] }}</td>
                        <td class="p-3">{{ $project['invoice_numbers'] }}</td>
                        <td class="p-3 text-right">{{ $rupiah($project['invoiced']) }}</td>
                        <td class="p-3 text-right">{{ $rupiah($project['received']) }}</td>
                        <td class="p-3 text-right">{{ $rupiah($project['outstanding']) }}</td>
                        <td class="p-3 text-right">{{ $rupiah($project['expense']) }}</td>
                        <td class="p-3 text-right font-semibold">{{ $rupiah($project['margin']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4 text-center text-gray-500">Tidak ada proyek dengan invoice confirmed pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($projectPerformance->count() > 50)
        <p class="px-5 py-3 text-xs text-gray-500">Menampilkan 50 proyek pertama. Seluruh proyek disertakan dalam ekspor Financial Report.</p>
    @endif
</details>
