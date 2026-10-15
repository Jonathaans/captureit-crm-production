<x-admin::layouts>
    <x-slot:title>Revisi Transaksi · {{ $state['quote']['quote_number'] }}</x-slot>
    @include('admin::invoices.partials.workspace-styles')
    @php
        $money = static fn ($v) => 'Rp '.number_format((float) $v, 2, ',', '.');
        $draft = array_replace_recursive($form, old());
        $draft['items'] = old('items', $form['items']);
        $kindLabel = static fn ($type) => ['down_payment' => 'DP', 'settlement' => 'Pelunasan'][''.$type] ?? 'Penuh';
        $dpInvoice = collect($invoices)->firstWhere('billing_type', 'down_payment');
        $paidDp = $dpInvoice && $dpInvoice['status'] === 'paid';
        $documents = [['name' => $state['quote']['quote_number'], 'kind' => 'quote', 'amount' => $state['quote']['grand_total'], 'paid' => null, 'url' => bouncer()->hasPermission('quotes.print') ? route('admin.quotes.print', $state['quote']['id']) : null]];
        foreach ($proformas as $p) {
            $documents[] = ['name' => $p['proforma_number'].' · '.$kindLabel($p['billing_type']), 'kind' => $p['billing_type'], 'amount' => $p['amount'], 'paid' => null, 'url' => bouncer()->hasPermission('invoices.view') ? route('admin.proformas.show', $p['id']) : null];
        }
        foreach ($invoices as $i) {
            $documents[] = ['name' => $i['invoice_number'].' · '.$kindLabel($i['billing_type']), 'kind' => $i['billing_type'] ?: 'full_payment', 'amount' => $i['grand_total'], 'paid' => $i['paid_amount'], 'url' => bouncer()->hasPermission('invoices.view') ? route('admin.invoices.show', $i['id']) : null];
        }
    @endphp
    <div v-commercial-revision>
        <div class="crm-finance-workspace" v-pre>
            <header class="fw-header">
                <div>
                    <p class="fw-eyebrow">{{ $state['quote']['project_code'] }} · Revisi {{ $state['version'] + 1 }}</p>
                    <h1>Revisi Transaksi</h1>
                    <p class="fw-subtitle">Satu perubahan untuk Quote, Proforma, dan Invoice yang terhubung.</p>
                </div>
                @if (bouncer()->hasPermission('invoices'))<a class="secondary-button" href="{{ route('admin.invoices.index') }}">Daftar invoice</a>@endif
            </header>
            @if (session('success'))<div role="status" class="fw-card fw-pad fw-positive">{{ session('success') }}</div>@endif
            @if (session('warning'))<div role="status" class="fw-note">{{ session('warning') }}</div>@endif
            @if ($errors->any())
                <div role="alert" class="rounded-xl bg-red-50 p-4 text-red-700"><strong>Revisi belum disimpan.</strong><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('admin.commercial-revisions.update', [$sourceType, $sourceId]) }}" class="fw-stack" id="commercial-revision-form">
                @csrf @method('PUT')
                <input type="hidden" name="fingerprint" value="{{ old('fingerprint', $fingerprint) }}">
                <section class="fw-card fw-pad">
                    <div class="fw-between"><h2 class="fw-title">Informasi transaksi</h2><span class="fw-badge">{{ $state['quote']['quote_number'] }}</span></div>
                    <div class="fw-form-grid" style="margin-top:20px">
                        <label class="fw-field">Nama event / subject<input name="subject" required maxlength="255" value="{{ $draft['subject'] }}"></label>
                        <label class="fw-field">Tanggal event<input name="event_date" type="date" value="{{ $draft['event_date'] }}"></label>
                        <label class="fw-field">Bill To / contact<select name="person_id" required>@foreach ($people as $person)<option value="{{ $person->id }}" @selected((string) $draft['person_id'] === (string) $person->id)>{{ $person->name }}{{ $person->company ? ' · '.$person->company : '' }}</option>@endforeach</select></label>
                        <label class="fw-field">Tampilkan Bill To<select name="bill_to_display_mode">@foreach (['person' => 'Contact', 'company' => 'Perusahaan', 'both' => 'Contact & perusahaan'] as $value => $label)<option value="{{ $value }}" @selected($draft['bill_to_display_mode'] === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="fw-field">Lokasi<input name="location" maxlength="255" value="{{ $draft['location'] }}"></label>
                        <label class="fw-field">Ketentuan pembayaran<textarea name="payment_term" maxlength="100">{{ $draft['payment_term'] }}</textarea></label>
                    </div>
                    <label class="fw-field" style="margin-top:16px">Catatan / deskripsi<textarea name="description" maxlength="10000">{{ $draft['description'] }}</textarea></label>
                    <details class="fw-details" style="margin-top:12px">
                        <summary>Alamat penagihan & pengiriman</summary>
                        <div class="fw-form-grid">
                            @foreach (['billing_address' => 'Penagihan', 'shipping_address' => 'Pengiriman'] as $field => $title)
                                <div class="fw-stack"><strong>{{ $title }}</strong>
                                    @foreach (['address' => 'Alamat', 'city' => 'Kota', 'state' => 'Provinsi', 'country' => 'Kode negara', 'postcode' => 'Kode pos'] as $key => $label)
                                        <label class="fw-field">{{ $label }}<input name="{{ $field }}[{{ $key }}]" maxlength="2000" value="{{ $draft[$field][$key] ?? '' }}"></label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </details>
                </section>
                <section class="fw-card">
                    <div class="fw-card-heading fw-between"><div><h2 class="fw-title">Rincian nilai penuh transaksi</h2><p class="fw-muted">Harga di bawah adalah harga penuh produk. Pembagian DP diatur terpisah.</p></div><button type="button" class="secondary-button" data-add-item>+ Tambah produk</button></div>
                    <div class="fw-scroll"><table class="fw-table"><thead><tr><th>Produk & deskripsi</th><th>Jumlah</th><th>Harga</th><th>Diskon & pajak</th><th class="text-right">Total item</th></tr></thead><tbody id="revision-items">
                        @foreach ($draft['items'] as $index => $item) @include('admin::invoices.revisions.item') @endforeach
                    </tbody></table></div>
                    <div class="fw-pad fw-form-grid">
                        <label class="fw-field">Diskon tambahan (Rp)<input name="document_discount_amount" data-document-discount type="number" min="0" step="0.01" required value="{{ $draft['document_discount_amount'] }}"></label>
                        <label class="fw-field">Pajak tambahan (Rp)<input name="document_tax_amount" data-document-tax type="number" min="0" step="0.01" required value="{{ $draft['document_tax_amount'] }}"></label>
                        <label class="fw-field">Penyesuaian (Rp)<input name="adjustment_amount" data-adjustment type="number" step="0.01" required value="{{ $draft['adjustment_amount'] }}"></label>
                        <label class="fw-field">Gross-up PPh 0,5%<select name="pph_gross_up" data-pph-gross-up><option value="0" @selected(empty($draft['pph_gross_up']))>Tidak</option><option value="1" @selected(! empty($draft['pph_gross_up']))>Ya — dipotong oleh pelanggan</option></select><span class="fw-muted">Bruto jasa = netto jasa ÷ 0,995. Pajak lain/PPN terpisah. Bukti potong diterima terpisah dari pelanggan.</span></label>
                        <div class="fw-metric fw-highlight"><p class="fw-metric-label">Total transaksi setelah revisi</p><p class="fw-value" data-grand-total aria-live="polite"></p><p data-pph-summary class="fw-muted"></p></div>
                    </div>
                </section>
                @if ($form['dp_amount'] !== null)
                    <section class="fw-card fw-pad"><h2 class="fw-title">Pembagian tagihan</h2><div class="fw-form-grid" style="margin-top:18px">
                        <label class="fw-field">Transfer netto DP (Rp)<input name="dp_amount" data-dp type="number" min="0.01" step="0.01" required value="{{ $draft['dp_amount'] }}" @readonly($paidDp)></label>
                        <div><p class="fw-muted">Nilai pelunasan</p><p class="fw-value" data-settlement></p></div>
                    </div><p class="fw-note">{{ $paidDp ? 'Transfer netto DP sudah lunas dan tetap. Jika gross-up berubah, bruto DP mengikuti potongan PPh; uang masuk tetap.' : 'Mengubah nominal DP hanya mengubah pembagian DP dan pelunasan. Total transaksi tetap mengikuti rincian produk.' }}</p></section>
                @endif
                <section class="fw-card">
                    <h2 class="fw-title fw-card-heading">Periksa perubahan dokumen</h2>
                    <div class="fw-scroll"><table class="fw-table"><thead><tr><th>Dokumen terkait</th><th class="text-right">Nilai saat ini</th><th class="text-right">Nilai setelah revisi</th><th class="text-right">PPh baru</th><th class="text-right">Sudah dibayar</th><th class="text-right">Sisa tagihan baru</th></tr></thead><tbody>
                        @foreach ($documents as $doc)
                            <tr data-document-kind="{{ $doc['kind'] }}" data-paid="{{ $doc['paid'] ?? '' }}">
                                <td>@if ($doc['url'])<a href="{{ $doc['url'] }}" class="fw-link" target="_blank" rel="noopener">{{ $doc['name'] }}</a>@else{{ $doc['name'] }}@endif</td>
                                <td class="fw-number">{{ $money($doc['amount']) }}</td><td class="fw-number" data-new-amount></td><td class="fw-number" data-new-pph></td><td class="fw-number">{{ $doc['paid'] === null ? '—' : $money($doc['paid']) }}</td><td class="fw-number" data-new-balance></td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                    <p class="fw-section-note">Financial report memakai nilai invoice setelah revisi. Quote dan proforma belum menjadi pendapatan tambahan. Tanggal terbit dan nomor dokumen tetap mengikuti masing-masing dokumen.</p>
                </section>
                @if ($has_work_orders)<p class="fw-note">Transaksi ini sudah mempunyai SPK. Jika tanggal event atau produk berubah, tinjau kembali SPK dan Surat Jalan bersama tim operasional.</p>@endif
                <section class="fw-card fw-pad">
                    <label class="fw-field">Alasan revisi<textarea name="reason" minlength="5" maxlength="2000" required placeholder="Contoh: Customer menambah durasi sewa menjadi 2 hari.">{{ old('reason') }}</textarea></label>
                    <p class="fw-note" style="margin-top:14px">Riwayat sebelum dan sesudah perubahan akan tersimpan. Cetak ulang dokumen setelah revisi dan kirim kembali PDF terbaru kepada customer.</p>
                    <div class="fw-actions" style="margin-top:20px"><button class="primary-button" type="submit">Simpan revisi seluruh dokumen</button><span class="fw-muted" data-revision-error role="alert"></span></div>
                </section>
            </form>
            @include('admin::invoices.revisions.history')
        </div>
    </div>
    @pushOnce('scripts')
        <template id="revision-item-template">@include('admin::invoices.revisions.item', ['index' => '__INDEX__', 'item' => []])</template>
        <script type="module">
            app.directive('commercial-revision', {
                mounted(root) {
                    const form = root.querySelector('#commercial-revision-form');
                    const body = form.querySelector('#revision-items');
                    let nextIndex = Math.max(-1, ...Array.from(body.querySelectorAll('[name]'), el => Number(el.name.match(/items\[(\d+)\]/)?.[1] ?? -1))) + 1;
                    const money = v => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 2 }).format(v);
                    const amount = (root, selector) => Number(root.querySelector(selector)?.value || 0);
                    const round = v => Math.round((v + Number.EPSILON) * 100) / 100;
                    function recalculate() {
                        let total = 0;
                        let otherTax = amount(form, '[data-document-tax]');
                        let valid = true;
                        body.querySelectorAll('[data-revision-item]').forEach(row => {
                            const base = round(amount(row, '[data-quantity]') * amount(row, '[data-price]'));
                            const discount = amount(row, '[data-discount]');
                            otherTax += amount(row, '[data-tax]');
                            const line = round(base - discount + amount(row, '[data-tax]'));
                            row.querySelector('[data-line-total]').textContent = money(line);
                            valid = valid && discount <= base && line >= 0;
                            total += line;
                        });
                        total = round(total - amount(form, '[data-document-discount]') + amount(form, '[data-document-tax]') + amount(form, '[data-adjustment]'));
                        const enabled = amount(form, '[data-pph-gross-up]') === 1;
                        const netService = round(total - otherTax);
                        const pph = enabled ? round(round(Math.max(0, netService) / 0.995) - netService) : 0;
                        valid = valid && (!enabled || netService > 0);
                        const net = total;
                        total = round(total + pph);
                        form.querySelector('[data-grand-total]').textContent = money(total);
                        form.querySelector('[data-pph-summary]').textContent = enabled ? 'PPh: ' + money(pph) + ' · Transfer netto: ' + money(net) : '';
                        const dpCash = amount(form, '[data-dp]');
                        const dp = round(dpCash + (net > 0 ? round(pph * dpCash / net) : 0));
                        const dpPph = total > 0 ? round(pph * dp / total) : 0;
                        if (form.querySelector('[data-settlement]')) form.querySelector('[data-settlement]').textContent = money(round(total - dp));
                        valid = valid && total > 0 && (!form.querySelector('[data-dp]') || (dp > 0 && dp < total));
                        form.querySelectorAll('[data-document-kind]').forEach(row => {
                            const kind = row.dataset.documentKind;
                            const value = kind === 'down_payment' ? dp : kind === 'settlement' ? round(total - dp) : total;
                            const paid = row.dataset.paid;
                            const kind = row.dataset.documentKind;
                            const tax = kind === 'settlement' ? round(pph - dpPph) : (kind === 'down_payment' ? dpPph : pph);
                            const cash = round(value - tax);
                            row.querySelector('[data-new-pph]').textContent = money(tax);
                            row.querySelector('[data-new-amount]').textContent = money(value);
                            row.querySelector('[data-new-balance]').textContent = paid === '' ? '—' : money(round(cash - Number(paid)));
                            valid = valid && (paid === '' || cash >= Number(paid));
                        });
                        form.querySelector('[data-revision-error]').textContent = valid ? '' : 'Periksa total, nominal DP, diskon, dan pembayaran yang sudah masuk.';
                        form.querySelector('[type=submit]').disabled = !valid;
                        return valid;
                    }
                    form.addEventListener('input', recalculate);
                    form.addEventListener('change', event => {
                        if (event.target.matches('[data-product]')) {
                            event.target.closest('tr').querySelector('input[data-name]').value = event.target.selectedOptions[0]?.dataset.name || '';
                        }
                        recalculate();
                    });
                    form.querySelector('[data-add-item]').addEventListener('click', () => {
                        const fragment = document.getElementById('revision-item-template').content.cloneNode(true);
                        fragment.querySelectorAll('[name]').forEach(el => el.name = el.name.replace('__INDEX__', nextIndex));
                        nextIndex++;
                        body.appendChild(fragment);
                        recalculate();
                    });
                    body.addEventListener('click', event => {
                        const button = event.target.closest('[data-remove-item]');
                        if (button && body.children.length > 1) { button.closest('tr').remove(); recalculate(); }
                    });
                    form.addEventListener('submit', event => { if (!recalculate()) event.preventDefault(); });
                    recalculate();
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
