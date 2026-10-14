<x-admin::layouts>
    <x-slot:title>Proforma Invoice</x-slot>
    @include('admin::invoices.partials.workspace-styles')
    <div class="crm-finance-workspace">
        <header class="fw-header">
            <div>
                <p class="fw-eyebrow">Penawaran / Penagihan</p>
                <h1>Proforma Invoice</h1>
                <p class="fw-subtitle">Kelola permintaan pembayaran, lalu terbitkan invoice saat event disetujui atau pembayaran diterima.</p>
            </div>
            @if (bouncer()->hasPermission('invoices.generate'))
                <a class="primary-button" href="{{ route('admin.invoices.billing.create') }}">+ Buat dari penawaran</a>
            @endif
        </header>
        @include('admin::invoices.proformas.follow-up', ['showProformaRows' => false])
        <section class="fw-card" aria-label="Daftar proforma">
            <div class="fw-between" style="padding-right:20px">
                <nav class="fw-tabs" aria-label="Filter status proforma">
                    @foreach (['' => 'Semua', 'issued' => 'Belum menjadi Invoice', 'converted' => 'Sudah menjadi Invoice', 'cancelled' => 'Dibatalkan'] as $value => $label)
                        <a href="{{ route('admin.proformas.index', $value ? ['status' => $value] : []) }}" @if ((string) $status === (string) $value) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <span class="fw-muted">{{ $proformas->total() }} dokumen</span>
            </div>
            <div class="fw-scroll">
                <table class="fw-table" style="min-width:1020px">
                    <thead><tr><th>Proforma</th><th>Penawaran / Proyek</th><th>Pelanggan</th><th class="fw-number">Nominal</th><th>Jatuh tempo</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($proformas as $proforma)
                            @php
                                $snapshot = $proforma->quote_snapshot['attributes'];
                                $isOverdue = $proforma->status === 'issued' && $proforma->due_at?->lt(today());
                            @endphp
                            <tr>
                                <td>
                                    @if (bouncer()->hasPermission('invoices.view'))
                                        <a class="fw-link" href="{{ route('admin.proformas.show', $proforma->id) }}">{{ $proforma->proforma_number }}</a>
                                    @else
                                        {{ $proforma->proforma_number }}
                                    @endif
                                    <small>{{ $proforma->issued_at?->format('d M Y') }}<br>Oleh {{ $proforma->creator?->name ?? '-' }}</small>
                                </td>
                                <td><strong>{{ $snapshot['subject'] ?? '-' }}</strong><small>{{ $snapshot['quote_number'] ?? '-' }} · {{ $snapshot['project_code'] ?? '-' }}</small></td>
                                <td>
                                    {{ ($snapshot['bill_to_company_name'] ?? '') ?: ($snapshot['bill_to_person_name'] ?? '-') }}
                                    @if (! empty($snapshot['bill_to_company_name']) && ! empty($snapshot['bill_to_person_name']))
                                        <small>{{ $snapshot['bill_to_person_name'] }}</small>
                                    @endif
                                </td>
                                <td class="fw-number"><strong>Rp {{ number_format((float) $proforma->amount, 2, ',', '.') }}</strong><small>{{ $proforma->billingLabel() }}</small></td>
                                <td>
                                    <span class="{{ $isOverdue ? 'fw-negative' : '' }}">{{ $proforma->due_at?->format('d M Y') }}</span>
                                    @if ($proforma->status === 'issued')
                                        <small>{{ $isOverdue ? 'Lewat jatuh tempo · ' : '' }}Umur {{ max(0, (int) $proforma->issued_at?->diffInDays(today(), false)) }} hari</small>
                                    @endif
                                </td>
                                <td><span class="fw-badge fw-badge-{{ $proforma->status }}">{{ ['issued' => 'Menunggu Admin Sales', 'converted' => 'Sudah masuk Invoice', 'cancelled' => 'Dibatalkan'][$proforma->status] ?? $proforma->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="fw-empty"><strong>Belum ada proforma pada status ini.</strong><br>Proforma yang dibuat dari penawaran akan muncul di sini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="fw-pad">{{ $proformas->links() }}</div>
        </section>
    </div>
</x-admin::layouts>
