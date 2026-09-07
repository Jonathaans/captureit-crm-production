<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">

    <title>{{ $invoice->invoice_number }}</title>

    @php
        /*
         * Logo:
         * Prefer logo-varbel.png, but keep support for the current
         * "logo varbel.png" filename.
         */
        $logoPath = public_path('images/logo-varbel.png');

        if (! file_exists($logoPath)) {
            $logoPath = public_path('images/logo varbel.png');
        }

        $logoData = null;

        if (file_exists($logoPath)) {
            $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));

            $mime = in_array($extension, ['jpg', 'jpeg'])
                ? 'image/jpeg'
                : 'image/png';

            $logoData = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($logoPath));
        }

        /*
         * Company identity for invoice header.
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
         * Billing address only.
         * Shipping Address intentionally does not appear in the document.
         */
        $billingAddress = is_array($invoice->billing_address)
            ? $invoice->billing_address
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

        /*
         * Dates.
         */
        $issuedDate = $invoice->issued_at
            ? core()->formatDate($invoice->issued_at, 'd M Y')
            : '-';

        $eventDate = $invoice->event_date
            ? core()->formatDate($invoice->event_date, 'd M Y')
            : '-';

        $dueDate = $invoice->due_at
            ? core()->formatDate($invoice->due_at, 'd M Y')
            : '-';

        /*
         * Payment status.
         */
        $paymentStatus = match ($invoice->status) {
            'paid' => 'PAID',
            'partial' => 'PARTIAL',
            default => 'UNPAID',
        };

        /* CRM_INVOICE_FLEXIBLE_BILLING_V1 */
        $billingType = $invoice->billing_type ?: 'full_payment';

        $billingLabel = match ($billingType) {
            'down_payment' => 'DOWN PAYMENT',
            'settlement' => 'PELUNASAN',
            default => 'FULL PAYMENT',
        };

        $dpInvoiceNumber = $invoice->dp_invoice_id
            ? \Illuminate\Support\Facades\DB::table('invoices')
                ->where('id', $invoice->dp_invoice_id)
                ->value('invoice_number')
            : null;

        $quoteTotalSnapshot = (float) (
            $invoice->quote_total_snapshot
            ?: $invoice->grand_total
        );

        $remainingAmountSnapshot = (float) (
            $invoice->remaining_amount_snapshot
            ?? 0
        );

        $billingPercentageLabel = $invoice->billing_percentage !== null
            ? rtrim(
                rtrim(
                    number_format(
                        (float) $invoice->billing_percentage,
                        4,
                        '.',
                        ''
                    ),
                    '0'
                ),
                '.'
            ).'%'
            : null;
    @endphp

    <style>
        @page {
            /*
             * Keep a permanent safe zone above the fixed footer.
             * DOMPDF will flow the next row/block to the next page
             * before it reaches this bottom margin.
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
            width: 25%;
        }

        .items-table .day,
        .items-table .qty {
            width: 7%;
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

        .payment-summary {
            width: 42%;
            margin-top: 12px;
            margin-left: auto;
            background: #f9fafb;
        }

        .payment-summary td {
            padding: 5px 7px;
        }

        .status-paid {
            color: #15803d;
            font-weight: bold;
        }

        .status-partial {
            color: #a16207;
            font-weight: bold;
        }

        .status-unpaid {
            color: #b91c1c;
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
            /* Extra breathing room before the signature section. */
            margin-top: 42px;
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
            /*
             * Reserved signing area.
             * 125px gives enough vertical space for a physical materai
             * plus handwritten signature without crowding the name line.
             */
            height: 125px;
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
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #9ca3af;
            font-size: 7px;
        }
    </style>
</head>

<body>

@php
    /*
     * Keep items as one continuous table.
     * Pagination is handled by DOMPDF using the reserved @page bottom margin
     * and page-break-inside rules, so long descriptions are handled safely.
     */
    $items = $invoice->items->values();
@endphp

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
        <h1>INVOICE</h1>

        <div class="document-number">
            {{ $invoice->invoice_number }}
        </div>
        @if ($invoice->billing_locked_at)
            <div style="margin-top:5px; font-size:10px; font-weight:700; color:#1d4ed8;">
                {{ $billingLabel }}
                @if ($billingPercentageLabel)
                    &middot; {{ $billingPercentageLabel }}
                @endif
            </div>
        @endif
    </div>

    <!-- Customer + Project Details -->
    <table class="info-table">
        <tr>
            <td class="left-info">
                <div class="section-label">
                    Bill To
                </div>

                <div class="customer-name">
                    {{ $invoice->person?->name ?? '-' }}
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
                    <span class="project-value">: {{ $issuedDate }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Project Name</span>
                    <span class="project-value">: {{ $invoice->subject ?? '-' }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Project Code</span>
                    <span class="project-value">: {{ $invoice->project_code ?? '-' }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Event Date</span>
                    <span class="project-value">: {{ $eventDate }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Location</span>
                    <span class="project-value">: {{ $invoice->location ?? '-' }}</span>
                </div>

                @if ($invoice->billing_locked_at)
                    <div class="project-row">
                        <span class="project-label">Billing Type</span>
                        <span class="project-value">: {{ $billingLabel }}</span>
                    </div>

                    @if ($dpInvoiceNumber)
                        <div class="project-row">
                            <span class="project-label">DP Reference</span>
                            <span class="project-value">: {{ $dpInvoiceNumber }}</span>
                        </div>
                    @endif
                @endif
                <div class="project-row">
                    <span class="project-label">Payment Term</span>
                    <span class="project-value">: {{ $invoice->payment_term ?? '-' }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Due Date</span>
                    <span class="project-value">: {{ $dueDate }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Invoice Items -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="no">No.</th>
                <th class="package">Package</th>
                <th class="description">Description</th>
                <th class="day">Day</th>
                <th class="qty">Qty</th>
                <th class="price">Unit Price</th>
                <th class="total">Total</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($items as $index => $item)
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
                        Rp {{ number_format((float) $item->price, 0, ',', '.') }}
                    </td>

                    <td class="total">
                        Rp {{ number_format(
                            (float) $item->total
                            + (float) $item->tax_amount
                            - (float) $item->discount_amount,
                            0,
                            ',',
                            '.'
                        ) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="padding:18px; text-align:center; color:#9ca3af;">
                        No items.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Invoice Summary -->
    <div class="summary-wrap">
        <table class="summary-table">
            @if ($invoice->billing_locked_at && $billingType !== 'full_payment')
                <tr>
                    <td class="summary-label">
                        Quote Contract Total
                    </td>
                    <td class="summary-value">
                        Rp {{ number_format($quoteTotalSnapshot, 0, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td class="summary-label">
                        Remaining After Invoice
                    </td>
                    <td class="summary-value">
                        Rp {{ number_format($remainingAmountSnapshot, 0, ',', '.') }}
                    </td>
                </tr>
            @endif

            <tr>
                <td class="summary-label">
                    Sub Total
                </td>

                <td class="summary-value">
                    Rp {{ number_format((float) $invoice->sub_total, 0, ',', '.') }}
                </td>
            </tr>

            <tr>
                <td class="summary-label">
                    Discount
                </td>

                <td class="summary-value">
                    Rp {{ number_format((float) $invoice->discount_amount, 0, ',', '.') }}
                </td>
            </tr>

            <tr>
                <td class="summary-label">
                    Tax
                </td>

                <td class="summary-value">
                    Rp {{ number_format((float) $invoice->tax_amount, 0, ',', '.') }}
                </td>
            </tr>

            @if ((float) $invoice->adjustment_amount !== 0.0)
                <tr>
                    <td class="summary-label">
                        Adjustment
                    </td>

                    <td class="summary-value">
                        Rp {{ number_format((float) $invoice->adjustment_amount, 0, ',', '.') }}
                    </td>
                </tr>
            @endif

            <tr class="grand-total">
                <td>
                    GRAND TOTAL
                </td>

                <td class="summary-value">
                    Rp {{ number_format((float) $invoice->grand_total, 0, ',', '.') }}
                </td>
            </tr>
        </table>

        <!-- Payment Status Summary -->
        <table class="payment-summary">
            <tr>
                <td class="summary-label">
                    Paid
                </td>

                <td class="summary-value">
                    Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}
                </td>
            </tr>

            <tr>
                <td class="summary-label">
                    Balance Due
                </td>

                <td class="summary-value">
                    Rp {{ number_format((float) $invoice->balance_due, 0, ',', '.') }}
                </td>
            </tr>

            <tr>
                <td class="summary-label">
                    Payment Status
                </td>

                <td class="summary-value">
                    @if ($invoice->status === 'paid')
                        <span class="status-paid">
                            {{ $paymentStatus }}
                        </span>
                    @elseif ($invoice->status === 'partial')
                        <span class="status-partial">
                            {{ $paymentStatus }}
                        </span>
                    @else
                        <span class="status-unpaid">
                            {{ $paymentStatus }}
                        </span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- Optional Description / Notes -->
    @if ($invoice->description)
        <div class="description-box">
            <strong>Notes</strong>
            {{ $invoice->description }}
        </div>
    @endif

    <!-- Payment Information + Signature -->
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
                    PT. VARBEL ANVAYA BERSAUDARA
                </div>

                <div class="signature-space"></div>

                <div class="signature-line"></div>

                <div class="signature-name">
                    Rudy Alexsander Tinambunan
                    <br>Director</br>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Member of Rental Indonesia.
    </div>
</body>
</html>