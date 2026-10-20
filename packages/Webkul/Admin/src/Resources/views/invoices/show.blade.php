<x-admin::layouts>
    <x-slot:title>
        {{ $invoice->invoice_number }}
    </x-slot>
    @push('styles')
        <style>
            .invoice-activity-grid { font-family:Inter,Arial,sans-serif; display:grid; grid-template-columns:minmax(0,1.35fr) minmax(340px,1fr); gap:24px; align-items:start; }
            .invoice-activity-grid > * { min-width:0; }
            @media (max-width:1150px) { .invoice-activity-grid { grid-template-columns:1fr; } }
            @media (max-width:600px) { .invoice-payment-row { grid-template-columns:1fr !important; gap:12px !important; } }
        </style>
    @endpush
    @include('admin::historical-documents.notice', ['record' => $invoice])


@php
    $expenseCategories = app(\Webkul\Invoice\Repositories\ExpenseCategoryRepository::class)->directory();
    $categoryLabels = $expenseCategories->pluck('name', 'code')->all();
    $totalExpense = (float) $invoice->expenses->sum('amount');

    $estimatedProfit =
        ($invoice->status === 'void' ? 0 : (float) $invoice->grand_total)
        - $totalExpense;

    $projectWorkOrder = app(\Webkul\Invoice\Services\WorkOrderService::class)->findForInvoice($invoice);
    $deliveryOrder = $projectWorkOrder?->deliveryOrders()->orderBy('id')->first()
        ?? $invoice->deliveryOrders->sortBy('id')->first();

    $billToIdentity = $invoice->billToIdentity();
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
                {{-- CRM_INVOICE_FLEXIBLE_BILLING_V1 --}}
                @if ($invoice->billing_locked_at)
                    @php
                        $billingTypeLabel = match ($invoice->billing_type) {
                            'down_payment' => 'DOWN PAYMENT',
                            'settlement' => 'PELUNASAN',
                            default => 'FULL PAYMENT',
                        };
                    @endphp
                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                        {{ $billingTypeLabel }}
                    </span>
                @endif
                @if ($invoice->status === 'void')
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">VOID / DIBATALKAN</span>
                @elseif ($invoice->status === 'paid')
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
            @if (! $invoice->quote_id && bouncer()->hasPermission('invoices.edit'))
            <a
                href="{{ route('admin.invoices.edit', $invoice->id) }}"
                class="secondary-button"
            >
                Edit Invoice
            </a>
            @elseif ($invoice->quote_id && bouncer()->hasPermission('invoices.edit') && in_array($invoice->status, ['unpaid', 'partial', 'paid']) && ! in_array($invoice->event_status, ['cancel', 'cancelled', 'canceled']))
                <a href="{{ route('admin.commercial-revisions.edit', ['invoice', $invoice->id]) }}" class="secondary-button">Revisi Transaksi</a>
            @endif

            @if ($invoice->quote && bouncer()->hasPermission('quotes.edit'))
                <a
                    href="{{ route('admin.quotes.edit', $invoice->quote->id) }}"
                    class="secondary-button"
                >
                    View Quote
                </a>
            @endif

            @if (bouncer()->hasPermission('invoices.print'))
            <a
                href="{{ route('admin.invoices.print', $invoice->id) }}"
                class="primary-button"
            >
                Print Invoice
                    </a>
            @endif
            @if ($projectWorkOrder && bouncer()->hasPermission('work-orders.view'))
                <a href="{{ route('admin.invoices.work-orders.open', $invoice->id) }}" class="secondary-button">Buka SPK</a>
            @elseif ($invoice->status !== 'void' && ! $projectWorkOrder && bouncer()->hasPermission('work-orders.generate'))
                <form method="POST" action="{{ route('admin.invoices.work-orders.store', $invoice->id) }}">
                    @csrf
                    <button type="submit" class="secondary-button">Buat SPK</button>
                </form>
            @endif
            @if ($deliveryOrder && bouncer()->hasPermission('delivery-orders.print'))
                <a href="{{ route('admin.delivery-orders.print', $deliveryOrder->id) }}" target="_blank" rel="noopener" class="secondary-button">Print Surat Jalan</a>
            @endif
            @if ($invoice->billing_type === 'down_payment' && $invoice->status === 'paid' && bouncer()->hasPermission('invoices.generate'))
                <a href="{{ route('admin.invoices.billing.create', ['quote_id' => $invoice->quote_id, 'billing_type' => 'settlement']) }}" class="secondary-button">Buat Proforma Pelunasan</a>
            @endif
            @if ($invoice->billing_type === 'settlement' && $invoice->dp_invoice_id && bouncer()->hasPermission('invoices.view'))
                <a href="{{ route('admin.invoices.show', $invoice->dp_invoice_id) }}" class="secondary-button">Buka Invoice DP</a>
            @endif
        </div>
    </div>


    <!-- ========================================================= -->
    <!-- PROJECT & INVOICE INFORMATION -->
    @if ($invoice->pph_gross_up)
        <div class="my-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-blue-900">
            <p class="font-semibold">Gross-up PPh {{ $invoice->pph_rate }}%</p>
            <p>Bruto: Rp {{ number_format((float) $invoice->grand_total, 2, ',', '.') }} · PPh dipotong pelanggan: Rp {{ number_format((float) $invoice->pph_amount, 2, ',', '.') }} · Transfer netto: Rp {{ number_format($invoice->cashPayable(), 2, ',', '.') }}</p>
            <p>Status pembayaran dan sisa tagihan mengikuti transfer netto. PPh adalah perhitungan potongan, bukan uang masuk atau bukti setor pajak. Bukti potong diterima terpisah dari pelanggan.</p>
        </div>
    @endif
    <!-- ========================================================= -->

    @if ($invoice->status === 'void')
        <div class="my-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
            <p class="font-semibold">Invoice dibatalkan karena koreksi. Dokumen ini bukan tagihan aktif.</p>
            <p>Seluruh pembayaran di bawah adalah riwayat salah input dan tidak dihitung sebagai penerimaan. SPK, DO, dan stok tetap menyimpan riwayat pekerjaan yang sudah terlaksana.</p>
            <p class="mt-2">Alasan: {{ $invoice->voidCorrection?->reason ?? '-' }}</p>
            <p>Dicatat: {{ $invoice->voidCorrection?->created_at?->format('d M Y H:i') ?? '-' }} · Pengguna CRM #{{ $invoice->voidCorrection?->actor_id ?? '-' }}</p>
        </div>
    @endif

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

                @if ($billToIdentity['mode'] === 'company')
                    <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                        {{ $billToIdentity['company_name'] }}
                    </p>
                @elseif ($billToIdentity['mode'] === 'both')
                    <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                        {{ $billToIdentity['company_name'] }}
                    </p>

                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        {{ $billToIdentity['person_name'] }}
                    </p>
                @else
                    <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                        {{ $billToIdentity['person_name'] }}
                    </p>
                @endif
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

        @if ($invoice->status !== 'void')
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
        @endif
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

        <div class="invoice-activity-grid">

        <!-- ===================================================== -->
        <!-- LEFT COLUMN -->
        <!-- ===================================================== -->

        <div
            class="invoice-activity-history"
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
                                    Quantity
                                </th>

                                <th class="px-6 py-3 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    Unit
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
                                @php
                                    $billingLine = \Webkul\Core\Support\SalesLineItem::invoiceDisplay($item->getAttributes());
                                @endphp
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
                                        {{ \Webkul\Invoice\Support\BillingDescription::display($invoice->getAttributes(), $item->description) ?: '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-center text-gray-700 dark:text-gray-300">
                                        {{ $billingLine['quantity'] }}
                                    </td>

                                    <td class="px-6 py-4 text-center text-gray-700 dark:text-gray-300">
                                        {{ $billingLine['unit'] === 'day' ? 'Day' : 'Pcs' }}
                                    </td>

                                    <td class="px-6 py-4 text-right text-gray-700 dark:text-gray-300">
                                        Rp {{ number_format((float) $billingLine['price'], 0, ',', '.') }}
                                    </td>

                                    <td class="px-6 py-4 text-right font-semibold text-gray-800 dark:text-white">
                                        Rp {{ number_format(\Webkul\Core\Support\SalesLineItem::invoiceBase($item->getAttributes()) + (float) $item->tax_amount - (float) $item->discount_amount, 0, ',', '.') }}
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
                id="payment-history"
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
                            {{ $invoice->status === 'void' ? 'DIBATALKAN: riwayat salah input, tanpa uang diterima.' : 'Riwayat pencatatan pembayaran. Pembayaran VOID tidak dihitung sebagai penerimaan.' }}
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
                        @php
                            $paymentIsVoid = $invoice->status === 'void' || $payment->voidRecord !== null;
                        @endphp
                        <div
                            class="invoice-payment-row rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-950"
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
                                        <p class="text-lg font-bold {{ $paymentIsVoid ? 'text-gray-500 line-through' : 'text-green-600 dark:text-green-400' }}">
                                            Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                        </p>

                                        @if ($paymentIsVoid)
                                            <span class="rounded bg-red-50 px-2 py-1 text-xs font-bold text-red-800">VOID · tidak dihitung</span>
                                        @endif

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

                                    @if ($payment->receipt_path)
                                        <a v-pre class="secondary-button mt-3" style="max-width:100%;overflow-wrap:anywhere;white-space:normal" href="{{ route('admin.invoices.payments.receipt', [$invoice->id, $payment->id]) }}">
                                            ↓ Unduh bon · {{ $payment->receipt_name }}
                                        </a>
                                    @endif
                                    @if ($payment->notes)
                                        <div
                                            class="mt-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        >
                                            {{ $payment->notes }}
                                        </div>
                                    @endif
                                    @if ($payment->voidRecord)
                                        <div class="mt-3 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                                            <p>Alasan VOID: {{ $payment->voidRecord->reason }}</p>
                                            <p>{{ $payment->voidRecord->created_at->format('d M Y H:i') }} · {{ $payment->voidRecord->actor?->name ?? ('Pengguna #'.$payment->voidRecord->actor_id) }} · Audit #{{ $payment->voidRecord->id }}</p>
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
                                @if (! $paymentIsVoid && in_array($invoice->status, ['unpaid', 'partial', 'paid'], true) && bouncer()->hasPermission('invoices.payment-void'))
                                    <a class="secondary-button mt-3" href="{{ route('admin.invoices.payments.void.preview', [$invoice->id, $payment->id]) }}">Void Pembayaran</a>
                                @endif
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

                            <span v-pre class="rounded-full bg-orange-100 px-2.5 py-1 text-xs font-semibold text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                                {{ \Webkul\Invoice\Repositories\ExpenseCategoryRepository::label($expense->category, $categoryLabels) }}
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

                                            <select v-pre
                                                name="category"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                                required
                                            >
                                                @foreach ($expenseCategories->filter(fn ($category) => $category->is_active || $category->code === $expense->category) as $category)
                                                    <option value="{{ $category->code }}" @selected($expense->category === $category->code)>{{ $category->name }}{{ $category->is_active ? '' : ' (nonaktif)' }}</option>
                                                @endforeach
                                                @if (! $expenseCategories->contains('code', $expense->category))
                                                    <option value="{{ $expense->category }}" selected>{{ \Webkul\Invoice\Repositories\ExpenseCategoryRepository::label($expense->category, $categoryLabels) }} (kategori lama)</option>
                                                @endif
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
        </div>

        <!-- RIGHT COLUMN -->
        @include('admin::invoices.partials.transaction-forms')
    </div>
    </section>
</x-admin::layouts>
