<x-admin::layouts>
    <x-slot:title>{{ $proforma->proforma_number }}</x-slot>
    @include('admin::invoices.partials.workspace-styles')
    @php
        $snapshot = $proforma->quote_snapshot['attributes'];
        $money = static fn ($value) => 'Rp '.number_format((float) $value, 2, ',', '.');
        $paymentErrors = $errors->hasAny(['amount', 'paid_at', 'payment_method', 'reference_number', 'notes']);
    @endphp
    <div class="crm-finance-workspace">
        <header class="fw-header">
            <div>
                @if (bouncer()->hasPermission('invoices'))
                    <a class="fw-link" href="{{ route('admin.proformas.index') }}">&larr; Daftar proforma</a>
                @endif
                <p class="fw-eyebrow" style="margin-top:20px">Proforma Invoice</p>
                <h1>{{ $proforma->proforma_number }}</h1>
                <p class="fw-subtitle">{{ $snapshot['subject'] ?? '-' }}</p>
            </div>
            <div class="fw-actions">
                <span class="fw-badge fw-badge-{{ $proforma->status }}">{{ ['issued' => 'Menunggu Admin Sales', 'converted' => 'Sudah menjadi Invoice', 'cancelled' => 'Dibatalkan'][$proforma->status] ?? $proforma->status }}</span>
                @if (bouncer()->hasPermission('invoices.print'))
                    <a class="secondary-button" target="_blank" rel="noopener" href="{{ route('admin.proformas.print', $proforma->id) }}">Cetak / Simpan PDF</a>
                @endif
            </div>
        </header>
        @if ($errors->any())
            <div role="alert" class="rounded-xl bg-red-50 p-4 text-red-700"><strong>Periksa kembali isian berikut.</strong><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <div class="fw-two-columns">
            <div class="fw-stack">
                <section class="fw-card">
                    <h2 class="fw-title fw-card-heading">Informasi penagihan</h2>
                    <dl class="fw-definition fw-pad">
                        <div><dt>Pelanggan / Perusahaan</dt><dd><strong>{{ $snapshot['bill_to_company_name'] ?? '-' }}</strong><br>{{ $snapshot['bill_to_person_name'] ?? '-' }}</dd></div>
                        <div><dt>Penawaran / Kode proyek</dt><dd>{{ $snapshot['quote_number'] ?? '-' }}<br>{{ $snapshot['project_code'] ?? '-' }}</dd></div>
                        <div><dt>Tanggal proforma</dt><dd>{{ $proforma->issued_at->format('d M Y') }}</dd></div>
                        <div><dt>Tanggal event</dt><dd>{{ ! empty($snapshot['event_date']) ? \Carbon\Carbon::parse($snapshot['event_date'])->format('d M Y') : '-' }}</dd></div>
                        <div style="grid-column:1 / -1"><dt>TOP / Ketentuan pembayaran</dt><dd style="white-space:pre-line">{{ $snapshot['payment_term'] ?? '-' }}</dd></div>
                    </dl>
                </section>
                @if ($proforma->invoice)
                    <section class="fw-card fw-pad">
                        <div class="fw-between"><h2 class="fw-title">Invoice telah diterbitkan</h2><span class="fw-badge {{ $proforma->invoice->status === 'void' ? 'fw-badge-cancelled' : 'fw-badge-converted' }}">{{ strtoupper($proforma->invoice->status) }}</span></div>
                        <p style="margin:16px 0;font-weight:600">{{ $proforma->invoice->invoice_number }} · {{ $proforma->billingLabel() }}</p>
                        @if ($proforma->invoice->status === 'void')
                            <p class="fw-note">Invoice hasil konversi sudah VOID. Catatan pembayaran sebelumnya dibatalkan sebagai salah input, tanpa uang diterima. Proforma ini disimpan sebagai riwayat.</p>
                        @endif
                        <p class="fw-muted">Alasan konversi semula: {{ $proforma->conversion_reason === 'event_confirmed' ? 'Event sudah deal / oke' : 'Pembayaran diterima' }}.<br>Dipindahkan {{ $proforma->converted_at?->format('d/m/Y H:i') }} oleh {{ $proforma->converter?->name ?? '-' }}.</p>
                        @if ($proforma->conversion_note)<p class="fw-subtitle">{{ $proforma->conversion_note }}</p>@endif
                        <div class="fw-actions" style="margin-top:20px">
                            @if (bouncer()->hasPermission('invoices.view'))
                                <a class="primary-button" href="{{ route('admin.invoices.show', $proforma->invoice_id) }}">Buka Invoice</a>
                            @endif
                            @if ($proforma->billing_type === 'down_payment' && $proforma->invoice->status === 'paid' && bouncer()->hasPermission('invoices.generate'))
                                <a class="fw-link" href="{{ route('admin.invoices.billing.create', ['quote_id' => $proforma->quote_id, 'billing_type' => 'settlement']) }}">Buat proforma pelunasan &rarr;</a>
                            @endif
                        </div>
                    </section>
                @endif
                @if ($proforma->status === 'issued' && bouncer()->hasPermission('invoices.generate'))
                    <div><p class="fw-eyebrow">Langkah berikutnya</p><p class="fw-muted">Pilih kondisi yang sesuai untuk menerbitkan invoice.</p></div>
                    <details class="fw-card fw-details" @if (! $paymentErrors && ! $errors->has('reason')) open @endif>
                        <summary><span><span class="fw-title">Event sudah deal / disetujui</span><span class="fw-muted block">Terbitkan invoice UNPAID, lalu catat pembayaran saat diterima.</span></span></summary>
                        <form method="POST" action="{{ route('admin.proformas.approve', $proforma->id) }}" class="fw-pad">
                            @csrf
                            <p class="fw-muted">Gunakan setelah event disetujui. SPK dapat dibuat dari invoice yang diterbitkan.</p>
                            <label class="fw-check"><input type="checkbox" name="event_confirmed" value="1" required @checked(old('event_confirmed'))>Event sudah deal / disetujui.</label>
                            <label class="fw-field">Catatan konfirmasi (opsional)<textarea name="confirmation_note" maxlength="2000">{{ old('confirmation_note') }}</textarea></label>
                            <button class="primary-button mt-4" type="submit">Event sudah deal — pindahkan ke Invoice</button>
                        </form>
                    </details>
                @endif
                @if ($proforma->status === 'issued' && bouncer()->hasPermission('invoices.generate') && bouncer()->hasPermission('invoices.payment'))
                    <details class="fw-card fw-details" @if ($paymentErrors) open @endif>
                        <summary><span><span class="fw-title">Pembayaran diterima</span><span class="fw-muted block">Terbitkan invoice PAID dan catat penerimaan sekaligus.</span></span></summary>
                        <form method="POST" action="{{ route('admin.proformas.payment', $proforma->id) }}" class="fw-pad">
                            @csrf
                            <p class="fw-note" style="margin-bottom:20px">Isi sesuai uang yang sudah masuk sebesar nominal proforma. Untuk pembayaran bertahap, gunakan konfirmasi event lalu catat penerimaan di Invoice.</p>
                            <div class="fw-form-grid">
                                <label class="fw-field">Nominal diterima (Rp)<input name="amount" type="number" min="0.01" step="0.01" required value="{{ old('amount', $proforma->amount) }}"></label>
                                <label class="fw-field">Waktu uang diterima<input name="paid_at" type="datetime-local" required value="{{ old('paid_at', now()->format('Y-m-d\TH:i')) }}"></label>
                                <label class="fw-field">Metode pembayaran<input name="payment_method" required maxlength="255" placeholder="Transfer bank" value="{{ old('payment_method') }}"></label>
                                <label class="fw-field">Nomor referensi transfer<input name="reference_number" maxlength="255" value="{{ old('reference_number') }}"></label>
                            </div>
                            <label class="fw-field" style="margin-top:18px">Catatan<textarea name="notes" maxlength="5000">{{ old('notes') }}</textarea></label>
                            <button class="primary-button mt-4" type="submit">Pembayaran diterima — pindahkan ke Invoice</button>
                        </form>
                    </details>
                @endif
                @if ($proforma->status === 'issued' && bouncer()->hasPermission('invoices.event-status'))
                    <details class="fw-card fw-details" @if ($errors->has('reason')) open @endif>
                        <summary><span class="fw-muted">Batalkan proforma</span></summary>
                        <form method="POST" action="{{ route('admin.proformas.cancel', $proforma->id) }}" class="fw-pad">
                            @csrf
                            <label class="fw-field">Alasan pembatalan<textarea name="reason" required maxlength="2000">{{ old('reason') }}</textarea></label>
                            <button class="secondary-button mt-3" type="submit">Batalkan proforma</button>
                        </form>
                    </details>
                @elseif ($proforma->status === 'cancelled')
                    <p class="fw-note">Dibatalkan: {{ $proforma->cancellation_reason }}</p>
                @endif
            </div>
            <aside class="fw-stack" aria-label="Ringkasan nominal">
                <section class="fw-card fw-pad fw-highlight">
                    <p class="fw-eyebrow">{{ $proforma->billingLabel() }}</p>
                    <p class="fw-muted">Nominal proforma</p>
                    <p class="fw-amount">{{ $money($proforma->amount) }}</p>
                    <span class="fw-badge fw-badge-issued">Jatuh tempo {{ $proforma->due_at->format('d M Y') }}</span>
                    <div class="fw-divider"></div>
                    <div class="fw-between fw-muted"><span>Total penawaran</span><strong>{{ $money($snapshot['grand_total']) }}</strong></div>
                    @if ($proforma->billing_type === 'down_payment')
                        <div class="fw-between fw-muted" style="margin-top:12px"><span>Sisa pelunasan</span><strong>{{ $money((float) $snapshot['grand_total'] - (float) $proforma->amount) }}</strong></div>
                    @endif
                </section>
                <p class="fw-note">Proforma adalah permintaan pembayaran. Nilainya masuk laporan keuangan setelah diterbitkan menjadi invoice.</p>
            </aside>
        </div>
    </div>
</x-admin::layouts>
