<x-admin::layouts>
    <x-slot:title>Penggunaan Consumable — {{ $order->delivery_order_number }}</x-slot>

    <div style="width:100%;max-width:1200px;margin:0 auto;display:grid;gap:16px;">
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <div>
                <p class="text-sm font-medium text-gray-500">Penggunaan Consumable</p>
                <h1 class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ $order->delivery_order_number }}</h1>
                <p class="mt-1 text-sm text-gray-500">Status surat jalan: {{ strtoupper($order->status) }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if (bouncer()->hasPermission('delivery-orders.view'))
                    <a class="secondary-button" href="{{ route('admin.delivery-orders.show', $order->id) }}">Lihat Surat Jalan</a>
                @endif
                <a class="secondary-button" href="{{ route('admin.inventory.consumable-usage.index') }}">Kembali</a>
            </div>
        </div>

        <dl class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;">
            @foreach ([
                'Project Code' => $order->project_code,
                'Project Name' => $order->project_name,
                'Customer' => $order->customer_name,
                'Invoice' => $order->invoice_number,
                'Sales Person' => $order->sales_person_name,
                'Event Date' => $order->event_date?->format('d M Y'),
            ] as $label => $value)
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ $label }}</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $value ?: '-' }}</dd>
                </div>
            @endforeach
        </dl>

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <a class="mt-2 inline-block font-semibold underline" href="{{ route('admin.inventory.consumable-usage.show', $order->id) }}">Muat ulang data terbaru</a>
            </div>
        @endif

        @if ($order->status === 'draft')
            <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Rilis surat jalan terlebih dahulu sebelum mencatat pemakaian consumable.</p>
        @elseif ($order->status === 'cancelled')
            <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Surat jalan sudah dibatalkan. Pemakaian hanya dapat dikurangi atau dikosongkan untuk mengembalikan stok.</p>
        @elseif (! $canEdit)
            <p class="rounded-xl border border-gray-200 p-4 text-sm text-gray-500">Akses Anda hanya untuk melihat penggunaan consumable.</p>
        @endif

        @if ($allocations->isNotEmpty())
            <section class="rounded-xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
                <h2 class="font-bold">Consumable dari alokasi surat jalan</h2>
                <p class="mt-1">Stok item ini sudah dikelola melalui surat jalan. Catat sisa barang melalui proses Return. Item ini tidak dapat diinput ulang di bawah.</p>
                <div class="mt-3" style="overflow-x:auto;">
                    <table class="w-full text-left text-sm">
                        <thead><tr><th class="p-2">Consumable</th><th class="p-2">Jumlah Alokasi</th><th class="p-2">Kembali</th><th class="p-2">Terpakai</th><th class="p-2">Status</th></tr></thead>
                        <tbody>
                            @foreach ($allocations as $allocation)
                                <tr>
                                    <td class="p-2">{{ $allocation->inventoryItem?->name ?: '-' }}</td>
                                    <td class="p-2">{{ (float) $allocation->quantity }} {{ $allocation->inventoryItem?->unit }}</td>
                                    <td class="p-2">{{ $allocation->status === 'returned' ? (float) $allocation->returned_quantity : 'Menunggu return' }}</td>
                                    <td class="p-2">{{ $allocation->status === 'returned' ? max(0, (float) $allocation->quantity - (float) $allocation->returned_quantity) : '-' }}</td>
                                    <td class="p-2">{{ strtoupper($allocation->status) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <form method="POST" action="{{ route('admin.inventory.consumable-usage.update', $order->id) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="usage_revision" value="{{ old('usage_revision', $revision) }}">

            <v-consumable-usage-form
                :items='@json($items)'
                :initial-rows='@json(old("items", $initialRows))'
                :editable='@json($canEdit)'
                :cancelled='@json($order->status === "cancelled")'
            ></v-consumable-usage-form>
        </form>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-consumable-usage-form-template">
            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold">Pemakaian Consumable Tambahan</h2>
                        <p class="mt-1 text-sm text-gray-500">Isi total pemakaian untuk event ini. Stok langsung disesuaikan saat disimpan.</p>
                        <p class="mt-1 text-sm text-gray-500">Saat dikoreksi, hanya selisih pemakaian yang mengubah stok. Menghapus baris mengembalikan stok yang tercatat.</p>
                    </div>
                    <button v-if="editable && !cancelled && items.length" type="button" class="secondary-button" @click="addRow">+ Tambah Consumable</button>
                </div>

                <p v-if="!items.length" class="mt-4 text-sm text-gray-500">Belum ada consumable tambahan yang dapat dipilih. Tambahkan item di menu Consumables, atau gunakan proses Return untuk item alokasi surat jalan.</p>
                <p v-else-if="!rows.length" class="mt-4 text-sm text-gray-500">Belum ada penggunaan consumable tambahan yang dicatat.</p>

                <div v-for="(row, index) in rows" :key="row.key" class="mt-4 rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:start;">
                        <div>
                            <label class="mb-1 block text-sm font-medium" :for="'consumable-' + row.key">Consumable</label>
                            <select :id="'consumable-' + row.key" :name="'items['+index+'][inventory_item_id]'" v-model="row.inventory_item_id" :disabled="!editable" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900" required>
                                <option value="" disabled>Pilih ribbon, frame, atau consumable</option>
                                <option v-for="item in items" :key="item.id" :value="item.id" :disabled="selectedElsewhere(item.id, row) || (!item.active && Number(row.inventory_item_id) !== item.id)">
                                    @{{ item.code }} — @{{ item.name }}@{{ item.active ? '' : ' (nonaktif)' }}
                                </option>
                            </select>
                            <p v-if="selected(row)" class="mt-2 text-xs text-gray-500">Stok: @{{ format(selected(row).stock) }} @{{ selected(row).unit }} · Tersedia: @{{ format(selected(row).available) }} @{{ selected(row).unit }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium" :for="'quantity-' + row.key">Jumlah Terpakai <span v-if="selected(row)">(@{{ selected(row).unit }})</span></label>
                            <input :id="'quantity-' + row.key" :name="'items['+index+'][quantity]'" type="number" min="0" step="0.01" v-model="row.quantity" :disabled="!editable" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900" required>
                            <p v-if="selected(row)" class="mt-2 text-xs text-gray-500">Sudah tercatat: @{{ format(selected(row).previous) }} @{{ selected(row).unit }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium" :for="'notes-' + row.key">Catatan</label>
                            <input :id="'notes-' + row.key" :name="'items['+index+'][notes]'" type="text" maxlength="2000" v-model="row.notes" :disabled="!editable" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900" placeholder="Opsional">
                        </div>
                    </div>
                    <button v-if="editable" type="button" class="mt-3 text-sm font-medium text-red-600" @click="rows.splice(index, 1)">Hapus baris</button>
                </div>

                <div v-if="editable" class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4 dark:border-gray-800">
                    <p class="text-xs text-gray-500">Mutasi stok menyimpan nomor surat jalan, jumlah, dan petugas yang mencatat.</p>
                    <button type="submit" class="primary-button">Simpan Penggunaan</button>
                </div>
            </section>
        </script>
        <script type="module">
            app.component('v-consumable-usage-form', {
                template: '#v-consumable-usage-form-template',
                props: ['items', 'initialRows', 'editable', 'cancelled'],
                data() {
                    return {
                        sequence: 0,
                        rows: [],
                    };
                },
                created() {
                    this.rows = (Array.isArray(this.initialRows) ? this.initialRows : []).map(row => ({ ...row, key: this.sequence++ }));
                },
                methods: {
                    addRow() {
                        this.rows.push({ key: this.sequence++, inventory_item_id: '', quantity: '', notes: '' });
                    },
                    selected(row) {
                        return this.items.find(item => item.id === Number(row.inventory_item_id));
                    },
                    selectedElsewhere(id, row) {
                        return this.rows.some(other => other !== row && Number(other.inventory_item_id) === id);
                    },
                    format(value) {
                        return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
