<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $proforma->proforma_number }}</title>
    <style>
        body { font: 14px/1.6 Arial, sans-serif; color: #18232e; margin: 32px auto; max-width: 820px; padding: 0 24px; }
        header { border-bottom: 3px solid #e97930; padding-bottom: 16px; } h1 { margin-bottom: 0; }
        table { border-collapse: collapse; width: 100%; margin: 24px 0; } th, td { border-bottom: 1px solid #dce1e5; padding: 10px; text-align: left; vertical-align: top; }
        .amount { text-align: right; white-space: nowrap; } .total { font-size: 20px; font-weight: bold; } .terms { white-space: pre-line; }
        @media print { button { display: none; } body { max-width: none; margin: 0; } tr { break-inside: avoid; } }
        @page { size: A4; margin: 18mm; }
    </style>
</head>
<body>
    @php
        $snapshot = $proforma->quote_snapshot['attributes'];
        $mode = $snapshot['bill_to_display_mode'] ?? 'person';
    @endphp
    <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    <header>
        <strong>{{ config('company.legal_name', 'PT. VARBEL ANVAYA BERSAUDARA') }}</strong><br>
        {{ config('company.address_line_1', 'Jl. Tomang Asli No. 22 RT. 005 RW. 002, Jatipulo, Palmerah') }}<br>
        {{ config('company.address_line_2', 'Kota Administrasi Jakarta Barat, DKI Jakarta') }}
        <h1>PROFORMA INVOICE</h1>
        {{ $proforma->proforma_number }}
        @if ($proforma->status === 'cancelled')<strong> · DIBATALKAN</strong>@endif
        @if ($proforma->status === 'converted')<strong> · SUDAH DIPINDAHKAN KE INVOICE</strong>@endif
    </header>
    <table>
        <tr><td><strong>Kepada</strong><br>
            @if ($mode !== 'person'){{ $snapshot['bill_to_company_name'] ?? '-' }}<br>@endif
            @if ($mode !== 'company'){{ $snapshot['bill_to_person_name'] ?? '-' }}@endif
        </td><td>Tanggal: {{ $proforma->issued_at->format('d/m/Y') }}<br>Jatuh tempo: {{ $proforma->due_at->format('d/m/Y') }}</td></tr>
        <tr><td>Penawaran: {{ $snapshot['quote_number'] ?? '-' }}<br>Proyek: {{ $snapshot['subject'] ?? '-' }}</td><td>Kode proyek: {{ $snapshot['project_code'] ?? '-' }}<br>Event: {{ $snapshot['event_date'] ?? '-' }}</td></tr>
    </table>
    <table>
        <thead><tr><th>Permintaan pembayaran</th><th class="amount">Nominal</th></tr></thead>
        <tbody>
            <tr><td>Total penawaran yang disepakati</td><td class="amount">Rp {{ number_format((float) $snapshot['grand_total'], 2, ',', '.') }}</td></tr>
            <tr class="total"><td>{{ $proforma->billingLabel() }}</td><td class="amount">Rp {{ number_format((float) $proforma->amount, 2, ',', '.') }}</td></tr>
            @if ($proforma->billing_type === 'down_payment')
            <tr><td>Sisa untuk invoice pelunasan</td><td class="amount">Rp {{ number_format((float) $snapshot['grand_total'] - (float) $proforma->amount, 2, ',', '.') }}</td></tr>
            @endif
        </tbody>
    </table>
    <strong>TOP / Ketentuan pembayaran</strong>
    <p class="terms">{{ $snapshot['payment_term'] ?? '-' }}</p>
    @if (config('company.payment_info'))<p class="terms">{{ config('company.payment_info') }}</p>@endif
    <p>Proforma ini belum merupakan invoice aktual. Invoice diterbitkan setelah event disetujui atau pembayaran diterima.</p>
</body>
</html>
