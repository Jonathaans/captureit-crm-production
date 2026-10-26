<x-admin::layouts>
    <x-slot:title>
        Inventory Dashboard
    </x-slot>

    @php
        $formatQty = static function ($value) {
            return rtrim(
                rtrim(
                    number_format((float) $value, 2, '.', ''),
                    '0'
                ),
                '.'
            );
        };

        $movementLabel = static function ($type) {
            return strtoupper(
                str_replace('_', ' ', (string) $type)
            );
        };

        $movementTone = static function ($type) {
            $type = strtolower((string) $type);

            if (str_contains($type, 'maintenance')) {
                return 'bg-violet-100 text-violet-700';
            }

            if (str_contains($type, 'return')) {
                return 'bg-cyan-100 text-cyan-700';
            }

            if (str_contains($type, 'out') || str_contains($type, 'missing')) {
                return 'bg-red-100 text-red-700';
            }

            if (str_contains($type, 'adjustment')) {
                return 'bg-blue-100 text-blue-700';
            }

            if (str_contains($type, 'allocated')) {
                return 'bg-amber-100 text-amber-700';
            }

            return 'bg-gray-100 text-gray-700';
        };

        $alertTone = static function ($type) {
            return match ($type) {
                'critical' => [
                    'icon' => 'bg-red-100 text-red-700 ring-red-200',
                    'value' => 'text-red-600',
                    'dot' => 'bg-red-500',
                ],
                'warning' => [
                    'icon' => 'bg-amber-100 text-amber-700 ring-amber-200',
                    'value' => 'text-amber-600',
                    'dot' => 'bg-amber-500',
                ],
                default => [
                    'icon' => 'bg-blue-100 text-blue-700 ring-blue-200',
                    'value' => 'text-blue-600',
                    'dot' => 'bg-blue-500',
                ],
            };
        };

        $problemTotal =
            $assetAttention['missing']
            + $assetAttention['maintenance']
            + $assetAttention['return_pending']
            + $assetAttention['damaged'];

        $assetAvailablePercent = $summary['total_assets'] > 0
            ? round(($summary['available'] / $summary['total_assets']) * 100)
            : 0;
    @endphp

    <div
        style="
            width:100% !important;
            max-width:1680px !important;
            margin:0 auto !important;
            display:flex !important;
            flex-direction:column !important;
            gap:18px !important;
        "
    >
        {{-- PAGE HEADER --}}
        <div
            style="
                width:100% !important;
                display:flex !important;
                align-items:flex-start !important;
                justify-content:space-between !important;
                gap:16px !important;
                flex-wrap:wrap !important;
            "
        >
            <div>
                <p class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">
                    Inventory Dashboard
                </p>

                <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">
                    Pantau kondisi asset, level stock, dan kebutuhan inventory secara real-time.
                </p>
            </div>

            <div
                style="
                    display:flex !important;
                    flex-wrap:wrap !important;
                    gap:8px !important;
                    align-items:center !important;
                    justify-content:flex-end !important;
                "
            >
                @if (bouncer()->hasPermission('inventory.alerts'))
                    <a
                        href="{{ route('admin.inventory.alerts.export-csv') }}"
                        class="secondary-button"
                    >
                        <span class="mr-1">⇩</span>
                        Export Report
                    </a>
                @endif

                @if (bouncer()->hasPermission('inventory.stock-opname'))
                    <a
                        href="{{ route('admin.inventory.stock-opname.index') }}"
                        class="secondary-button"
                    >
                        Stock Opname
                    </a>
                @endif

                @if (bouncer()->hasPermission('inventory.qa'))
                    <a
                        href="{{ route('admin.inventory.qa.index') }}"
                        class="secondary-button"
                    >
                        Warehouse QA
                    </a>
                @endif

                @if (bouncer()->hasPermission('inventory.consumable-usage'))
                    <a
                        href="{{ route('admin.inventory.consumable-usage.index') }}"
                        class="secondary-button"
                    >
                        Penggunaan Consumable
                    </a>
                @endif

                @if (bouncer()->hasPermission('inventory.consumables.create'))
                    <a
                        href="{{ route('admin.inventory.consumables.create') }}"
                        class="primary-button"
                    >
                        + Consumable
                    </a>
                @endif

                @if (bouncer()->hasPermission('inventory.assets.create'))
                    <a
                        href="{{ route('admin.inventory.assets.create') }}"
                        class="primary-button"
                    >
                        + Asset
                    </a>
                @endif
            </div>
        </div>

        {{-- KPI CARDS --}}
        <div
            style="
                width:100% !important;
                display:grid !important;
                grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)) !important;
                gap:14px !important;
                align-items:stretch !important;
            "
        >
            {{-- Total Assets --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-gray-800 dark:bg-gray-900" style="
                    min-width:0 !important;
                    min-height:168px !important;
                    padding:18px !important;
                    border:1px solid #e5e7eb !important;
                    border-radius:14px !important;
                    background:#ffffff !important;
                    box-shadow:0 1px 3px rgba(15,23,42,.06) !important;
                    display:flex !important;
                    flex-direction:column !important;
                    justify-content:space-between !important;
                    overflow:hidden !important;
                ">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 ring-1 ring-inset ring-amber-100">
                        <svg viewBox="0 0 24 24"
                        width="24"
                        height="24"
                        style="width:24px !important;height:24px !important;max-width:24px !important;max-height:24px !important;display:block !important;" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="m12 3 8 4-8 4-8-4 8-4Zm8 4v10l-8 4-8-4V7m8 4v10" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>

                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-amber-700">
                        Assets
                    </span>
                </div>

                <p class="mt-4 text-[11px] font-black uppercase tracking-wider text-gray-500">
                    Total Assets
                </p>

                <p class="mt-1 text-3xl font-black text-gray-900 dark:text-white">
                    {{ $summary['total_assets'] }}
                </p>

                <div class="mt-5 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-300">
                            100% dari total asset
                        </p>

                        <p class="mt-1 text-[11px] text-gray-400">
                            Serialized inventory
                        </p>
                    </div>

                    <svg
                        viewBox="0 0 90 28"
                        width="90"
                        height="28"
                        class="text-amber-500"
                        style="
                            width:90px !important;
                            height:28px !important;
                            min-width:90px !important;
                            max-width:90px !important;
                            min-height:28px !important;
                            max-height:28px !important;
                            flex:0 0 90px !important;
                            display:block !important;
                        "
                        fill="none"
                    >
                        <path d="M2 22 14 18 25 20 36 9 47 18 58 6 69 17 80 14 88 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 26H88" stroke="currentColor" stroke-width="1" opacity=".18"/>
                    </svg>
                </div>
            </div>

            {{-- Available --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-gray-800 dark:bg-gray-900" style="
                    min-width:0 !important;
                    min-height:168px !important;
                    padding:18px !important;
                    border:1px solid #e5e7eb !important;
                    border-radius:14px !important;
                    background:#ffffff !important;
                    box-shadow:0 1px 3px rgba(15,23,42,.06) !important;
                    display:flex !important;
                    flex-direction:column !important;
                    justify-content:space-between !important;
                    overflow:hidden !important;
                ">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-100">
                        <svg viewBox="0 0 24 24"
                        width="24"
                        height="24"
                        style="width:24px !important;height:24px !important;max-width:24px !important;max-height:24px !important;display:block !important;" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="12" r="8"></circle>
                            <path d="m8.5 12 2.2 2.2L15.8 9" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>

                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-emerald-700">
                        Ready
                    </span>
                </div>

                <p class="mt-4 text-[11px] font-black uppercase tracking-wider text-gray-500">
                    Available
                </p>

                <p class="mt-1 text-3xl font-black text-gray-900 dark:text-white">
                    {{ $summary['available'] }}
                </p>

                <div class="mt-5 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-300">
                            {{ $assetAvailablePercent }}% tersedia
                        </p>

                        <p class="mt-1 text-[11px] text-gray-400">
                            Ready for allocation
                        </p>
                    </div>

                    <svg
                        viewBox="0 0 90 28"
                        width="90"
                        height="28"
                        class="text-emerald-500"
                        style="
                            width:90px !important;
                            height:28px !important;
                            min-width:90px !important;
                            max-width:90px !important;
                            min-height:28px !important;
                            max-height:28px !important;
                            flex:0 0 90px !important;
                            display:block !important;
                        "
                        fill="none"
                    >
                        <path d="M2 21 14 18 25 20 36 16 47 5 58 17 69 20 80 15 88 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 26H88" stroke="currentColor" stroke-width="1" opacity=".18"/>
                    </svg>
                </div>
            </div>

            {{-- Allocated / Picked --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-gray-800 dark:bg-gray-900" style="
                    min-width:0 !important;
                    min-height:168px !important;
                    padding:18px !important;
                    border:1px solid #e5e7eb !important;
                    border-radius:14px !important;
                    background:#ffffff !important;
                    box-shadow:0 1px 3px rgba(15,23,42,.06) !important;
                    display:flex !important;
                    flex-direction:column !important;
                    justify-content:space-between !important;
                    overflow:hidden !important;
                ">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 ring-1 ring-inset ring-blue-100">
                        <svg viewBox="0 0 24 24"
                        width="24"
                        height="24"
                        style="width:24px !important;height:24px !important;max-width:24px !important;max-height:24px !important;display:block !important;" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="m12 3 8 4-8 4-8-4 8-4Zm8 4v10l-8 4-8-4V7m8 4v10" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>

                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-blue-700">
                        Reserved
                    </span>
                </div>

                <p class="mt-4 text-[11px] font-black uppercase tracking-wider text-gray-500">
                    Allocated / Picked
                </p>

                <p class="mt-1 text-3xl font-black text-gray-900 dark:text-white">
                    {{ $summary['allocated'] + $summary['picked'] }}
                </p>

                <div class="mt-5 flex items-end justify-between gap-4">
                    <p class="text-xs font-bold text-gray-700 dark:text-gray-300">
                        {{ $summary['allocated'] }} allocated · {{ $summary['picked'] }} picked
                    </p>

                    <svg
                        viewBox="0 0 90 28"
                        width="90"
                        height="28"
                        class="text-blue-500"
                        style="
                            width:90px !important;
                            height:28px !important;
                            min-width:90px !important;
                            max-width:90px !important;
                            min-height:28px !important;
                            max-height:28px !important;
                            flex:0 0 90px !important;
                            display:block !important;
                        "
                        fill="none"
                    >
                        <path d="M2 20 14 20 25 20 36 19 47 20 58 19 69 20 80 19 88 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
            </div>

            {{-- Out / Return Pending --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-gray-800 dark:bg-gray-900" style="
                    min-width:0 !important;
                    min-height:168px !important;
                    padding:18px !important;
                    border:1px solid #e5e7eb !important;
                    border-radius:14px !important;
                    background:#ffffff !important;
                    box-shadow:0 1px 3px rgba(15,23,42,.06) !important;
                    display:flex !important;
                    flex-direction:column !important;
                    justify-content:space-between !important;
                    overflow:hidden !important;
                ">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 text-violet-600 ring-1 ring-inset ring-violet-100">
                        <svg viewBox="0 0 24 24"
                        width="24"
                        height="24"
                        style="width:24px !important;height:24px !important;max-width:24px !important;max-height:24px !important;display:block !important;" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M7 7h10a3 3 0 0 1 3 3v7H8m0 0 3-3m-3 3 3 3M4 4v9" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>

                    <span class="rounded-full bg-violet-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-violet-700">
                        Workflow
                    </span>
                </div>

                <p class="mt-4 text-[11px] font-black uppercase tracking-wider text-gray-500">
                    Out / Return Pending
                </p>

                <p class="mt-1 text-3xl font-black text-gray-900 dark:text-white">
                    {{ $summary['out'] + $summary['return_pending'] }}
                </p>

                <div class="mt-5 flex items-end justify-between gap-4">
                    <p class="text-xs font-bold text-gray-700 dark:text-gray-300">
                        {{ $summary['out'] }} out · {{ $summary['return_pending'] }} pending
                    </p>

                    <svg
                        viewBox="0 0 90 28"
                        width="90"
                        height="28"
                        class="text-violet-500"
                        style="
                            width:90px !important;
                            height:28px !important;
                            min-width:90px !important;
                            max-width:90px !important;
                            min-height:28px !important;
                            max-height:28px !important;
                            flex:0 0 90px !important;
                            display:block !important;
                        "
                        fill="none"
                    >
                        <path d="M2 20 14 19 25 10 36 20 47 16 58 18 69 15 80 18 88 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>

            {{-- Problem Assets --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-gray-800 dark:bg-gray-900" style="
                    min-width:0 !important;
                    min-height:168px !important;
                    padding:18px !important;
                    border:1px solid #e5e7eb !important;
                    border-radius:14px !important;
                    background:#ffffff !important;
                    box-shadow:0 1px 3px rgba(15,23,42,.06) !important;
                    display:flex !important;
                    flex-direction:column !important;
                    justify-content:space-between !important;
                    overflow:hidden !important;
                ">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-600 ring-1 ring-inset ring-red-100">
                        <svg viewBox="0 0 24 24"
                        width="24"
                        height="24"
                        style="width:24px !important;height:24px !important;max-width:24px !important;max-height:24px !important;display:block !important;" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M12 8v5m0 3h.01M10.3 4.7 3.7 17a2 2 0 0 0 1.76 3h13.08a2 2 0 0 0 1.76-3L13.7 4.7a2 2 0 0 0-3.4 0Z" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>

                    <span class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-red-700">
                        Attention
                    </span>
                </div>

                <p class="mt-4 text-[11px] font-black uppercase tracking-wider text-gray-500">
                    Problem Assets
                </p>

                <p class="mt-1 text-3xl font-black text-gray-900 dark:text-white">
                    {{ $summary['problem_assets'] }}
                </p>

                <div class="mt-5 flex items-end justify-between gap-4">
                    <p class="text-xs font-bold text-gray-700 dark:text-gray-300">
                        {{ $summary['damaged'] }} damaged · {{ $summary['missing'] }} missing · {{ $summary['maintenance'] }} maintenance
                    </p>

                    <svg
                        viewBox="0 0 90 28"
                        width="90"
                        height="28"
                        class="text-red-500"
                        style="
                            width:90px !important;
                            height:28px !important;
                            min-width:90px !important;
                            max-width:90px !important;
                            min-height:28px !important;
                            max-height:28px !important;
                            flex:0 0 90px !important;
                            display:block !important;
                        "
                        fill="none"
                    >
                        <path d="M2 21 14 10 25 20 36 14 47 22 58 17 69 19 80 13 88 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- MAIN CARDS --}}
        <div
            style="
                width:100% !important;
                display:grid !important;
                grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)) !important;
                gap:14px !important;
                align-items:stretch !important;
            "
        >
            {{-- Stock Health --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900" style="
                    min-width:0 !important;
                    min-height:330px !important;
                    padding:18px !important;
                    border:1px solid #e5e7eb !important;
                    border-radius:14px !important;
                    background:#ffffff !important;
                    box-shadow:0 1px 3px rgba(15,23,42,.06) !important;
                    overflow:hidden !important;
                ">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-black text-gray-900 dark:text-white">
                                Stock Health
                            </p>

                            <span class="flex h-5 w-5 items-center justify-center rounded-full border border-gray-200 text-[10px] font-bold text-gray-400">
                                i
                            </span>
                        </div>

                        <p class="mt-1 text-xs text-gray-500">
                            Kondisi quantity-tracked inventory.
                        </p>
                    </div>

                    @if ($stockHealth['out'] > 0)
                        <span class="rounded-full bg-red-100 px-3 py-1 text-[10px] font-black text-red-700">
                            ACTION NEEDED
                        </span>
                    @elseif ($stockHealth['low'] > 0)
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-[10px] font-black text-amber-700">
                            LOW STOCK
                        </span>
                    @else
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-[10px] font-black text-emerald-700">
                            STOCK HEALTHY
                        </span>
                    @endif
                </div>

                <div class="mt-7 flex items-center gap-7 max-sm:flex-col">
                    <div
                        class="relative"
                        style="
                            width:136px !important;
                            height:136px !important;
                            min-width:136px !important;
                            max-width:136px !important;
                            min-height:136px !important;
                            max-height:136px !important;
                            flex:0 0 136px !important;
                            border-radius:9999px !important;
                            display:flex !important;
                            align-items:center !important;
                            justify-content:center !important;
                        "
                        style="background: conic-gradient(
                            #22c55e 0 {{ $stockHealth['healthy_percent'] }}%,
                            #f59e0b {{ $stockHealth['healthy_percent'] }}% {{ min($stockHealth['healthy_percent'] + $stockHealth['low_percent'], 100) }}%,
                            #ef4444 {{ min($stockHealth['healthy_percent'] + $stockHealth['low_percent'], 100) }}% 100%
                        );"
                    >
                        <div class="bg-white dark:bg-gray-900"
                            style="
                                width:92px !important;
                                height:92px !important;
                                border-radius:9999px !important;
                                display:flex !important;
                                flex-direction:column !important;
                                align-items:center !important;
                                justify-content:center !important;
                                box-shadow:inset 0 1px 3px rgba(15,23,42,.08) !important;
                            ">
                            <p class="text-2xl font-black text-gray-900 dark:text-white">
                                {{ $stockHealth['healthy_percent'] }}%
                            </p>

                            <p class="text-xs font-bold text-gray-500">
                                Healthy
                            </p>
                        </div>
                    </div>

                    <div class="min-w-0 flex-1 space-y-4">
                        <div class="grid grid-cols-[1fr_auto_auto] items-center gap-4">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    Healthy
                                </span>
                            </div>

                            <span class="text-sm font-black text-gray-900 dark:text-white">
                                {{ $stockHealth['healthy'] }} item
                            </span>

                            <span class="text-xs font-bold text-gray-500">
                                {{ $stockHealth['healthy_percent'] }}%
                            </span>
                        </div>

                        <div class="grid grid-cols-[1fr_auto_auto] items-center gap-4">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    Low Stock
                                </span>
                            </div>

                            <span class="text-sm font-black text-gray-900 dark:text-white">
                                {{ $stockHealth['low'] }} item
                            </span>

                            <span class="text-xs font-bold text-gray-500">
                                {{ $stockHealth['low_percent'] }}%
                            </span>
                        </div>

                        <div class="grid grid-cols-[1fr_auto_auto] items-center gap-4">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    Out of Stock
                                </span>
                            </div>

                            <span class="text-sm font-black text-gray-900 dark:text-white">
                                {{ $stockHealth['out'] }} item
                            </span>

                            <span class="text-xs font-bold text-gray-500">
                                {{ $stockHealth['out_percent'] }}%
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950/30">
                    @if ($stockHealth['low'] === 0 && $stockHealth['out'] === 0)
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>

                            <div>
                                <p class="text-sm font-black text-gray-900 dark:text-white">
                                    Semua item dalam kondisi stock aman.
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Pertahankan performa inventory saat ini.
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                                !
                            </span>

                            <div>
                                <p class="text-sm font-black text-gray-900 dark:text-white">
                                    {{ $stockHealth['low'] + $stockHealth['out'] }} item membutuhkan perhatian.
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Gunakan Alerts & Reorder untuk tindak lanjut restock.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Asset Attention --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900" style="
                    min-width:0 !important;
                    min-height:330px !important;
                    padding:18px !important;
                    border:1px solid #e5e7eb !important;
                    border-radius:14px !important;
                    background:#ffffff !important;
                    box-shadow:0 1px 3px rgba(15,23,42,.06) !important;
                    overflow:hidden !important;
                ">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-black text-gray-900 dark:text-white">
                                Asset Attention
                            </p>

                            <span class="flex h-5 w-5 items-center justify-center rounded-full border border-gray-200 text-[10px] font-bold text-gray-400">
                                i
                            </span>
                        </div>

                        <p class="mt-1 text-xs text-gray-500">
                            Asset yang memerlukan perhatian.
                        </p>
                    </div>

                    <a
                        href="{{ route('admin.inventory.assets.index') }}"
                        class="text-xs font-black text-brandColor hover:underline"
                    >
                        Lihat semua
                    </a>
                </div>

                <div class="mt-5 divide-y divide-gray-100 dark:divide-gray-800">
                    <a
                        href="{{ route('admin.inventory.alerts.index', ['tab' => 'assets']) }}"
                        class="flex items-center gap-3 py-3.5"
                    >
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 text-red-600 ring-1 ring-red-100">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M12 8v5m0 3h.01M10.3 4.7 3.7 17a2 2 0 0 0 1.76 3h13.08a2 2 0 0 0 1.76-3L13.7 4.7a2 2 0 0 0-3.4 0Z" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-black text-gray-900 dark:text-white">
                                Missing Assets
                            </p>

                            <p class="mt-0.5 text-xs text-gray-500">
                                Asset tidak ditemukan.
                            </p>
                        </div>

                        <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-black text-red-700">
                            {{ $assetAttention['missing'] }}
                        </span>

                        <span class="text-gray-300">›</span>
                    </a>

                    <a
                        href="{{ route('admin.inventory.maintenance.index') }}"
                        class="flex items-center gap-3 py-3.5"
                    >
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M14.7 6.3a4 4 0 0 0-5.6 5.6l-5.4 5.4a2 2 0 1 0 2.8 2.8l5.4-5.4a4 4 0 0 0 5.6-5.6l-2.4 2.4-2.6-2.6 2.2-2.6Z" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-black text-gray-900 dark:text-white">
                                Maintenance
                            </p>

                            <p class="mt-0.5 text-xs text-gray-500">
                                Sedang dalam proses repair.
                            </p>
                        </div>

                        <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-black text-violet-700">
                            {{ $assetAttention['maintenance'] }}
                        </span>

                        <span class="text-gray-300">›</span>
                    </a>

                    <a
                        href="{{ route('admin.inventory.alerts.index', ['tab' => 'returns']) }}"
                        class="flex items-center gap-3 py-3.5"
                    >
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600 ring-1 ring-cyan-100">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M7 7h10a3 3 0 0 1 3 3v7H8m0 0 3-3m-3 3 3 3M4 4v9" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-black text-gray-900 dark:text-white">
                                Return Pending
                            </p>

                            <p class="mt-0.5 text-xs text-gray-500">
                                Menunggu finalize return.
                            </p>
                        </div>

                        <span class="rounded-full bg-cyan-100 px-2.5 py-1 text-xs font-black text-cyan-700">
                            {{ $assetAttention['return_pending'] }}
                        </span>

                        <span class="text-gray-300">›</span>
                    </a>

                    <a
                        href="{{ route('admin.inventory.alerts.index', ['tab' => 'assets']) }}"
                        class="flex items-center gap-3 py-3.5"
                    >
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 ring-1 ring-amber-100">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M12 8v5m0 3h.01M10.3 4.7 3.7 17a2 2 0 0 0 1.76 3h13.08a2 2 0 0 0 1.76-3L13.7 4.7a2 2 0 0 0-3.4 0Z" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-black text-gray-900 dark:text-white">
                                Damaged Assets
                            </p>

                            <p class="mt-0.5 text-xs text-gray-500">
                                Perlu perbaikan.
                            </p>
                        </div>

                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-black text-amber-700">
                            {{ $assetAttention['damaged'] }}
                        </span>

                        <span class="text-gray-300">›</span>
                    </a>
                </div>
            </div>

            {{-- Alerts & Reorder --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900" style="
                    min-width:0 !important;
                    min-height:330px !important;
                    padding:18px !important;
                    border:1px solid #e5e7eb !important;
                    border-radius:14px !important;
                    background:#ffffff !important;
                    box-shadow:0 1px 3px rgba(15,23,42,.06) !important;
                    overflow:hidden !important;
                ">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-black text-gray-900 dark:text-white">
                            Alerts & Reorder
                        </p>

                        <p class="mt-1 text-xs text-gray-500">
                            Preview masalah prioritas inventory.
                        </p>
                    </div>

                    @if (bouncer()->hasPermission('inventory.alerts'))
                        <a
                            href="{{ route('admin.inventory.alerts.index') }}"
                            class="text-xs font-black text-brandColor hover:underline"
                        >
                            Lihat semua
                        </a>
                    @endif
                </div>

                <div class="mt-5 grid gap-2.5">
                    @forelse ($dashboardAlerts as $alert)
                        @php
                            $tone = $alertTone($alert['type']);
                        @endphp

                        <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-3.5 dark:border-gray-800 dark:bg-gray-900">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 {{ $tone['icon'] }}">
                                @if ($alert['type'] === 'critical')
                                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M12 8v5m0 3h.01M10.3 4.7 3.7 17a2 2 0 0 0 1.76 3h13.08a2 2 0 0 0 1.76-3L13.7 4.7a2 2 0 0 0-3.4 0Z" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                @elseif ($alert['type'] === 'warning')
                                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M4 7h16M6 7l1 12h10l1-12M9 11v4M15 11v4M9 4h6" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                @else
                                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="m12 3 8 4-8 4-8-4 8-4Zm8 4v10l-8 4-8-4V7m8 4v10" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                @endif
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-black text-gray-900 dark:text-white">
                                    {{ $alert['title'] }}
                                </p>

                                <p class="mt-0.5 truncate text-xs text-gray-500">
                                    {{ $alert['subtitle'] }}
                                </p>
                            </div>

                            <div class="shrink-0 text-right">
                                <p class="text-sm font-black {{ $tone['value'] }}">
                                    {{ $alert['value'] }}
                                </p>

                                <p class="mt-0.5 text-[11px] text-gray-500">
                                    {{ $alert['detail'] }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="flex min-h-[220px] flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 p-6 text-center dark:border-gray-800">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>

                            <p class="mt-3 text-sm font-black text-gray-900 dark:text-white">
                                No active alerts
                            </p>

                            <p class="mt-1 text-xs text-gray-500">
                                Inventory sedang sehat.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- RECENT MOVEMENTS --}}
        <div
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
            style="
                width:100% !important;
                border:1px solid #e5e7eb !important;
                border-radius:14px !important;
                background:#ffffff !important;
                box-shadow:0 1px 3px rgba(15,23,42,.06) !important;
                overflow:hidden !important;
            "
        >
            <div class="flex items-start justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-800">
                <div>
                    <p class="font-black text-gray-900 dark:text-white">
                        Recent Movements
                    </p>

                    <p class="mt-1 text-xs text-gray-500">
                        Pergerakan inventory terbaru.
                    </p>
                </div>

                <a
                    href="{{ route('admin.inventory.movements.index') }}"
                    class="text-xs font-black text-brandColor hover:underline"
                >
                    Lihat semua
                </a>
            </div>

            @if ($recentMovements->isEmpty())
                <div class="p-10 text-center text-sm text-gray-500">
                    Belum ada movement inventory.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table
                        style="
                            width:100% !important;
                            min-width:980px !important;
                            border-collapse:collapse !important;
                            table-layout:auto !important;
                        "
                    >
                        <thead class="border-b border-gray-200 bg-gray-50/80 text-left text-[11px] font-black uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/30">
                            <tr>
                                <th class="px-5 py-3">Waktu</th>
                                <th class="px-4 py-3">Tipe</th>
                                <th class="px-4 py-3">Item / Asset</th>
                                <th class="px-4 py-3">Warehouse</th>
                                <th class="px-4 py-3">Quantity</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Reference</th>
                                <th class="px-5 py-3 text-right">Dilakukan Oleh</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($recentMovements as $movement)
                                <tr class="border-b border-gray-100 transition hover:bg-gray-50/60 last:border-b-0 dark:border-gray-800 dark:hover:bg-gray-800/30">
                                    <td class="whitespace-nowrap px-5 py-4 text-xs font-semibold text-gray-500">
                                        {{ \Carbon\Carbon::parse($movement->occurred_at)->format('d M Y H:i') }}
                                    </td>

                                    <td class="px-4 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-black {{ $movementTone($movement->movement_type) }}">
                                            {{ $movementLabel($movement->movement_type) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-4">
                                        <p class="text-sm font-black text-gray-900 dark:text-white">
                                            {{ $movement->asset_code ?: $movement->item_name }}
                                        </p>

                                        <p class="mt-0.5 text-xs text-gray-500">
                                            {{ $movement->item_code }} — {{ $movement->item_name }}
                                        </p>
                                    </td>

                                    <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $movement->warehouse_name ?: 'Warehouse' }}
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4 text-sm font-bold text-gray-900 dark:text-white">
                                        {{ $formatQty($movement->quantity) }} {{ $movement->unit }}
                                    </td>

                                    <td class="px-4 py-4">
                                        @if ($movement->to_status)
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-black text-emerald-700">
                                                {{ strtoupper(str_replace('_', ' ', $movement->to_status)) }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">
                                                —
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-4 font-mono text-xs text-gray-500">
                                        {{ $movement->reference_number ?: '-' }}
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-4 text-right text-xs font-semibold text-gray-500">
                                        {{ $movement->performed_by_name ?: 'System' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-admin::layouts>
