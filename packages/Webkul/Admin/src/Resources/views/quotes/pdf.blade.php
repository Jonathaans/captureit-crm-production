<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">

    @php
        $locale = app()->getLocale();

        /*
         * Logo:
         * Prefer logo-varbel.png, but still support the current filename
         * "logo varbel.png" so the PDF works immediately.
         */
        $logoPath = public_path('images/logo-varbel.png');

        if (! file_exists($logoPath)) {
            $logoPath = public_path('images/logo varbel.png');
        }

        $logoData = null;

        if (file_exists($logoPath)) {
            $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
            $mime = $extension === 'jpg' || $extension === 'jpeg'
                ? 'image/jpeg'
                : 'image/png';

            $logoData = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($logoPath));
        }

        /*
         * Company identity for quotation header.
         *
         * Each value can still be overridden from config/company.php later,
         * but these fallbacks keep the PDF immediately usable.
         */
        $companyName = config('app.name', 'Varbel Corps');

        $companyLegalName = config(
            'company.legal_name',
            'PT. VARBEL ANVAYA BERSAUDARA'
        );

        $companyAddressLine1 = config(
            'company.address_line_1',
            'Jl. Tomang Asli No. 22 RT. 005 RW. 002, Jatipulo, Palmerah'
        );

        $companyAddressLine2 = config(
            'company.address_line_2',
            'Kota Administrasi Jakarta Barat, DKI Jakarta'
        );

        $companyPhone = config(
            'company.phone',
            '081585573616'
        );

        $companyEmail = config(
            'company.email',
            'financephoto.360@gmail.com'
        );

        $companyNpwp = config(
            'company.npwp',
            '1000.0000.1027.6657'
        );

        $paymentInfo = config('company.payment_info');

        /*
         * Professional quotation number.
         * Falls back to the old ID only for legacy quotes.
         */
        $quoteNumber = $quote->quote_number ?? '#'.$quote->id;

        $billingAddress = is_array($quote->billing_address)
            ? $quote->billing_address
            : [];

        $addressLine = $billingAddress['address'] ?? '';

        $cityLine = trim(
            implode(' ', array_filter([
                $billingAddress['postcode'] ?? null,
                $billingAddress['city'] ?? null,
            ]))
        );

        $stateLine = $billingAddress['state'] ?? '';

        $countryLine = ! empty($billingAddress['country'])
            ? core()->country_name($billingAddress['country'])
            : '';

        $createdDate = $quote->created_at
            ? core()->formatDate($quote->created_at, 'd M Y')
            : '-';

        $eventDate = $quote->event_date
            ? core()->formatDate($quote->event_date, 'd M Y')
            : '-';

        $validUntil = $quote->expired_at
            ? core()->formatDate($quote->expired_at, 'd M Y')
            : '-';
    @endphp

    <style>
        @page {
            /*
             * Reserve a safe zone for the fixed footer on every page.
             * Content will continue on page 2 before reaching the footer.
             */
            /* CRM_DOCUMENT_PDF_SAFE_TOP_BOUNDARY_V1: 20 mm text-safe top boundary on every A4 page. */
            margin: 76px 28px 72px 28px;
        }

        * {
            box-sizing: border-box;
            font-family: "DejaVu Sans", Arial, sans-serif;
        }

        body {
            margin: 0;
            color: #1f2937;
            font-size: 10px;
            line-height: 1.45;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .top-table td {
            vertical-align: middle;
        }

        .logo-cell {
            width: 52%;
            text-align: left;
        }

        .company-cell {
            width: 48%;
            padding-left: 24px;
            text-align: left;
            color: #111827;
        }

        .logo {
            max-width: 170px;
            max-height: 82px;
        }

        .company-name {
            margin: 0 0 3px 0;
            color: #111827;
            font-size: 12px;
            line-height: 1.25;
            font-weight: bold;
        }

        .company-line {
            margin: 1px 0;
            color: #111827;
            font-size: 8px;
            line-height: 1.45;
        }

        .company-label {
            font-weight: bold;
        }

        .header-rule {
            margin-top: 12px;
            border-top: 2px solid #d5aa2a;
        }

        .document-title {
            padding: 18px 0 14px 0;
            text-align: center;
        }

        .document-title h1 {
            margin: 0;
            color: #111827;
            font-size: 24px;
            letter-spacing: 2px;
        }

        .document-number {
            margin-top: 4px;
            color: #6b7280;
            font-size: 10px;
        }

        .info-table {
            margin-top: 4px;
            margin-bottom: 18px;
        }

        .info-table td {
            width: 50%;
            vertical-align: top;
        }

        .left-info {
            padding-right: 22px;
        }

        .right-info {
            padding-left: 22px;
        }

        .section-label {
            margin-bottom: 7px;
            color: #111827;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .8px;
        }

        .customer-name {
            margin-bottom: 4px;
            color: #111827;
            font-size: 12px;
            font-weight: bold;
        }

        .address-line {
            margin: 1px 0;
            color: #4b5563;
        }

        .project-row {
            margin-bottom: 4px;
        }

        .project-label {
            display: inline-block;
            width: 92px;
            color: #6b7280;
            font-size: 9px;
        }

        .project-value {
            color: #111827;
            font-weight: bold;
        }

        .items-table {
            margin-top: 6px;
        }

        .items-table thead th {
            padding: 8px 7px;
            background: #111827;
            color: #ffffff;
            border: 1px solid #111827;
            font-size: 8px;
            text-align: center;
            text-transform: uppercase;
        }

        .items-table tbody td {
            padding: 9px 7px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .items-table .no {
            width: 7%;
            text-align: center;
        }

        .items-table .package {
            width: 18%;
        }

        .items-table .description {
            width: 28%;
        }

        .items-table .day,
        .items-table .qty {
            width: 8%;
            text-align: center;
        }

        .items-table .price,
        .items-table .total {
            width: 15%;
            text-align: right;
        }
        .page-break {
            page-break-before: always;
        }

        .items-table {
            page-break-inside: auto;
        }

        .items-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .items-table thead {
            /* Repeat the item header automatically on page 2+. */
            display: table-header-group;
        }

        .items-table tbody {
            display: table-row-group;
        }
        .item-name {
            color: #111827;
            font-weight: bold;
        }

        .item-sku {
            margin-top: 2px;
            color: #9ca3af;
            font-size: 7px;
        }

        .summary-wrap {
            margin-top: 12px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .summary-table {
            width: 42%;
            margin-left: auto;
        }

        .summary-table td {
            padding: 4px 6px;
        }

        .summary-label {
            color: #6b7280;
        }

        .summary-value {
            text-align: right;
            color: #111827;
        }

        .grand-total td {
            padding-top: 8px;
            padding-bottom: 8px;
            border-top: 2px solid #111827;
            color: #111827;
            font-size: 11px;
            font-weight: bold;
        }

        .description-box {
            margin-top: 16px;
            page-break-inside: avoid;
            break-inside: avoid;
            padding: 10px 12px;
            background: #f9fafb;
            border-left: 3px solid #d5aa2a;
        }

        .description-box strong {
            display: block;
            margin-bottom: 4px;
            color: #111827;
        }

        .bottom-table {
            margin-top: 30px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .bottom-table td {
            width: 50%;
            vertical-align: top;
        }

        .payment-box {
            padding-right: 25px;
        }

        .payment-text {
            color: #4b5563;
            white-space: pre-line;
        }

        .signature-box {
            padding-left: 25px;
            text-align: center;
        }

        .signature-space {
            height: 110px;
        }

        .signature-line {
            width: 180px;
            margin: 0 auto 5px auto;
            border-top: 1px solid #111827;
        }

        .signature-name {
            font-weight: bold;
            color: #111827;
            line-height: 1.45;
        }

.footer {
    position: fixed;

    left: 0;
    right: 0;
    /*
             * IMPORTANT FOR DOMPDF:
             * The footer must live INSIDE the reserved @page bottom margin.
             * A positive/zero bottom value places the fixed footer back inside
             * the normal content area and can touch the Director/signature.
             */
            bottom: -42px;

    padding-top: 8px;
    padding-bottom: 4px;

    border-top: 1px solid #e5e7eb;

    text-align: center;

    color: #9ca3af;
    font-size: 7px;
}
    </style>
</head>

<body>
    <!-- KOP -->
    <table class="top-table">
        <tr>
            <td class="logo-cell">
                @if ($logoData)
                    <img
                        class="logo"
                        src="{{ $logoData }}"
                        alt="Logo"
                    >
                @endif
            </td>

            <td class="company-cell">
                <div class="company-name">
                    {{ $companyLegalName }}
                </div>

                <div class="company-line">
                    {{ $companyAddressLine1 }}
                </div>

                <div class="company-line">
                    {{ $companyAddressLine2 }}
                </div>

                <div class="company-line">
                    <span class="company-label">Telp:</span>
                    {{ $companyPhone }}
                </div>

                <div class="company-line">
                    <span class="company-label">Email:</span>
                    {{ $companyEmail }}
                </div>

                <div class="company-line">
                    <span class="company-label">NPWP:</span>
                    {{ $companyNpwp }}
                </div>
            </td>
        </tr>
    </table>

    <div class="header-rule"></div>

    <!-- Document Title -->
    <div class="document-title">
        <h1>QUOTATION</h1>

        <div class="document-number">
            {{ $quoteNumber }}
        </div>
    </div>

    <!-- Customer + Project Details -->
    <table class="info-table">
        <tr>
            <td class="left-info">
                <div class="section-label">
                    Bill To
                </div>

                <div class="customer-name">
                    {{ $quote->person->name ?? '-' }}
                </div>

                @if ($addressLine)
                    <div class="address-line">{{ $addressLine }}</div>
                @endif

                @if ($cityLine)
                    <div class="address-line">{{ $cityLine }}</div>
                @endif

                @if ($stateLine)
                    <div class="address-line">{{ $stateLine }}</div>
                @endif

                @if ($countryLine)
                    <div class="address-line">{{ $countryLine }}</div>
                @endif
            </td>

            <td class="right-info">
                <div class="project-row">
                    <span class="project-label">Date</span>
                    <span class="project-value">: {{ $createdDate }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Project Name</span>
                    <span class="project-value">: {{ $quote->subject ?? '-' }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Project Code</span>
                    <span class="project-value">: {{ $quote->project_code ?? '-' }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Event Date</span>
                    <span class="project-value">: {{ $eventDate }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Location</span>
                    <span class="project-value">: {{ $quote->location ?? '-' }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Payment Term</span>
                    <span class="project-value">: {{ $quote->payment_term ?? '-' }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Valid Until</span>
                    <span class="project-value">: {{ $validUntil }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Quote Items -->
    <table class="items-table">
        <thead>
            <tr>
                <th>No.</th>
                <th>Package</th>
                <th>Description</th>
                <th>Day</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($quote->items as $index => $item)
                <tr>
                    <td class="no">
                        {{ $index + 1 }}
                    </td>

                    <td class="package">
                        <div class="item-name">
                            {{ $item->name }}
                        </div>

                        @if (! empty($item->sku))
                            <div class="item-sku">
                                SKU: {{ $item->sku }}
                            </div>
                        @endif
                    </td>

                    <td class="description">
                        {{ $item->description ?? '-' }}
                    </td>

                    <td class="day">
                        {{ $item->day ?? 1 }}
                    </td>

                    <td class="qty">
                        {{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}
                    </td>

                    <td class="price">
                        {!! core()->formatBasePrice($item->price, true) !!}
                    </td>

                    <td class="total">
                        {!! core()->formatBasePrice(
                            $item->total
                            + $item->tax_amount
                            - $item->discount_amount,
                            true
                        ) !!}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="padding: 18px; text-align: center; color: #9ca3af;">
                        No items.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Summary -->
    <div class="summary-wrap">
        <table class="summary-table">
            <tr>
                <td class="summary-label">
                    Sub Total
                </td>

                <td class="summary-value">
                    {!! core()->formatBasePrice($quote->sub_total, true) !!}
                </td>
            </tr>

            <tr>
                <td class="summary-label">
                    Discount
                </td>

                <td class="summary-value">
                    {!! core()->formatBasePrice($quote->discount_amount, true) !!}
                </td>
            </tr>

            <tr>
                <td class="summary-label">
                    Tax
                </td>

                <td class="summary-value">
                    {!! core()->formatBasePrice($quote->tax_amount, true) !!}
                </td>
            </tr>

            @if ((float) $quote->adjustment_amount !== 0.0)
                <tr>
                    <td class="summary-label">
                        Adjustment
                    </td>

                    <td class="summary-value">
                        {!! core()->formatBasePrice($quote->adjustment_amount, true) !!}
                    </td>
                </tr>
            @endif

            <tr class="grand-total">
                <td>
                    GRAND TOTAL
                </td>

                <td class="summary-value">
                    {!! core()->formatBasePrice($quote->grand_total, true) !!}
                </td>
            </tr>
        </table>
    </div>

    <!-- Optional Project Description -->
    @if ($quote->description)
        <div class="description-box">
            <strong>Notes</strong>
            {{ $quote->description }}
        </div>
    @endif

    <!-- Payment + Signature -->
    <table class="bottom-table">
        <tr>
            <td class="payment-box">
                <div class="section-label">
                    Payment Information
                </div>

                <div class="payment-text">
                    {{ $paymentInfo ?: '-' }}
                </div>
            </td>

            <td class="signature-box">
                <div class="section-label">
                    Prepared By
                </div>

                <div class="signature-space"></div>

                <div class="signature-line"></div>

                <div class="signature-name">
                    PT VARBEL ANVAYA BERSAUDARA
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Member of Rental Indonesia.
    </div>
</body>
</html>