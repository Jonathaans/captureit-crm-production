<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>
        {{ $deliveryOrder->delivery_order_number }}
    </title>

    <style>
        @page {
            size: A4 portrait;

            /*
             * Bottom margin intentionally larger so fixed footer
             * never collides with document content.
             */
            margin: 12mm 12mm 21mm 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5px;
            line-height: 1.35;
        }

        .footer {
            position: fixed;
            right: 0;
            bottom: -14mm;
            left: 0;
            height: 10mm;
            border-top: 0.5px solid #d1d5db;
            padding-top: 2.5mm;
            color: #6b7280;
            font-size: 7.5px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            padding: 0;
            border: 0;
        }

        .footer-right {
            text-align: right;
        }

        .header-table,
        .info-table,
        .equipment-table,
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
            border: 0;
        }

        .logo-cell {
            width: 42%;
        }

        .logo {
            width: 42mm;
            max-height: 18mm;
            object-fit: contain;
        }

        .company-name {
            margin-top: 2mm;
            font-size: 8px;
            color: #4b5563;
        }

        .document-cell {
            width: 58%;
            text-align: right;
        }

        .document-title {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .document-number {
            margin-top: 2mm;
            font-size: 12px;
            font-weight: 700;
        }

        .document-status {
            margin-top: 1mm;
            color: #6b7280;
            font-size: 8px;
            text-transform: uppercase;
        }

        .top-rule {
            margin: 5mm 0 4mm;
            border-top: 1.5px solid #111827;
        }

        .section {
            margin-top: 4mm;
        }

        .section-title {
            margin: 0 0 2mm;
            padding-bottom: 1.3mm;
            border-bottom: 0.7px solid #9ca3af;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .info-table td {
            width: 25%;
            padding: 1.4mm 2.5mm 1.4mm 0;
            vertical-align: top;
            border: 0;
        }

        .label {
            margin-bottom: 0.6mm;
            color: #6b7280;
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .value {
            font-size: 9px;
            font-weight: 600;
            word-wrap: break-word;
        }

        .value.normal {
            font-weight: 400;
        }

        .full-width-cell {
            width: 100% !important;
        }

        /*
        |--------------------------------------------------------------------------
        | Equipment Table
        |--------------------------------------------------------------------------
        |
        | thead repeats when table continues on the next PDF page.
        | Each row is kept together where possible.
        |
        */

        .equipment-table {
            margin-top: 2mm;
        }

        .equipment-table thead {
            display: table-header-group;
        }

        .equipment-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .equipment-table th {
            padding: 2mm 1.5mm;
            border: 0.6px solid #9ca3af;
            background: #f3f4f6;
            font-size: 7.7px;
            text-align: left;
            text-transform: uppercase;
        }

        .equipment-table td {
            padding: 1.8mm 1.5mm;
            border: 0.6px solid #d1d5db;
            vertical-align: top;
            font-size: 8.3px;
        }

        .col-no {
            width: 7%;
            text-align: center !important;
        }

        .col-item {
            width: 19%;
        }

        .col-description {
            width: 22%;
        }

        .col-actual {
            width: 24%;
        }

        .col-qty {
            width: 8%;
            text-align: center !important;
        }

        .col-unit {
            width: 9%;
            text-align: center !important;
        }

        .col-notes {
            width: 11%;
        }

        .empty-equipment {
            padding: 5mm !important;
            color: #6b7280;
            text-align: center;
        }

        .notes-box {
            min-height: 14mm;
            padding: 2.5mm;
            border: 0.6px solid #d1d5db;
            white-space: pre-line;
        }

        /*
        |--------------------------------------------------------------------------
        | Signatures
        |--------------------------------------------------------------------------
        |
        | CRITICAL:
        | Entire signature block must stay together.
        |
        | If there is not enough space after Notes/Equipment on Page 1,
        | DomPDF moves this WHOLE block to Page 2 automatically.
        |
        */

        .signature-section {
            margin-top: 6mm;

            page-break-inside: avoid !important;
            break-inside: avoid-page !important;
        }

        .signature-table {
            table-layout: fixed;

            page-break-inside: avoid !important;
            break-inside: avoid-page !important;
        }

        .signature-table td {
            width: 25%;
            padding: 0 2.2mm;
            vertical-align: top;
            text-align: center;
            border: 0;
        }

        .signature-action {
            min-height: 5mm;
            font-size: 8.5px;
            font-weight: 700;
        }

        .signature-role {
            margin-top: 0.8mm;
            min-height: 4mm;
            color: #6b7280;
            font-size: 7.5px;
        }

        /*
         * Real handwriting area.
         * 34 mm gives enough physical room for manual signature.
         */
        .signature-space {
            height: 34mm;
        }

        .signature-line {
            border-top: 0.7px solid #111827;
            padding-top: 1.5mm;
            font-size: 8px;
            font-weight: 700;
        }

        .signature-name {
            min-height: 5mm;
        }

        .small-muted {
            color: #6b7280;
            font-size: 7.5px;
        }
    </style>
</head>

<body>
    {{-- ============================================================= --}}
    {{-- FOOTER - appears on every PDF page --}}
    {{-- ============================================================= --}}

    <div class="footer">
        <table class="footer-table">
            <tr>
                <td>
                    Capture It Photobooth &bull; Varbel Corps
                </td>

                <td class="footer-right">
                    {{ $deliveryOrder->delivery_order_number }}
                </td>
            </tr>
        </table>
    </div>

    {{-- ============================================================= --}}
    {{-- HEADER --}}
    {{-- ============================================================= --}}

    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @php
                    $logoPath = public_path('images/logo-varbel.png');
                @endphp

                @if (file_exists($logoPath))
                    <img
                        src="{{ $logoPath }}"
                        class="logo"
                        alt="Varbel Corps"
                    >
                @else
                    <div style="font-size: 16px; font-weight: 700;">
                        CAPTURE IT
                    </div>
                @endif

                <div class="company-name">
                    Capture It Photobooth &bull; Varbel Corps
                </div>
            </td>

            <td class="document-cell">
                <p class="document-title">
                    SURAT JALAN
                </p>

                <div class="document-number">
                    {{ $deliveryOrder->delivery_order_number }}
                </div>

                <div class="document-status">
                    Status:
                    {{ strtoupper($deliveryOrder->status ?: 'draft') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="top-rule"></div>

    {{-- ============================================================= --}}
    {{-- PROJECT INFORMATION --}}
    {{-- ============================================================= --}}

    <div class="section">
        <div class="section-title">
            Project Information
        </div>

        <table class="info-table">
            <tr>
                <td>
                    <div class="label">Project Code</div>
                    <div class="value">
                        {{ $deliveryOrder->project_code ?: '-' }}
                    </div>
                </td>

                <td>
                    <div class="label">Project Name</div>
                    <div class="value">
                        {{ $deliveryOrder->project_name ?: '-' }}
                    </div>
                </td>

                <td>
                    <div class="label">Customer</div>
                    <div class="value">
                        {{ $deliveryOrder->customer_name ?: '-' }}
                    </div>
                </td>

                <td>
                    <div class="label">Sales</div>
                    <div class="value">
                        {{ $deliveryOrder->sales_person_name ?: '-' }}
                    </div>
                </td>
            </tr>

            <tr>
                <td>
                    <div class="label">Invoice</div>
                    <div class="value normal">
                        {{ $deliveryOrder->invoice_number ?: '-' }}
                    </div>
                </td>

                <td>
                    <div class="label">Quote</div>
                    <div class="value normal">
                        {{ $deliveryOrder->quote_number ?: '-' }}
                    </div>
                </td>

                <td>
                    <div class="label">Created By</div>
                    <div class="value normal">
                        {{ $deliveryOrder->creator?->name ?: '-' }}
                    </div>
                </td>

                <td>
                    <div class="label">Issued Date</div>
                    <div class="value normal">
                        {{
                            $deliveryOrder->issued_at
                                ? $deliveryOrder->issued_at->format('d M Y')
                                : '-'
                        }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ============================================================= --}}
    {{-- EVENT & DELIVERY --}}
    {{-- ============================================================= --}}

    <div class="section">
        <div class="section-title">
            Event & Delivery Information
        </div>

        <table class="info-table">
            <tr>
                <td>
                    <div class="label">Event Date</div>
                    <div class="value">
                        {{
                            $deliveryOrder->event_date
                                ? $deliveryOrder->event_date->format('d M Y')
                                : '-'
                        }}
                    </div>
                </td>

                <td>
                    <div class="label">Event Time</div>
                    <div class="value">
                        {{ $deliveryOrder->event_time ?: '-' }}
                    </div>
                </td>

                <td>
                    <div class="label">Delivery Date</div>
                    <div class="value">
                        {{
                            $deliveryOrder->delivery_date
                                ? $deliveryOrder->delivery_date->format('d M Y')
                                : '-'
                        }}
                    </div>
                </td>

                <td>
                    <div class="label">Delivery Time</div>
                    <div class="value">
                        {{ $deliveryOrder->delivery_time ?: '-' }}
                    </div>
                </td>
            </tr>

            <tr>
                <td colspan="2" style="width: 50%;">
                    <div class="label">Event Location</div>
                    <div class="value normal">
                        {{ $deliveryOrder->location ?: '-' }}
                    </div>
                </td>

                <td colspan="2" style="width: 50%;">
                    <div class="label">Delivery Address</div>
                    <div class="value normal">
                        {{ $deliveryOrder->delivery_address ?: '-' }}
                    </div>
                </td>
            </tr>

            <tr>
                <td>
                    <div class="label">Recipient</div>
                    <div class="value">
                        {{ $deliveryOrder->recipient_name ?: '-' }}
                    </div>
                </td>

                <td>
                    <div class="label">Recipient Phone</div>
                    <div class="value normal">
                        {{ $deliveryOrder->recipient_phone ?: '-' }}
                    </div>
                </td>

                <td>
                    <div class="label">PIC</div>
                    <div class="value">
                        {{ $deliveryOrder->pic_name ?: '-' }}
                    </div>
                </td>

                <td>
                    <div class="label">PIC Phone</div>
                    <div class="value normal">
                        {{ $deliveryOrder->pic_phone ?: '-' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ============================================================= --}}
    {{-- EQUIPMENT --}}
    {{-- ============================================================= --}}

    <div class="section">
        <div class="section-title">
            Equipment / Items
        </div>

        <table class="equipment-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-item">Item</th>
                    <th class="col-description">Description</th>
                    <th class="col-actual">Actual Asset</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-unit">Unit</th>
                    <th class="col-notes">Notes</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($deliveryOrder->items as $index => $item)
                    @php
                        $itemAllocations = $item->allocations
                            ->where('status', '!=', 'released');

                        $serializedAssetCodes = $itemAllocations
                            ->where('tracking_type', 'serialized')
                            ->map(
                                fn ($allocation) =>
                                    $allocation->inventoryAsset?->asset_code
                            )
                            ->filter()
                            ->unique()
                            ->values();
                    @endphp

                    <tr>
                        <td class="col-no">
                            {{ $index + 1 }}
                        </td>

                        <td class="col-item">
                            <strong>
                                {{ $item->name }}
                            </strong>
                        </td>

                        <td class="col-description">
                            {{ $item->description ?: '-' }}
                        </td>

                        <td class="col-actual">
                            @if ($serializedAssetCodes->isNotEmpty())
                                @foreach ($serializedAssetCodes as $assetCode)
                                    <div>
                                        <strong>{{ $assetCode }}</strong>
                                    </div>
                                @endforeach
                            @elseif ($item->inventoryItem?->isQuantityTracked())
                                Quantity Stock
                            @else
                                -
                            @endif
                        </td>

                        <td class="col-qty">
                            {{
                                rtrim(
                                    rtrim(
                                        number_format(
                                            (float) $item->quantity,
                                            2,
                                            '.',
                                            ''
                                        ),
                                        '0'
                                    ),
                                    '.'
                                )
                            }}
                        </td>

                        <td class="col-unit">
                            {{ $item->unit ?: 'unit' }}
                        </td>

                        <td class="col-notes">
                            {{ $item->notes ?: '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="7"
                            class="empty-equipment"
                        >
                            No equipment items.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ============================================================= --}}
    {{-- NOTES --}}
    {{-- ============================================================= --}}

    @if ($deliveryOrder->notes)
        <div class="section">
            <div class="section-title">
                Notes
            </div>

            <div class="notes-box">
                {{ $deliveryOrder->notes }}
            </div>
        </div>
    @endif

    {{-- ============================================================= --}}
    {{-- SIGNATURES --}}
    {{-- ============================================================= --}}
    {{--
        IMPORTANT:
        signature-section has page-break-inside: avoid.

        If the remaining space on Page 1 is not enough for all four
        signature columns, DomPDF moves the entire signature section
        to Page 2 instead of cutting it.
    --}}

    <div class="signature-section">
        <div class="section-title">
            Signatures
        </div>

        <table class="signature-table">
            <tr>
                {{-- 1. Sales --}}
                <td>
                    <div class="signature-action">
                        Prepared By
                    </div>

                    <div class="signature-role">
                        Sales
                    </div>

                    <div class="signature-space"></div>

                    <div class="signature-line">
                        <div class="signature-name">
                            {{ $deliveryOrder->sales_person_name ?: '________________' }}
                        </div>

                    </div>
                </td>

                {{-- 2. Head Warehouse --}}
                <td>
                    <div class="signature-action">
                        Approved By
                    </div>

                    <div class="signature-role">
                        Head Warehouse
                    </div>

                    <div class="signature-space"></div>

                    <div class="signature-line">
                        <div class="signature-name">
                            Hafiz Eka Nugroho
                        </div>
                    </div>
                </td>

                {{-- 3. Staff Warehouse --}}
                <td>
                    <div class="signature-action">
                        Released By
                    </div>

                    <div class="signature-role">
                        Staff Warehouse
                    </div>

                    <div class="signature-space"></div>

                    <div class="signature-line">
                        <div class="signature-name">
                            {{
                                $deliveryOrder->released_by_name
                                ?: '________________'
                            }}
                        </div>

                    </div>
                </td>

                {{-- 4. PIC --}}
                <td>
                    <div class="signature-action">
                        Received By
                    </div>

                    <div class="signature-role">
                        PIC
                    </div>

                    <div class="signature-space"></div>

                    <div class="signature-line">
                        <div class="signature-name">
                            {{
                                $deliveryOrder->pic_name
                                ?: '________________'
                            }}
                        </div>

                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
