<x-admin::layouts>
    <x-slot:title>Periksa Data Lama</x-slot>
    @include('admin::invoices.partials.workspace-styles')
    <div class="crm-finance-workspace">
        <header><a class="fw-link" href="{{ route('admin.historical-documents.index') }}">&larr; Data lama</a><p class="fw-eyebrow" style="margin-top:20px">{{ $batch->source }}</p><h1>{{ $batch->status === 'imported' ? 'Data berhasil disimpan' : ($batch->status === 'mapping' ? 'Cocokkan kolom' : 'Periksa sebelum menyimpan') }}</h1><p class="fw-subtitle">{{ $batch->status === 'imported' ? 'Dokumen tersedia di daftar Quotation dan Invoice.' : 'Belum ada quotation, invoice, atau pembayaran yang disimpan.' }}</p></header>
        @include('admin::historical-documents.errors')
        @if ($batch->status !== 'imported' && $batch->expires_at->isPast())
            <p class="fw-note">Preview sudah kedaluwarsa. Silakan input atau unggah kembali untuk memeriksa data terbaru.</p>
        @elseif ($batch->status === 'mapping')
            <p class="fw-note">Pilih sheet sumber lalu pasangkan kolomnya. Gunakan template untuk melihat contoh nama kolom dan daftar ID Contact, Sales, serta Produk. Satu baris Dokumen mewakili satu QT/INV; rincian Item dan Pembayaran merujuk nomor tersebut.</p>
            <form method="POST" action="{{ route('admin.historical-documents.map', $batch->id) }}" class="fw-stack" id="historical-mapping">@csrf
                @foreach ($fields as $group => $columns)
                    @php
                        $selectedSheet = old('mapping.'.$group.'.sheet', $mapping[$group]['sheet']);
                    @endphp
                    <details class="fw-card fw-details" open><summary><span class="fw-title">{{ $labels[$group] }}</span></summary><div class="fw-pad"><label class="fw-field">Sheet sumber<select name="mapping[{{ $group }}][sheet]" data-source="{{ $group }}"><option value="">Tidak digunakan</option>@foreach ($batch->payload['sheets'] as $i => $sheet)<option value="{{ $i }}" @selected((string) $selectedSheet === (string) $i)>{{ $sheet['name'] }} · {{ count($sheet['rows']) }} baris</option>@endforeach</select></label><div class="fw-form-grid" style="margin-top:20px">
                        @foreach ($columns as $field)
                            <label class="fw-field">{{ $field }}<select name="mapping[{{ $group }}][columns][{{ $field }}]" data-column-group="{{ $group }}" data-field="{{ $field }}"><option value="">Tidak dipetakan</option>@foreach (($batch->payload['sheets'][$selectedSheet]['headers'] ?? []) as $i => $header)<option value="{{ $i }}" @selected((string) old('mapping.'.$group.'.columns.'.$field, $mapping[$group]['columns'][$field]) === (string) $i)>{{ $header ?: 'Kolom '.($i + 1) }}</option>@endforeach</select></label>
                        @endforeach
                    </div></div></details>
                @endforeach
                <button type="submit" class="primary-button" style="align-self:flex-start">Buat preview</button>
            </form>
            @push('scripts')<script>
                (() => {
                    const sheets = {{ Illuminate\Support\Js::from(array_map(fn ($sheet) => $sheet['headers'], $batch->payload['sheets'])) }};
                    document.addEventListener('change', event => {
                        const group = event.target.dataset.source;
                        if (!group) return;
                        const headers = sheets[event.target.value] || [];
                        document.querySelectorAll('[data-column-group="'+group+'"]').forEach(select => {
                            select.replaceChildren(new Option('Tidak dipetakan', ''));
                            headers.forEach((header, index) => select.add(new Option(header || 'Kolom '+(index+1), index, false, header === select.dataset.field)));
                        });
                    });
                })();
            </script>@endpush
        @else
            @php
                $documents = $batch->payload['documents'];
                $money = static fn ($value) => 'Rp '.number_format((float) $value, 2, ',', '.');
                $invoiceTotal = collect($documents)->where('type', 'invoice')->sum('grand_total');
                $receivedTotal = collect($documents)->where('type', 'invoice')->sum('paid_amount');
            @endphp
            <section class="fw-metrics fw-metrics-three"><div class="fw-metric"><p class="fw-metric-label">Dokumen</p><p class="fw-value">{{ count($documents) }}</p><small>{{ collect($documents)->where('type', 'quote')->count() }} quotation · {{ collect($documents)->where('type', 'invoice')->count() }} invoice</small></div><div class="fw-metric"><p class="fw-metric-label">Nilai invoice</p><p class="fw-value">{{ $money($invoiceTotal) }}</p><small>Nilai quotation tidak dijumlahkan sebagai invoice</small></div><div class="fw-metric"><p class="fw-metric-label">Pembayaran tercatat</p><p class="fw-value">{{ $money($receivedTotal) }}</p><small>Mengikuti tanggal penerimaan masing-masing</small></div></section>
            @foreach ($documents as $document)
                <details class="fw-card fw-details"><summary><span><span class="fw-title">{{ strtoupper($document['type']) }} · {{ $document['number'] }}</span><span class="fw-muted block">{{ $document['document_date'] }} · {{ $document['subject'] }} · {{ $money($document['grand_total']) }}</span></span><span class="fw-badge">{{ $document['type'] === 'invoice' ? strtoupper($document['status']) : 'Quotation' }}</span></summary><div class="fw-pad">
                    <div class="fw-form-grid"><div><p class="fw-muted">Pelanggan / perusahaan</p><p>{{ $document['bill_to_person_name'] }}<br>{{ $document['bill_to_company_name'] }}</p></div><div><p class="fw-muted">Kode proyek / unit bisnis</p><p>{{ $document['project_code'] }}<br>{{ $document['business_unit'] }}</p></div><div><p class="fw-muted">Tanggal dokumen / jatuh tempo</p><p>{{ $document['document_date'] }} / {{ $document['due_at'] ?? '-' }}</p></div><div><p class="fw-muted">Quotation / invoice DP rujukan</p><p>{{ $document['quote_number'] ?? '-' }} / {{ $document['dp_invoice_number'] ?? '-' }}</p></div></div>
                    <div class="fw-scroll" style="margin-top:24px"><table class="fw-table"><thead><tr><th>Item</th><th>Qty × hari</th><th class="fw-number">Total baris</th></tr></thead><tbody>@foreach ($document['items'] as $item)<tr><td>{{ $item['name'] }}</td><td>{{ $item['quantity'] }} × {{ $item['day'] }}</td><td class="fw-number">{{ $money($item['total']) }}</td></tr>@endforeach</tbody></table></div>
                    <p class="fw-subtitle">Subtotal {{ $money($document['sub_total']) }} − diskon {{ $money($document['discount_amount']) }} + pajak {{ $money($document['tax_amount']) }} + penyesuaian {{ $money($document['adjustment_amount']) }} = <strong>{{ $money($document['grand_total']) }}</strong></p>
                    @if ($document['type'] === 'invoice')<h3 class="fw-title" style="margin-top:24px">Pembayaran</h3>@forelse ($document['payments'] as $payment)<p class="fw-subtitle">{{ $payment['paid_at'] }} · {{ $money($payment['amount']) }} · {{ $payment['payment_method'] }} · {{ $payment['reference_number'] ?? '-' }}</p>@empty<p class="fw-muted">Belum ada pembayaran.</p>@endforelse<p class="fw-subtitle">Sisa tagihan: <strong>{{ $money($document['balance_due']) }}</strong></p>@endif
                </div></details>
            @endforeach
            @if ($batch->status === 'preview')
                <p class="fw-note">Dokumen akan masuk laporan sesuai tanggal aslinya. Nilai invoice pada laporan mengikuti status event dan filter yang dipilih. Periksa rincian setiap dokumen di atas.</p>
                <form method="POST" action="{{ route('admin.historical-documents.store', $batch->id) }}">@csrf<label class="fw-check"><input type="checkbox" name="confirm" value="1" required>Saya sudah memeriksa nomor, tanggal, nilai, dan pembayaran.</label><div class="fw-actions"><button type="submit" class="primary-button">Simpan {{ count($documents) }} dokumen</button>@if ($batch->source === 'Input manual')<a class="fw-link" href="{{ route('admin.historical-documents.create', ['batch' => $batch->id]) }}">Ubah isian</a>@endif</div></form>
            @else
                <section class="fw-card fw-pad"><h2 class="fw-title">Dokumen tersimpan</h2>@foreach ($batch->result as $record)<p class="fw-subtitle">{{ strtoupper($record['type']) }} · @if ($record['type'] === 'invoice' && bouncer()->hasPermission('invoices.view'))<a class="fw-link" href="{{ route('admin.invoices.show', $record['id']) }}">{{ $record['number'] }} &rarr;</a>@elseif ($record['type'] === 'quote' && bouncer()->hasPermission('quotes.edit'))<a class="fw-link" href="{{ route('admin.quotes.edit', $record['id']) }}">{{ $record['number'] }} &rarr;</a>@else{{ $record['number'] }}@endif</p>@endforeach</section>
            @endif
        @endif
    </div>
</x-admin::layouts>
