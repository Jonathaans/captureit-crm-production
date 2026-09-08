<x-admin::layouts>
    <x-slot:title>
        Missing Asset Recovery Station
    </x-slot>

    {{-- INVENTORY_MISSING_RECOVERY_STATION_VIEW_V2 --}}
    <div class="mx-auto grid max-w-5xl gap-5">
        <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
            <div>
                <p class="text-2xl font-bold text-gray-800 dark:text-white">
                    Missing Asset Recovery Station
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    Scan barang fisik, verifikasi identitas di server, lalu konfirmasi kondisi dan lokasi penemuan.
                </p>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('admin.inventory.assets.index') }}" class="secondary-button">
                    Daftar Asset
                </a>

                <a href="{{ route('admin.inventory.movements.index') }}" class="secondary-button">
                    Inventory Movement
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-semibold text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800">
                {{ session('warning') }}
            </div>
        @endif

        @if ($verificationExpired)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800">
                Verifikasi sebelumnya sudah melewati 30 menit. Scan ulang barang fisik.
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <p class="font-bold">Recovery belum dapat dilanjutkan:</p>

                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-3 gap-3 max-md:grid-cols-1">
            <div class="rounded-xl border p-4 {{ $verifiedAsset ? 'border-green-300 bg-green-50' : 'border-blue-300 bg-blue-50' }}">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">01 — Scan Fisik</p>
                <p class="mt-2 font-bold text-gray-800">{{ $verifiedAsset ? 'SELESAI' : 'AKTIF' }}</p>
                <p class="mt-1 text-xs text-gray-600">Input scanner nyata, bukan listener keyboard global.</p>
            </div>

            <div class="rounded-xl border p-4 {{ $verifiedAsset ? 'border-blue-300 bg-blue-50' : 'border-gray-200 bg-gray-50' }}">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">02 — Periksa Kondisi</p>
                <p class="mt-2 font-bold text-gray-800">{{ $verifiedAsset ? 'AKTIF' : 'MENUNGGU SCAN' }}</p>
                <p class="mt-1 text-xs text-gray-600">Good, fair, atau damaged beserta alasannya.</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">03 — Audit Trail</p>
                <p class="mt-2 font-bold text-gray-800">OTOMATIS</p>
                <p class="mt-1 text-xs text-gray-600">Status, alert, dan movement diperbarui dalam satu transaksi.</p>
            </div>
        </div>

        @if (! $verifiedAsset)
            <section class="rounded-2xl border border-blue-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4 max-sm:flex-wrap">
                    <div>
                        <p class="text-lg font-bold text-gray-800 dark:text-white">
                            Scan Barcode / QR Asset
                        </p>

                        <p class="mt-1 text-sm text-gray-500">
                            Kursor sudah aktif pada kolom scanner. Langsung scan; Enter dari scanner akan mengirim form otomatis.
                        </p>
                    </div>

                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">
                        READY TO SCAN
                    </span>
                </div>

                @if ($expectedAsset)
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Asset yang hendak dipulihkan</p>
                        <p class="mt-1 text-lg font-bold text-amber-900">{{ $expectedAsset->asset_code }}</p>
                        <p class="text-sm text-amber-700">
                            {{ $expectedAsset->item?->code }} — {{ $expectedAsset->item?->name }}
                        </p>
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ route('admin.inventory.missing-recovery.verify') }}"
                    class="mt-5"
                >
                    @csrf

                    <label for="recovery-scanner-input" class="mb-2 block text-sm font-bold text-gray-700 dark:text-gray-200">
                        Scanner Input
                    </label>

                    <div class="rounded-2xl border-2 border-blue-400 bg-blue-50 p-3 ring-4 ring-blue-100 dark:bg-gray-950">
                        <input
                            id="recovery-scanner-input"
                            name="barcode"
                            type="text"
                            value=""
                            autofocus
                            required
                            autocomplete="off"
                            autocapitalize="characters"
                            enterkeyhint="done"
                            spellcheck="false"
                            placeholder="Langsung scan barcode / QR di sini..."
                            class="w-full border-0 bg-transparent px-3 py-4 font-mono text-xl font-bold tracking-wide text-gray-900 outline-none placeholder:text-gray-400 focus:ring-0 dark:text-white"
                            onfocus="this.select()"
                        >
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-4 max-sm:flex-col max-sm:items-stretch">
                        <p class="text-xs leading-5 text-gray-500">
                            Tidak memakai tombol aktivasi dan tidak memakai buffer keyboard JavaScript.
                            Nilai diverifikasi terhadap asset berstatus MISSING di server.
                        </p>

                        <button type="submit" class="primary-button justify-center whitespace-nowrap">
                            Verifikasi Scan
                        </button>
                    </div>
                </form>
            </section>
        @else
            <section class="rounded-2xl border border-green-300 bg-green-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4 max-sm:flex-wrap">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-green-700">Scan berhasil diverifikasi</p>
                        <p class="mt-1 text-2xl font-bold text-green-950">{{ $verifiedAsset->asset_code }}</p>
                        <p class="mt-1 text-sm text-green-800">
                            {{ $verifiedAsset->item?->code }} — {{ $verifiedAsset->item?->name }}
                        </p>
                        <p class="mt-2 text-xs font-semibold text-green-700">
                            Status: MISSING · Warehouse terakhir: {{ $verifiedAsset->warehouse?->name ?? '-' }}
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.inventory.missing-recovery.clear') }}">
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="secondary-button">
                            Batalkan & Scan Ulang
                        </button>
                    </form>
                </div>
            </section>

            <form
                method="POST"
                action="{{ route('admin.inventory.missing-recovery.complete', $verifiedAsset->id) }}"
                class="grid gap-4"
            >
                @csrf

                <input type="hidden" name="recovery_token" value="{{ $recoveryToken }}">

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-lg font-bold text-gray-800 dark:text-white">
                        Konfirmasi Kondisi dan Lokasi
                    </p>

                    <p class="mt-1 text-sm text-gray-500">
                        Informasi ini disimpan pada asset dan movement. Kondisi DAMAGED otomatis membuka damaged alert.
                    </p>

                    <div class="mt-5 grid grid-cols-2 gap-4 max-md:grid-cols-1">
                        <div>
                            <label for="condition" class="mb-1.5 block text-sm font-medium">Kondisi Saat Ditemukan *</label>
                            <select
                                id="condition"
                                name="condition"
                                class="w-full rounded-md border px-3 py-2.5 dark:border-gray-800 dark:bg-gray-900"
                                required
                            >
                                <option value="good" @selected(old('condition', 'good') === 'good')>Baik / Ready → AVAILABLE</option>
                                <option value="fair" @selected(old('condition') === 'fair')>Cukup / Fair → AVAILABLE</option>
                                <option value="damaged" @selected(old('condition') === 'damaged')>Rusak / Damaged → DAMAGED</option>
                            </select>
                        </div>

                        <div>
                            <label for="found-warehouse" class="mb-1.5 block text-sm font-medium">Ditemukan di Warehouse *</label>
                            <select
                                id="found-warehouse"
                                name="found_warehouse_id"
                                class="w-full rounded-md border px-3 py-2.5 dark:border-gray-800 dark:bg-gray-900"
                                required
                            >
                                <option value="">Pilih warehouse</option>
                                @foreach ($warehouses as $warehouse)
                                    <option
                                        value="{{ $warehouse->id }}"
                                        @selected((int) old('found_warehouse_id', $verifiedAsset->warehouse_id) === (int) $warehouse->id)
                                    >
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-span-2 max-md:col-span-1">
                            <label for="found-location" class="mb-1.5 block text-sm font-medium">Lokasi Detail Saat Ditemukan *</label>
                            <input
                                id="found-location"
                                type="text"
                                name="found_location"
                                value="{{ old('found_location') }}"
                                placeholder="Contoh: Rak Kamera A-03, Gudang Utama"
                                maxlength="255"
                                class="w-full rounded-md border px-3 py-2.5 dark:border-gray-800 dark:bg-gray-900"
                                required
                            >
                        </div>

                        <div id="damage-reason-wrap" class="col-span-2 {{ old('condition') === 'damaged' ? '' : 'hidden' }} max-md:col-span-1">
                            <label for="damage-reason" class="mb-1.5 block text-sm font-medium text-red-700">Alasan / Detail Kerusakan *</label>
                            <textarea
                                id="damage-reason"
                                name="damage_reason"
                                rows="3"
                                maxlength="2000"
                                placeholder="Jelaskan kerusakan yang ditemukan dan bagian yang terdampak."
                                class="w-full rounded-md border border-red-200 px-3 py-2.5 dark:border-red-900 dark:bg-gray-900"
                                @if (old('condition') === 'damaged') required @endif
                            >{{ old('damage_reason') }}</textarea>
                        </div>

                        <div class="col-span-2 max-md:col-span-1">
                            <label for="found-note" class="mb-1.5 block text-sm font-medium">Catatan Penemuan *</label>
                            <textarea
                                id="found-note"
                                name="found_note"
                                rows="3"
                                maxlength="2000"
                                placeholder="Contoh: ditemukan kembali setelah pengecekan rak penyimpanan."
                                class="w-full rounded-md border px-3 py-2.5 dark:border-gray-800 dark:bg-gray-900"
                                required
                            >{{ old('found_note') }}</textarea>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input
                            id="confirmed"
                            type="checkbox"
                            name="confirmed"
                            value="1"
                            class="mt-1"
                            required
                        >

                        <span>
                            <span class="block text-sm font-semibold text-gray-800 dark:text-white">
                                Saya sudah memeriksa barang fisik, identitas barcode, kondisi, dan lokasi penemuan.
                            </span>
                            <span class="mt-1 block text-xs text-gray-500">
                                Recovery menutup missing alert dan membuat movement read-only atas nama akun Anda.
                            </span>
                        </span>
                    </label>

                    <button type="submit" class="primary-button mt-5 w-full justify-center">
                        Confirm Found & Catat Movement
                    </button>
                </section>
            </form>
        @endif
    </div>

    @pushOnce('scripts')
        <script>
            @if (! $verifiedAsset)
                (() => {
                    const scannerInput = document.getElementById('recovery-scanner-input');
                    const scannerForm = scannerInput?.form;

                    if (!scannerInput || !scannerForm) {
                        return;
                    }

                    const focusScanner = () => {
                        scannerInput.focus({ preventScroll: true });
                        scannerInput.select();
                    };

                    requestAnimationFrame(focusScanner);
                    setTimeout(focusScanner, 250);
                    window.addEventListener('pageshow', focusScanner, { once: true });

                    scannerInput.addEventListener('keydown', (event) => {
                        if (event.key === 'Tab' && scannerInput.value.trim()) {
                            event.preventDefault();
                            scannerForm.requestSubmit();
                        }
                    });
                })();
            @else
                (() => {
                    const condition = document.getElementById('condition');
                    const reasonWrap = document.getElementById('damage-reason-wrap');
                    const reason = document.getElementById('damage-reason');

                    if (!condition || !reasonWrap || !reason) {
                        return;
                    }

                    const syncDamageReason = () => {
                        const damaged = condition.value === 'damaged';
                        reasonWrap.classList.toggle('hidden', !damaged);
                        reason.required = damaged;
                    };

                    condition.addEventListener('change', syncDamageReason);
                    syncDamageReason();
                })();
            @endif
        </script>
    @endPushOnce
</x-admin::layouts>
