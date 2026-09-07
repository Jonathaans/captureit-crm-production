@php
    /* CRM_DELIVERY_ORDER_UNLIMITED_ITEMS_UI_V1 */
    $existingItems = old('items');

    if ($existingItems === null) {
        $existingItems = $deliveryOrder->items
            ->map(function ($item) {
                return [
                    'inventory_item_id' => $item->inventory_item_id,
                    'name'              => $item->name,
                    'description'       => $item->description,
                    'quantity'          => $item->quantity,
                    'unit'              => $item->unit,
                    'notes'             => $item->notes,
                ];
            })
            ->values()
            ->toArray();
    }

    if (empty($existingItems)) {
        $existingItems = [[
            'inventory_item_id' => null,
            'name'              => '',
            'description'       => '',
            'quantity'          => 1,
            'unit'              => 'unit',
            'notes'             => '',
        ]];
    }

    /* Normalize sparse old-input keys so newly appended rows never reuse an index. */
    $existingItems = array_values($existingItems);

    $inventoryItems = \Webkul\Warehouse\Models\InventoryItem::query()
        ->where('is_active', true)
        ->orderBy('code')
        ->orderBy('name')
        ->get([
            'id',
            'code',
            'name',
            'tracking_type',
            'unit',
        ]);
@endphp

<div
    class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
    data-crm-equipment-editor
    data-next-index="{{ count($existingItems) }}"
>
    <div class="border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white px-5 py-5 dark:border-gray-800 dark:from-gray-900 dark:to-gray-950">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-3xl">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-base font-semibold text-gray-800 dark:text-white">
                        Equipment / Inventory Requirement
                    </p>

                    <span
                        class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                        data-equipment-count
                    >
                        {{ count($existingItems) }} item
                    </span>
                </div>

                <p class="mt-1.5 text-xs leading-5 text-gray-500 dark:text-gray-400">
                    Tambahkan sebanyak apa pun kebutuhan Surat Jalan. Inventory Item menghubungkan
                    kebutuhan dengan master stok; actual asset dipilih saat Allocation / Picking.
                </p>
            </div>

            <button
                type="button"
                class="primary-button inline-flex items-center gap-2 whitespace-nowrap"
                data-add-equipment
            >
                <span class="text-base leading-none">+</span>
                Tambah Item
            </button>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <label class="relative block w-full max-w-md">
                <span class="sr-only">Cari item</span>
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">⌕</span>
                <input
                    type="search"
                    placeholder="Cari nama, deskripsi, inventory, atau catatan..."
                    class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 outline-none transition focus:border-brandColor focus:ring-2 focus:ring-brandColor/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    data-equipment-search
                >
            </label>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                Tidak ada batas 10 item. Baris kosong otomatis diabaikan saat disimpan.
            </p>
        </div>
    </div>

    <div class="max-h-[680px] overflow-auto" data-equipment-table-wrap>
        <table class="w-full min-w-[1420px] border-separate border-spacing-0">
            <thead class="sticky top-0 z-10 bg-gray-100 shadow-sm dark:bg-gray-950">
                <tr>
                    <th class="w-[52px] border-b border-gray-200 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800">
                        #
                    </th>
                    <th class="min-w-[180px] border-b border-gray-200 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800">
                        Item
                    </th>
                    <th class="min-w-[220px] border-b border-gray-200 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800">
                        Description
                    </th>
                    <th class="min-w-[330px] border-b border-gray-200 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800">
                        Inventory Item
                    </th>
                    <th class="w-[105px] border-b border-gray-200 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800">
                        Qty
                    </th>
                    <th class="w-[110px] border-b border-gray-200 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800">
                        Unit
                    </th>
                    <th class="min-w-[220px] border-b border-gray-200 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800">
                        Notes
                    </th>
                    <th class="w-[82px] border-b border-gray-200 px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800">
                        Aksi
                    </th>
                </tr>
            </thead>

            <tbody data-equipment-rows>
                @foreach ($existingItems as $index => $item)
                    @php
                        $selectedInventoryItemId = $item['inventory_item_id'] ?? null;
                    @endphp

                    <tr class="group bg-white transition hover:bg-amber-50/40 dark:bg-gray-900 dark:hover:bg-gray-800/60" data-equipment-row>
                        <td class="border-b border-gray-100 px-3 py-3 align-top text-sm font-semibold text-gray-400 dark:border-gray-800" data-row-number>
                            {{ $index + 1 }}
                        </td>

                        <td class="border-b border-gray-100 px-3 py-3 align-top dark:border-gray-800">
                            <input
                                type="text"
                                name="items[{{ $index }}][name]"
                                value="{{ $item['name'] ?? '' }}"
                                placeholder="Contoh: Camera"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brandColor focus:ring-2 focus:ring-brandColor/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                data-equipment-name
                            >
                        </td>

                        <td class="border-b border-gray-100 px-3 py-3 align-top dark:border-gray-800">
                            <input
                                type="text"
                                name="items[{{ $index }}][description]"
                                value="{{ $item['description'] ?? '' }}"
                                placeholder="Spesifikasi atau detail"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brandColor focus:ring-2 focus:ring-brandColor/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                        </td>

                        <td class="border-b border-gray-100 px-3 py-3 align-top dark:border-gray-800">
                            <select
                                name="items[{{ $index }}][inventory_item_id]"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brandColor focus:ring-2 focus:ring-brandColor/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                data-inventory-select
                            >
                                <option value="">-- Not linked --</option>

                                @foreach ($inventoryItems as $inventoryItem)
                                    <option
                                        value="{{ $inventoryItem->id }}"
                                        data-name="{{ $inventoryItem->name }}"
                                        data-unit="{{ $inventoryItem->unit ?: 'unit' }}"
                                        @selected((string) $selectedInventoryItemId === (string) $inventoryItem->id)
                                    >
                                        {{ $inventoryItem->code }} — {{ $inventoryItem->name }}
                                        ({{ ucfirst($inventoryItem->tracking_type) }})
                                    </option>
                                @endforeach
                            </select>

                            @error("items.{$index}.inventory_item_id")
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </td>

                        <td class="border-b border-gray-100 px-3 py-3 align-top dark:border-gray-800">
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="items[{{ $index }}][quantity]"
                                value="{{ $item['quantity'] ?? 1 }}"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brandColor focus:ring-2 focus:ring-brandColor/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                        </td>

                        <td class="border-b border-gray-100 px-3 py-3 align-top dark:border-gray-800">
                            <input
                                type="text"
                                name="items[{{ $index }}][unit]"
                                value="{{ $item['unit'] ?? 'unit' }}"
                                placeholder="unit"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brandColor focus:ring-2 focus:ring-brandColor/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                data-equipment-unit
                            >
                        </td>

                        <td class="border-b border-gray-100 px-3 py-3 align-top dark:border-gray-800">
                            <input
                                type="text"
                                name="items[{{ $index }}][notes]"
                                value="{{ $item['notes'] ?? '' }}"
                                placeholder="Catatan item"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brandColor focus:ring-2 focus:ring-brandColor/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                        </td>

                        <td class="border-b border-gray-100 px-3 py-3 text-center align-top dark:border-gray-800">
                            <button
                                type="button"
                                class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-200 dark:border-red-900/60 dark:text-red-400 dark:hover:bg-red-900/20"
                                title="Hapus baris"
                                data-remove-equipment
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="hidden px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400" data-equipment-empty-search>
            Tidak ada item yang cocok dengan pencarian.
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-5 py-4 dark:border-gray-800 dark:bg-gray-950">
        <div class="text-xs leading-5 text-gray-500 dark:text-gray-400">
            <strong class="text-gray-700 dark:text-gray-300">Belum memilih actual asset.</strong>
            Kode asset seperti CAM-0001 dipilih pada tahap Allocation / Picking.
        </div>

        <button
            type="button"
            class="secondary-button inline-flex items-center gap-2"
            data-add-equipment
        >
            <span class="text-base leading-none">+</span>
            Tambah Baris
        </button>
    </div>

    @error('items')
        <p class="border-t border-red-100 bg-red-50 px-5 py-3 text-xs text-red-600 dark:border-red-900/40 dark:bg-red-900/10">
            {{ $message }}
        </p>
    @enderror

    <template data-equipment-template>
        <tr class="group bg-white transition hover:bg-amber-50/40 dark:bg-gray-900 dark:hover:bg-gray-800/60" data-equipment-row>
            <td class="border-b border-gray-100 px-3 py-3 align-top text-sm font-semibold text-gray-400 dark:border-gray-800" data-row-number></td>
            <td class="border-b border-gray-100 px-3 py-3 align-top dark:border-gray-800">
                <input type="text" name="items[__INDEX__][name]" placeholder="Contoh: Camera" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brandColor focus:ring-2 focus:ring-brandColor/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white" data-equipment-name>
            </td>
            <td class="border-b border-gray-100 px-3 py-3 align-top dark:border-gray-800">
                <input type="text" name="items[__INDEX__][description]" placeholder="Spesifikasi atau detail" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brandColor focus:ring-2 focus:ring-brandColor/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
            </td>
            <td class="border-b border-gray-100 px-3 py-3 align-top dark:border-gray-800">
                <select name="items[__INDEX__][inventory_item_id]" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brandColor focus:ring-2 focus:ring-brandColor/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white" data-inventory-select>
                    <option value="">-- Not linked --</option>
                    @foreach ($inventoryItems as $inventoryItem)
                        <option value="{{ $inventoryItem->id }}" data-name="{{ $inventoryItem->name }}" data-unit="{{ $inventoryItem->unit ?: 'unit' }}">
                            {{ $inventoryItem->code }} — {{ $inventoryItem->name }} ({{ ucfirst($inventoryItem->tracking_type) }})
                        </option>
                    @endforeach
                </select>
            </td>
            <td class="border-b border-gray-100 px-3 py-3 align-top dark:border-gray-800">
                <input type="number" step="0.01" min="0.01" name="items[__INDEX__][quantity]" value="1" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brandColor focus:ring-2 focus:ring-brandColor/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
            </td>
            <td class="border-b border-gray-100 px-3 py-3 align-top dark:border-gray-800">
                <input type="text" name="items[__INDEX__][unit]" value="unit" placeholder="unit" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brandColor focus:ring-2 focus:ring-brandColor/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white" data-equipment-unit>
            </td>
            <td class="border-b border-gray-100 px-3 py-3 align-top dark:border-gray-800">
                <input type="text" name="items[__INDEX__][notes]" placeholder="Catatan item" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brandColor focus:ring-2 focus:ring-brandColor/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
            </td>
            <td class="border-b border-gray-100 px-3 py-3 text-center align-top dark:border-gray-800">
                <button type="button" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-200 dark:border-red-900/60 dark:text-red-400 dark:hover:bg-red-900/20" title="Hapus baris" data-remove-equipment>
                    Hapus
                </button>
            </td>
        </tr>
    </template>
</div>

{{-- CRM_DELIVERY_ORDER_CONTROLS_EXTERNAL_LOADER_V1_2 --}}
<script
    src="{{ asset('js/crm-delivery-order-equipment-v1-2.js') }}?v=1.2.0"
    defer
></script>
