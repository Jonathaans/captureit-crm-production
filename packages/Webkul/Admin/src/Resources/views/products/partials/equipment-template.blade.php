@php
    $equipmentTemplate = $product->equipmentTemplate;

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

    $existingEquipmentItems = old('equipment_items');

    if ($existingEquipmentItems === null) {
        $existingEquipmentItems = $equipmentTemplate
            ?->items
            ?->map(function ($item) {
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
            ->toArray()
            ?? [];
    }

    /*
     * Minimal 10 row.
     * Kalau template punya > 10 barang,
     * semuanya tetap tampil.
     */
    $equipmentRowCount = max(
        20,
        count($existingEquipmentItems)
    );
@endphp

<div
    class="box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
>
    <div class="mb-5">
        <p class="text-base font-semibold text-gray-800 dark:text-white">
            Equipment Template
        </p>

        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Template menentukan kebutuhan standar product. Kolom Inventory Item
            menghubungkan kebutuhan tersebut ke master inventory. Actual asset
            seperti CAM-0001 atau CAM-002 baru dipilih saat allocation/picking.
        </p>
    </div>

    <div class="mb-5 grid grid-cols-2 gap-4 max-md:grid-cols-1">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white">
                Template Name
            </label>

            <input
                type="text"
                name="equipment_template_name"
                value="{{ old(
                    'equipment_template_name',
                    $equipmentTemplate?->name
                        ?? $product->name .' Equipment Template'
                ) }}"
                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
            >
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white">
                Template Status
            </label>

            <div class="flex h-[42px] items-center gap-2">
                <input
                    type="hidden"
                    name="equipment_template_active"
                    value="0"
                >

                <input
                    type="checkbox"
                    name="equipment_template_active"
                    value="1"
                    @checked(
                        old(
                            'equipment_template_active',
                            $equipmentTemplate?->is_active ?? true
                        )
                    )
                    class="h-4 w-4"
                >

                <span class="text-sm text-gray-700 dark:text-gray-300">
                    Active
                </span>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[1280px]">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-800">
                    <th class="w-[45px] px-2 py-3 text-left text-xs font-semibold text-gray-500">
                        #
                    </th>

                    <th class="min-w-[150px] px-2 py-3 text-left text-xs font-semibold text-gray-500">
                        Item
                    </th>

                    <th class="min-w-[190px] px-2 py-3 text-left text-xs font-semibold text-gray-500">
                        Description
                    </th>

                    <th class="min-w-[280px] px-2 py-3 text-left text-xs font-semibold text-gray-500">
                        Inventory Item
                    </th>

                    <th class="w-[90px] px-2 py-3 text-left text-xs font-semibold text-gray-500">
                        Qty
                    </th>

                    <th class="w-[100px] px-2 py-3 text-left text-xs font-semibold text-gray-500">
                        Unit
                    </th>

                    <th class="min-w-[180px] px-2 py-3 text-left text-xs font-semibold text-gray-500">
                        Notes
                    </th>
                </tr>
            </thead>

            <tbody>
                @for ($index = 0; $index < $equipmentRowCount; $index++)
                    @php
                        $item = $existingEquipmentItems[$index] ?? [];
                        $selectedInventoryItemId = $item['inventory_item_id'] ?? null;
                    @endphp

                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="px-2 py-2 text-sm text-gray-500">
                            {{ $index + 1 }}
                        </td>

                        <td class="px-2 py-2">
                            <input
                                type="text"
                                name="equipment_items[{{ $index }}][name]"
                                value="{{ $item['name'] ?? '' }}"
                                placeholder="Camera"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>

                        <td class="px-2 py-2">
                            <input
                                type="text"
                                name="equipment_items[{{ $index }}][description]"
                                value="{{ $item['description'] ?? '' }}"
                                placeholder="Canon EOS 700D"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>

                        <td class="px-2 py-2">
                            <select
                                name="equipment_items[{{ $index }}][inventory_item_id]"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                                <option value="">
                                    -- Not linked --
                                </option>

                                @foreach ($inventoryItems as $inventoryItem)
                                    <option
                                        value="{{ $inventoryItem->id }}"
                                        @selected(
                                            (string) $selectedInventoryItemId
                                            === (string) $inventoryItem->id
                                        )
                                    >
                                        {{ $inventoryItem->code }}
                                        — {{ $inventoryItem->name }}
                                        ({{ ucfirst($inventoryItem->tracking_type) }})
                                    </option>
                                @endforeach
                            </select>

                            @error("equipment_items.{$index}.inventory_item_id")
                                <p class="mt-1 text-xs text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </td>

                        <td class="px-2 py-2">
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="equipment_items[{{ $index }}][quantity]"
                                value="{{ $item['quantity'] ?? 1 }}"
                                class="w-full rounded-md border border-gray-300 bg-white px-2 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>

                        <td class="px-2 py-2">
                            <input
                                type="text"
                                name="equipment_items[{{ $index }}][unit]"
                                value="{{ $item['unit'] ?? 'unit' }}"
                                placeholder="unit"
                                class="w-full rounded-md border border-gray-300 bg-white px-2 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>

                        <td class="px-2 py-2">
                            <input
                                type="text"
                                name="equipment_items[{{ $index }}][notes]"
                                value="{{ $item['notes'] ?? '' }}"
                                placeholder="Catatan"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>

    <div class="mt-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white">
            Template Notes
        </label>

        <textarea
            name="equipment_template_notes"
            rows="3"
            placeholder="Catatan template equipment..."
            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
        >{{ old(
            'equipment_template_notes',
            $equipmentTemplate?->notes
        ) }}</textarea>
    </div>

    <div class="mt-4 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-700 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-300">
        <strong>Inventory Mapping:</strong>
        pilih master inventory untuk equipment yang perlu dikontrol warehouse.
        Mapping ini tidak memilih asset fisik tertentu. Asset code aktual dipilih
        pada workflow Delivery Order berikutnya.
    </div>

    <div class="mt-3 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-950">
        Baris dengan nama Item kosong tidak akan disimpan.
        Mengubah template ini tidak mengubah Surat Jalan yang sudah pernah dibuat.
    </div>
</div>

<!-- CRM_EQUIPMENT_ROWS_FIX_V14_INCLUDE -->
<script src="{{ asset('js/crm-delivery-order-equipment-v1-2.js') }}?v=1.4.0"></script>
<!-- /CRM_EQUIPMENT_ROWS_FIX_V14_INCLUDE -->
