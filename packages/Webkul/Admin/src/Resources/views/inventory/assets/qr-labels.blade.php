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
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            padding: 14px 22px;
            border-bottom: 1px solid #dbe3ee;
            background: #f8fafc;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.08);
        }

        .toolbar-left {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            min-width: 0;
            flex: 1;
        }

        .toolbar-right {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            padding-bottom: 1px;
        }

        .toolbar-info {
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.35;
        }

        .item-filter {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            min-width: 0;
            flex: 1;
        }

        .item-checklist {
            width: min(540px, 48vw);
            min-width: 340px;
            padding: 12px;
            border: 1px solid #d8e0eb;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 5px 16px rgba(15, 23, 42, 0.06);
        }

        .item-checklist-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
        }

        .item-checklist-heading {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }

        .item-checklist-kicker {
            color: #94a3b8;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .item-checklist-title {
            color: #0f172a;
            font-size: 14px;
            font-weight: 800;
        }

        .item-checklist-count {
            flex: 0 0 auto;
            padding: 5px 8px;
            border: 1px solid #efd37d;
            border-radius: 999px;
            color: #8a6410;
            background: #fff8df;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .item-checklist-tools {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }

        .item-checklist-hint {
            overflow: hidden;
            color: #64748b;
            font-size: 11px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .select-all-control {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex: 0 0 auto;
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            color: #475569;
            background: #f8fafc;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
        }

        .item-checklist-options {
            display: grid;
            gap: 6px;
            max-height: 150px;
            overflow-y: auto;
            padding: 1px;
        }

        .item-option {
            display: grid;
            grid-template-columns: 18px minmax(0, 1fr);
            align-items: center;
            gap: 10px;
            min-height: 38px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            color: #111827;
            background: #ffffff;
            font-size: 12px;
            line-height: 1.25;
            cursor: pointer;
            transition: border-color 120ms ease,
                background 120ms ease, transform 120ms ease;
        }

        .item-option:hover {
            border-color: #d7ad2b;
            background: #fffcf1;
            transform: translateY(-1px);
        }

        .item-option input,
        .select-all-control input {
            width: 16px;
            height: 16px;
            margin: 0;
            accent-color: #c79a19;
        }

        .item-option-copy {
            display: flex;
            align-items: baseline;
            gap: 8px;
            min-width: 0;
        }

        .item-option-code {
            flex: 0 0 auto;
            color: #0f172a;
            font-weight: 800;
        }

        .item-option-name {
            overflow: hidden;
            color: #64748b;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .item-filter .filter-button {
            min-height: 42px;
            white-space: nowrap;
        }

        .selection-summary {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 170px;
            min-height: 72px;
            padding: 10px 13px;
            border: 1px solid #dbe3ee;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 3px 10px rgba(15, 23, 42, 0.04);
        }

        .selection-summary strong {
            color: #0f172a;
            font-size: 14px;
            font-weight: 800;
        }

        .selection-summary span {
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.4;
        }

        .print-scale {
            align-self: center;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .toolbar a,
        .toolbar button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border: 1px solid #c79a19;
            border-radius: 10px;
            padding: 9px 14px;
            color: #8a6410;
            background: #ffffff;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: transform 120ms ease, box-shadow 120ms ease;
        }

        .toolbar a:hover,
        .toolbar button:hover {
            box-shadow: 0 4px 10px rgba(138, 100, 16, 0.16);
            transform: translateY(-1px);
        }

        .toolbar button {
            color: #ffffff;
            background: #c79a19;
        }
        .item-filter {
            position: relative;
            display: block;
            flex: 1 1 420px;
            min-width: 320px;
            margin: 0;
        }

        .item-dropdown {
            position: relative;
            width: min(520px, 100%);
        }

        .item-dropdown-trigger {
            width: 100%;
            min-height: 72px !important;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 11px 13px !important;
            color: #101828 !important;
            background: #fff !important;
            border: 1px solid #d8e0eb !important;
            border-radius: 11px !important;
            text-align: left;
            box-shadow: 0 4px 12px rgba(16, 24, 40, .04);
        }

        .item-dropdown-trigger:hover {
            background: #fff !important;
            border-color: #d39d08 !important;
            box-shadow: 0 5px 14px rgba(16, 24, 40, .08);
            transform: none !important;
        }

        .item-dropdown-trigger-copy {
            min-width: 0;
            display: grid;
            gap: 3px;
        }

        .item-dropdown-kicker {
            color: #98a2b3;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .item-dropdown-title {
            overflow: hidden;
            color: #101828;
            font-size: 14px;
            font-weight: 800;
            line-height: 1.3;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .item-dropdown-meta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
        }

        .item-dropdown-count {
            min-width: 28px;
            padding: 5px 8px;
            color: #9a6a00;
            background: #fff6d8;
            border: 1px solid #efd58a;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            text-align: center;
        }

        .item-dropdown-chevron {
            color: #667085;
            font-size: 17px;
            line-height: 1;
        }

        .item-dropdown-menu {
            position: absolute;
            z-index: 50;
            top: calc(100% + 8px);
            left: 0;
            width: min(520px, calc(100vw - 40px));
            padding: 12px;
            background: #fff;
            border: 1px solid #d8e0eb;
            border-radius: 12px;
            box-shadow: 0 16px 32px rgba(16, 24, 40, .16);
        }

        .item-dropdown-menu[hidden] {
            display: none;
        }

        .item-dropdown-menu-head,
        .item-dropdown-actions,
        .item-dropdown-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .item-dropdown-menu-head {
            padding: 2px 2px 10px;
        }

        .item-dropdown-menu-title,
        .item-dropdown-menu-subtitle {
            display: block;
        }

        .item-dropdown-menu-title {
            color: #101828;
            font-size: 13px;
            font-weight: 800;
        }

        .item-dropdown-menu-subtitle {
            margin-top: 2px;
            color: #98a2b3;
            font-size: 11px;
        }

        .item-dropdown-close {
            width: 28px;
            min-height: 28px !important;
            padding: 0 !important;
            color: #667085 !important;
            background: #f2f4f7 !important;
            border: 0 !important;
            border-radius: 7px !important;
            font-size: 18px !important;
        }

        .item-dropdown-close:hover {
            color: #344054 !important;
            background: #eaecf0 !important;
            box-shadow: none !important;
        }

        .item-dropdown-search {
            position: relative;
            display: block;
            margin-bottom: 9px;
        }

        .item-dropdown-search-icon {
            position: absolute;
            top: 50%;
            left: 11px;
            color: #98a2b3;
            font-size: 16px;
            line-height: 1;
            transform: translateY(-50%);
        }

        .item-dropdown-search input {
            width: 100%;
            height: 38px;
            padding: 0 11px 0 31px;
            color: #101828;
            background: #fff;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            outline: 0;
            font-size: 12px;
        }

        .item-dropdown-search input:focus {
            border-color: #d39d08;
            box-shadow: 0 0 0 3px rgba(211, 157, 8, .14);
        }

        .item-dropdown-actions {
            margin: 0 2px 8px;
            color: #667085;
            font-size: 11px;
        }

        .select-all-control {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #344054;
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
        }

        .select-all-control input,
        .item-dropdown-option input {
            width: 15px;
            height: 15px;
            margin: 0;
            accent-color: #d39d08;
        }

        .item-dropdown-options {
            display: grid;
            gap: 5px;
            max-height: 310px;
            padding: 2px;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .item-dropdown-option {
            display: flex;
            align-items: center;
            gap: 9px;
            min-height: 40px;
            padding: 7px 9px;
            color: #344054;
            background: #fff;
            border: 1px solid #e4e7ec;
            border-radius: 8px;
            cursor: pointer;
        }

        .item-dropdown-option:hover {
            background: #fffcf2;
            border-color: #e9c55e;
        }

        .item-dropdown-option[hidden] {
            display: none;
        }

        .item-option-copy {
            min-width: 0;
            display: flex;
            align-items: baseline;
            gap: 7px;
            overflow: hidden;
        }

        .item-option-code,
        .item-option-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .item-option-code {
            flex: 0 0 auto;
            color: #101828;
            font-size: 11px;
            font-weight: 800;
        }

        .item-option-name {
            color: #667085;
            font-size: 11px;
        }

        .item-dropdown-empty {
            padding: 18px 8px;
            color: #98a2b3;
            font-size: 12px;
            text-align: center;
        }

        .item-dropdown-footer {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eaecf0;
        }

        .item-dropdown-footer .filter-button {
            min-height: 34px;
            padding: 0 12px;
        }

        @media (max-width: 700px) {
            .item-filter,
            .item-dropdown {
                width: 100%;
                min-width: 0;
            }

            .item-dropdown-menu {
                width: 100%;
                min-width: 0;
            }
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

        @media (max-width: 1100px) {
            .toolbar {
                flex-wrap: wrap;
            }

            .toolbar-left,
            .toolbar-right {
                width: 100%;
            }

            .toolbar-right {
                justify-content: flex-end;
            }
        }

        @media (max-width: 700px) {
            .toolbar {
                position: static;
                padding: 12px;
            }

            .toolbar-left,
            .item-filter {
                flex-wrap: wrap;
            }

            .item-checklist {
                width: 100%;
                min-width: 0;
            }

            .item-filter .filter-button {
                flex: 1;
            }

            .toolbar-right {
                align-items: center;
                justify-content: space-between;
            }

            .selection-summary {
                flex: 1;
                min-width: 0;
            }
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

            <form method="GET" action="{{ route('admin.inventory.assets.qr-labels.index') }}" class="item-filter">
            <input type="hidden" name="inventory_item_filter" value="1">
            <div class="item-dropdown" data-inventory-dropdown>
                <button type="button" class="item-dropdown-trigger" aria-haspopup="true" aria-expanded="false">
                    <span class="item-dropdown-trigger-copy">
                        <span class="item-dropdown-kicker">Barcode / QR print</span>
                        <span class="item-dropdown-title" data-selected-label>Select inventory items</span>
                    </span>
                    <span class="item-dropdown-meta">
                        <span class="item-dropdown-count" data-selected-count>{{ $selectedItemIds->count() }}</span>
                        <span class="item-dropdown-chevron" aria-hidden="true">▾</span>
                    </span>
                </button>

                <div class="item-dropdown-menu" data-inventory-menu hidden>
                    <div class="item-dropdown-menu-head">
                        <div>
                            <strong class="item-dropdown-menu-title">Inventory items</strong>
                            <span class="item-dropdown-menu-subtitle">Search by code or item name.</span>
                        </div>
                        <button type="button" class="item-dropdown-close" data-close-inventory-menu aria-label="Close">&times;</button>
                    </div>

                    <label class="item-dropdown-search">
                        <span class="item-dropdown-search-icon" aria-hidden="true">⌕</span>
                        <input type="search" class="item-dropdown-search-input" data-inventory-search placeholder="Search inventory items..." autocomplete="off">
                    </label>

                    <div class="item-dropdown-actions">
                        <label for="select-all-inventory-items" class="select-all-control">
                            <input type="checkbox" id="select-all-inventory-items">
                            Select all visible
                        </label>
                        <span data-visible-count>{{ $inventoryItems->count() }} visible</span>
                    </div>

                    <div class="item-dropdown-options" data-inventory-options role="listbox" aria-multiselectable="true">
                        @foreach ($inventoryItems as $item)
                            <label class="item-dropdown-option" data-inventory-option>
                                <input
                                    type="checkbox"
                                    name="inventory_item_ids[]"
                                    value="{{ $item->id }}"
                                    class="inventory-item-checkbox"
                                    @checked($selectedItemIds->contains($item->id))
                                >
                                <span class="item-option-copy">
                                    <strong class="item-option-code">{{ $item->code }}</strong>
                                    <span class="item-option-name">{{ $item->name }}</span>
                                </span>
                            </label>
                        @endforeach
                        <div class="item-dropdown-empty" data-inventory-empty hidden>No matching inventory item.</div>
                    </div>

                    <div class="item-dropdown-footer">
                        <span class="item-dropdown-menu-subtitle" data-selection-hint>No items selected</span>
                        <button type="submit" class="filter-button">Apply selection</button>
                    </div>
                </div>
            </div>
        </form>

            <div class="selection-summary">
                <strong>{{ $assets->count() }} labels</strong>
                <span>
                    {{ $selectedItemIds->count() }} inventory items
                    &middot; 225 labels / A4
                </span>
                <span>
                    20 x 10 mm &middot;
                    {{ (int) ceil($assets->count() / 225) }} page
                </span>
            </div>
        </div>

        <div class="toolbar-right">
            <span class="print-scale">Print scale: 100% &middot; Actual size</span>

            <button type="button" onclick="window.print()">
                Print selected labels
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
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dropdown = document.querySelector('[data-inventory-dropdown]');

            if (! dropdown) {
                return;
            }

            const trigger = dropdown.querySelector('.item-dropdown-trigger');
            const menu = dropdown.querySelector('[data-inventory-menu]');
            const closeButton = dropdown.querySelector('[data-close-inventory-menu]');
            const search = dropdown.querySelector('[data-inventory-search]');
            const selectAll = dropdown.querySelector('#select-all-inventory-items');
            const selectedCount = dropdown.querySelector('[data-selected-count]');
            const selectedLabel = dropdown.querySelector('[data-selected-label]');
            const visibleCount = dropdown.querySelector('[data-visible-count]');
            const selectionHint = dropdown.querySelector('[data-selection-hint]');
            const emptyState = dropdown.querySelector('[data-inventory-empty]');
            const options = Array.from(dropdown.querySelectorAll('[data-inventory-option]'));
            const checkboxes = Array.from(dropdown.querySelectorAll('.inventory-item-checkbox'));
            const form = dropdown.closest('form');

            const visibleOptions = () => options.filter((option) => ! option.hidden);

            const syncState = () => {
                const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
                const visible = visibleOptions();
                const visibleSelected = visible.filter((option) => {
                    const checkbox = option.querySelector('.inventory-item-checkbox');

                    return checkbox && checkbox.checked;
                }).length;

                selectedCount.textContent = selected;
                selectedLabel.textContent = selected === 0
                    ? 'Select inventory items'
                    : `${selected} item${selected === 1 ? '' : 's'} selected`;
                selectionHint.textContent = selected === 0
                    ? 'No items selected'
                    : `${selected} item${selected === 1 ? '' : 's'} selected`;
                visibleCount.textContent = `${visible.length} visible`;
                selectAll.checked = visible.length > 0 && visibleSelected === visible.length;
                selectAll.indeterminate = visibleSelected > 0 && visibleSelected < visible.length;
                emptyState.hidden = visible.length !== 0;
            };

            const setMenuOpen = (open) => {
                menu.hidden = ! open;
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');

                if (open) {
                    search.focus();
                }
            };

            trigger.addEventListener('click', () => {
                setMenuOpen(menu.hidden);
            });

            closeButton.addEventListener('click', () => {
                setMenuOpen(false);
            });

            document.addEventListener('click', (event) => {
                if (! dropdown.contains(event.target)) {
                    setMenuOpen(false);
                }
            });

            search.addEventListener('input', () => {
                const query = search.value.trim().toLowerCase();

                options.forEach((option) => {
                    option.hidden = query !== '' && ! option.textContent.toLowerCase().includes(query);
                });

                syncState();
            });

            selectAll.addEventListener('change', () => {
                visibleOptions().forEach((option) => {
                    const checkbox = option.querySelector('.inventory-item-checkbox');

                    if (checkbox) {
                        checkbox.checked = selectAll.checked;
                    }
                });

                syncState();
            });

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', syncState);
            });

            if (form) {
                form.addEventListener('submit', () => setMenuOpen(false));
            }

            syncState();
        });
    </script>
</body>
</html>
