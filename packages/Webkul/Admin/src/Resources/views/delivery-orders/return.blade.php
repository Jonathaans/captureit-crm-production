<x-admin::layouts>
    <x-slot:title>
        Return Warehouse - {{ $deliveryOrder->delivery_order_number }}
    </x-slot>

    @php
        $status = strtolower(
            $deliveryOrder->status ?: 'draft'
        );

        $formatQty = static function ($value) {
            return rtrim(
                rtrim(
                    number_format((float) $value, 2, '.', ''),
                    '0'
                ),
                '.'
            );
        };

        $serializedAllocations = $allocations
            ->where('tracking_type', 'serialized');

        $quantityAllocations = $allocations
            ->where('tracking_type', 'quantity');

        $receivedSerialized = $serializedAllocations
            ->where('status', 'return_pending');

        $waitingSerialized = $serializedAllocations
            ->where('status', 'out');

        $activeQuantityAllocations = $quantityAllocations
            ->whereIn(
                'status',
                [
                    'out',
                    'return_pending',
                ]
            );

        $hasPendingFinalize = $receivedSerialized->isNotEmpty()
            || $activeQuantityAllocations->isNotEmpty();
    @endphp

    <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900">
        <div>
            <a
                href="{{ route(
                    'admin.delivery-orders.show',
                    $deliveryOrder->id
                ) }}"
                class="text-sm text-gray-600 hover:text-brandColor dark:text-gray-300"
            >
                &larr; Back to Surat Jalan
            </a>

            <div class="mt-2 flex items-center gap-3">
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    Return Warehouse
                </p>

                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold uppercase text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $status }}
                </span>
            </div>

            <p class="mt-1 text-sm text-gray-500">
                {{ $deliveryOrder->delivery_order_number }}
                &middot;
                {{ $deliveryOrder->project_code ?: '-' }}

                @if ($deliveryOrder->project_name)
                    &middot;
                    {{ $deliveryOrder->project_name }}
                @endif
            </p>
        </div>

        <div class="flex gap-2">
            <div class="rounded-lg bg-yellow-50 px-4 py-3 text-center">
                <p class="text-xs font-bold uppercase text-yellow-700">
                    Waiting
                </p>

                <p
                    id="waiting-count"
                    class="mt-1 text-xl font-bold text-yellow-800"
                >
                    {{ $waitingSerialized->count() }}
                </p>
            </div>

            <div class="rounded-lg bg-blue-50 px-4 py-3 text-center">
                <p class="text-xs font-bold uppercase text-blue-700">
                    Received
                </p>

                <p
                    id="received-count"
                    class="mt-1 text-xl font-bold text-blue-800"
                >
                    {{ $receivedSerialized->count() }}
                </p>
            </div>
        </div>
    </div>

    @if ($canOperate)
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-5 dark:border-green-900 dark:bg-green-900/20">
            <div class="flex items-start justify-between gap-4 max-lg:flex-wrap">
                <div>
                    <p class="text-lg font-bold text-green-900 dark:text-green-200">
                        Scan semua barang yang kembali
                    </p>

                    <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                        Tidak perlu klik input dan tidak perlu pilih condition saat scan.
                        Scan hanya menjadi bukti barang fisik sudah masuk warehouse.
                    </p>
                </div>

                <div
                    id="return-scanner-state"
                    class="rounded-full bg-white px-4 py-2 text-xs font-bold text-green-700"
                >
                    READY - WAITING FOR QR
                </div>
            </div>

            <div class="mt-4 rounded-md bg-white/80 p-4 text-sm text-green-900 dark:bg-gray-950/40 dark:text-green-200">
                <strong>Flow:</strong>
                OUT &rarr; Scan &rarr; RETURN PENDING / RECEIVED &rarr; Inspection &rarr; Finalize Return.
            </div>

            @if (bouncer()->hasPermission('delivery-orders.return.check-in'))
            <details
                id="return-manual-disclosure"
                class="group mt-4 rounded-lg border border-yellow-200 bg-yellow-50 dark:border-gray-800 dark:bg-gray-900"
                @if ($errors->has('barcode')) open @endif
            >
                <summary
                    id="return-manual-toggle"
                    aria-controls="return-manual-panel"
                    class="flex cursor-pointer list-none items-center justify-between gap-4 p-4 max-sm:flex-wrap"
                >
                    <div class="flex items-start gap-3">
                        <div class="rounded-full bg-yellow-100 px-3 py-2 text-xs font-bold text-yellow-700">
                            !
                        </div>

                        <div>
                            <p class="font-bold text-yellow-800 dark:text-white">
                                QR rusak atau tidak terbaca?
                            </p>

                            <p class="mt-1 text-xs text-yellow-700 dark:text-gray-300">
                                Ketik Asset Code, Barcode, atau Serial Number. Input manual tetap melewati validasi Surat Jalan dan proses return yang sama.
                            </p>
                        </div>
                    </div>

                    <span
                        class="secondary-button whitespace-nowrap max-sm:w-full max-sm:justify-center"
                    >
                        Input Manual
                    </span>
                </summary>

                <div
                    id="return-manual-panel"
                    class="mx-4 mb-4 rounded-lg border border-yellow-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-950"
                >
                    <form
                        id="return-manual-form"
                        method="POST"
                        action="{{ route(
                            'admin.delivery-orders.return.scan-check-in',
                            $deliveryOrder->id
                        ) }}"
                        class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-3 max-sm:grid-cols-1"
                    >
                        @csrf
                        @method('PUT')

                        <div>
                            <label
                                for="return-manual-code"
                                class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-600 dark:text-gray-300"
                            >
                                Asset Code / Barcode / Serial Number
                            </label>

                            <input
                                id="return-manual-code"
                                type="text"
                                name="barcode"
                                value="{{ old('barcode') }}"
                                maxlength="150"
                                autocomplete="off"
                                spellcheck="false"
                                data-allow-typing
                                required
                                placeholder="Contoh: CAM-0007"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 font-mono text-sm font-semibold uppercase text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >

                            @error('barcode')
                                <p class="mt-2 text-xs font-semibold text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <button
                            id="return-manual-submit"
                            type="submit"
                            class="primary-button max-sm:w-full max-sm:justify-center"
                        >
                            Submit Manual
                        </button>
                    </form>

                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Sistem tidak mengubah database secara langsung. Asset hanya diterima jika kode ditemukan, terdaftar pada Surat Jalan ini, dan status return-nya valid.
                    </p>
                </div>
            </details>
            @endif

            <div
                id="return-scan-message"
                class="mt-4 hidden rounded-md px-4 py-3 text-sm font-semibold"
            ></div>

            <p
                id="return-last-scan"
                class="mt-3 text-xs text-green-700 dark:text-green-300"
            >
                Belum ada scan pada sesi ini.
            </p>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route(
            'admin.delivery-orders.return.finalize',
            $deliveryOrder->id
        ) }}"
        class="mt-5"
    >
        @csrf
        @method('PUT')

        <div>
            <div class="mb-3">
                <p class="text-lg font-bold text-gray-800 dark:text-white">
                    Serialized Return Inspection
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    Scan semuanya terlebih dahulu. Barang yang sudah diterima default GOOD.
                    Setelah scanning selesai, ubah hanya yang FAIR atau DAMAGED.
                </p>
            </div>

            <div class="grid gap-3">
                @forelse ($serializedAllocations as $allocation)
                    @php
                        $isWaiting = $allocation->status === 'out';
                        $isReceived = $allocation->status === 'return_pending';
                        $isReturned = $allocation->status === 'returned';
                        // RETURN DAMAGED NOTE V1
                        $selectedCondition = old('conditions.'.$allocation->id, 'good');
                    @endphp

                    <div
                        id="return-allocation-{{ $allocation->id }}"
                        data-return-allocation-id="{{ $allocation->id }}"
                        data-return-status-value="{{ $allocation->status }}"
                        class="rounded-lg border {{
                            $isWaiting
                                ? 'border-yellow-200'
                                : (
                                    $isReceived
                                        ? 'border-blue-200'
                                        : 'border-green-200'
                                )
                        }} bg-white p-4 dark:bg-gray-900"
                    >
                        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 max-sm:grid-cols-1">
                            <div>
                                <p class="font-bold text-gray-800 dark:text-white">
                                    {{ $allocation->deliveryOrderItem?->name ?: '-' }}
                                </p>

                                <p class="mt-1 font-mono text-sm font-bold text-gray-600 dark:text-gray-300">
                                    {{ $allocation->inventoryAsset?->asset_code ?: '-' }}
                                </p>

                                <p
                                    data-received-info
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    @if ($isReceived)
                                        Received:
                                        {{ optional($allocation->return_pending_at)->format('d M Y H:i') ?: '-' }}
                                    @elseif ($isReturned)
                                        Finalized:
                                        {{ optional($allocation->checked_in_at)->format('d M Y H:i') ?: '-' }}
                                    @else
                                        Belum discan kembali.
                                    @endif
                                </p>
                            </div>

                            <div class="flex items-center gap-3 max-sm:flex-wrap">
                                <span
                                    data-return-status
                                    class="rounded-full px-3 py-1 text-xs font-bold {{
                                        $isWaiting
                                            ? 'bg-yellow-100 text-yellow-700'
                                            : (
                                                $isReceived
                                                    ? 'bg-blue-100 text-blue-700'
                                                    : 'bg-green-100 text-green-700'
                                            )
                                    }}"
                                >
                                    @if ($isWaiting)
                                        WAITING SCAN
                                    @elseif ($isReceived)
                                        RECEIVED
                                    @else
                                        RETURNED
                                    @endif
                                </span>

                                @if ($isReceived)
                                    <select
                                        name="conditions[{{ $allocation->id }}]"
                                        data-condition-select
                                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    >
                                        <option value="good" @selected($selectedCondition === 'good')>GOOD</option>
                                        <option value="fair" @selected($selectedCondition === 'fair')>FAIR</option>
                                        <option value="damaged" @selected($selectedCondition === 'damaged')>DAMAGED</option>
                                    </select>
                                @elseif ($isReturned)
                                    <span class="rounded-md bg-gray-100 px-3 py-2 text-xs font-bold uppercase text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        {{ $allocation->return_condition ?: '-' }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if ($isReceived)
                            <!-- RETURN DAMAGED NOTE V1 -->
                            <div
                                data-damage-note-container
                                class="mt-3 rounded-md border border-red-200 bg-red-50 p-3 {{ $selectedCondition === 'damaged' ? '' : 'hidden' }}"
                            >
                                <label class="mb-1 block text-xs font-bold text-red-700">
                                    Alasan Barang Rusak <span class="text-red-600">*</span>
                                </label>

                                <textarea
                                    name="return_notes[{{ $allocation->id }}]"
                                    data-damage-note
                                    rows="2"
                                    maxlength="2000"
                                    placeholder="Jelaskan kerusakan barang saat kembali..."
                                    @disabled($selectedCondition !== 'damaged')
                                    @required($selectedCondition === 'damaged')
                                    class="w-full rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-gray-800"
                                >{{ old('return_notes.'.$allocation->id) }}</textarea>

                                @error('return_notes.'.$allocation->id)
                                    <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @elseif (
                            $isReturned
                            && $allocation->return_condition === 'damaged'
                            && $allocation->return_notes
                        )
                            <div class="mt-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                                <strong>Alasan Rusak:</strong>
                                {{ $allocation->return_notes }}
                            </div>
                        @endif

                        @if (
                            $isWaiting
                            && $canOperate
                            && bouncer()->hasPermission('delivery-orders.return.check-in')
                        )
                            <div class="mt-3 flex justify-end">
                                <button
                                    type="submit"
                                    form="missing-form-{{ $allocation->id }}"
                                    class="text-xs font-semibold text-red-600 hover:underline"
                                    onclick="return confirm('Asset {{ $allocation->inventoryAsset?->asset_code }} benar-benar tidak kembali dan akan ditandai MISSING?')"
                                >
                                    Mark Missing
                                </button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                        Tidak ada serialized asset pada Surat Jalan ini.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="mt-6">
            <div class="mb-3">
                <p class="text-lg font-bold text-gray-800 dark:text-white">
                    Manual Quantity Return
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    Paper Roll dan Frame tetap manual. Isi jumlah fisik yang benar-benar kembali.
                    Sisa otomatis dianggap consumed / terpakai.
                </p>
            </div>

            <div class="grid gap-3">
                @forelse ($quantityAllocations as $allocation)
                    @php
                        $quantityActive = in_array(
                            $allocation->status,
                            [
                                'out',
                                'return_pending',
                            ],
                            true
                        );
                    @endphp

                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="grid grid-cols-4 items-end gap-3 max-lg:grid-cols-2 max-sm:grid-cols-1">
                            <div>
                                <p class="font-bold text-gray-800 dark:text-white">
                                    {{ $allocation->deliveryOrderItem?->name ?: '-' }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $allocation->inventoryItem?->code ?: '-' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-500">
                                    Quantity OUT
                                </p>

                                <p class="mt-1 font-bold text-gray-800 dark:text-white">
                                    {{ $formatQty($allocation->quantity) }}
                                    {{ $allocation->inventoryItem?->unit ?: '-' }}
                                </p>
                            </div>

                            @if ($quantityActive)
                                <div>
                                    <label class="mb-1 block text-xs font-bold text-gray-600 dark:text-gray-300">
                                        Returned Quantity
                                    </label>

                                    <input
                                        type="number"
                                        name="quantities[{{ $allocation->id }}]"
                                        value="0"
                                        min="0"
                                        max="{{ $formatQty($allocation->quantity) }}"
                                        step="0.01"
                                        required
                                        data-allow-typing
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    >
                                </div>

                                <div class="text-xs text-gray-500">
                                    Maks:
                                    {{ $formatQty($allocation->quantity) }}
                                    {{ $allocation->inventoryItem?->unit ?: '-' }}
                                </div>
                            @else
                                <div>
                                    <p class="text-xs text-gray-500">
                                        Returned
                                    </p>

                                    <p class="mt-1 font-bold text-green-700">
                                        {{ $formatQty($allocation->returned_quantity ?: 0) }}
                                        {{ $allocation->inventoryItem?->unit ?: '-' }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs text-gray-500">
                                        Consumed
                                    </p>

                                    <p class="mt-1 font-bold text-gray-700 dark:text-gray-300">
                                        {{
                                            $formatQty(
                                                max(
                                                    (float) $allocation->quantity
                                                    - (float) ($allocation->returned_quantity ?: 0),
                                                    0
                                                )
                                            )
                                        }}
                                        {{ $allocation->inventoryItem?->unit ?: '-' }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                        Tidak ada quantity stock pada Surat Jalan ini.
                    </div>
                @endforelse
            </div>
        </div>

        @if (
            $canOperate
            && $hasPendingFinalize
            && bouncer()->hasPermission('delivery-orders.return.check-in')
        )
            <div class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-5 dark:border-blue-900 dark:bg-blue-900/20">
                <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
                    <div>
                        <p class="font-bold text-blue-900 dark:text-blue-200">
                            Finalize Return
                        </p>

                        <p class="mt-1 text-xs text-blue-700 dark:text-blue-300">
                            Pastikan semua serialized asset sudah RECEIVED atau ditandai Missing,
                            lalu review condition dan quantity return.
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="primary-button"
                        onclick="return confirm('Finalize seluruh Return yang sudah diterima dan quantity return?')"
                    >
                        Finalize Return
                    </button>
                </div>
            </div>
        @endif
    </form>

    {{-- Missing is an exception and remains outside the batch inspection form. --}}
    @foreach ($serializedAllocations->where('status', 'out') as $allocation)
        @if (
            $canOperate
            && bouncer()->hasPermission('delivery-orders.return.check-in')
        )
            <form
                id="missing-form-{{ $allocation->id }}"
                method="POST"
                action="{{ route(
                    'admin.delivery-orders.return.check-in',
                    [
                        $deliveryOrder->id,
                        $allocation->id,
                    ]
                ) }}"
                class="hidden"
            >
                @csrf
                @method('PUT')

                <input
                    type="hidden"
                    name="condition"
                    value="missing"
                >
            </form>
        @endif
    @endforeach

    @if (
        $status === 'delivered'
        && $allReturned
        && bouncer()->hasPermission('delivery-orders.returned')
    )
        <div class="mt-5 rounded-lg border border-green-200 bg-green-50 p-5 dark:border-green-900 dark:bg-green-900/20">
            <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
                <div>
                    <p class="font-bold text-green-900 dark:text-green-200">
                        Semua inventory sudah selesai Return.
                    </p>

                    <p class="mt-1 text-xs text-green-700 dark:text-green-300">
                        Surat Jalan siap ditutup sebagai RETURNED.
                    </p>
                </div>

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
                    >
                        Mark as Returned
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if (
        $canOperate
        && bouncer()->hasPermission('delivery-orders.return.check-in')
    )
        <script>
            (() => {
                const endpoint = @json(
                    route(
                        'admin.delivery-orders.return.scan-check-in',
                        $deliveryOrder->id
                    )
                );

                const csrf = @json(csrf_token());

                let scanBuffer = '';
                let lastKeyAt = 0;
                let queue = [];
                let processing = false;

                const state = document.getElementById('return-scanner-state');
                const messageBox = document.getElementById('return-scan-message');
                const lastScan = document.getElementById('return-last-scan');
                const waitingCount = document.getElementById('waiting-count');
                const receivedCount = document.getElementById('received-count');
                const manualDisclosure = document.getElementById('return-manual-disclosure');
                const manualForm = document.getElementById('return-manual-form');
                const manualInput = document.getElementById('return-manual-code');
                const manualSubmit = document.getElementById('return-manual-submit');

                function setManualOpen(open) {
                    if (! manualDisclosure) {
                        return;
                    }

                    manualDisclosure.open = Boolean(open);

                    if (
                        open
                        && manualInput
                    ) {
                        window.setTimeout(
                            () => manualInput.focus(),
                            0
                        );
                    }
                }

                function showMessage(message, type = 'success') {
                    messageBox.classList.remove(
                        'hidden',
                        'bg-green-100',
                        'text-green-800',
                        'bg-red-100',
                        'text-red-800',
                        'bg-yellow-100',
                        'text-yellow-800'
                    );

                    if (type === 'error') {
                        messageBox.classList.add(
                            'bg-red-100',
                            'text-red-800'
                        );
                    } else if (type === 'warning') {
                        messageBox.classList.add(
                            'bg-yellow-100',
                            'text-yellow-800'
                        );
                    } else {
                        messageBox.classList.add(
                            'bg-green-100',
                            'text-green-800'
                        );
                    }

                    messageBox.textContent = message;
                }

                function beep(success = true) {
                    try {
                        const AudioContext = window.AudioContext || window.webkitAudioContext;

                        if (! AudioContext) {
                            return;
                        }

                        const context = new AudioContext();
                        const oscillator = context.createOscillator();
                        const gain = context.createGain();

                        oscillator.frequency.value = success ? 880 : 220;
                        gain.gain.value = 0.04;

                        oscillator.connect(gain);
                        gain.connect(context.destination);

                        oscillator.start();
                        oscillator.stop(
                            context.currentTime
                            + (
                                success
                                    ? 0.08
                                    : 0.18
                            )
                        );
                    } catch (error) {
                        // Visual feedback remains available.
                    }
                }

                function updateCounters() {
                    const waiting = document.querySelectorAll(
                        '[data-return-status-value="out"]'
                    ).length;

                    const received = document.querySelectorAll(
                        '[data-return-status-value="return_pending"]'
                    ).length;

                    if (waitingCount) {
                        waitingCount.textContent = waiting;
                    }

                    if (receivedCount) {
                        receivedCount.textContent = received;
                    }
                }

                function ensureDamageNoteField(card, allocationId) {
                    let container = card.querySelector(
                        '[data-damage-note-container]'
                    );

                    if (container) {
                        return container;
                    }

                    container = document.createElement('div');
                    container.dataset.damageNoteContainer = '1';
                    container.className = 'mt-3 hidden rounded-md border border-red-200 bg-red-50 p-3';
                    container.innerHTML = `
                        <label class="mb-1 block text-xs font-bold text-red-700">
                            Alasan Barang Rusak <span class="text-red-600">*</span>
                        </label>
                        <textarea
                            name="return_notes[${allocationId}]"
                            data-damage-note
                            rows="2"
                            maxlength="2000"
                            placeholder="Jelaskan kerusakan barang saat kembali..."
                            disabled
                            class="w-full rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-gray-800"
                        ></textarea>
                    `;
                    card.appendChild(container);

                    return container;
                }

                function syncDamageNoteField(select) {
                    const card = select.closest(
                        '[data-return-allocation-id]'
                    );

                    if (! card) {
                        return;
                    }

                    const allocationId = card.dataset.returnAllocationId;
                    const container = ensureDamageNoteField(
                        card,
                        allocationId
                    );
                    const note = container.querySelector(
                        '[data-damage-note]'
                    );
                    const damaged = select.value === 'damaged';

                    container.classList.toggle('hidden', ! damaged);

                    if (note) {
                        note.disabled = ! damaged;
                        note.required = damaged;

                        if (! damaged) {
                            note.value = '';
                        }
                    }
                }

                function bindConditionSelect(select) {
                    if (select.dataset.damageNoteBound === '1') {
                        return;
                    }

                    select.dataset.damageNoteBound = '1';
                    select.addEventListener(
                        'change',
                        () => syncDamageNoteField(select)
                    );
                    syncDamageNoteField(select);
                }

                function updateAllocation(allocation) {
                    const card = document.querySelector(
                        `[data-return-allocation-id="${allocation.id}"]`
                    );

                    if (! card) {
                        return;
                    }

                    const oldStatus = card.dataset.returnStatusValue;
                    const badge = card.querySelector('[data-return-status]');
                    const info = card.querySelector('[data-received-info]');

                    card.dataset.returnStatusValue = 'return_pending';

                    if (badge) {
                        badge.textContent = 'RECEIVED';
                        badge.className = 'rounded-full px-3 py-1 text-xs font-bold bg-blue-100 text-blue-700';
                    }

                    if (info) {
                        info.textContent = allocation.received_at
                            ? `Received: ${allocation.received_at}`
                            : 'Received - waiting inspection.';
                    }

                    /*
                     * Add default GOOD inspection selector only after scan.
                     */
                    if (! card.querySelector('[data-condition-select]')) {
                        const actions = badge?.parentElement;

                        if (actions) {
                            const select = document.createElement('select');

                            select.name = `conditions[${allocation.id}]`;
                            select.dataset.conditionSelect = '1';
                            select.className = 'rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold';
                            select.innerHTML = `
                                <option value="good" selected>GOOD</option>
                                <option value="fair">FAIR</option>
                                <option value="damaged">DAMAGED</option>
                            `;

                            actions.appendChild(select);
                            bindConditionSelect(select);
                        }
                    }

                    /*
                     * Missing action is no longer relevant after physical scan.
                     */
                    const missingButton = card.querySelector(
                        'button[form^="missing-form-"]'
                    );

                    if (missingButton) {
                        missingButton.closest('.mt-3')?.remove();
                    }

                    if (oldStatus === 'out') {
                        updateCounters();
                    }
                }

                async function processNext() {
                    if (
                        processing
                        || queue.length === 0
                    ) {
                        return;
                    }

                    processing = true;

                    const job = queue.shift();
                    const code = job.code;
                    let successful = false;

                    state.textContent = `PROCESSING ${code}`;
                    lastScan.textContent = `Last scan: ${code}`;

                    try {
                        const response = await fetch(
                            endpoint,
                            {
                                method: 'PUT',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({
                                    barcode: code,
                                }),
                            }
                        );

                        const data = await response.json();

                        if (! response.ok) {
                            const errors = data.errors || {};
                            const firstError = Object.values(errors)
                                .flat()
                                .shift();

                            throw new Error(
                                firstError
                                || data.message
                                || 'Return scan gagal.'
                            );
                        }

                        if (
                            data.allocation.status === 'return_pending'
                        ) {
                            updateAllocation(
                                data.allocation
                            );
                        }

                        showMessage(
                            data.message,
                            data.duplicate
                                ? 'warning'
                                : 'success'
                        );

                        successful = true;

                        beep(true);
                    } catch (error) {
                        showMessage(
                            error.message || 'Return scan gagal.',
                            'error'
                        );

                        beep(false);
                    } finally {
                        if (job.source === 'manual') {
                            if (manualSubmit) {
                                manualSubmit.disabled = false;
                                manualSubmit.classList.remove(
                                    'cursor-not-allowed',
                                    'opacity-60'
                                );
                            }

                            if (successful) {
                                if (manualInput) {
                                    manualInput.value = '';
                                }

                                setManualOpen(false);
                            } else {
                                setManualOpen(true);
                            }
                        }

                        processing = false;

                        state.textContent = queue.length
                            ? `QUEUE ${queue.length} - PROCESSING`
                            : 'READY - WAITING FOR QR';

                        processNext();
                    }
                }

                function enqueue(code, source = 'scanner') {
                    code = String(code || '').trim();

                    if (! code) {
                        return;
                    }

                    queue.push({
                        code,
                        source,
                    });

                    if (
                        source === 'manual'
                        && manualSubmit
                    ) {
                        manualSubmit.disabled = true;
                        manualSubmit.classList.add(
                            'cursor-not-allowed',
                            'opacity-60'
                        );
                    }

                    processNext();
                }

                manualForm?.addEventListener(
                    'submit',
                    (event) => {
                        event.preventDefault();

                        const code = manualInput?.value.trim() || '';

                        if (! code) {
                            showMessage(
                                'Masukkan Asset Code, Barcode, atau Serial Number.',
                                'error'
                            );
                            setManualOpen(true);

                            return;
                        }

                        enqueue(
                            code,
                            'manual'
                        );
                    }
                );

                document.addEventListener(
                    'keydown',
                    (event) => {
                        const target = event.target;

                        if (
                            target
                            && (
                                target.matches('[data-allow-typing]')
                                || target.tagName === 'INPUT'
                                || target.tagName === 'TEXTAREA'
                                || target.tagName === 'SELECT'
                                || target.isContentEditable
                            )
                        ) {
                            return;
                        }

                        if (
                            event.ctrlKey
                            || event.altKey
                            || event.metaKey
                        ) {
                            return;
                        }

                        const now = Date.now();

                        if (
                            lastKeyAt
                            && now - lastKeyAt > 800
                        ) {
                            scanBuffer = '';
                        }

                        lastKeyAt = now;

                        if (event.key === 'Enter') {
                            if (scanBuffer.trim()) {
                                event.preventDefault();

                                const code = scanBuffer.trim();

                                scanBuffer = '';

                                enqueue(code);
                            }

                            return;
                        }

                        if (
                            event.key.length === 1
                            && ! event.repeat
                        ) {
                            event.preventDefault();

                            scanBuffer += event.key;
                        }
                    }
                );

                document.querySelectorAll('[data-condition-select]')
                    .forEach(bindConditionSelect);

                updateCounters();
            })();
        </script>
    @endif
</x-admin::layouts>
