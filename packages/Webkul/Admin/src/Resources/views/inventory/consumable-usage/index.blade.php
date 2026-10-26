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

    <div class="mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
        <form method="GET" action="{{ route('admin.inventory.consumable-usage.index') }}" class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4 dark:border-gray-800">
            <div>
                <label for="usage-search" class="mb-1 block text-xs font-medium text-gray-500">Cari Surat Jalan / Project / Customer</label>
                <input id="usage-search" name="search" value="{{ $filters['search'] ?? '' }}" class="rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900" placeholder="Nomor SJ, invoice, customer...">
            </div>
            <div>
                <label for="usage-from" class="mb-1 block text-xs font-medium text-gray-500">Event dari</label>
                <input id="usage-from" name="event_from" type="date" value="{{ $filters['event_from'] ?? '' }}" class="rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900">
            </div>
            <div>
                <label for="usage-to" class="mb-1 block text-xs font-medium text-gray-500">Event sampai</label>
                <input id="usage-to" name="event_to" type="date" value="{{ $filters['event_to'] ?? '' }}" class="rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900">
            </div>
            <div>
                <label for="usage-per-page" class="mb-1 block text-xs font-medium text-gray-500">Per halaman</label>
                <select id="usage-per-page" name="per_page" class="rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900">
                    @foreach ([10, 20, 50] as $perPage)
                        <option value="{{ $perPage }}" @selected($orders->perPage() === $perPage)>{{ $perPage }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <button type="submit" class="primary-button">Tampilkan</button>
            <a href="{{ route('admin.inventory.consumable-usage.index') }}" class="secondary-button">Reset</a>
        </form>

        <div style="overflow-x:auto;">
            <table class="w-full text-left text-sm" style="min-width:1000px;">
                <thead class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                    <tr>
                        @foreach ([
                            'delivery_order_number' => 'Surat Jalan Number',
                            'project_code' => 'Project Code',
                            'project_name' => 'Project Name',
                            'customer_name' => 'Customer',
                            'invoice_number' => 'Invoice',
                            'sales_person_name' => 'Sales Person',
                            'event_date' => 'Event Date',
                        ] as $column => $label)
                            <th class="px-4 py-3 font-medium">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc', 'page' => 1]) }}">
                                    {{ $label }} {{ $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '' }}
                                </a>
                            </th>
                        @endforeach
                        <th class="px-4 py-3 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php($usageUrl = route('admin.inventory.consumable-usage.show', $order->id))
                        <tr class="border-b border-gray-200 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-950" data-consumable-order="{{ $order->id }}">
                            @foreach ([
                                $order->delivery_order_number,
                                $order->project_code,
                                $order->project_name,
                                $order->customer_name,
                                $order->invoice_number,
                                $order->sales_person_name,
                                $order->event_date?->format('d M Y'),
                            ] as $value)
                                <td>
                                    <a href="{{ $usageUrl }}" class="block px-4 py-3 {{ $loop->first ? 'font-semibold text-brandColor' : '' }}" title="Buka penggunaan consumable {{ $order->delivery_order_number }}">
                                        {{ $value ?: '-' }}
                                    </a>
                                </td>
                            @endforeach
                            <td class="px-4 py-2">
                                <a href="{{ $usageUrl }}" class="primary-button whitespace-nowrap">Input Penggunaan</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-6 text-center text-gray-500">Tidak ada surat jalan yang sesuai dengan pencarian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <nav class="flex flex-wrap items-center justify-between gap-3 p-4 text-sm" aria-label="Halaman surat jalan">
            <p class="text-gray-500">{{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} dari {{ $orders->total() }} surat jalan</p>
            <div class="flex gap-2">
                @if ($orders->previousPageUrl())
                    <a class="secondary-button" href="{{ $orders->previousPageUrl() }}">Sebelumnya</a>
                @endif
                @if ($orders->nextPageUrl())
                    <a class="secondary-button" href="{{ $orders->nextPageUrl() }}">Berikutnya</a>
                @endif
            </div>
        </nav>
    </div>
</x-admin::layouts>
