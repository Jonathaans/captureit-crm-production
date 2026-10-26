<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $proforma->proforma_number }}</title>
    @php
        $snapshot = $proforma->quote_snapshot['attributes'];
        // Match the current billing portion, not the entire quote for DP/settlement.
        $fullPayment = $proforma->billing_type === 'full_payment';
        $subTotal = $fullPayment ? ($snapshot['sub_total'] ?? $proforma->amount) : $proforma->cashPayable();
        $discount = $fullPayment ? ($snapshot['discount_amount'] ?? 0) : 0;
        $identity = $proforma->billToIdentity();
        $mode = $identity['mode'];
        $money = static fn ($value) => 'Rp '.number_format((float) $value, 2, ',', '.');
        $address = $snapshot['billing_address'] ?? [];
        $address = is_array($address) ? $address : (json_decode($address, true) ?: []);
        $logoPath = public_path('images/logo-varbel.png');
        if (! file_exists($logoPath)) {
            $logoPath = public_path('images/logo varbel.png');
        }
        $logoData = file_exists($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
    @endphp
    <style>
        @page { size:A4; margin:20mm 12mm 18mm; }
        * { box-sizing:border-box; }
        body { margin:0; color:#1f2937; font:11px/1.65 Arial, sans-serif; background:#eef0f3; }
        .print-toolbar { max-width:210mm; margin:24px auto 16px; padding:0 12px; display:flex; align-items:center; justify-content:space-between; gap:16px; font-size:13px; color:#64748b; }
        .print-toolbar button { border:0; border-radius:8px; background:#172033; color:#fff; padding:12px 20px; cursor:pointer; font:600 13px Arial,sans-serif; }
        .paper { width:210mm; min-height:297mm; padding:20mm 12mm 18mm; margin:0 auto 32px; background:white; box-shadow:0 8px 32px #18232e12; }
        table { width:100%; border-collapse:collapse; table-layout:fixed; }
        th, td { text-align:left; vertical-align:top; overflow-wrap:anywhere; }
        .top-table td { vertical-align:middle; }
        .logo-cell { width:52%; }
        .company-cell { width:48%; padding-left:24px; color:#111827; }
        .logo { max-width:170px; max-height:82px; }
        .company-name { margin:0 0 3px; font-size:12px; line-height:1.25; font-weight:bold; }
        .company-line { margin:1px 0; font-size:8px; line-height:1.45; }
        .header-rule { margin-top:12px; border-top:2px solid #d5aa2a; }
        .document-title { text-align:center; padding:24px 0 26px; }
        h1 { margin:0; color:#111827; font-size:24px; letter-spacing:2px; line-height:1.4; }
        .document-number { margin-top:6px; color:#64748b; font-size:11px; }
        .document-status { margin:12px 0 0; color:#9a5c1c; font-size:10px; font-weight:bold; }
        .void-note { padding:12px 14px; border:1px solid #b55a5a; color:#8f3333; margin-bottom:20px; }
        .meta-table { margin-bottom:24px; }
        .meta-table > tbody > tr > td:first-child { width:55%; padding-right:24px; }
        .meta-table > tbody > tr > td:last-child { width:45%; }
        .label { margin-bottom:6px; font-size:9px; font-weight:bold; letter-spacing:.9px; text-transform:uppercase; color:#6b7280; }
        .customer-name { font-size:12px; font-weight:bold; margin:0 0 4px; }
        .metadata td { padding:3px 0; font-size:10px; }
        .metadata td:first-child { width:43%; color:#64748b; }
        .metadata td:last-child { font-weight:bold; }
        .project { border-top:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; padding:15px 0; margin-bottom:24px; }
        .project strong { font-size:12px; }
        .project-line { color:#64748b; margin-top:4px; font-size:10px; }
        .billing th { background:#f3f4f6; padding:11px 14px; font-size:9px; letter-spacing:.6px; text-transform:uppercase; color:#4b5563; border-bottom:1px solid #d1d5db; }
        .billing td { padding:18px 14px; border-bottom:1px solid #e5e7eb; }
        .billing .description { font-size:12px; font-weight:bold; }
        .billing .sub { color:#6b7280; font-size:10px; margin-top:4px; }
        .amount { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .totals { margin:18px 0 28px; }
        .totals td { padding:7px 14px; }
        .totals .total td { padding:13px 14px; border-top:2px solid #d5aa2a; border-bottom:1px solid #e5e7eb; font-weight:bold; font-size:15px; }
        .terms-heading { margin:0 0 8px; font-size:11px; font-weight:bold; break-after:avoid; }
        .terms { white-space:pre-line; overflow-wrap:anywhere; margin:0 0 20px; font-size:10px; line-height:1.8; }
        .note { margin-top:28px; padding-top:14px; border-top:1px solid #e5e7eb; color:#6b7280; font-size:9px; line-height:1.7; }
        @media screen and (max-width:820px) { .paper { width:100%; min-height:auto; padding:32px 20px; } .print-toolbar { flex-wrap:wrap; } .company-cell { padding-left:12px; } .logo { max-width:140px; } h1 { font-size:21px; } }
        @media print {
            body { background:#fff; }
            .print-toolbar, #crm-comm-floating, #crm-comm-toasts { display:none !important; }
            .paper { width:auto; min-height:0; margin:0; padding:0; box-shadow:none; }
            .top-table, .document-title, .project, tr { break-inside:avoid; }
            thead { display:table-header-group; }
        }
    </style>
</head>
<body>
    <div class="print-toolbar"><span>Proforma Invoice · Format A4</span><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
    <main class="paper">
        {{-- Match the existing Quote letterhead: logo, legal identity, and gold rule. --}}
        <table class="top-table">
            <tr>
                <td class="logo-cell">
                    @if ($logoData)<img class="logo" src="{{ $logoData }}" alt="Logo perusahaan">@endif
                </td>
                <td class="company-cell">
                    <div class="company-name">{{ config('company.legal_name', 'PT. VARBEL ANVAYA BERSAUDARA') }}</div>
                    <div class="company-line">{{ config('company.address_line_1', 'Jl. Tomang Asli No. 22 RT. 005 RW. 002, Jatipulo, Palmerah') }}</div>
                    <div class="company-line">{{ config('company.address_line_2', 'Kota Administrasi Jakarta Barat, DKI Jakarta') }}</div>
                    <div class="company-line"><strong>Telp:</strong> {{ config('company.phone', '081585573616') }}</div>
                    <div class="company-line"><strong>Email:</strong> {{ config('company.email', 'financephoto.360@gmail.com') }}</div>
                    <div class="company-line"><strong>NPWP:</strong> {{ config('company.npwp', '1000.0000.1027.6657') }}</div>
                </td>
            </tr>
        </table>
        <div class="header-rule"></div>
        <div class="document-title">
            <h1>PROFORMA INVOICE</h1>
            <div class="document-number">{{ $proforma->proforma_number }}</div>
            @if ($proforma->status === 'cancelled')<p class="document-status">DIBATALKAN</p>@endif
            @if ($proforma->status === 'converted')<p class="document-status">SUDAH DIPINDAHKAN KE INVOICE</p>@endif
        </div>
        @if ($proforma->invoice?->status === 'void')
            <div class="void-note"><strong>INVOICE HASIL KONVERSI SUDAH VOID.</strong> Proforma ini merupakan riwayat.
                @if ($proforma->invoice->replaced_by_invoice_id)
                    Invoice DP telah diganti dengan {{ $proforma->invoice->replacementInvoice->invoice_number }}.
                @elseif ($proforma->invoice->voidCorrection?->kind === 'invoice_cancel')
                    Tagihan pada invoice tersebut telah dibatalkan.
                @else
                    Catatan pembayaran pada invoice tersebut dibatalkan sebagai salah input.
                @endif
            </div>
        @endif
        <table class="meta-table">
            <tr>
                <td>
                    <div class="label">Ditagihkan kepada</div>
                    @if ($mode !== 'person')<div class="customer-name">{{ $identity['company_name'] ?? '-' }}</div>@endif
                    @if ($mode !== 'company')<div @class(['customer-name' => $mode === 'person'])>{{ $identity['person_name'] ?? '-' }}</div>@endif
                    @if (! empty($address['address']))<div style="white-space:pre-line">{{ $address['address'] }}</div>@endif
                    <div>{{ implode(', ', array_filter([$address['city'] ?? '', $address['state'] ?? '', $address['postcode'] ?? ''])) }}</div>
                </td>
                <td>
                    <table class="metadata">
                        <tr><td>Tanggal terbit</td><td>{{ $proforma->issued_at->format('d M Y') }}</td></tr>
                        <tr><td>Jatuh tempo</td><td>{{ $proforma->due_at->format('d M Y') }}</td></tr>
                        <tr><td>No. penawaran</td><td>{{ $snapshot['quote_number'] ?? '-' }}</td></tr>
                        <tr><td>Kode proyek</td><td>{{ $snapshot['project_code'] ?? '-' }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
        <div class="project">
            <div class="label">Proyek / Acara</div>
            <strong>{{ $snapshot['subject'] ?? '-' }}</strong>
            <div class="project-line">Tanggal event: {{ ! empty($snapshot['event_date']) ? \Carbon\Carbon::parse($snapshot['event_date'])->format('d M Y') : '-' }} @if (! empty($snapshot['location'])) · {{ $snapshot['location'] }} @endif</div>
        </div>
        <table class="billing">
            <thead><tr><th style="width:62%">Permintaan pembayaran</th><th class="amount">Nominal</th></tr></thead>
            <tbody><tr><td><div class="description">{{ $proforma->billingLabel() }}</div><div class="sub">Sesuai penawaran {{ $snapshot['quote_number'] ?? '-' }} dan ketentuan pembayaran yang disepakati.</div></td><td class="amount"><strong>{{ $money($proforma->amount) }}</strong></td></tr></tbody>
        </table>
        <table class="totals">
            <tr><td>Sub Total</td><td class="amount">{{ $money($subTotal) }}</td></tr>
            <tr><td>Discount</td><td class="amount">{{ $money($discount) }}</td></tr>
            <tr class="total"><td>GRAND TOTAL</td><td class="amount">{{ $money($proforma->amount) }}</td></tr>
        </table>
        <h2 class="terms-heading">TOP / Ketentuan pembayaran</h2>
        <p class="terms">{{ $snapshot['payment_term'] ?? '-' }}</p>
        @if (config('company.payment_info'))
            <h2 class="terms-heading">Informasi pembayaran</h2>
            <p class="terms">{{ config('company.payment_info') }}</p>
        @endif
        <p class="note">Proforma ini merupakan permintaan pembayaran dan belum menjadi invoice aktual. Invoice diterbitkan setelah event disetujui atau pembayaran diterima. Mohon cantumkan nomor proforma pada konfirmasi pembayaran.</p>
    </main>
</body>
</html>
