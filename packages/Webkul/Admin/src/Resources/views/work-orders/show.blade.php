<x-admin::layouts>
    <x-slot:title>
        {{ $workOrder->work_order_number }}
    </x-slot>

    @php
        $status = strtolower((string) ($workOrder->status ?: 'draft'));

        $statusStyle = match ($status) {
            'released' => 'background:#dbeafe;color:#1d4ed8;',
            'completed' => 'background:#dcfce7;color:#15803d;',
            'cancelled' => 'background:#fee2e2;color:#b91c1c;',
            default => 'background:#f3f4f6;color:#4b5563;',
        };
    @endphp

    <div class="flex flex-col gap-4">
        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <a
                        href="{{ route('admin.invoices.show', $workOrder->invoice_id) }}"
                        class="text-sm font-semibold text-blue-600"
                    >
                        ← Back to Invoice
                    </a>

                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-bold">
                            {{ $workOrder->work_order_number }}
                        </h1>

                        <span
                            style="{{ $statusStyle }}padding:5px 10px;border-radius:9999px;font-size:10px;font-weight:800;"
                        >
                            {{ strtoupper($status) }}
                        </span>
                    </div>

                    <div class="mt-1 text-sm text-gray-500">
                        {{ $workOrder->project_code ?: '-' }}
                        @if ($workOrder->project_name)
                            · {{ $workOrder->project_name }}
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($canEdit && $status !== 'cancelled')
                        <a
                            href="{{ route('admin.work-orders.edit', $workOrder->id) }}"
                            class="secondary-button"
                        >
                            Edit SPK
                        </a>
                    @endif

                    @if ($canPrint)
                        <a
                            href="{{ route('admin.work-orders.print', $workOrder->id) }}"
                            class="secondary-button"
                        >
                            PDF SPK
                        </a>
                    @endif

                    @if ($canGenerateDeliveryOrder && ! in_array($status, ['cancelled', 'completed'], true))
                        <form
                            method="POST"
                            action="{{ route('admin.work-orders.delivery-orders.generate', $workOrder->id) }}"
                            onsubmit="
                                const button = this.querySelector('button');
                                if (button) {
                                    button.disabled = true;
                                    button.textContent = 'Generating...';
                                }
                            "
                        >
                            @csrf

                            <button class="primary-button">
                                + Generate Surat Jalan
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div
            style="
                display:grid;
                grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
                gap:12px;
            "
        >
            @foreach ([
                'Invoice' => $workOrder->invoice_number ?: '-',
                'Project Code' => $workOrder->project_code ?: '-',
                'Customer' => $workOrder->customer_name ?: '-',
                'Sales' => $workOrder->sales_person_name ?: '-',
                'Event Date' => $workOrder->event_date?->format('d M Y') ?: '-',
                'Location' => $workOrder->location ?: '-',
            ] as $label => $value)
                <div class="rounded-xl border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500">
                        {{ $label }}
                    </div>

                    <div class="mt-1 font-bold">
                        {{ $value }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">
                        Product / Service
                    </h2>

                    <p class="mt-1 text-xs text-gray-500">
                        SPK hanya membawa nama produk/service. Harga Invoice tidak disalin.
                    </p>
                </div>

                @if ($canEdit && $status !== 'cancelled')
                    <a
                        href="{{ route('admin.work-orders.edit', $workOrder->id) }}"
                        class="secondary-button"
                    >
                        Edit / Add Item
                    </a>
                @endif
            </div>

            <div class="mt-4">
                @forelse ($workOrder->items as $index => $item)
                    <div class="border-b py-3 last:border-b-0">
                        <div class="font-semibold">
                            {{ $index + 1 }}. {{ $item->name }}
                        </div>

                        @if ($item->notes)
                            <div class="mt-1 text-xs text-gray-500">
                                {{ $item->notes }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-sm text-gray-500">
                        Belum ada item SPK.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-bold">
                Notes / Operational Instruction
            </h2>

            <div class="mt-3 whitespace-pre-wrap text-sm">
                {{ $workOrder->notes ?: 'Belum ada note.' }}
            </div>
        </div>

        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-bold">
                Tanda Tangan / Acknowledgement
            </h2>

            <div
                class="mt-4"
                style="
                    display:grid;
                    grid-template-columns:repeat(3,minmax(0,1fr));
                    gap:12px;
                "
            >
                @foreach ([
                    'Admin Sales' => $workOrder->admin_sales_name,
                    'Sales' => $workOrder->sales_name,
                    'Operational' => $workOrder->operational_name,
                ] as $label => $name)
                    <div class="rounded-lg border p-4 text-center">
                        <div class="text-xs font-bold uppercase text-gray-500">
                            {{ $label }}
                        </div>

                        <div class="mt-8 border-b"></div>

                        <div class="mt-2 font-semibold">
                            {{ $name ?: '________________' }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">
                        Surat Jalan dari SPK
                    </h2>

                    <p class="mt-1 text-xs text-gray-500">
                        Satu SPK dapat memiliki banyak Surat Jalan.
                    </p>
                </div>

                @if ($canGenerateDeliveryOrder && ! in_array($status, ['cancelled', 'completed'], true))
                    <form
                        method="POST"
                        action="{{ route('admin.work-orders.delivery-orders.generate', $workOrder->id) }}"
                    >
                        @csrf

                        <button class="primary-button">
                            + Generate Additional SJ
                        </button>
                    </form>
                @endif
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-gray-50 dark:bg-gray-950">
                        <tr>
                            <th class="p-3">#</th>
                            <th class="p-3">Surat Jalan</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Requirement</th>
                            <th class="p-3">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($workOrder->deliveryOrders as $index => $deliveryOrder)
                            <tr class="border-b">
                                <td class="p-3">
                                    {{ $index + 1 }}
                                </td>

                                <td class="p-3 font-bold">
                                    {{ $deliveryOrder->delivery_order_number }}
                                </td>

                                <td class="p-3">
                                    {{ strtoupper($deliveryOrder->status ?: 'draft') }}
                                </td>

                                <td class="p-3">
                                    {{ $deliveryOrder->items->count() }} item
                                </td>

                                <td class="p-3">
                                    <a
                                        href="{{ route('admin.delivery-orders.show', $deliveryOrder->id) }}"
                                        class="secondary-button"
                                    >
                                        View SJ
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="5"
                                    class="p-8 text-center text-gray-500"
                                >
                                    Belum ada Surat Jalan dari SPK ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap gap-2">
                @if ($canUpdateStatus && $status === 'draft')
                    <form
                        method="POST"
                        action="{{ route('admin.work-orders.release', $workOrder->id) }}"
                    >
                        @csrf
                        @method('PUT')

                        <button class="primary-button">
                            Release SPK
                        </button>
                    </form>
                @endif

                @if ($canUpdateStatus && ! in_array($status, ['completed', 'cancelled'], true))
                    <form
                        method="POST"
                        action="{{ route('admin.work-orders.complete', $workOrder->id) }}"
                    >
                        @csrf
                        @method('PUT')

                        <button class="secondary-button">
                            Mark Completed
                        </button>
                    </form>
                @endif

                @if ($canUpdateStatus && ! in_array($status, ['completed', 'cancelled'], true))
                    <form
                        method="POST"
                        action="{{ route('admin.work-orders.cancel', $workOrder->id) }}"
                        onsubmit="return confirm('Cancel SPK ini?')"
                    >
                        @csrf
                        @method('PUT')

                        <button class="secondary-button">
                            Cancel SPK
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-admin::layouts>
