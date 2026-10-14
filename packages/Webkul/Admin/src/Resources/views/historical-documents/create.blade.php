<x-admin::layouts>
    <x-slot:title>Input Dokumen Lama</x-slot>
    @include('admin::invoices.partials.workspace-styles')
    @push('styles')<style>.legacy-rows input,.legacy-rows select{width:100%;padding:10px;border:1px solid var(--fw-line);border-radius:8px;background:var(--fw-bg);color:var(--fw-text);margin:4px 0}.legacy-rows td{min-width:130px}.legacy-rows td:first-child{min-width:250px}.legacy-rows td:last-child{min-width:60px}</style>@endpush
    @php($d = old('documents.0', $document ?? ['type' => request('type', 'invoice'), 'items' => [[]], 'payments' => []]))
    <div class="crm-finance-workspace">
        <header><a class="fw-link" href="{{ route('admin.historical-documents.index') }}">&larr; Data lama</a><h1 style="margin-top:16px">Input dokumen lama</h1><p class="fw-subtitle">Nomor dan tanggal mengikuti dokumen asli. Periksa preview sebelum menyimpan.</p></header>
        @include('admin::historical-documents.errors')
        <form method="POST" action="{{ route('admin.historical-documents.preview') }}" class="fw-stack" id="historical-form">@csrf
            <section class="fw-card fw-pad"><h2 class="fw-title" style="margin-bottom:20px">Identitas dokumen</h2><div class="fw-form-grid">
                <label class="fw-field">Jenis dokumen<select name="documents[0][type]" id="historical-type"><option value="quote" @selected(($d['type'] ?? '') === 'quote')>Quotation</option><option value="invoice" @selected(($d['type'] ?? '') === 'invoice')>Invoice</option></select></label>
                <label class="fw-field">Nomor asli<input name="documents[0][number]" maxlength="30" required placeholder="Contoh: INV-2608-0001" value="{{ $d['number'] ?? '' }}"></label>
                <label class="fw-field">Tanggal dokumen<input name="documents[0][document_date]" type="date" max="{{ today()->toDateString() }}" required value="{{ $d['document_date'] ?? '' }}"></label>
                <label class="fw-field">Nama proyek / acara<input name="documents[0][subject]" required maxlength="255" value="{{ $d['subject'] ?? '' }}"></label>
                <label class="fw-field">Contact / pelanggan<select name="documents[0][person_id]" required><option value="">Pilih contact</option>@foreach ($people as $person)<option value="{{ $person->id }}" @selected((string) ($d['person_id'] ?? '') === (string) $person->id)>{{ $person->name }}{{ $person->company ? ' · '.$person->company : '' }}</option>@endforeach</select></label>
                <label class="fw-field">Sales pemilik dokumen<select name="documents[0][user_id]" required><option value="">Pilih sales</option>@foreach ($owners as $owner)<option value="{{ $owner->id }}" @selected((string) ($d['user_id'] ?? '') === (string) $owner->id)>{{ $owner->name }}</option>@endforeach</select></label>
                <label class="fw-field">Unit bisnis<select name="documents[0][business_unit]" required>@foreach ($businessUnits as $key => $label)<option value="{{ $key }}" @selected(($d['business_unit'] ?? 'capture_it') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="fw-field">Tampilkan tagihan kepada<select name="documents[0][bill_to_display_mode]">@foreach (['person' => 'Person', 'company' => 'Company', 'both' => 'Company dan Person'] as $key => $label)<option value="{{ $key }}" @selected(($d['bill_to_display_mode'] ?? 'both') === $key)>{{ $label }}</option>@endforeach</select></label>
            </div></section>
            <fieldset class="fw-card fw-pad" data-invoice-fields><h2 class="fw-title" style="margin-bottom:20px">Rincian invoice</h2><div class="fw-form-grid">
                <label class="fw-field">Jenis tagihan<select name="documents[0][billing_type]">@foreach (['full_payment' => 'Penuh / tanpa DP', 'down_payment' => 'DP', 'settlement' => 'Pelunasan'] as $key => $label)<option value="{{ $key }}" @selected(($d['billing_type'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="fw-field">Jatuh tempo<input name="documents[0][due_at]" type="date" required value="{{ $d['due_at'] ?? '' }}"></label>
                <label class="fw-field">Nomor quotation asal<input name="documents[0][quote_number]" maxlength="30" placeholder="Wajib untuk DP / pelunasan" value="{{ $d['quote_number'] ?? '' }}"></label>
                <label class="fw-field">Nomor invoice DP<input name="documents[0][dp_invoice_number]" maxlength="30" placeholder="Wajib untuk pelunasan" value="{{ $d['dp_invoice_number'] ?? '' }}"></label>
                <label class="fw-field">Status event<select name="documents[0][event_status]">@foreach (['confirm' => 'Confirm', 'prospect' => 'Prospect', 'cancel' => 'Cancel'] as $key => $label)<option value="{{ $key }}" @selected(($d['event_status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label>
            </div><p class="fw-subtitle">Invoice penuh dapat dimasukkan tanpa quotation. Untuk invoice terhubung, kode proyek dan unit bisnis mengikuti quotation asal.</p></fieldset>
            <details class="fw-card fw-details"><summary><span class="fw-title">Tanggal event, alamat, dan keterangan tambahan</span></summary><div class="fw-pad fw-form-grid">
                @foreach (['project_code' => ['Kode proyek lama (opsional)', 'text'], 'event_date' => ['Tanggal event', 'date'], 'location' => ['Lokasi', 'text'], 'payment_term' => ['TOP / ketentuan pembayaran (maks. 100 karakter)', 'text'], 'expired_at' => ['Quotation berlaku sampai', 'date']] as $field => [$label, $type])
                    <label class="fw-field">{{ $label }}<input name="documents[0][{{ $field }}]" type="{{ $type }}" value="{{ $d[$field] ?? '' }}"></label>
                @endforeach
                <label class="fw-field">Alamat tagihan<textarea name="documents[0][address]">{{ $d['address'] ?? '' }}</textarea></label><label class="fw-field">Catatan dokumen<textarea name="documents[0][description]">{{ $d['description'] ?? '' }}</textarea></label>
            </div></details>
            <section class="fw-card"><div class="fw-card-heading fw-between"><h2 class="fw-title">Rincian item</h2><button type="button" class="fw-link" data-add-item>+ Tambah item</button></div><p class="fw-section-note">Isi total setiap baris sesuai dokumen lama, sebelum diskon/pajak tingkat dokumen. Produk CRM wajib untuk quotation.</p><div class="fw-scroll"><table class="fw-table legacy-rows"><thead><tr><th>Produk / nama item</th><th>Qty</th><th>Hari</th><th>Total baris (Rp)</th><th></th></tr></thead><tbody id="historical-items">@foreach (($d['items'] ?? [[]]) as $i => $item)@include('admin::historical-documents.item')@endforeach</tbody></table></div></section>
            <section class="fw-card fw-pad"><div class="fw-form-grid">
                @foreach (['discount_amount' => 'Diskon dokumen (Rp)', 'tax_amount' => 'Pajak dokumen (Rp)', 'adjustment_amount' => 'Penyesuaian (Rp)', 'grand_total' => 'Total akhir dokumen (Rp)'] as $field => $label)<label class="fw-field">{{ $label }}<input name="documents[0][{{ $field }}]" type="number" step="0.01" required @if ($field !== 'adjustment_amount') min="{{ $field === 'grand_total' ? '0.01' : '0' }}" @endif value="{{ $d[$field] ?? ($field === 'grand_total' ? '' : 0) }}"></label>@endforeach
            </div><p class="fw-subtitle">Total akhir harus sama dengan jumlah item − diskon + pajak + penyesuaian.</p></section>
            <fieldset class="fw-card" data-invoice-fields><div class="fw-card-heading fw-between"><h2 class="fw-title">Pembayaran yang sudah diterima</h2><button type="button" class="fw-link" data-add-payment>+ Tambah pembayaran</button></div><p class="fw-section-note">Kosongkan jika belum dibayar. Gunakan tanggal penerimaan sebenarnya agar laporan uang masuk sesuai periodenya.</p><div class="fw-scroll"><table class="fw-table legacy-rows"><thead><tr><th>Tanggal diterima</th><th>Nominal (Rp)</th><th>Metode / referensi</th><th></th></tr></thead><tbody id="historical-payments">@foreach (($d['payments'] ?? []) as $i => $payment)@include('admin::historical-documents.payment')@endforeach</tbody></table></div></fieldset>
            <div class="fw-actions"><button class="primary-button" type="submit">Periksa data</button><a class="fw-link" href="{{ route('admin.historical-documents.index') }}">Kembali</a></div>
        </form>
    </div>
    @push('scripts')
        <script>
            (() => {
                let itemIndex = {{ count($d['items'] ?? [[]]) + 100 }}, paymentIndex = {{ count($d['payments'] ?? []) + 100 }};
                const itemTemplate = {{ Illuminate\Support\Js::from(view('admin::historical-documents.item', ['i' => '__ROW__', 'item' => [], 'products' => $products])->render()) }};
                const paymentTemplate = {{ Illuminate\Support\Js::from(view('admin::historical-documents.payment', ['i' => '__ROW__', 'payment' => []])->render()) }};
                const toggle = () => document.querySelectorAll('[data-invoice-fields]').forEach(el => { el.hidden = document.getElementById('historical-type')?.value !== 'invoice'; el.disabled = el.hidden; });
                document.addEventListener('change', event => {
                    if (event.target.id === 'historical-type') toggle();
                    if (event.target.matches('[data-product]')) {
                        const name = event.target.closest('tr').querySelector('[data-item-name]');
                        if (!name.value) name.value = event.target.selectedOptions[0]?.dataset.name || '';
                    }
                });
                document.addEventListener('click', event => {
                    if (event.target.closest('[data-add-item]')) document.getElementById('historical-items').insertAdjacentHTML('beforeend', itemTemplate.replaceAll('__ROW__', itemIndex++));
                    if (event.target.closest('[data-add-payment]')) document.getElementById('historical-payments').insertAdjacentHTML('beforeend', paymentTemplate.replaceAll('__ROW__', paymentIndex++));
                    if (event.target.closest('[data-remove-row]')) event.target.closest('tr').remove();
                });
                document.addEventListener('DOMContentLoaded', toggle);
                setTimeout(toggle, 0);
            })();
        </script>
    @endpush
</x-admin::layouts>
