<x-admin::layouts>
    <x-slot:title>Penggunaan Consumable</x-slot>

    <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Penggunaan Consumable</h1>
            <p class="mt-1 text-sm text-gray-500">Pilih nomor surat jalan untuk mencatat pemakaian ribbon, frame, dan consumable event.</p>
        </div>

        @if (bouncer()->hasPermission('inventory.dashboard'))
            <a class="secondary-button" href="{{ route('admin.inventory.dashboard') }}">Inventory Dashboard</a>
        @endif
    </div>

    <div class="mt-4">
        <x-admin::datagrid :src="route('admin.inventory.consumable-usage.index')" />
    </div>
</x-admin::layouts>
