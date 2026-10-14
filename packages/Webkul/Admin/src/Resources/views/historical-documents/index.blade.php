<x-admin::layouts>
    <x-slot:title>Input Data Lama</x-slot>
    @include('admin::invoices.partials.workspace-styles')
    <div class="crm-finance-workspace">
        <header class="fw-header"><div><p class="fw-eyebrow">Migrasi dokumen</p><h1>Input Data Lama</h1><p class="fw-subtitle">Pertahankan nomor dan tanggal asli quotation, invoice, serta pembayaran.</p></div><a class="fw-link" href="{{ route('admin.invoices.index') }}">Daftar invoice &rarr;</a></header>
        @include('admin::historical-documents.errors')
        <div class="fw-two-columns">
            <section class="fw-card fw-pad"><h2 class="fw-title">Import Excel</h2><p class="fw-subtitle">Isi sheet Dokumen, Item, dan Pembayaran. Anda dapat mencocokkan kolom file sendiri sebelum memeriksa hasilnya.</p><a class="fw-link" style="display:inline-block;margin:16px 0" href="{{ route('admin.historical-documents.template') }}">Unduh template dan daftar ID master &darr;</a>
                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.historical-documents.upload') }}">@csrf<label class="fw-field">File XLSX, XLS, atau CSV (maks. 2 MB)<input name="file" type="file" accept=".xlsx,.xls,.csv" required></label><button class="primary-button mt-4" type="submit">Unggah dan cocokkan kolom</button></form>
            </section>
            <section class="fw-card fw-pad"><h2 class="fw-title">Input satu dokumen</h2><p class="fw-subtitle">Isi quotation atau invoice beserta rincian item. Pembayaran dapat dicatat sekaligus dengan invoice.</p><a class="primary-button" style="margin-top:24px" href="{{ route('admin.historical-documents.create') }}">Input manual</a></section>
        </div>
        <p class="fw-note">Tanggal dokumen menentukan periode invoice; tanggal penerimaan menentukan periode uang masuk. Tanggal input ke CRM tetap tercatat terpisah. Data lama tidak otomatis membuat proforma, SPK, jadwal event, atau email.</p>
        <section class="fw-card"><h2 class="fw-title fw-card-heading">Riwayat input Anda</h2><div class="fw-scroll"><table class="fw-table"><thead><tr><th>Sumber</th><th>Tanggal input</th><th>Status</th><th>Dokumen</th></tr></thead><tbody>@forelse ($batches as $batch)<tr><td><a class="fw-link" href="{{ route('admin.historical-documents.show', $batch->id) }}">{{ $batch->source }}</a></td><td>{{ $batch->created_at->format('d M Y H:i') }}</td><td>{{ ['mapping' => 'Cocokkan kolom', 'preview' => 'Belum disimpan', 'imported' => 'Tersimpan'][$batch->status] ?? $batch->status }}</td><td>{{ count($batch->result ?? $batch->payload['documents'] ?? []) }}</td></tr>@empty<tr><td colspan="4" class="fw-empty">Belum ada input data lama.</td></tr>@endforelse</tbody></table></div><div class="fw-pad">{{ $batches->links() }}</div></section>
    </div>
</x-admin::layouts>
