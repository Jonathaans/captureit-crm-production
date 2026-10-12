<x-admin::layouts>
    <x-slot:title>
        Inventory Allocation - {{ $deliveryOrder->delivery_order_number }}
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

        $status = strtolower(
            $deliveryOrder->status ?: 'draft'
        );

        $serializedItems = $deliveryOrder->items
            ->filter(
                fn ($item) =>
                    $item->inventoryItem
                    && $item->inventoryItem->isSerialized()
            );

        $quantityItems = $deliveryOrder->items
            ->filter(
                fn ($item) =>
                    $item->inventoryItem
                    && $item->inventoryItem->isQuantityTracked()
            );
    @endphp

    <div
        data-allocation-scanner
        data-editable="{{ $editable ? '1' : '0' }}"
        data-endpoint="{{ route('admin.delivery-orders.inventory-allocation.scan', $deliveryOrder->id) }}"
        data-csrf="{{ csrf_token() }}"
    >
    <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900">
        <div>
            <a
                href="{{ route(
                    'admin.delivery-orders.show',
                    $deliveryOrder->id
                ) }}"
                class="text-sm text-gray-600 hover:text-brandColor dark:text-gray-300"
            >
                &larr; Back to Surat Jalan
            </a>

            <div class="mt-2 flex items-center gap-3">
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    Manage Allocation Items
                </p>

                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold uppercase text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $status }}
                </span>
            </div>

            <p class="mt-1 text-sm text-gray-500">
                {{ $deliveryOrder->delivery_order_number }}
                &middot; {{ $deliveryOrder->project_code ?: '-' }}

                @if ($deliveryOrder->project_name)
                    &middot; {{ $deliveryOrder->project_name }}
                @endif
            </p>
        </div>

        @if ($editable)
            <div
                id="scanner-state"
                class="rounded-lg border border-green-200 bg-green-50 px-5 py-3 text-right"
            >
                <p class="text-xs font-bold uppercase text-green-600">
                    Scanner
                </p>

                <p
                    id="scanner-state-text"
                    class="mt-1 text-sm font-bold text-green-800"
                >
                    READY - langsung scan QR
                </p>
            </div>
        @else
            <div class="rounded-lg bg-gray-100 px-4 py-3 text-sm font-semibold text-gray-600">
                Read Only
            </div>
        @endif
    </div>

    @if ($editable)
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-5">
            <div class="flex items-start justify-between gap-4 max-lg:flex-wrap">
                <div>
                    <p class="text-lg font-bold text-green-900">
                        Scan beruntun atau masukkan kode alat
                    </p>

                    <p class="mt-1 text-sm text-green-700">
                        Scan asset serialized dari urutan mana pun, lalu Enter.
                        Scan berikutnya tetap masuk antrean saat permintaan sebelumnya diproses.
                        Pastikan setiap kode berstatus TERSIMPAN sebelum meninggalkan halaman.
                    </p>
                </div>

                <div class="rounded-full bg-white px-4 py-2 text-xs font-bold text-green-700">
                    AVAILABLE &rarr; ALLOCATED
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <div
                    id="scan-indicator"
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-green-600 text-xl font-bold text-white"
                >
                    QR
                </div>

                <div>
                    <p class="text-sm font-semibold text-green-900">
                        Siap menerima scan atau input manual
                    </p>

                    <p
                        id="last-scan"
                        class="mt-1 text-xs text-green-700"
                    >
                        Belum ada scan pada sesi ini.
                    </p>
                </div>
            </div>

            <form
                id="allocation-manual-form"
                method="POST"
                action="{{ route('admin.delivery-orders.inventory-allocation.scan', $deliveryOrder->id) }}"
                class="mt-4 flex items-end gap-3 max-sm:flex-wrap"
            >
                @csrf
                @method('PUT')
                <div class="min-w-0 flex-1">
                    <label for="allocation-manual-code" class="mb-1 block text-sm font-semibold text-green-900">
                        Input manual / scanner: Asset Code, Barcode, atau Serial Number
                    </label>
                    <input
                        id="allocation-manual-code"
                        type="text"
                        name="barcode"
                        maxlength="255"
                        required
                        autocomplete="off"
                        spellcheck="false"
                        data-allow-typing
                        placeholder="Contoh: CAM-001, lalu Enter"
                        class="w-full rounded-md border border-green-300 bg-white px-3 py-2.5 text-sm text-gray-900"
                    >
                </div>
                <button type="submit" class="primary-button">Tambahkan alat</button>
            </form>
            <p class="mt-2 text-xs text-green-800">
                Setiap kode mewakili satu alat fisik. Untuk permintaan 4 unit, masukkan 4 kode alat yang berbeda.
            </p>
            <div id="scan-message" hidden role="status"></div>
            <details class="mt-4" open>
                <summary class="cursor-pointer text-sm font-semibold text-green-900">Hasil input: 20 terakhir dan kode yang perlu dicek</summary>
                <ol id="scan-history" aria-live="polite" class="mt-2 grid max-h-64 gap-2 overflow-y-auto"></ol>
            </details>

            <div class="mt-4 rounded-md border border-green-200 bg-white/70 px-4 py-3 text-xs text-green-800">
                <strong>Double Event Protection aktif.</strong>
                Asset yang masih ALLOCATED, OUT, atau RETURN PENDING pada Surat Jalan lain
                akan ditolak otomatis. Asset baru dapat dipakai lagi setelah Return selesai
                dan status kembali AVAILABLE.
            </div>
        </div>
    @endif

    @if ($deliveryOrder->items->contains(fn ($item) => ! $item->inventoryItem))
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <strong>Ada request yang belum terhubung ke Inventory Item.</strong>
            Edit Surat Jalan terlebih dahulu sebelum release.
        </div>
    @endif

    <div class="mt-5">
        <div class="mb-3 flex items-end justify-between gap-3">
            <div>
                <p class="text-lg font-bold text-gray-800 dark:text-white">
                    Serialized Assets
                </p>

                <p class="text-xs text-gray-500">
                    Camera, Printer, Mini PC, Lighting, dan asset unik lainnya.
                </p>
            </div>

            <p class="text-xs text-gray-500">
                {{ $serializedItems->count() }} request
            </p>
        </div>

        <div class="grid gap-4">
            @forelse ($serializedItems as $item)
                @php
                    $summary = $summaries[$item->id];

                    $allocations = $item->allocations
                        ->whereIn(
                            'status',
                            \Webkul\Invoice\Models\DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                        )
                        ->where('tracking_type', 'serialized');
                @endphp

                <div
                    id="allocation-item-{{ $item->id }}"
                    data-item-id="{{ $item->id }}"
                    class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex items-start justify-between gap-4 max-sm:flex-wrap">
                        <div>
                            <p class="text-base font-bold text-gray-800 dark:text-white">
                                {{ $item->name }}
                            </p>

                            @if ($item->description)
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ $item->description }}
                                </p>
                            @endif

                            <p class="mt-2 text-xs font-semibold text-gray-500">
                                {{ $item->inventoryItem->code }}
                                &middot;
                                {{ $item->inventoryItem->name }}
                            </p>
                        </div>

                        <span
                            data-complete-badge
                            class="rounded-full px-3 py-1 text-xs font-bold {{ $summary['complete'] ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}"
                        >
                            {{ $summary['complete'] ? 'COMPLETE' : 'WAITING SCAN' }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-4 gap-3 max-lg:grid-cols-2 max-sm:grid-cols-1">
                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">Request</p>

                            <p class="mt-1 text-xl font-bold text-gray-800">
                                {{ $formatQty($summary['need']) }}
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">Scanned</p>

                            <p
                                data-allocated-count
                                class="mt-1 text-xl font-bold text-blue-700"
                            >
                                {{ $formatQty($summary['allocated']) }}
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">Free Available</p>

                            <p
                                data-free-count
                                class="mt-1 text-xl font-bold text-green-700"
                            >
                                {{ $formatQty($summary['free_available']) }}
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">Shortage</p>

                            <p
                                data-shortage-count
                                class="mt-1 text-xl font-bold {{ $summary['shortage'] > 0 ? 'text-red-700' : 'text-gray-800' }}"
                            >
                                {{ $formatQty($summary['shortage']) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <p class="mb-2 text-xs font-bold uppercase text-gray-500">
                            Actual Assets
                        </p>

                        <div
                            data-asset-list
                            class="flex flex-wrap gap-2"
                        >
                            @forelse ($allocations as $allocation)
                                <span
                                    data-asset-id="{{ $allocation->inventory_asset_id }}"
                                    class="rounded-full bg-blue-100 px-3 py-1.5 text-xs font-bold text-blue-700"
                                >
                                    {{ $allocation->inventoryAsset?->asset_code ?: '-' }}
                                    &#10003;
                                </span>
                            @empty
                                <span
                                    data-empty-assets
                                    class="text-sm text-gray-400"
                                >
                                    Belum ada asset discan.
                                </span>
                            @endforelse
                        </div>
                    </div>

                    @if ($editable)
                        <div data-allocation-reset @if ($summary['allocated'] <= 0) hidden @endif class="mt-4 text-right">
                            <form
                                method="POST"
                                action="{{ route(
                                    'admin.delivery-orders.inventory-allocation.release',
                                    [
                                        $deliveryOrder->id,
                                        $item->id,
                                    ]
                                ) }}"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="text-xs font-semibold text-red-600 hover:underline"
                                    onclick="return confirm('Reset seluruh scanned asset untuk {{ $item->name }}?')"
                                >
                                    Reset scanned assets
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">
                    Tidak ada serialized asset pada request ini.
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-6">
        <div class="mb-3">
            <p class="text-lg font-bold text-gray-800 dark:text-white">
                Manual Quantity Preparation
            </p>

            <p class="mt-1 text-xs text-gray-500">
                Quantity items tidak discan. Masukkan jumlah fisik yang benar-benar disiapkan.
                Paper Roll dan Frame tetap menggunakan flow ini.
            </p>
        </div>

        <div class="grid gap-3">
            @forelse ($quantityItems as $item)
                @php
                    $summary = $summaries[$item->id];
                @endphp

                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
                        <div>
                            <p class="font-bold text-gray-800 dark:text-white">
                                {{ $item->name }}
                            </p>

                            <p class="mt-1 text-xs text-gray-500">
                                {{ $item->inventoryItem->code }}
                                &middot;
                                {{ $item->inventoryItem->name }}
                            </p>
                        </div>

                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $summary['complete'] ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                            {{ $summary['complete'] ? 'READY' : 'INPUT REQUIRED' }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-4 gap-3 max-lg:grid-cols-2 max-sm:grid-cols-1">
                        <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                            <p class="text-xs text-gray-500">Request</p>

                            <p class="mt-1 font-bold">
                                {{ $formatQty($summary['need']) }}
                                {{ $item->inventoryItem->unit }}
                            </p>
                        </div>

                        <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                            <p class="text-xs text-gray-500">Prepared</p>

                            <p class="mt-1 font-bold text-blue-700">
                                {{ $formatQty($summary['allocated']) }}
                                {{ $item->inventoryItem->unit }}
                            </p>
                        </div>

                        <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                            <p class="text-xs text-gray-500">Physical Stock</p>

                            <p class="mt-1 font-bold text-gray-800 dark:text-white">
                                {{ $formatQty($item->inventoryItem->quantity_on_hand) }}
                                {{ $item->inventoryItem->unit }}
                            </p>
                        </div>

                        <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                            <p class="text-xs text-gray-500">Free Available</p>

                            <p class="mt-1 font-bold {{ $summary['free_available'] + 0.0001 < $summary['need'] ? 'text-red-700' : 'text-green-700' }}">
                                {{ $formatQty($summary['free_available']) }}
                                {{ $item->inventoryItem->unit }}
                            </p>
                        </div>
                    </div>

                    @if ($editable)
                        <form
                            method="POST"
                            action="{{ route(
                                'admin.delivery-orders.inventory-allocation.update',
                                [
                                    $deliveryOrder->id,
                                    $item->id,
                                ]
                            ) }}"
                            class="mt-4 grid grid-cols-[minmax(0,1fr)_auto] items-end gap-3 max-sm:grid-cols-1"
                        >
                            @csrf
                            @method('PUT')

                            <div>
                                <label class="mb-1 block text-xs font-bold text-gray-600 dark:text-gray-300">
                                    Prepared Quantity
                                </label>

                                <div class="flex items-center gap-2">
                                    <input
                                        type="number"
                                        name="quantity"
                                        value="{{ $formatQty($summary['allocated']) }}"
                                        min="0"
                                        max="{{ $formatQty($summary['need']) }}"
                                        step="0.01"
                                        required
                                        data-allow-typing
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    >

                                    <span class="whitespace-nowrap text-sm font-semibold text-gray-500">
                                        {{ $item->inventoryItem->unit }}
                                    </span>
                                </div>

                                <p class="mt-1 text-xs text-gray-400">
                                    Agar request COMPLETE, Prepared Quantity harus sama dengan Request.
                                </p>
                            </div>

                            <button
                                type="submit"
                                class="primary-button"
                            >
                                Save Quantity
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                    Tidak ada quantity stock pada request ini.
                </div>
            @endforelse
        </div>

        <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-xs text-blue-800 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-200">
            <strong>Barcode Box:</strong>
            item seperti Extension Cable Box dan Props Box akan otomatis masuk bagian Serialized Assets
            setelah master Inventory Item dibuat sebagai <strong>serialized</strong> dan Equipment Template
            dipetakan ke item box tersebut.
        </div>
    </div>

    </div>
</x-admin::layouts>
