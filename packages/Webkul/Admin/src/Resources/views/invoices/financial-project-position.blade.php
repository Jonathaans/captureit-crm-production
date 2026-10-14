<details class="fw-card fw-details">
    <summary><span><span class="fw-title">Posisi proyek</span><span class="fw-muted block">{{ $projectPerformance->count() }} proyek · posisi saat ini</span></span></summary>
    <p class="fw-section-note">Proyek dengan invoice confirmed pada periode/filter yang dipilih. DP dan pelunasan digabung per proyek, termasuk invoice proyek pada bulan lain. Margin = seluruh invoice confirmed yang telah terbit dikurangi seluruh biaya pada invoice proyek. Nilai proforma dan sisa penawaran tidak dihitung. Ini posisi saat ini, bukan saldo penutupan periode.</p>
    <div class="fw-scroll">
        <table class="fw-table" style="min-width:1000px">
            <thead class="bg-gray-50 dark:bg-gray-950"><tr><th class="p-3">Proyek / Company</th><th class="p-3">Invoice confirmed</th><th class="fw-number">Total invoice</th><th class="fw-number">Diterima</th><th class="fw-number">Piutang saat ini</th><th class="fw-number">Biaya proyek</th><th class="fw-number">Margin dari invoice</th></tr></thead>
            <tbody>
                @forelse ($projectPerformance->take(50) as $project)
                    <tr class="border-t dark:border-gray-800">
                        <td class="p-3">{{ $project['project_code'] }}<br>{{ $project['subject'] }}<br>{{ $project['company'] }}</td>
                        <td class="p-3">{{ $project['invoice_numbers'] }}</td>
                        <td class="fw-number">{{ $rupiah($project['invoiced']) }}</td>
                        <td class="fw-number">{{ $rupiah($project['received']) }}</td>
                        <td class="fw-number">{{ $rupiah($project['outstanding']) }}</td>
                        <td class="fw-number">{{ $rupiah($project['expense']) }}</td>
                        <td class="fw-number font-semibold">{{ $rupiah($project['margin']) }}</td>
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
