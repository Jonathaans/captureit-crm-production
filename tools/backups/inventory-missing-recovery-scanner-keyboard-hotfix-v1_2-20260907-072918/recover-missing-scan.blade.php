<x-admin::layouts>
    <x-slot:title>
        Scan Missing Asset Recovery
    </x-slot>

    <div class="mx-auto grid max-w-5xl gap-4">
        <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
            <div>
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    Missing Asset Recovery
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    Scan fisik asset, periksa kondisinya, lalu simpan ke audit trail Inventory Movement.
                </p>
            </div>

            <a
                href="{{ route('admin.inventory.assets.edit', $asset->id) }}"
                class="secondary-button"
            >
                Kembali ke Asset
            </a>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <p class="font-semibold">Recovery belum dapat disimpan:</p>

                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-3 gap-3 max-md:grid-cols-1">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Asset</p>
                <p class="mt-1 text-lg font-bold text-gray-800 dark:text-white">{{ $asset->asset_code }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ $asset->item?->code }} — {{ $asset->item?->name }}</p>
            </div>

            <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-red-600">Status Saat Ini</p>
                <p class="mt-1 text-lg font-bold text-red-700 dark:text-red-300">MISSING</p>
                <p class="mt-1 text-sm text-red-600/80">Recovery manual dinonaktifkan.</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Warehouse Terakhir</p>
                <p class="mt-1 text-lg font-bold text-gray-800 dark:text-white">{{ $asset->warehouse?->name ?? '-' }}</p>
                <p class="mt-1 text-sm text-gray-500">Scan harus cocok dengan identitas asset ini.</p>
            </div>
        </div>

        <form
            id="missing-recovery-form"
            method="POST"
            action="{{ route('admin.inventory.assets.missing-recovery.store', $asset->id) }}"
            class="grid gap-4"
        >
            @csrf

            <input type="hidden" name="recovery_token" value="{{ $recoveryToken }}">
            <input id="scanned-barcode" type="hidden" name="scanned_barcode" value="">

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4 max-sm:flex-wrap">
                    <div>
                        <p class="text-base font-bold text-gray-800 dark:text-white">1. Scan Barcode / QR Asset</p>
                        <p class="mt-1 text-sm text-gray-500">
                            Gunakan kamera perangkat atau scanner USB. Tidak tersedia input kode manual.
                        </p>
                    </div>

                    <span
                        id="scan-badge"
                        class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700"
                    >
                        BELUM DIPINDAI
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-[minmax(0,1.15fr)_minmax(280px,.85fr)] gap-4 max-md:grid-cols-1">
                    <div
                        id="camera-shell"
                        class="relative hidden min-h-64 overflow-hidden rounded-xl bg-gray-950"
                    >
                        <video
                            id="barcode-camera"
                            class="h-72 w-full object-cover"
                            playsinline
                            muted
                        ></video>

                        <div class="pointer-events-none absolute inset-0 grid place-items-center">
                            <div class="h-36 w-72 max-w-[80%] rounded-xl border-2 border-white/90 shadow-[0_0_0_999px_rgba(0,0,0,.28)]"></div>
                        </div>
                    </div>

                    <div class="grid content-start gap-3">
                        <button id="camera-button" type="button" class="primary-button justify-center">
                            Scan dengan Kamera
                        </button>

                        <button id="usb-button" type="button" class="secondary-button justify-center">
                            Aktifkan Scanner USB
                        </button>

                        <button id="reset-button" type="button" class="secondary-button hidden justify-center">
                            Reset Scan
                        </button>

                        <div
                            id="scan-result-box"
                            class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-950"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Hasil Scan</p>
                            <p id="scan-result" class="mt-2 break-all font-mono text-sm font-semibold text-gray-700 dark:text-gray-200">—</p>
                            <p id="scan-help" class="mt-2 text-xs text-gray-500">
                                Arahkan kamera atau aktifkan scanner USB, lalu pindai label pada barang fisik.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-base font-bold text-gray-800 dark:text-white">2. Konfirmasi Kondisi dan Lokasi</p>
                <p class="mt-1 text-sm text-gray-500">
                    Informasi ini tersimpan pada asset dan movement. Kondisi DAMAGED otomatis membuka damaged alert.
                </p>

                <div class="mt-4 grid grid-cols-2 gap-4 max-md:grid-cols-1">
                    <div>
                        <label for="condition" class="mb-1.5 block text-sm font-medium">Kondisi Saat Ditemukan *</label>
                        <select
                            id="condition"
                            name="condition"
                            class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                            required
                        >
                            <option value="good" @selected(old('condition') === 'good')>Baik / Ready → AVAILABLE</option>
                            <option value="fair" @selected(old('condition') === 'fair')>Cukup / Fair → AVAILABLE</option>
                            <option value="damaged" @selected(old('condition') === 'damaged')>Rusak / Damaged → DAMAGED</option>
                        </select>
                    </div>

                    <div>
                        <label for="found-warehouse" class="mb-1.5 block text-sm font-medium">Ditemukan di Warehouse *</label>
                        <select
                            id="found-warehouse"
                            name="found_warehouse_id"
                            class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                            required
                        >
                            <option value="">Pilih warehouse</option>
                            @foreach ($warehouses as $warehouse)
                                <option
                                    value="{{ $warehouse->id }}"
                                    @selected((int) old('found_warehouse_id', $asset->warehouse_id) === (int) $warehouse->id)
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
                            class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                            required
                        >
                    </div>

                    <div id="damage-reason-wrap" class="col-span-2 hidden max-md:col-span-1">
                        <label for="damage-reason" class="mb-1.5 block text-sm font-medium text-red-700">Alasan / Detail Kerusakan *</label>
                        <textarea
                            id="damage-reason"
                            name="damage_reason"
                            rows="3"
                            maxlength="2000"
                            placeholder="Jelaskan kerusakan yang ditemukan dan bagian yang terdampak."
                            class="w-full rounded-md border border-red-200 px-3 py-2 dark:border-red-900 dark:bg-gray-900"
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
                            class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                            required
                        >{{ old('found_note') }}</textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
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
                            Recovery akan menutup missing alert dan membuat movement read-only atas nama akun Anda.
                        </span>
                    </span>
                </label>

                <button
                    id="submit-recovery"
                    type="submit"
                    class="primary-button mt-4 w-full justify-center opacity-60"
                    disabled
                >
                    Scan Asset Terlebih Dahulu
                </button>
            </section>
        </form>
    </div>

    @pushOnce('scripts')
        <script>
            (() => {
                const expectedValues = @json(array_values(array_unique(array_filter([
                    strtoupper(trim((string) $asset->barcode_value)),
                    strtoupper(trim((string) $asset->asset_code)),
                ]))));
                const cameraShell = document.getElementById('camera-shell');
                const video = document.getElementById('barcode-camera');
                const cameraButton = document.getElementById('camera-button');
                const usbButton = document.getElementById('usb-button');
                const resetButton = document.getElementById('reset-button');
                const hiddenBarcode = document.getElementById('scanned-barcode');
                const scanResult = document.getElementById('scan-result');
                const scanHelp = document.getElementById('scan-help');
                const scanBadge = document.getElementById('scan-badge');
                const resultBox = document.getElementById('scan-result-box');
                const condition = document.getElementById('condition');
                const damageReasonWrap = document.getElementById('damage-reason-wrap');
                const damageReason = document.getElementById('damage-reason');
                const confirmed = document.getElementById('confirmed');
                const submitButton = document.getElementById('submit-recovery');

                let mediaStream = null;
                let detector = null;
                let detectFrame = null;
                let usbActive = false;
                let usbBuffer = '';
                let usbLastKeyAt = 0;
                let scanMatched = false;

                const normalize = (value) => String(value || '').trim().toUpperCase();

                const updateSubmit = () => {
                    const ready = scanMatched && confirmed.checked;
                    submitButton.disabled = !ready;
                    submitButton.classList.toggle('opacity-60', !ready);
                    submitButton.textContent = ready
                        ? 'Confirm Found & Catat Movement'
                        : (scanMatched ? 'Centang Konfirmasi untuk Melanjutkan' : 'Scan Asset Terlebih Dahulu');
                };

                const stopCamera = () => {
                    if (detectFrame) {
                        cancelAnimationFrame(detectFrame);
                        detectFrame = null;
                    }

                    if (mediaStream) {
                        mediaStream.getTracks().forEach((track) => track.stop());
                        mediaStream = null;
                    }

                    video.srcObject = null;
                    cameraShell.classList.add('hidden');
                    cameraButton.textContent = 'Scan dengan Kamera';
                };

                const setMismatch = (value) => {
                    scanMatched = false;
                    hiddenBarcode.value = '';
                    scanResult.textContent = value || '—';
                    scanHelp.textContent = 'Barcode tidak cocok dengan asset {{ $asset->asset_code }}. Silakan scan label barang yang benar.';
                    scanBadge.textContent = 'TIDAK COCOK';
                    scanBadge.className = 'rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700';
                    resultBox.className = 'rounded-xl border border-red-300 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30';
                    resetButton.classList.remove('hidden');
                    updateSubmit();
                };

                const acceptScan = (rawValue) => {
                    const value = normalize(rawValue);

                    if (!value) {
                        return;
                    }

                    if (!expectedValues.includes(value)) {
                        setMismatch(value);
                        return;
                    }

                    scanMatched = true;
                    hiddenBarcode.value = rawValue.trim();
                    scanResult.textContent = rawValue.trim();
                    scanHelp.textContent = 'Identitas cocok. Lanjutkan pemeriksaan kondisi dan lokasi.';
                    scanBadge.textContent = 'SCAN COCOK';
                    scanBadge.className = 'rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700';
                    resultBox.className = 'rounded-xl border border-green-300 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950/30';
                    resetButton.classList.remove('hidden');
                    usbActive = false;
                    usbButton.textContent = 'Aktifkan Scanner USB';
                    stopCamera();
                    updateSubmit();
                };

                const resetScan = () => {
                    stopCamera();
                    usbActive = false;
                    usbBuffer = '';
                    scanMatched = false;
                    hiddenBarcode.value = '';
                    scanResult.textContent = '—';
                    scanHelp.textContent = 'Arahkan kamera atau aktifkan scanner USB, lalu pindai label pada barang fisik.';
                    scanBadge.textContent = 'BELUM DIPINDAI';
                    scanBadge.className = 'rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700';
                    resultBox.className = 'rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-950';
                    resetButton.classList.add('hidden');
                    usbButton.textContent = 'Aktifkan Scanner USB';
                    updateSubmit();
                };

                const scanCameraFrame = async () => {
                    if (!mediaStream || !detector) {
                        return;
                    }

                    try {
                        const barcodes = await detector.detect(video);

                        if (barcodes.length > 0) {
                            acceptScan(barcodes[0].rawValue);
                            return;
                        }
                    } catch (error) {
                        scanHelp.textContent = 'Kamera belum dapat membaca label. Tahan perangkat stabil atau gunakan scanner USB.';
                    }

                    detectFrame = requestAnimationFrame(scanCameraFrame);
                };

                cameraButton.addEventListener('click', async () => {
                    usbActive = false;
                    usbButton.textContent = 'Aktifkan Scanner USB';

                    if (mediaStream) {
                        stopCamera();
                        return;
                    }

                    if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
                        scanHelp.textContent = 'Browser ini belum mendukung scan kamera. Gunakan Chrome/Edge terbaru melalui HTTPS/localhost atau scanner USB.';
                        return;
                    }

                    try {
                        const supported = await BarcodeDetector.getSupportedFormats();
                        const wanted = ['qr_code', 'code_128', 'code_39', 'ean_13', 'ean_8']
                            .filter((format) => supported.includes(format));

                        detector = new BarcodeDetector(wanted.length ? { formats: wanted } : undefined);
                        mediaStream = await navigator.mediaDevices.getUserMedia({
                            video: { facingMode: { ideal: 'environment' } },
                            audio: false,
                        });
                        video.srcObject = mediaStream;
                        await video.play();
                        cameraShell.classList.remove('hidden');
                        cameraButton.textContent = 'Tutup Kamera';
                        scanHelp.textContent = 'Kamera aktif. Posisikan barcode/QR di dalam kotak.';
                        detectFrame = requestAnimationFrame(scanCameraFrame);
                    } catch (error) {
                        stopCamera();
                        scanHelp.textContent = 'Kamera tidak dapat dibuka. Periksa izin browser atau gunakan scanner USB.';
                    }
                });

                usbButton.addEventListener('click', () => {
                    stopCamera();
                    usbActive = !usbActive;
                    usbBuffer = '';
                    usbButton.textContent = usbActive ? 'Scanner USB Aktif — Scan Sekarang' : 'Aktifkan Scanner USB';
                    scanHelp.textContent = usbActive
                        ? 'Scanner USB aktif. Pindai label barang; sistem menunggu karakter hingga tombol Enter dari scanner.'
                        : 'Mode scanner USB dihentikan.';
                });

                document.addEventListener('keydown', (event) => {
                    if (!usbActive) {
                        return;
                    }

                    const now = Date.now();

                    if (now - usbLastKeyAt > 1500) {
                        usbBuffer = '';
                    }

                    usbLastKeyAt = now;

                    if (event.key === 'Enter') {
                        event.preventDefault();
                        const value = usbBuffer;
                        usbBuffer = '';
                        acceptScan(value);
                        return;
                    }

                    if (event.key.length === 1) {
                        event.preventDefault();
                        usbBuffer += event.key;
                    }
                });

                const syncDamageReason = () => {
                    const isDamaged = condition.value === 'damaged';
                    damageReasonWrap.classList.toggle('hidden', !isDamaged);
                    damageReason.required = isDamaged;
                };

                condition.addEventListener('change', syncDamageReason);
                confirmed.addEventListener('change', updateSubmit);
                resetButton.addEventListener('click', resetScan);
                window.addEventListener('pagehide', stopCamera);
                syncDamageReason();
                updateSubmit();
            })();
        </script>
    @endPushOnce
</x-admin::layouts>
