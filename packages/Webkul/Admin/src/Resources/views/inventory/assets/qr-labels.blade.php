<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>Inventory Asset QR Labels - A4 - 20x10 mm</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            color: #111827;
            background: #e5e7eb;
            font-family: Arial, Helvetica, sans-serif;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #d1d5db;
            background: white;
        }

        .toolbar-left,
        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toolbar-info {
            color: #4b5563;
            font-size: 12px;
            font-weight: 700;
        }

        .item-filter {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .item-filter select {
            max-width: 320px;
            min-height: 34px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 6px 10px;
            color: #111827;
            background: white;
            font-size: 13px;
        }

        .toolbar a,
        .toolbar button {
            border: 1px solid #c79a19;
            border-radius: 6px;
            padding: 8px 12px;
            color: #8a6410;
            background: white;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .toolbar button {
            color: white;
            background: #c79a19;
        }

        .preview-wrapper {
            display: grid;
            justify-content: center;
            gap: 18px;
            padding: 20px;
        }

        /* INVENTORY QR LABEL 20X10MM V1
         * A4 usable area after 8 mm page margins: 194 x 281 mm.
         * 9 columns x 25 rows = 225 physical labels per page.
         * Every label is exactly 20 x 10 mm. The QR remains square at
         * 8 x 8 mm so it is not distorted.
         */
        .sheet {
            display: grid;
            grid-template-columns: repeat(9, 20mm);
            grid-template-rows: repeat(25, 10mm);
            gap: 1mm;
            width: 194mm;
            min-height: 281mm;
            align-content: start;
            justify-content: start;
            padding: 3.5mm 3mm;
            background: white;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
        }

        .label {
            display: grid;
            grid-template-columns: 8mm minmax(0, 1fr);
            align-items: center;
            gap: 0.8mm;
            width: 20mm;
            height: 10mm;
            overflow: hidden;
            border: 0.2mm dashed #9ca3af;
            border-radius: 0.6mm;
            padding: 0.7mm;
            background: white;
        }

        .qr {
            display: block;
            width: 8mm;
            height: 8mm;
            object-fit: contain;
        }

        .label-copy {
            min-width: 0;
            overflow: hidden;
        }

        .asset-code {
            overflow-wrap: anywhere;
            color: #111827;
            font-family: "Courier New", Courier, monospace;
            font-size: 5pt;
            font-weight: 800;
            line-height: 1.05;
        }

        .item-name {
            max-height: 3.2mm;
            margin-top: 0.6mm;
            overflow: hidden;
            color: #4b5563;
            font-size: 3.7pt;
            font-weight: 600;
            line-height: 1.05;
        }

        .empty {
            margin: 20px;
            padding: 30px;
            border: 1px dashed #9ca3af;
            background: white;
            text-align: center;
        }

        @media print {
            html,
            body {
                width: 210mm;
                min-height: 297mm;
                background: white;
            }

            .toolbar,
            .empty {
                display: none !important;
            }

            .preview-wrapper {
                display: block;
                padding: 0;
            }

            .sheet {
                width: 194mm;
                min-height: 281mm;
                margin: 0;
                box-shadow: none;
                page-break-after: always;
                break-after: page;
            }

            .sheet:last-child {
                page-break-after: auto;
                break-after: auto;
            }

            .label {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="{{ route(
                'admin.inventory.assets.index',
                $selectedItemId ? ['inventory_item_id' => $selectedItemId] : []
            ) }}">
                &larr; Back to Assets
            </a>

            <form
                method="GET"
                action="{{ route('admin.inventory.assets.qr-labels.index') }}"
                class="item-filter"
            >
                <label for="inventory-item-filter" class="toolbar-info">
                    Inventory Item
                </label>

                <select
                    id="inventory-item-filter"
                    name="inventory_item_id"
                    onchange="this.form.submit()"
                >
                    <option value="">All serialized items</option>

                    @foreach ($inventoryItems as $item)
                        <option
                            value="{{ $item->id }}"
                            @selected((int) $selectedItemId === (int) $item->id)
                        >
                            {{ $item->code }} — {{ $item->name }}
                        </option>
                    @endforeach
                </select>
            </form>

            <span class="toolbar-info">
                {{ $assets->count() }} label
                &middot;
                225 label / A4
                &middot;
                20 x 10 mm / label
                &middot;
                {{ (int) ceil($assets->count() / 225) }} page
            </span>
        </div>

        <div class="toolbar-right">
            <span class="toolbar-info">Print scale: 100% / Actual size</span>

            <button type="button" onclick="window.print()">
                Print A4 QR Sheet
            </button>
        </div>
    </div>

    @if ($assets->isEmpty())
        <div class="empty">
            Tidak ada asset untuk dicetak.
        </div>
    @else
        <div class="preview-wrapper">
            @foreach ($assets->chunk(225) as $pageAssets)
                <section class="sheet">
                    @foreach ($pageAssets as $asset)
                        <div class="label">
                            <img
                                src="{{ route(
                                    'admin.inventory.assets.qr-labels.svg',
                                    $asset->id
                                ) }}"
                                class="qr"
                                alt="QR {{ $asset->qr_value ?: $asset->asset_code }}"
                            >

                            <div class="label-copy">
                                <div class="asset-code">
                                    {{ $asset->asset_code }}
                                </div>

                                <div class="item-name">
                                    {{ $asset->item?->name ?: '-' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </section>
            @endforeach
        </div>
    @endif
</body>
</html>
