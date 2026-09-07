<x-admin::layouts>
    <x-slot:title>
        {{ $invoice->invoice_number }}
    </x-slot>

@php
    $totalExpense = (float) $invoice->expenses->sum('amount');

    $estimatedProfit =
        (float) $invoice->grand_total
        - $totalExpense;

    $deliveryOrder =
        $invoice->deliveryOrders
            ->sortBy('id')
            ->first();
@endphp


    <!-- ========================================================= -->
    <!-- PAGE HEADER -->
    <!-- ========================================================= -->

    <div
        class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
        style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;"
    >
        <div>
            <a
                href="{{ route('admin.invoices.index') }}"
                class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-100 dark:border-blue-900/40 dark:bg-blue-900/20 dark:text-blue-300"
            >
                ← Back to Invoices
            </a>

            <div class="mt-3 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
                    {{ $invoice->invoice_number }}
                </h1>

                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Project Code:
                    </span>

                    <span class="rounded-md bg-blue-50 px-2.5 py-1 text-sm font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                        {{ $invoice->project_code ?? '-' }}
                    </span>
                </div>
                @if ($invoice->status === 'paid')
                    <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                        PAID
                    </span>
                @elseif ($invoice->status === 'partial')
                    <span class="rounded-full bg-yellow-100 px-3 py-1 text-xs font-semibold text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                        PARTIAL
                    </span>
                @else
                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400">
                        UNPAID
                    </span>
                @endif
            </div>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $invoice->subject }}
            </p>
        </div>

        <div class="flex items-center gap-2 max-sm:w-full max-sm:flex-wrap">
            <a
                href="{{ route('admin.invoices.edit', $invoice->id) }}"
                class="secondary-button"
            >
                Edit Invoice
            </a>

            @if ($invoice->quote)
                <a
                    href="{{ route('admin.quotes.edit', $invoice->quote->id) }}"
                    class="secondary-button"
                >
                    View Quote
                </a>
            @endif

            <a
                href="{{ route('admin.invoices.print', $invoice->id) }}"
                class="primary-button"
            >
                Print Invoice
                    </a>
@if ($deliveryOrder)
    @if (
        bouncer()->hasPermission('delivery-orders.view')
    )
        <a
            href="{{ route('admin.invoices.work-orders.open', $invoice->id) }}"
            class="secondary-button"
        >
            View SPK
        </a>
@if (
    bouncer()->hasPermission(
        'delivery-orders.generate'
    )
)
    <form
        method="POST"
        action="{{ route(
            'admin.invoices.work-orders.store',
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
            class="secondary-button"
        >
            Open / Generate SPK
        </button>
    </form>
@endif
    @endif

    @if (
        bouncer()->hasPermission('delivery-orders.print')
    )
        <a
            href="{{ route(
                'admin.delivery-orders.print',
                $deliveryOrder->id
            ) }}"
            class="secondary-button"
        >
            Print Surat Jalan
        </a>
    @endif
@else
    @if (
        bouncer()->hasPermission('delivery-orders.generate')
    )
        <form
            method="POST"
            action="{{ route(
                'admin.invoices.work-orders.store',
                $invoice->id
            ) }}"
            style="margin: 0;"
        >
            @csrf

            <button
                type="submit"
                class="secondary-button"
            >
                Generate Surat Perintah Kerja
            </button>
        </form>
    @endif
@endif
        </div>
    </div>


    <!-- ========================================================= -->
    <!-- PROJECT & INVOICE INFORMATION -->
    <!-- ========================================================= -->

    <section
        class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
        style="margin-top:32px;"
    >
        <div style="margin-bottom:20px;">
            <div class="flex items-center justify-between gap-3 max-sm:flex-wrap">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                        Project & Invoice Information
                    </h2>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Ringkasan project, customer, sales, dan tanggal penting invoice.
                    </p>
                </div>

                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    Project Detail
                </span>
            </div>
        </div>

        <div
            style="
                display:grid;
                grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));
                gap:14px;
                width:100%;
            "
        >
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Bill To
                </p>

                <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                    {{ $invoice->person?->name ?? '-' }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Sales Person
                </p>

                <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                    {{ $invoice->user?->name ?? '-' }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Event Date
                </p>

                <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                    {{ $invoice->event_date?->format('d M Y') ?? '-' }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Location
                </p>

                <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                    {{ $invoice->location ?: '-' }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Payment Term
                </p>

                <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                    {{ $invoice->payment_term ?: '-' }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Issued Date
                </p>

                <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                    {{ $invoice->issued_at?->format('d M Y') ?? '-' }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Due Date
                </p>

                <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                    {{ $invoice->due_at?->format('d M Y') ?? '-' }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Quote Reference
                </p>

                <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                    @if ($invoice->quote)
                        {{ $invoice->quote->quote_number ?: 'Quote #'.$invoice->quote->id }}
                    @else
                        -
                    @endif
                </p>
            </div>
        </div>
    </section>

    <!-- ========================================================= -->
<!-- EVENT STATUS -->
<!-- ========================================================= -->

<section style="margin-top:32px;">
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
            Event Management
        </h2>

        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Update status event tanpa mengubah status pembayaran invoice.
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Event Status
            </p>

            <div class="mt-2">
                @if ($invoice->event_status === 'confirm')
                    <span class="inline-flex rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700 dark:bg-green-900/40 dark:text-green-300">
                        CONFIRM
                    </span>

                @elseif ($invoice->event_status === 'cancel')
                    <span class="inline-flex rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700 dark:bg-red-900/40 dark:text-red-300">
                        CANCEL
                    </span>

                @else
                    <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                        PROSPECT
                    </span>
                @endif
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('admin.invoices.event-status.update', $invoice->id) }}"
            class="flex flex-col gap-3 sm:flex-row sm:items-end"
        >
            @csrf
            @method('PUT')

            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Change Event Status
                </label>

                <select
                    name="event_status"
                    class="min-w-[200px] cursor-pointer rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-gray-800 outline-none focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    required
                >
                    <option
                        value="prospect"
                        @selected($invoice->event_status === 'prospect')
                    >
                        Prospect
                    </option>

                    <option
                        value="confirm"
                        @selected($invoice->event_status === 'confirm')
                    >
                        Confirm
                    </option>

                    <option
                        value="cancel"
                        @selected($invoice->event_status === 'cancel')
                    >
                        Cancel
                    </option>
                </select>
            </div>

            <button
                type="submit"
                class="primary-button"
            >
                Update Event Status
            </button>
        </form>
    </div>
</div>
</section>


    <!-- ========================================================= -->
    <!-- FINANCIAL SUMMARY -->
    <!-- ========================================================= -->

    <section style="margin-top:32px;">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                Financial Overview
            </h2>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Ringkasan nilai invoice, pembayaran, outstanding, expense, dan estimasi profit.
            </p>
        </div>

        <div
            style="
                display:grid;
                grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
                gap:16px;
                width:100%;
            "
        >

        <!-- Grand Total -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Grand Total
            </p>

            <p class="mt-2 text-xl font-bold text-gray-800 dark:text-white">
                Rp {{ number_format((float) $invoice->grand_total, 0, ',', '.') }}
            </p>
        </div>

        <!-- Paid -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Paid
            </p>

            <p class="mt-2 text-xl font-bold text-green-600 dark:text-green-400">
                Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}
            </p>
        </div>

        <!-- Balance -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Balance Due
            </p>

            <p
                class="mt-2 text-xl font-bold
                    {{ (float) $invoice->balance_due > 0
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-gray-800 dark:text-white' }}"
            >
                Rp {{ number_format((float) $invoice->balance_due, 0, ',', '.') }}
            </p>
        </div>

        <!-- Total Expense -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Total Expense
            </p>

            <p class="mt-2 text-xl font-bold text-orange-600 dark:text-orange-400">
                Rp {{ number_format($totalExpense, 0, ',', '.') }}
            </p>
        </div>

        <!-- Estimated Profit -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Est. Profit
            </p>

            <p
                class="mt-2 text-xl font-bold
                    {{ $estimatedProfit >= 0
                        ? 'text-green-600 dark:text-green-400'
                        : 'text-red-600 dark:text-red-400' }}"
            >
                Rp {{ number_format($estimatedProfit, 0, ',', '.') }}
            </p>
        </div>
        </div>
    </section>


    <!-- ========================================================= -->
    <!-- MAIN CONTENT -->
    <!-- ========================================================= -->

    <section style="margin-top:40px;">
        <div class="mb-5">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                Invoice Activity & Details
            </h2>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Detail package, riwayat payment/expense, dan form pencatatan transaksi.
            </p>
        </div>

        <div
            style="
                display:grid;
                grid-template-columns:repeat(auto-fit, minmax(min(520px, 100%), 1fr));
                gap:24px;
                align-items:start;
            "
        >

        <!-- ===================================================== -->
        <!-- LEFT COLUMN -->
        <!-- ===================================================== -->

        <div
            class="space-y-6"
            style="display:flex; flex-direction:column; gap:24px;"
        >

            <!-- ================================================= -->
            <!-- INVOICE ITEMS -->
            <!-- ================================================= -->

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                        Invoice Items
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950">
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    Package
                                </th>

                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    Description
                                </th>

                                <th class="px-6 py-3 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    Day
                                </th>

                                <th class="px-6 py-3 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    Qty
                                </th>

                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    Unit Price
                                </th>

                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    Total
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($invoice->items as $item)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-gray-800 dark:text-white">
                                            {{ $item->name }}
                                        </p>

                                        @if ($item->sku)
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                SKU: {{ $item->sku }}
                                            </p>
                                        @endif
                                    </td>

                                    <td class="max-w-xs px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $item->description ?: '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-center text-gray-700 dark:text-gray-300">
                                        {{ $item->day ?? 1 }}
                                    </td>

                                    <td class="px-6 py-4 text-center text-gray-700 dark:text-gray-300">
                                        {{ $item->quantity }}
                                    </td>

                                    <td class="px-6 py-4 text-right text-gray-700 dark:text-gray-300">
                                        Rp {{ number_format((float) $item->price, 0, ',', '.') }}
                                    </td>

                                    <td class="px-6 py-4 text-right font-semibold text-gray-800 dark:text-white">
                                        Rp {{ number_format((float) $item->total, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="6"
                                        class="px-6 py-10 text-center text-gray-500 dark:text-gray-400"
                                    >
                                        Tidak ada item pada invoice ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Item Totals -->
                <div class="flex justify-end border-t border-gray-200 px-6 py-5 dark:border-gray-800">
                    <div class="w-full max-w-sm space-y-3">

                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">
                                Subtotal
                            </span>

                            <span class="font-medium text-gray-800 dark:text-white">
                                Rp {{ number_format((float) $invoice->sub_total, 0, ',', '.') }}
                            </span>
                        </div>

                        @if ((float) $invoice->discount_amount > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">
                                    Discount
                                </span>

                                <span class="font-medium text-red-600 dark:text-red-400">
                                    - Rp {{ number_format((float) $invoice->discount_amount, 0, ',', '.') }}
                                </span>
                            </div>
                        @endif

                        @if ((float) $invoice->tax_amount > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">
                                    Tax
                                </span>

                                <span class="font-medium text-gray-800 dark:text-white">
                                    Rp {{ number_format((float) $invoice->tax_amount, 0, ',', '.') }}
                                </span>
                            </div>
                        @endif

                        @if ((float) $invoice->adjustment_amount != 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">
                                    Adjustment
                                </span>

                                <span class="font-medium text-gray-800 dark:text-white">
                                    Rp {{ number_format((float) $invoice->adjustment_amount, 0, ',', '.') }}
                                </span>
                            </div>
                        @endif

                        <div class="flex justify-between border-t border-gray-200 pt-3 dark:border-gray-700">
                            <span class="font-semibold text-gray-800 dark:text-white">
                                Grand Total
                            </span>

                            <span class="text-lg font-bold text-gray-800 dark:text-white">
                                Rp {{ number_format((float) $invoice->grand_total, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>


            <!-- ================================================= -->
            <!-- PAYMENT HISTORY -->
            <!-- ================================================= -->

            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
                style="border-top:3px solid #16a34a;"
            >
                <div
                    class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"
                    style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;"
                >
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-green-600 dark:text-green-400">
                            Money In History
                        </p>

                        <h2 class="mt-1 text-lg font-semibold text-gray-800 dark:text-white">
                            Payment History
                        </h2>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Semua pembayaran yang sudah diterima dari customer.
                        </p>
                    </div>

                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <span
                            class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                        >
                            {{ $invoice->payments->count() }} transaction(s)
                        </span>

                        <div class="text-right">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Total Received
                            </p>

                            <p class="mt-1 text-lg font-bold text-green-600 dark:text-green-400">
                                Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div style="display:flex; flex-direction:column; gap:12px; padding:16px;">
                    @forelse ($invoice->payments->sortByDesc('paid_at') as $payment)
                        <div
                            class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-950"
                            style="
                                display:grid;
                                grid-template-columns:minmax(0, 1fr) auto;
                                gap:20px;
                                align-items:start;
                            "
                        >
                            <div style="display:flex; gap:14px; min-width:0;">
                                <div
                                    style="
                                        width:40px;
                                        height:40px;
                                        min-width:40px;
                                        display:flex;
                                        align-items:center;
                                        justify-content:center;
                                        border-radius:9999px;
                                        background:#dcfce7;
                                        color:#15803d;
                                        font-size:20px;
                                        font-weight:700;
                                    "
                                >
                                    +
                                </div>

                                <div style="min-width:0;">
                                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                        <p class="text-lg font-bold text-green-600 dark:text-green-400">
                                            Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                        </p>

                                        <span
                                            class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-300"
                                        >
                                            {{ ucwords(str_replace('_', ' ', $payment->payment_method ?? '-')) }}
                                        </span>
                                    </div>

                                    <div
                                        class="mt-3"
                                        style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;"
                                    >
                                        @if ($payment->reference_number)
                                            <span
                                                class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            >
                                                Ref: {{ $payment->reference_number }}
                                            </span>
                                        @endif

                                        @if ($payment->creator)
                                            <span
                                                class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            >
                                                by {{ $payment->creator->name }}
                                            </span>
                                        @endif
                                    </div>

                                    @if ($payment->notes)
                                        <div
                                            class="mt-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        >
                                            {{ $payment->notes }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="text-right max-sm:text-left">
                                <p class="font-semibold text-gray-800 dark:text-white">
                                    {{ $payment->paid_at?->format('d M Y') ?? '-' }}
                                </p>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $payment->paid_at?->format('H:i') ?? '' }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div
                            class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center dark:border-gray-700 dark:bg-gray-950"
                        >
                            <div
                                style="
                                    width:44px;
                                    height:44px;
                                    margin:0 auto;
                                    display:flex;
                                    align-items:center;
                                    justify-content:center;
                                    border-radius:9999px;
                                    background:#dcfce7;
                                    color:#15803d;
                                    font-size:22px;
                                    font-weight:700;
                                "
                            >
                                +
                            </div>

                            <p class="mt-3 font-semibold text-gray-700 dark:text-gray-200">
                                Belum ada pembayaran
                            </p>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Payment yang dicatat akan muncul di sini.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>


 <!-- ================================================= -->
<!-- EXPENSE HISTORY -->
<!-- ================================================= -->

<div
    class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
    style="border-top:3px solid #f97316;"
>
    <div
        class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"
        style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;"
    >
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-orange-600 dark:text-orange-400">
                Money Out History
            </p>

            <h2 class="mt-1 text-lg font-semibold text-gray-800 dark:text-white">
                Expense History
            </h2>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Semua bon dan pengeluaran yang tercatat untuk project ini.
            </p>
        </div>

        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <span
                class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300"
            >
                {{ $invoice->expenses->count() }} record(s)
            </span>

            <div class="text-right">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Total Expense
            </p>

            <p class="mt-1 text-lg font-bold text-orange-600 dark:text-orange-400">
                Rp {{ number_format($totalExpense, 0, ',', '.') }}
            </p>
            </div>
        </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:12px; padding:16px;">
        @forelse ($invoice->expenses->sortByDesc('expense_date') as $expense)
            <div
                class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-950"
            >

                <!-- EXPENSE ROW -->
                <div class="flex items-start justify-between gap-4 max-sm:flex-col">

                    <!-- LEFT -->
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-gray-800 dark:text-white">
                                {{ $expense->description }}
                            </p>

                            <span class="rounded-full bg-orange-100 px-2.5 py-1 text-xs font-semibold text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                                {{ ucwords(str_replace('_', ' ', $expense->category)) }}
                            </span>
                        </div>

                        <div
                            class="mt-3"
                            style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;"
                        >
                            @if ($expense->vendor_name)
                                <span
                                    class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    Vendor: {{ $expense->vendor_name }}
                                </span>
                            @endif

                            @if ($expense->reference_number)
                                <span
                                    class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    Bon: {{ $expense->reference_number }}
                                </span>
                            @endif

                            @if ($expense->creator)
                                <span
                                    class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    by {{ $expense->creator->name }}
                                </span>
                            @endif
                        </div>

                        @if ($expense->notes)
                            <div
                                class="mt-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            >
                                {{ $expense->notes }}
                            </div>
                        @endif

                        <!-- ACTIONS -->
                        <div class="mt-4 flex flex-wrap items-center gap-3">

                            <!-- VIEW BON -->
                            @if ($expense->receipt_path)
                                @php
                                    /* PURCHASE ORDER PAID PDF RECEIPT V1 */
                                    $receiptPath = trim((string) $expense->receipt_path);
                                    $isPoPaymentProof = preg_match(
                                        '~/admin/purchase-orders/\d+/payment-proof(?:\?.*)?$~i',
                                        $receiptPath
                                    ) === 1;
                                    $receiptUrl = $isPoPaymentProof
                                        ? (parse_url($receiptPath, PHP_URL_PATH) ?: $receiptPath)
                                        : asset('storage/'.ltrim($receiptPath, '/'));
                                @endphp
                                <a
                                    href="{{ $receiptUrl }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    View Receipt / Bon
                                </a>
                            @endif

                            <!-- EDIT -->
                            <details class="group">
                                <summary
                                    class="cursor-pointer list-none rounded-lg border border-yellow-200 bg-yellow-50 px-3 py-2 text-xs font-semibold text-yellow-700 transition hover:bg-yellow-100 dark:border-yellow-900/40 dark:bg-yellow-900/20 dark:text-yellow-300"
                                >
                                    Edit
                                </summary>

                                <!-- EDIT FORM -->
                                <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-950">
                                    <h3 class="mb-4 font-semibold text-gray-800 dark:text-white">
                                        Edit Expense
                                    </h3>

                                    <form
                                        method="POST"
                                        action="{{ route('admin.invoices.expenses.update', [
                                            $invoice->id,
                                            $expense->id
                                        ]) }}"
                                        enctype="multipart/form-data"
                                        class="space-y-4"
                                    >
                                        @csrf
                                        @method('PUT')

                                        <!-- Category -->
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Category
                                                <span class="text-red-600">*</span>
                                            </label>

                                            <select
                                                name="category"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                                required
                                            >
                                                <option
                                                    value="transport"
                                                    @selected($expense->category === 'transport')
                                                >
                                                    Transport
                                                </option>

                                                <option
                                                    value="crew"
                                                    @selected($expense->category === 'crew')
                                                >
                                                    Crew
                                                </option>

                                                <option
                                                    value="printing"
                                                    @selected($expense->category === 'printing')
                                                >
                                                    Printing
                                                </option>

                                                <option
                                                    value="equipment"
                                                    @selected($expense->category === 'equipment')
                                                >
                                                    Equipment
                                                </option>

                                                <option
                                                    value="vendor"
                                                    @selected($expense->category === 'vendor')
                                                >
                                                    Vendor
                                                </option>

                                                <option
                                                    value="consumption"
                                                    @selected($expense->category === 'consumption')
                                                >
                                                    Consumption
                                                </option>

                                                <option
                                                    value="other"
                                                    @selected($expense->category === 'other')
                                                >
                                                    Other
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Description -->
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Description
                                                <span class="text-red-600">*</span>
                                            </label>

                                            <input
                                                type="text"
                                                name="description"
                                                value="{{ $expense->description }}"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                                required
                                            >
                                        </div>

                                        <!-- Amount -->
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Amount
                                                <span class="text-red-600">*</span>
                                            </label>

                                            <input
                                                type="number"
                                                name="amount"
                                                value="{{ (float) $expense->amount }}"
                                                min="1"
                                                step="1"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                                required
                                            >
                                        </div>

                                        <!-- Expense Date -->
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Expense Date
                                                <span class="text-red-600">*</span>
                                            </label>

                                            <input
                                                type="date"
                                                name="expense_date"
                                                value="{{ $expense->expense_date?->format('Y-m-d') }}"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                                required
                                            >
                                        </div>

                                        <!-- Vendor -->
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Vendor
                                            </label>

                                            <input
                                                type="text"
                                                name="vendor_name"
                                                value="{{ $expense->vendor_name }}"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            >
                                        </div>

                                        <!-- Reference -->
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Reference / No. Bon
                                            </label>

                                            <input
                                                type="text"
                                                name="reference_number"
                                                value="{{ $expense->reference_number }}"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            >
                                        </div>

                                        <!-- Replace Receipt -->
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Replace Bon
                                            </label>

                                            @if ($expense->receipt_path)
                                                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                                                    Bon lama tetap digunakan jika tidak memilih file baru.
                                                </p>
                                            @endif

                                            <input
                                                type="file"
                                                name="receipt"
                                                accept=".jpg,.jpeg,.png,.pdf"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            >
                                        </div>

                                        <!-- Notes -->
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Notes
                                            </label>

                                            <textarea
                                                name="notes"
                                                rows="3"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            >{{ $expense->notes }}</textarea>
                                        </div>

                                        <button
                                            type="submit"
                                            class="primary-button"
                                        >
                                            Update Expense
                                        </button>
                                    </form>
                                </div>
                            </details>


                            <!-- DELETE -->
                            <form
                                method="POST"
                                action="{{ route('admin.invoices.expenses.delete', [
                                    $invoice->id,
                                    $expense->id
                                ]) }}"
                                onsubmit="return confirm('Hapus expense ini? Data dan bon yang terkait akan dihapus.');"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-100 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-300"
                                >
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>


                    <!-- RIGHT -->
                    <div class="text-right max-sm:text-left">
                        <p class="text-lg font-bold text-orange-600 dark:text-orange-400">
                            - Rp {{ number_format((float) $expense->amount, 0, ',', '.') }}
                        </p>

                        <span
                            class="mt-2 inline-block rounded-md bg-white px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-900 dark:text-gray-300"
                        >
                            {{ $expense->expense_date?->format('d M Y') ?? '-' }}
                        </span>
                    </div>
                </div>
            </div>

        @empty
            <div
                class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center dark:border-gray-700 dark:bg-gray-950"
            >
                <div
                    style="
                        width:44px;
                        height:44px;
                        margin:0 auto;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        border-radius:9999px;
                        background:#ffedd5;
                        color:#ea580c;
                        font-size:22px;
                        font-weight:700;
                    "
                >
                    −
                </div>

                <p class="mt-3 font-semibold text-gray-700 dark:text-gray-200">
                    Belum ada pengeluaran
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Expense yang dicatat akan muncul di sini.
                </p>
            </div>
        @endforelse
    </div>
</div>


        <!-- ===================================================== -->
        <!-- RIGHT COLUMN -->
        <!-- ===================================================== -->

        <div
            class="space-y-6"
            style="display:flex; flex-direction:column; gap:32px;"
        >

            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Transaction Actions
                </p>

                <h3 class="mt-1 text-lg font-semibold text-gray-800 dark:text-white">
                    Payment & Expense
                </h3>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Catat transaksi masuk dan pengeluaran project dari satu area.
                </p>
            </div>

            <!-- ================================================= -->
            <!-- ADD PAYMENT -->
            <!-- ================================================= -->

            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
                style="border-top:3px solid #16a34a;"
            >
                <div class="p-6 pb-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-green-600 dark:text-green-400">
                                Money In
                            </p>

                            <h2 class="mt-1 text-lg font-semibold text-gray-800 dark:text-white">
                                Record Payment
                            </h2>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Catat pembayaran yang diterima dari customer.
                            </p>
                        </div>

                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ $invoice->status }}
                        </span>
                    </div>
                </div>

                @if ($invoice->status === 'paid')
                    <div class="mt-5 rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                        <p class="font-medium text-green-700 dark:text-green-400">
                            Invoice sudah lunas.
                        </p>

                        <p class="mt-1 text-sm text-green-600 dark:text-green-500">
                            Tidak ada sisa pembayaran.
                        </p>
                    </div>
                @else
                    <div
                        class="px-6 pb-2"
                        style="
                            display:grid;
                            grid-template-columns:repeat(2, minmax(0, 1fr));
                            gap:12px;
                        "
                    >
                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-950">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Remaining
                            </p>

                            <p class="mt-1 text-lg font-bold text-red-600 dark:text-red-400">
                                Rp {{ number_format((float) $invoice->balance_due, 0, ',', '.') }}
                            </p>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-950">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Received
                            </p>

                            <p class="mt-1 text-lg font-bold text-green-600 dark:text-green-400">
                                Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}
                            </p>
                        </div>
                    </div>

                    <details open>
                        <summary
                            class="mx-6 mt-3 flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm font-semibold"
                            style="
                                color:#ffffff;
                                background:#0f172a;
                                border-color:#166534;
                            "
                        >
                            <span style="display:flex; align-items:center; gap:10px;">
                                <span
                                    style="
                                        display:inline-flex;
                                        align-items:center;
                                        justify-content:center;
                                        width:24px;
                                        height:24px;
                                        border-radius:9999px;
                                        background:#16a34a;
                                        color:#ffffff;
                                        font-weight:700;
                                        line-height:1;
                                    "
                                >
                                    +
                                </span>

                                <span>Input Payment</span>
                            </span>

                            <span
                                style="
                                    color:#bbf7d0;
                                    font-size:12px;
                                    font-weight:500;
                                "
                            >
                                Click to expand / collapse
                            </span>
                        </summary>

                    <form
                        method="POST"
                        action="{{ route('admin.invoices.payments.store', $invoice->id) }}"
                        class="space-y-5 p-6"
                    >
                        @csrf

                        <!-- Payment Amount -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Amount
                                <span class="text-red-600">*</span>
                            </label>

                            <input
                                type="number"
                                name="amount"
                                min="1"
                                max="{{ (float) $invoice->balance_due }}"
                                step="1"
                                placeholder="Masukkan nominal pembayaran"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                required
                            >

                            @error('amount')
                                <p class="mt-2 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Payment Method -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Payment Method
                            </label>

                            <select
                                name="payment_method"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                                <option value="bank_transfer">
                                    Bank Transfer
                                </option>

                                <option value="cash">
                                    Cash
                                </option>

                                <option value="other">
                                    Other
                                </option>
                            </select>
                        </div>

                        <!-- Payment Reference -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Reference Number
                            </label>

                            <input
                                type="text"
                                name="reference_number"
                                placeholder="Contoh: BCA-TRX-001"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                        </div>

                        <!-- Payment Date -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <!-- Payment Date -->
    <div>
        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Payment Date
        </label>

        <input
            type="date"
            name="paid_date"
            value="{{ old('paid_date', now()->format('Y-m-d')) }}"
            onclick="if (this.showPicker) this.showPicker()"
            class="w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
        >

        @error('paid_date')
            <p class="mt-2 text-sm text-red-600">
                {{ $message }}
            </p>
        @enderror
    </div>

    <!-- Payment Time -->
    <div>
        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Payment Time
        </label>

        <input
            type="time"
            name="paid_time"
            value="{{ old('paid_time', now()->format('H:i')) }}"
            step="60"
            onclick="if (this.showPicker) this.showPicker()"
            class="w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
        >

        @error('paid_time')
            <p class="mt-2 text-sm text-red-600">
                {{ $message }}
            </p>
        @enderror
    </div>
</div>

                        <!-- Payment Notes -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Notes
                            </label>

                            <textarea
                                name="notes"
                                rows="3"
                                placeholder="Catatan pembayaran..."
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            class="primary-button w-full justify-center"
                        >
                            Add Payment
                        </button>
                    </form>
                    </details>
                @endif
            </div>


            <!-- ================================================= -->
            <!-- ADD EXPENSE -->
            <!-- ================================================= -->

            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
                style="border-top:3px solid #f97316;"
            >
                <div class="p-6 pb-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-orange-600 dark:text-orange-400">
                                Money Out
                            </p>

                            <h2 class="mt-1 text-lg font-semibold text-gray-800 dark:text-white">
                                Record Expense
                            </h2>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Catat bon atau pengeluaran yang berkaitan dengan invoice ini.
                            </p>
                        </div>

                        <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-bold uppercase text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                            Expense
                        </span>
                    </div>
                </div>

                <div
                    class="px-6 pb-2"
                    style="
                        display:grid;
                        grid-template-columns:repeat(2, minmax(0, 1fr));
                        gap:12px;
                    "
                >
                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-950">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Current Expense
                        </p>

                        <p class="mt-1 text-lg font-bold text-orange-600 dark:text-orange-400">
                            Rp {{ number_format($totalExpense, 0, ',', '.') }}
                        </p>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-950">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Est. Profit
                        </p>

                        <p class="mt-1 text-lg font-bold {{ $estimatedProfit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            Rp {{ number_format($estimatedProfit, 0, ',', '.') }}
                        </p>
                    </div>
                </div>

                @if ($invoice->event_status === 'cancel')
                    <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/40 dark:bg-red-900/10">
                        <p class="font-semibold text-red-700 dark:text-red-300">
                            Expense dinonaktifkan untuk event Cancel.
                        </p>

                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                            History expense tetap tersedia, tetapi expense baru tidak dapat ditambahkan.
                        </p>
                    </div>
                @else
                    <details>
                        <summary
                            class="mx-6 mt-3 flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm font-semibold"
                            style="
                                color:#ffffff;
                                background:#111827;
                                border-color:#475569;
                            "
                        >
                            <span style="display:flex; align-items:center; gap:10px;">
                                <span
                                    style="
                                        display:inline-flex;
                                        align-items:center;
                                        justify-content:center;
                                        width:24px;
                                        height:24px;
                                        border-radius:9999px;
                                        background:#f97316;
                                        color:#ffffff;
                                        font-weight:700;
                                        line-height:1;
                                    "
                                >
                                    +
                                </span>

                                <span>Input Expense</span>
                            </span>

                            <span
                                style="
                                    color:#cbd5e1;
                                    font-size:12px;
                                    font-weight:500;
                                "
                            >
                                Click to expand / collapse
                            </span>
                        </summary>

                <form
                    method="POST"
                    action="{{ route('admin.invoices.expenses.store', $invoice->id) }}"
                    enctype="multipart/form-data"
                    class="space-y-5 p-6"
                >
                    @csrf

                    <!-- Category -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Category
                            <span class="text-red-600">*</span>
                        </label>

                        <select
                            name="category"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            required
                        >
                            <option value="">
                                Select Category
                            </option>

                            <option value="transport" @selected(old('category') === 'transport')>
                                Transport
                            </option>

                            <option value="crew" @selected(old('category') === 'crew')>
                                Crew
                            </option>

                            <option value="printing" @selected(old('category') === 'printing')>
                                Printing
                            </option>

                            <option value="equipment" @selected(old('category') === 'equipment')>
                                Equipment
                            </option>

                            <option value="vendor" @selected(old('category') === 'vendor')>
                                Vendor
                            </option>

                            <option value="consumption" @selected(old('category') === 'consumption')>
                                Consumption
                            </option>

                            <option value="other" @selected(old('category') === 'other')>
                                Other
                            </option>
                        </select>

                        @error('category')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Expense Description -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Description
                            <span class="text-red-600">*</span>
                        </label>

                        <input
                            type="text"
                            name="description"
                            value="{{ old('description') }}"
                            placeholder="Contoh: Transport crew"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            required
                        >

                        @error('description')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Expense Amount -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Amount
                            <span class="text-red-600">*</span>
                        </label>

                        <input
                            type="number"
                            name="amount"
                            min="1"
                            step="1"
                            placeholder="Contoh: 500000"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            required
                        >

                        @error('amount')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Expense Date -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Expense Date
                            <span class="text-red-600">*</span>
                        </label>

                        <input
                            type="date"
                            name="expense_date"
                            value="{{ old('expense_date', now()->toDateString()) }}"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            required
                        >

                        @error('expense_date')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Vendor -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Vendor
                        </label>

                        <input
                            type="text"
                            name="vendor_name"
                            value="{{ old('vendor_name') }}"
                            placeholder="Contoh: Grab / Percetakan ABC"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >

                        @error('vendor_name')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Reference -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Reference / No. Bon
                        </label>

                        <input
                            type="text"
                            name="reference_number"
                            value="{{ old('reference_number') }}"
                            placeholder="Contoh: BON-001"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >

                        @error('reference_number')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Receipt -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Upload Bon
                        </label>

                        <input
                            type="file"
                            name="receipt"
                            accept=".jpg,.jpeg,.png,.pdf"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300"
                        >

                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Format: JPG, JPEG, PNG atau PDF. Maksimal 5 MB.
                        </p>

                        @error('receipt')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Expense Notes -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Notes
                        </label>

                        <textarea
                            name="notes"
                            rows="3"
                            placeholder="Catatan tambahan..."
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >{{ old('notes') }}</textarea>

                        @error('notes')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="primary-button w-full justify-center"
                    >
                        Save Expense
                    </button>
                </form>
                    </details>
                @endif
            </div>
        </div>
    </div>
    </section>
</x-admin::layouts>
