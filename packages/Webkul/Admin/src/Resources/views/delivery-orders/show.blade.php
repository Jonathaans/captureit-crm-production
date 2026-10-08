<x-admin::layouts>
    <x-slot:title>
        {{ $deliveryOrder->delivery_order_number }}
    </x-slot>

    @php
        $status = strtolower(
            $deliveryOrder->status ?: 'draft'
        );

        $statusClasses = match ($status) {
            'issued' =>
                'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',

            'delivered' =>
                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',

            'returned' =>
                'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',

            'cancelled' =>
                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',

            default =>
                'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
        };

        $inventoryAllocationHistory = $deliveryOrder->items
            ->flatMap(
                fn ($item) => $item->allocations
            );

        $hasReturnOutstanding = $inventoryAllocationHistory
            ->whereIn(
                'status',
                [
                    'out',
                    'return_pending',
                ]
            )
            ->isNotEmpty();

        $hasReturnedInventory = $inventoryAllocationHistory
            ->where('status', 'returned')
            ->isNotEmpty();
    @endphp

    {{-- ========================================================= --}}
    {{-- PAGE HEADER --}}
    {{-- ========================================================= --}}

    <div
        class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="grid gap-2">
            <div>
                <a
                    href="{{ route('admin.delivery-orders.index') }}"
                    class="text-sm text-gray-600 hover:text-brandColor dark:text-gray-300"
                >
                    &larr; Back
                </a>
            </div>

            <div class="flex items-center gap-3">
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    {{ $deliveryOrder->delivery_order_number }}
                </p>

                <span
                    class="rounded-full px-3 py-1 text-xs font-bold uppercase {{ $statusClasses }}"
                >
                    {{ $status }}
                </span>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $deliveryOrder->project_code ?: '-' }}

                @if ($deliveryOrder->project_name)
                 &bull; {{ $deliveryOrder->project_name }}
            @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($deliveryOrder->invoice_id)
                <a
                    href="{{ route(
                        'admin.invoices.show',
                        $deliveryOrder->invoice_id
                    ) }}"
                    class="secondary-button"
                >
                    View Invoice
                </a>
            @endif

            @if (
                $status === 'draft'
                && bouncer()->hasPermission('delivery-orders.edit')
            )
                <a
                    href="{{ route(
                        'admin.delivery-orders.edit',
                        $deliveryOrder->id
                    ) }}"
                    class="secondary-button"
                >
                    Edit Surat Jalan
                </a>
            @endif

            @if (
                in_array($status, ['delivered', 'returned'], true)
                && bouncer()->hasPermission('delivery-orders.return')
            )
                <a
                    href="{{ route(
                        'admin.delivery-orders.return.show',
                        $deliveryOrder->id
                    ) }}"
                    class="secondary-button"
                >
                    Return / Check-In
                </a>
            @endif

            @if (
                bouncer()->hasPermission('delivery-orders.print')
            )
                <a
                    href="{{ route(
                        'admin.delivery-orders.print',
                        $deliveryOrder->id
                    ) }}"
                    target="_blank"
                    rel="noopener"
                    class="primary-button"
                >
                    Print Surat Jalan
                </a>
            @endif
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- STATUS WORKFLOW --}}
    {{-- ========================================================= --}}

    <div
        class="mt-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="flex flex-wrap items-start justify-between gap-5">
            <div>
                <p class="text-base font-semibold text-gray-800 dark:text-white">
                    Delivery Status
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Workflow: Draft -> Scan Allocation -> Release/Issue (Auto OUT) -> Delivered -> Scan Return -> Returned
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($status === 'draft')
                    @if (
                        bouncer()->hasPermission('delivery-orders.issue')
                    )
                        <form
                            method="POST"
                            action="{{ route(
                                'admin.delivery-orders.issue',
                                $deliveryOrder->id
                            ) }}"
                        >
                            @csrf
                            @method('PUT')

                            <button
                                type="submit"
                                class="primary-button"
                                onclick="return confirm('Issue Surat Jalan ini? Setelah di-issue, dokumen dianggap resmi.')"
                            >
                                Issue Surat Jalan
                            </button>
                        </form>
                    @endif

                    @if (
                        bouncer()->hasPermission('delivery-orders.cancel')
                    )
                        <form
                            method="POST"
                            action="{{ route(
                                'admin.delivery-orders.cancel',
                                $deliveryOrder->id
                            ) }}"
                        >
                            @csrf
                            @method('PUT')

                            <button
                                type="submit"
                                class="secondary-button"
                                onclick="return confirm('Batalkan Surat Jalan ini?')"
                            >
                                Cancel
                            </button>
                        </form>
                    @endif
                @elseif ($status === 'issued')
                    @if (
                        bouncer()->hasPermission('delivery-orders.delivered')
                    )
                        <form
                            method="POST"
                            action="{{ route(
                                'admin.delivery-orders.delivered',
                                $deliveryOrder->id
                            ) }}"
                        >
                            @csrf
                            @method('PUT')

                            <button
                                type="submit"
                                class="primary-button"
                                onclick="return confirm('Tandai barang sudah delivered ke PIC?')"
                            >
                                Mark as Delivered
                            </button>
                        </form>
                    @endif

                    @if (
                        bouncer()->hasPermission('delivery-orders.cancel')
                    )
                        <form
                            method="POST"
                            action="{{ route(
                                'admin.delivery-orders.cancel',
                                $deliveryOrder->id
                            ) }}"
                        >
                            @csrf
                            @method('PUT')

                            <button
                                type="submit"
                                class="secondary-button"
                                onclick="return confirm('Batalkan Surat Jalan ini?')"
                            >
                                Cancel
                            </button>
                        </form>
                    @endif
                @elseif ($status === 'delivered')
                    @if (
                        bouncer()->hasPermission('delivery-orders.return')
                    )
                        <a
                            href="{{ route(
                                'admin.delivery-orders.return.show',
                                $deliveryOrder->id
                            ) }}"
                            class="secondary-button"
                        >
                            Return / Check-In
                        </a>
                    @endif

                    @if (
                        ! $hasReturnOutstanding
                        && $hasReturnedInventory
                        && bouncer()->hasPermission('delivery-orders.returned')
                    )
                        <form
                            method="POST"
                            action="{{ route(
                                'admin.delivery-orders.returned',
                                $deliveryOrder->id
                            ) }}"
                        >
                            @csrf
                            @method('PUT')

                            <button
                                type="submit"
                                class="primary-button"
                                onclick="return confirm('Seluruh inventory sudah selesai Check-In. Tandai Surat Jalan sebagai RETURNED?')"
                            >
                                Mark as Returned
                            </button>
                        </form>
                    @endif
                @elseif ($status === 'returned')
                    <span
                        class="rounded-lg bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 dark:bg-green-900/20 dark:text-green-300"
                    >
                        Workflow Complete
                    </span>
                @elseif ($status === 'cancelled')
                    <span
                        class="rounded-lg bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 dark:bg-red-900/20 dark:text-red-300"
                    >
                        Surat Jalan Cancelled
                    </span>
                @endif
            </div>
        </div>

        <div
            class="mt-5 grid grid-cols-4 gap-4 max-xl:grid-cols-2 max-sm:grid-cols-1"
        >
            <div
                class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950"
            >
                <p class="text-xs font-medium uppercase text-gray-500">
                    Current Status
                </p>

                <p class="mt-2 text-sm font-semibold uppercase text-gray-800 dark:text-white">
                    {{ $status }}
                </p>
            </div>

            <div
                class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950"
            >
                <p class="text-xs font-medium uppercase text-gray-500">
                    Issued At
                </p>

                <p class="mt-2 text-sm text-gray-800 dark:text-white">
                    {{
                        $deliveryOrder->issued_at
                            ? $deliveryOrder->issued_at->format('d M Y H:i')
                            : '-'
                    }}
                </p>
            </div>

            <div
                class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950"
            >
                <p class="text-xs font-medium uppercase text-gray-500">
                    Delivered At
                </p>

                <p class="mt-2 text-sm text-gray-800 dark:text-white">
                    {{
                        $deliveryOrder->delivered_at
                            ? $deliveryOrder->delivered_at->format('d M Y H:i')
                            : '-'
                    }}
                </p>
            </div>

            <div
                class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950"
            >
                <p class="text-xs font-medium uppercase text-gray-500">
                    Returned At
                </p>

                <p class="mt-2 text-sm text-gray-800 dark:text-white">
                    {{
                        $deliveryOrder->returned_at
                            ? $deliveryOrder->returned_at->format('d M Y H:i')
                            : '-'
                    }}
                </p>
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-4">
        {{-- ===================================================== --}}
        {{-- PROJECT INFORMATION --}}
        {{-- ===================================================== --}}

        <div
            class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <p class="mb-5 text-base font-semibold text-gray-800 dark:text-white">
                Project Information
            </p>

            <div
                class="grid grid-cols-4 gap-x-8 gap-y-5 max-xl:grid-cols-2 max-sm:grid-cols-1"
            >
                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Surat Jalan
                    </p>

                    <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-white">
                        {{ $deliveryOrder->delivery_order_number }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Project Code
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->project_code ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Project Name
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->project_name ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Customer
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->customer_name ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Invoice
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->invoice_number ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Quote
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->quote_number ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Sales Person
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->sales_person_name ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Created By
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->creator?->name ?: '-' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- EVENT INFORMATION --}}
        {{-- ===================================================== --}}

        <div
            class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <p class="mb-5 text-base font-semibold text-gray-800 dark:text-white">
                Event Information
            </p>

            <div
                class="grid grid-cols-4 gap-x-8 gap-y-5 max-xl:grid-cols-2 max-sm:grid-cols-1"
            >
                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Event Date
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{
                            $deliveryOrder->event_date
                                ? $deliveryOrder->event_date->format('d M Y')
                                : '-'
                        }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Event Time
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->event_time ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Location
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->location ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Delivery Date
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{
                            $deliveryOrder->delivery_date
                                ? $deliveryOrder->delivery_date->format('d M Y')
                                : '-'
                        }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Delivery Time
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->delivery_time ?: '-' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- RECIPIENT --}}
        {{-- ===================================================== --}}

        <div
            class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <p class="mb-5 text-base font-semibold text-gray-800 dark:text-white">
                Delivery / Recipient
            </p>

            <div
                class="grid grid-cols-4 gap-x-8 gap-y-5 max-xl:grid-cols-2 max-sm:grid-cols-1"
            >
                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Recipient
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->recipient_name ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Recipient Phone
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->recipient_phone ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        PIC Event
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->pic_name ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">
                        PIC Phone
                    </p>

                    <p class="mt-1 text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->pic_phone ?: '-' }}
                    </p>
                </div>

                <div
                    class="col-span-4 max-xl:col-span-2 max-sm:col-span-1"
                >
                    <p class="text-xs font-medium uppercase text-gray-500">
                        Delivery Address
                    </p>

                    <p class="mt-1 whitespace-pre-line text-sm text-gray-800 dark:text-white">
                        {{ $deliveryOrder->delivery_address ?: '-' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- EQUIPMENT / INVENTORY REQUIREMENT --}}
        @include('admin::delivery-orders.partials.equipment-show')


        {{-- ===================================================== --}}{{-- NOTES --}}
        {{-- ===================================================== --}}

        <div
            class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <p class="mb-3 text-base font-semibold text-gray-800 dark:text-white">
                Notes
            </p>

            <p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">
                {{ $deliveryOrder->notes ?: '-' }}
            </p>
        </div>
    </div>
</x-admin::layouts>
