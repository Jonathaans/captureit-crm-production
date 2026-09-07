<x-admin::layouts>
    {{-- CRM_INVOICE_FLEXIBLE_BILLING_UI_HOTFIX_V1_2 --}}
    <x-slot:title>
        Generate Invoice dari Quote
    </x-slot>

    @php
        $selectedQuoteId = (string) old(
            'quote_id',
            $selectedQuote?->id
        );
        $initialType = old('billing_type', 'down_payment');
        $initialMethod = old('billing_method', 'nominal');
        $initialAmount = old('billing_amount', '');
        $initialPercentage = old('billing_percentage', '');
    @endphp

    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <a
                        href="{{ route('admin.invoices.index') }}"
                        class="text-sm font-semibold text-brandColor hover:underline"
                    >
                        &larr; Kembali ke Invoice
                    </a>

                    <h1 class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">
                        Generate Invoice dari Quote
                    </h1>

                    <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Pilih DP fleksibel, Full Payment, atau Pelunasan. Nominal DP
                        tetap dapat diedit sampai tombol Generate ditekan.
                    </p>
                </div>

                <span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                    Audit-safe billing
                </span>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                <div class="font-semibold">Invoice belum dapat dibuat:</div>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            id="crm-flexible-billing-form"
            method="POST"
            action="{{ route('admin.invoices.billing.store') }}"
            class="grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(320px,.65fr)]"
        >
            @csrf

            <div class="space-y-5">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-5">
                        <div class="text-xs font-semibold uppercase tracking-[.18em] text-brandColor">
                            01 &middot; Quote
                        </div>
                        <h2 class="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                            Pilih sumber tagihan
                        </h2>
                    </div>

                    <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Quote
                    </label>

                    <select
                        id="crm-billing-quote"
                        name="quote_id"
                        required
                        class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-brandColor dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option value="">-- Pilih Quote --</option>

                        @foreach ($quotes as $quoteOption)
                            <option
                                value="{{ $quoteOption->id }}"
                                @selected($selectedQuoteId === (string) $quoteOption->id)
                            >
                                {{ $quoteOption->quote_number ?: '#'.$quoteOption->id }}
                                &mdash; {{ $quoteOption->project_code ?: '-' }}
                                &mdash; {{ $quoteOption->subject ?: '-' }}
                                &mdash; Rp {{ number_format((float) $quoteOption->grand_total, 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>

                    <div id="crm-billing-loading" class="mt-3 hidden text-sm text-gray-500">
                        Memuat posisi tagihan Quote...
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-5">
                        <div class="text-xs font-semibold uppercase tracking-[.18em] text-brandColor">
                            02 &middot; Jenis Invoice
                        </div>
                        <h2 class="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                            Tentukan tahap pembayaran
                        </h2>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach ([
                            'down_payment' => ['DP', 'Nominal fleksibel, tidak harus 50%.'],
                            'full_payment' => ['Full Payment', 'Tagih 100% Grand Total Quote.'],
                            'settlement' => ['Pelunasan', 'Sisa Quote setelah DP berstatus PAID.'],
                        ] as $value => [$label, $help])
                            <label class="cursor-pointer">
                                <input
                                    type="radio"
                                    name="billing_type"
                                    value="{{ $value }}"
                                    class="peer sr-only"
                                    @checked($initialType === $value)
                                >

                                <span class="block h-full rounded-xl border border-gray-200 p-4 transition peer-checked:border-brandColor peer-checked:bg-orange-50 dark:border-gray-700 dark:peer-checked:bg-orange-950/20">
                                    <span class="block font-bold text-gray-900 dark:text-white">
                                        {{ $label }}
                                    </span>
                                    <span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-gray-400">
                                        {{ $help }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div
                        id="crm-dp-inputs"
                        class="mt-5 grid gap-4 rounded-xl bg-gray-50 p-4 dark:bg-gray-950 md:grid-cols-2"
                    >
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Metode Input DP
                            </label>
                            <select
                                id="crm-billing-method"
                                name="billing_method"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                                <option value="nominal" @selected($initialMethod === 'nominal')>
                                    Nominal
                                </option>
                                <option value="percentage" @selected($initialMethod === 'percentage')>
                                    Persentase
                                </option>
                            </select>
                        </div>

                        <div id="crm-percentage-wrap">
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Persentase DP
                            </label>
                            <div class="relative">
                                <input
                                    id="crm-billing-percentage"
                                    name="billing_percentage"
                                    type="number"
                                    min="0.0001"
                                    max="99.9999"
                                    step="0.0001"
                                    value="{{ $initialPercentage }}"
                                    class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 pr-10 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                >
                                <span class="absolute right-4 top-3 text-sm text-gray-500">%</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Nominal Invoice
                        </label>
                        <input
                            id="crm-billing-amount"
                            name="billing_amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            value="{{ $initialAmount }}"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-lg font-bold text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            placeholder="Contoh: 2000000"
                        >
                        <p id="crm-amount-help" class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">
                            Nominal adalah nilai final yang disimpan. Anda dapat mengubahnya sebelum Generate.
                        </p>
                    </div>

                    <div
                        id="crm-eligibility-message"
                        class="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-700 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-300"
                    >
                        Pilih Quote untuk memeriksa status billing.
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-5">
                        <div class="text-xs font-semibold uppercase tracking-[.18em] text-brandColor">
                            03 &middot; Tanggal
                        </div>
                        <h2 class="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                            Tanggal penerbitan dan jatuh tempo
                        </h2>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Invoice Date
                            </label>
                            <input
                                name="issued_at"
                                type="date"
                                value="{{ old('issued_at', now()->toDateString()) }}"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Due Date
                            </label>
                            <input
                                name="due_at"
                                type="date"
                                value="{{ old('due_at', now()->addDays(7)->toDateString()) }}"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                        </div>
                    </div>
                </section>
            </div>

            <aside class="lg:sticky lg:top-20 lg:self-start">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-950">
                        <div class="text-xs font-semibold uppercase tracking-[.18em] text-gray-500">
                            Live Preview
                        </div>
                        <h2 id="crm-preview-title" class="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                            Belum ada Quote
                        </h2>
                        <p id="crm-preview-project" class="mt-1 text-xs text-gray-500"></p>
                    </div>

                    <div class="space-y-4 p-5">
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span class="text-gray-500">Grand Total Quote</span>
                            <strong id="crm-preview-total" class="text-gray-900 dark:text-white">Rp 0</strong>
                        </div>
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span class="text-gray-500">Sudah Di-invoice</span>
                            <strong id="crm-preview-invoiced" class="text-gray-900 dark:text-white">Rp 0</strong>
                        </div>
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span class="text-gray-500">Invoice Baru</span>
                            <strong id="crm-preview-new" class="text-brandColor">Rp 0</strong>
                        </div>
                        <div class="border-t border-gray-200 pt-4 dark:border-gray-800">
                            <div class="flex items-center justify-between gap-4">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">Sisa Setelah Generate</span>
                                <strong id="crm-preview-remaining" class="text-xl text-gray-900 dark:text-white">Rp 0</strong>
                            </div>
                        </div>

                        <div id="crm-existing-invoices" class="space-y-2"></div>

                        <div class="rounded-xl bg-amber-50 p-4 text-xs leading-5 text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                            Setelah dibuat, nilai komersial dan item Invoice dikunci.
                            Koreksi dilakukan dengan Cancel lalu Generate ulang.
                        </div>

                        <button
                            id="crm-generate-button"
                            type="submit"
                            disabled
                            class="primary-button w-full justify-center disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Generate Invoice
                        </button>
                    </div>
                </div>
            </aside>
        </form>
    </div>

    @pushOnce('scripts')
        <script>
            (() => {
                let quoteSelect = document.getElementById('crm-billing-quote');
                let form = document.getElementById('crm-flexible-billing-form');

                if (! quoteSelect || ! form) {
                    return;
                }

                const summaryUrl = @json(
                    route(
                        'admin.invoices.billing.summary',
                        ['quoteId' => '__QUOTE__']
                    )
                );
                const initialSummary = @json($billingSummary);
                let amountInput = document.getElementById('crm-billing-amount');
                let methodSelect = document.getElementById('crm-billing-method');
                let percentageInput = document.getElementById('crm-billing-percentage');
                let percentageWrap = document.getElementById('crm-percentage-wrap');
                let dpInputs = document.getElementById('crm-dp-inputs');
                let message = document.getElementById('crm-eligibility-message');
                let submitButton = document.getElementById('crm-generate-button');
                let loading = document.getElementById('crm-billing-loading');
                let summary = initialSummary;
                let syncing = false;

                const refreshElements = () => {
                    quoteSelect = document.getElementById('crm-billing-quote');
                    form = document.getElementById('crm-flexible-billing-form');
                    amountInput = document.getElementById('crm-billing-amount');
                    methodSelect = document.getElementById('crm-billing-method');
                    percentageInput = document.getElementById('crm-billing-percentage');
                    percentageWrap = document.getElementById('crm-percentage-wrap');
                    dpInputs = document.getElementById('crm-dp-inputs');
                    message = document.getElementById('crm-eligibility-message');
                    submitButton = document.getElementById('crm-generate-button');
                    loading = document.getElementById('crm-billing-loading');
                };

                const currency = new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                });

                const selectedType = () => (
                    form.querySelector('input[name="billing_type"]:checked')?.value
                    || 'down_payment'
                );

                const renderExisting = () => {
                    const container = document.getElementById('crm-existing-invoices');
                    container.innerHTML = '';

                    if (! summary) {
                        return;
                    }

                    [
                        ['DP', summary.down_payment],
                        ['Full Payment', summary.full_payment],
                        ['Pelunasan', summary.settlement],
                    ].forEach(([label, invoice]) => {
                        if (! invoice) {
                            return;
                        }

                        const row = document.createElement('div');
                        row.className = 'rounded-lg border border-gray-200 p-3 text-xs dark:border-gray-700';
                        row.textContent = label + ': ' + invoice.invoice_number
                            + ' · ' + currency.format(invoice.amount)
                            + ' · ' + String(invoice.status || '').toUpperCase();
                        container.appendChild(row);
                    });
                };

                const render = (preserveDpAmount = true) => {
                    refreshElements();

                    if (
                        ! form
                        || ! amountInput
                        || ! methodSelect
                        || ! percentageInput
                        || ! percentageWrap
                        || ! dpInputs
                        || ! message
                        || ! submitButton
                    ) {
                        return;
                    }

                    const type = selectedType();
                    const isDp = type === 'down_payment';
                    const isPercentage = methodSelect.value === 'percentage';

                    dpInputs.classList.toggle('hidden', ! isDp);
                    percentageWrap.classList.toggle('hidden', ! isPercentage);
                    methodSelect.disabled = ! isDp;
                    percentageInput.disabled = ! isDp || ! isPercentage;
                    percentageInput.required = isDp && isPercentage;
                    amountInput.readOnly = ! isDp;
                    amountInput.required = isDp && ! isPercentage;

                    if (! summary) {
                        amountInput.value = isDp && preserveDpAmount
                            ? amountInput.value
                            : '';
                        submitButton.disabled = true;
                        message.textContent = 'Pilih Quote untuk memeriksa status billing.';
                        document.getElementById('crm-preview-title').textContent = 'Belum ada Quote';
                        document.getElementById('crm-preview-project').textContent = '';
                        document.getElementById('crm-preview-total').textContent = currency.format(0);
                        document.getElementById('crm-preview-invoiced').textContent = currency.format(0);
                        document.getElementById('crm-preview-new').textContent = currency.format(0);
                        document.getElementById('crm-preview-remaining').textContent = currency.format(0);
                        renderExisting();
                        return;
                    }

                    if (type === 'full_payment') {
                        amountInput.value = Number(summary.quote_total || 0).toFixed(2);
                    } else if (type === 'settlement') {
                        amountInput.value = Number(summary.remaining || 0).toFixed(2);
                    } else if (! preserveDpAmount && isPercentage) {
                        syncAmountFromPercentage();
                    }

                    const amount = Number(amountInput.value || 0);
                    const eligible = Boolean(summary.eligibility?.[type]);
                    const validAmount = type !== 'down_payment'
                        || (amount > 0 && amount < Number(summary.quote_total || 0));

                    // Keep eligible billing clickable even if a browser/plugin
                    // suppresses an input event. Laravel remains authoritative
                    // for amount validation and returns a visible error message.
                    submitButton.disabled = ! eligible;
                    message.textContent = summary.messages?.[type]
                        || 'Jenis invoice tidak tersedia.';

                    message.className = eligible && validAmount
                        ? 'mt-4 rounded-xl border border-green-200 bg-green-50 p-4 text-sm leading-6 text-green-700 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300'
                        : 'mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300';

                    document.getElementById('crm-preview-title').textContent =
                        summary.quote_number + ' · ' + summary.subject;
                    document.getElementById('crm-preview-project').textContent =
                        summary.project_code;
                    document.getElementById('crm-preview-total').textContent =
                        currency.format(summary.quote_total || 0);
                    document.getElementById('crm-preview-invoiced').textContent =
                        currency.format(summary.active_invoiced || 0);
                    document.getElementById('crm-preview-new').textContent =
                        currency.format(amount);
                    document.getElementById('crm-preview-remaining').textContent =
                        currency.format(
                            Math.max(
                                0,
                                Number(summary.remaining || 0) - amount
                            )
                        );

                    renderExisting();
                };

                const syncAmountFromPercentage = () => {
                    refreshElements();

                    if (syncing || ! summary) {
                        return;
                    }

                    syncing = true;
                    const percentage = Number(percentageInput.value || 0);
                    amountInput.value = (
                        Number(summary.quote_total || 0)
                        * percentage
                        / 100
                    ).toFixed(2);
                    syncing = false;
                };

                const syncPercentageFromAmount = () => {
                    refreshElements();

                    if (
                        syncing
                        || ! summary
                        || methodSelect.value !== 'percentage'
                    ) {
                        return;
                    }

                    syncing = true;
                    const total = Number(summary.quote_total || 0);
                    const amount = Number(amountInput.value || 0);
                    percentageInput.value = total > 0
                        ? ((amount / total) * 100).toFixed(4)
                        : '';
                    syncing = false;
                };

                const loadSummary = async () => {
                    refreshElements();

                    if (! quoteSelect || ! amountInput || ! percentageInput) {
                        return;
                    }

                    const quoteId = quoteSelect.value;
                    summary = null;
                    amountInput.value = '';
                    percentageInput.value = '';
                    render();

                    if (! quoteId) {
                        return;
                    }

                    loading.classList.remove('hidden');

                    try {
                        const response = await fetch(
                            summaryUrl.replace('__QUOTE__', quoteId),
                            {
                                headers: {
                                    Accept: 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            }
                        );

                        if (! response.ok) {
                            throw new Error('Quote tidak dapat dimuat.');
                        }

                        const payload = await response.json();
                        summary = payload.data;
                        render(false);
                    } catch (error) {
                        message.textContent = error.message
                            || 'Quote tidak dapat dimuat.';
                    } finally {
                        loading.classList.add('hidden');
                    }
                };

                const changeMethod = () => {
                    refreshElements();

                    if (methodSelect.value === 'percentage') {
                        if (! percentageInput.value) {
                            const total = Number(summary?.quote_total || 0);
                            const currentAmount = Number(amountInput.value || 0);

                            percentageInput.value = total > 0 && currentAmount > 0
                                ? ((currentAmount / total) * 100).toFixed(4)
                                : '50';
                        }

                        syncAmountFromPercentage();
                    }

                    render();
                };

                const changePercentage = () => {
                    syncAmountFromPercentage();
                    render();
                };

                const changeAmount = () => {
                    syncPercentageFromAmount();
                    render();
                };

                document.addEventListener('change', (event) => {
                    const target = event.target;

                    if (! (target instanceof HTMLElement)) {
                        return;
                    }

                    if (target.id === 'crm-billing-quote') {
                        loadSummary();
                    } else if (target.id === 'crm-billing-method') {
                        changeMethod();
                    } else if (target.matches('input[name="billing_type"]')) {
                        render(false);
                    }
                }, true);

                document.addEventListener('input', (event) => {
                    const target = event.target;

                    if (! (target instanceof HTMLElement)) {
                        return;
                    }

                    if (target.id === 'crm-billing-percentage') {
                        changePercentage();
                    } else if (target.id === 'crm-billing-amount') {
                        changeAmount();
                    }
                }, true);

                document.addEventListener('submit', (event) => {
                    if (
                        ! (event.target instanceof HTMLFormElement)
                        || event.target.id !== 'crm-flexible-billing-form'
                    ) {
                        return;
                    }

                    refreshElements();

                    if (submitButton.disabled) {
                        event.preventDefault();
                        return;
                    }

                    submitButton.disabled = true;
                    submitButton.textContent = 'Membuat Invoice...';
                }, true);

                window.crmFlexibleBillingV12 = {
                    loadSummary,
                    render,
                    changeMethod,
                    changePercentage,
                    changeAmount,
                };

                render();
            })();
        </script>
    @endPushOnce
</x-admin::layouts>
