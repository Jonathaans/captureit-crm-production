<section class="fw-stack">
    <h2 class="fw-title">Riwayat revisi</h2>
    @forelse ($history as $revision)
        @php
            $beforeRevision = json_decode($revision->before, true);
            $afterRevision = json_decode($revision->after, true);
            $labels = ['subject' => 'Nama event', 'bill_to_display_mode' => 'Tampilan Bill To', 'bill_to_person_name' => 'Contact', 'bill_to_company_name' => 'Perusahaan', 'event_date' => 'Tanggal event', 'location' => 'Lokasi', 'payment_term' => 'Ketentuan pembayaran', 'description' => 'Deskripsi', 'billing_address' => 'Alamat penagihan', 'shipping_address' => 'Alamat pengiriman', 'grand_total' => 'Total transaksi'];
            $displayValue = static function ($field, $value) use ($money) {
                if ($field === 'grand_total') return $money($value);
                if (in_array($field, ['billing_address', 'shipping_address'])) return implode(', ', array_filter(json_decode($value ?? '[]', true) ?: []));
                if ($field === 'bill_to_display_mode') return ['person' => 'Contact', 'company' => 'Perusahaan', 'both' => 'Contact & perusahaan'][$value] ?? $value;
                return $value ?: '—';
            };
        @endphp
        <details class="fw-card fw-details">
            <summary><span><strong>Revisi {{ $revision->version }}</strong> · {{ $revision->actor_name }}<span class="fw-muted block">{{ \Carbon\Carbon::parse($revision->created_at)->format('d M Y H:i') }} · {{ $revision->reason }}</span></span><span class="fw-muted">{{ $money($beforeRevision['quote']['grand_total']) }} → {{ $money($afterRevision['quote']['grand_total']) }}</span></summary>
            <div class="fw-scroll"><table class="fw-table"><thead><tr><th>Data</th><th>Sebelum</th><th>Sesudah</th></tr></thead><tbody>
                @foreach ($labels as $field => $label)
                    @if (($beforeRevision['quote'][$field] ?? null) != ($afterRevision['quote'][$field] ?? null))
                        <tr><td>{{ $label }}</td><td style="white-space:pre-wrap">{{ $displayValue($field, $beforeRevision['quote'][$field] ?? null) }}</td><td style="white-space:pre-wrap">{{ $displayValue($field, $afterRevision['quote'][$field] ?? null) }}</td></tr>
                    @endif
                @endforeach
                @foreach ($afterRevision['proformas'] ?? [] as $afterProforma)
                    @php $beforeProforma = collect($beforeRevision['proformas'] ?? [])->firstWhere('id', $afterProforma['id']); @endphp
                    @if ($beforeProforma && ($beforeProforma['billing_type'] !== $afterProforma['billing_type'] || (float) $beforeProforma['amount'] !== (float) $afterProforma['amount']))
                        <tr><td>{{ $afterProforma['proforma_number'] }} · Jenis / nilai proforma</td><td>{{ $kindLabel($beforeProforma['billing_type']) }} · {{ $money($beforeProforma['amount']) }}</td><td>{{ $kindLabel($afterProforma['billing_type']) }} · {{ $money($afterProforma['amount']) }}</td></tr>
                    @endif
                @endforeach
                <tr><td>Produk</td>@foreach ([$beforeRevision, $afterRevision] as $snapshot)<td>@foreach ($snapshot['quote_items'] as $line)<p>{{ $line['name'] }} · {{ $line['quantity'] }} × {{ $money($line['price']) }}</p>@endforeach</td>@endforeach</tr>
                @foreach ($afterRevision['invoices'] as $afterInvoice)
                    @php $beforeInvoice = collect($beforeRevision['invoices'])->firstWhere('id', $afterInvoice['id']); @endphp
                    @if (! $beforeInvoice || (float) $beforeInvoice['grand_total'] !== (float) $afterInvoice['grand_total'] || $beforeInvoice['status'] !== $afterInvoice['status'] || $beforeInvoice['billing_type'] !== $afterInvoice['billing_type'])
                        <tr><td>{{ $afterInvoice['invoice_number'] }}</td><td>{{ $beforeInvoice ? $kindLabel($beforeInvoice['billing_type']).' · '.$money($beforeInvoice['grand_total']).' · '.strtoupper($beforeInvoice['status']) : 'Belum diterbitkan' }}</td><td>{{ $kindLabel($afterInvoice['billing_type']) }} · {{ $money($afterInvoice['grand_total']) }} · {{ strtoupper($afterInvoice['status']) }}</td></tr>
                    @endif
                @endforeach
            </tbody></table></div>
        </details>
    @empty
        <p class="fw-muted">Belum ada revisi transaksi.</p>
    @endforelse
</section>
