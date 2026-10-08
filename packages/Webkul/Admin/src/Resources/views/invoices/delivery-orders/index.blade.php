<x-admin::layouts>
    <x-slot:title>
        Surat Jalan {{ $invoice->invoice_number }}
    </x-slot>

    @php
        $statusStyle = static function ($status) {
            return match (strtolower((string) $status)) {
                'issued' => [
                    'label' => 'ISSUED',
                    'bg' => '#dbeafe',
                    'color' => '#1d4ed8',
                ],

                'delivered' => [
                    'label' => 'DELIVERED',
                    'bg' => '#ede9fe',
                    'color' => '#6d28d9',
                ],

                'returned' => [
                    'label' => 'RETURNED',
                    'bg' => '#dcfce7',
                    'color' => '#15803d',
                ],

                'cancelled' => [
                    'label' => 'CANCELLED',
                    'bg' => '#fee2e2',
                    'color' => '#b91c1c',
                ],

                default => [
                    'label' => 'DRAFT',
                    'bg' => '#f3f4f6',
                    'color' => '#4b5563',
                ],
            };
        };
    @endphp

    <div
        style="
            width:100%;
            display:flex;
            flex-direction:column;
            gap:14px;
        "
    >
        <section
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    align-items:flex-start;
                    gap:16px;
                    flex-wrap:wrap;
                "
            >
                <div>
                    <a
                        href="{{ route('admin.invoices.show', $invoice->id) }}"
                        class="text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400"
                    >
                        ← Back to Invoice
                    </a>

                    <div
                        class="mt-3"
                        style="
                            display:flex;
                            align-items:center;
                            gap:10px;
                            flex-wrap:wrap;
                        "
                    >
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            Surat Jalan
                        </h1>

                        <span
                            style="
                                display:inline-flex;
                                padding:5px 10px;
                                border-radius:9999px;
                                background:#eff6ff;
                                color:#1d4ed8;
                                font-size:11px;
                                font-weight:800;
                            "
                        >
                            {{ $deliveryOrders->count() }} SJ
                        </span>
                    </div>

                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Semua pengiriman untuk invoice / event yang sama.
                    </p>
                </div>

                <div
                    style="
                        display:flex;
                        gap:8px;
                        flex-wrap:wrap;
                    "
                >
                    @if (
                        bouncer()->hasPermission(
                            'delivery-orders.generate'
                        )
                    )
                        <form
                            method="POST"
                            action="{{ route(
                                'admin.invoices.delivery-order.generate',
                                $invoice->id
                            ) }}"
                            onsubmit="
                                const button = this.querySelector('button');
                                if (button) {
                                    button.disabled = true;
                                    button.textContent = 'Generating...';
                                }
                            "
                            style="margin:0;"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="primary-button"
                            >
                                {{ $deliveryOrders->isEmpty()
                                    ? 'Generate Surat Jalan'
                                    : '+ Generate Additional SJ' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div
                class="mt-5"
                style="
                    display:grid;
                    grid-template-columns:repeat(auto-fit,minmax(210px,1fr));
                    gap:10px;
                "
            >
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">
                        Invoice
                    </p>

                    <p class="mt-1 font-bold text-gray-900 dark:text-white">
                        {{ $invoice->invoice_number }}
                    </p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">
                        Project
                    </p>

                    <p class="mt-1 font-bold text-gray-900 dark:text-white">
                        {{ $invoice->project_code ?: '-' }}
                    </p>

                    <p class="mt-1 text-xs text-gray-500">
                        {{ $invoice->subject ?: '-' }}
                    </p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">
                        Customer
                    </p>

                    <p class="mt-1 font-bold text-gray-900 dark:text-white">
                        {{ $invoice->person?->name ?: '-' }}
                    </p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">
                        Sales
                    </p>

                    <p class="mt-1 font-bold text-gray-900 dark:text-white">
                        {{ $invoice->user?->name ?: '-' }}
                    </p>
                </div>
            </div>
        </section>

        @if ($deliveryOrders->isNotEmpty())
            <section
                class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800"
            >
                <strong>Multi-SJ aktif.</strong>
                Setiap Surat Jalan memiliki requirement, allocation, Issue/OUT,
                Delivered, Return, dan Inventory Movement sendiri.
                SJ tambahan dibuat kosong agar barang dari SJ sebelumnya tidak
                terduplikasi. Isi hanya barang tambahan / tertinggal pada SJ baru.
            </section>
        @endif

        <section
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="border-b border-gray-200 p-4 dark:border-gray-800"
            >
                <p class="font-bold text-gray-900 dark:text-white">
                    Delivery History
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    Nomor Surat Jalan mengikuti sequence SJ global dan tetap berlanjut walaupun berasal dari Invoice yang sama.
                </p>
            </div>

            @if ($deliveryOrders->isEmpty())
                <div class="p-8 text-center">
                    <p class="font-bold text-gray-800 dark:text-white">
                        Belum ada Surat Jalan
                    </p>

                    <p class="mt-2 text-sm text-gray-500">
                        Generate Surat Jalan pertama untuk memulai workflow gudang.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:bg-gray-950"
                        >
                            <tr>
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3">Surat Jalan</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Requirement</th>
                                <th class="px-4 py-3">Delivery Date</th>
                                <th class="px-4 py-3">Created</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($deliveryOrders as $index => $deliveryOrder)
                                @php
                                    $badge = $statusStyle(
                                        $deliveryOrder->status
                                    );
                                @endphp

                                <tr
                                    class="border-b border-gray-100 last:border-b-0 dark:border-gray-800"
                                >
                                    <td class="px-4 py-4 text-sm text-gray-500">
                                        {{ $index + 1 }}
                                    </td>

                                    <td class="px-4 py-4">
                                        <p class="font-bold text-gray-900 dark:text-white">
                                            {{ $deliveryOrder->delivery_order_number }}
                                        </p>

                                        @if ($index > 0)
                                            <span class="mt-1 inline-block text-[10px] font-bold uppercase tracking-wide text-blue-600">
                                                Additional Delivery
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-4">
                                        <span
                                            style="
                                                display:inline-flex;
                                                padding:5px 9px;
                                                border-radius:9999px;
                                                background:{{ $badge['bg'] }};
                                                color:{{ $badge['color'] }};
                                                font-size:10px;
                                                font-weight:800;
                                            "
                                        >
                                            {{ $badge['label'] }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-4 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        {{ $deliveryOrder->items_count }} item
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $deliveryOrder->delivery_date
                                            ? $deliveryOrder->delivery_date->format('d M Y')
                                            : '-' }}
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-500">
                                        {{ $deliveryOrder->created_at?->format('d M Y H:i') ?: '-' }}
                                    </td>

                                    <td class="px-4 py-4">
                                        <div
                                            style="
                                                display:flex;
                                                justify-content:flex-end;
                                                gap:8px;
                                                flex-wrap:wrap;
                                            "
                                        >
                                            @if (
                                                bouncer()->hasPermission(
                                                    'delivery-orders.view'
                                                )
                                            )
                                                <a
                                                    href="{{ route(
                                                        'admin.delivery-orders.show',
                                                        $deliveryOrder->id
                                                    ) }}"
                                                    class="secondary-button"
                                                >
                                                    View
                                                </a>
                                            @endif

                                            @if (
                                                bouncer()->hasPermission(
                                                    'delivery-orders.print'
                                                )
                                            )
                                                <a
                                                    href="{{ route(
                                                        'admin.delivery-orders.print',
                                                        $deliveryOrder->id
                                                    ) }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="secondary-button"
                                                >
                                                    Print
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-admin::layouts>
