<?php
declare(strict_types=1);

echo "MISSING ASSET SCANNER FOCUS FIX V2.2\n";
echo "====================================\n\n";

$root = dirname(__DIR__);
chdir($root);

$viewBase = $root.'/packages/Webkul/Admin/src/Resources/views/inventory';
$controller = $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/MissingAssetQrRecoveryController.php';

function findViewV22(string $base): ?string
{
    if (! is_dir($base)) {
        return null;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $base,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($it as $file) {
        if (
            ! $file->isFile()
            || ! str_ends_with(strtolower($file->getFilename()), '.blade.php')
        ) {
            continue;
        }

        $text = @file_get_contents($file->getPathname());

        if (
            is_string($text)
            && (
                str_contains($text, 'MISSING_ASSET_SCANNER_ONLY_V2_1')
                || str_contains($text, 'MISSING_ASSET_SCANNER_FOCUS_FIX_V2_2')
            )
        ) {
            return $file->getPathname();
        }
    }

    return null;
}

function replaceRecoveryBlockV22(
    string $source,
    string $marker,
    string $replacement
): string {
    $markerPos = strpos($source, $marker);

    if ($markerPos === false) {
        throw new RuntimeException("Marker {$marker} tidak ditemukan.");
    }

    $commentStart = strrpos(substr($source, 0, $markerPos), '{{--');

    if ($commentStart === false) {
        $commentStart = $markerPos;
    }

    /*
     * We need the outer @if that starts the recovery panel.  Search forward
     * from the marker, then balance nested @if / @endif pairs.
     */
    $firstIf = strpos($source, '@if', $markerPos);

    if ($firstIf === false) {
        throw new RuntimeException('Outer @if recovery tidak ditemukan.');
    }

    $tokenPattern = '/@(if|endif)\b/';
    $offset = $firstIf;
    $depth = 0;
    $end = null;

    while (
        preg_match(
            $tokenPattern,
            $source,
            $match,
            PREG_OFFSET_CAPTURE,
            $offset
        )
    ) {
        $token = $match[1][0];
        $tokenPos = $match[0][1];

        if ($token === 'if') {
            $depth++;
        } else {
            $depth--;

            if ($depth === 0) {
                $end = $tokenPos + strlen('@endif');
                break;
            }
        }

        $offset = $tokenPos + strlen($match[0][0]);
    }

    if ($end === null) {
        throw new RuntimeException('Outer @endif recovery tidak ditemukan.');
    }

    while (
        $end < strlen($source)
        && ($source[$end] === "\r" || $source[$end] === "\n")
    ) {
        $end++;
    }

    return substr($source, 0, $commentStart)
        .$replacement
        ."\n"
        .substr($source, $end);
}

$view = findViewV22($viewBase);

if (! $view) {
    fwrite(STDERR, "[FAIL] View scanner recovery V2.1 tidak ditemukan.\n");
    exit(1);
}

echo "Target view:\n{$view}\n\n";

$source = (string) file_get_contents($view);

if (str_contains($source, 'MISSING_ASSET_SCANNER_FOCUS_FIX_V2_2')) {
    echo "[OK] V2.2 sudah terpasang.\n";
    exit(0);
}

if (! str_contains($source, 'MISSING_ASSET_SCANNER_ONLY_V2_1')) {
    fwrite(STDERR, "[FAIL] V2.1 belum terpasang pada view target.\n");
    exit(1);
}

$backup = $view.'.bak-missing-asset-scanner-focus-v2_2-'.date('Ymd-His');

if (! copy($view, $backup)) {
    fwrite(STDERR, "[FAIL] Gagal membuat backup view.\n");
    exit(1);
}

echo "Backup:\n{$backup}\n\n";

$block = <<<'BLADE'
{{-- MISSING_ASSET_QR_RECOVERY_V2_UI --}}
{{-- MISSING_ASSET_SCANNER_FOCUS_FIX_V2_2 --}}
@if (strtolower((string) $asset->status) === 'missing')
    @php
        $crmMissingAllocation =
            \Illuminate\Support\Facades\DB::table(
                'delivery_order_inventory_allocations'
            )
                ->where(
                    'inventory_asset_id',
                    $asset->id
                )
                ->where(
                    'return_condition',
                    'missing'
                )
                ->orderByDesc('id')
                ->first();

        $crmMissingDeliveryOrder =
            $crmMissingAllocation
                ? \Illuminate\Support\Facades\DB::table(
                    'delivery_orders'
                )
                    ->where(
                        'id',
                        $crmMissingAllocation->delivery_order_id
                    )
                    ->first()
                : null;

        $crmMissingReference = null;

        if ($crmMissingDeliveryOrder) {
            $crmMissingDoArray = (array) $crmMissingDeliveryOrder;

            foreach ([
                'delivery_order_number',
                'do_number',
                'sj_number',
                'reference_number',
                'number',
            ] as $crmMissingRefColumn) {
                if (
                    array_key_exists(
                        $crmMissingRefColumn,
                        $crmMissingDoArray
                    )
                    && trim(
                        (string) $crmMissingDoArray[
                            $crmMissingRefColumn
                        ]
                    ) !== ''
                ) {
                    $crmMissingReference =
                        (string) $crmMissingDoArray[
                            $crmMissingRefColumn
                        ];

                    break;
                }
            }
        }

        if (
            ! $crmMissingReference
            && $crmMissingAllocation
        ) {
            $crmMissingReference =
                'DO-'.$crmMissingAllocation->delivery_order_id;
        }

        $crmScanPanelId =
            'crm-missing-scan-panel-'.$asset->id;

        $crmScanCaptureId =
            'crm-missing-scan-capture-'.$asset->id;

        $crmScanFormId =
            'crm-missing-scan-form-'.$asset->id;

        $crmScanValueId =
            'crm-missing-scan-value-'.$asset->id;

        $crmScanStatusId =
            'crm-missing-scan-status-'.$asset->id;

        $crmScanMessageId =
            'crm-missing-scan-message-'.$asset->id;
    @endphp

    <div
        id="{{ $crmScanPanelId }}"
        class="mb-6 rounded-lg border border-red-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        data-crm-missing-scanner="1"
    >
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <p class="text-base font-semibold text-gray-900 dark:text-white">
                    Missing Asset Recovery
                </p>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Recovery hanya melalui scan QR / barcode fisik asset.
                </p>
            </div>

            <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">
                MISSING
            </span>
        </div>

        <div class="mb-4 grid gap-2 rounded-md bg-gray-50 p-4 text-sm dark:bg-gray-950">
            <div>
                <span class="font-semibold">Asset:</span>
                {{ $asset->asset_code }}
            </div>

            @if ($crmMissingReference)
                <div>
                    <span class="font-semibold">Missing From:</span>
                    {{ $crmMissingReference }}
                </div>
            @endif

            @if ($crmMissingAllocation)
                <div>
                    <span class="font-semibold">Tracking:</span>
                    Return allocation #{{ $crmMissingAllocation->id }}
                </div>
            @endif
        </div>

        @if (session('missing_asset_scan_success'))
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
                ✓ {{ session('missing_asset_scan_success') }}
            </div>
        @endif

        @error('scan_code')
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                ✕ SCAN REJECTED: {{ $message }}
            </div>
        @enderror

        <div
            class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 px-5 py-8 text-center dark:border-gray-700 dark:bg-gray-950"
            aria-live="polite"
        >
            <div
                id="{{ $crmScanStatusId }}"
                class="text-sm font-bold tracking-wide text-brandColor"
            >
                WAITING FOR SCAN
            </div>

            <div
                id="{{ $crmScanMessageId }}"
                class="mt-2 text-sm text-gray-600 dark:text-gray-300"
            >
                Scanner aktif otomatis. Scan QR / barcode fisik tanpa klik apa pun.
            </div>

            <div class="mt-3 text-xs text-gray-500">
                Scanner harus mengirim karakter seperti keyboard. Enter di akhir scan didukung.
            </div>
        </div>

        {{-- Invisible keyboard-wedge capture. It has no name and is never submitted. --}}
        <input
            id="{{ $crmScanCaptureId }}"
            type="text"
            value=""
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            tabindex="-1"
            aria-hidden="true"
            style="
                position: fixed;
                left: -10000px;
                top: -10000px;
                width: 1px;
                height: 1px;
                opacity: 0;
                pointer-events: none;
            "
        />

        <form
            id="{{ $crmScanFormId }}"
            method="POST"
            action="{{ route('admin.inventory.assets.missing-recover-scan', $asset->id) }}"
            class="hidden"
            aria-hidden="true"
        >
            @csrf

            <input
                id="{{ $crmScanValueId }}"
                type="hidden"
                name="scan_code"
                value=""
            />
        </form>
    </div>

    <script>
        (() => {
            const bootMissingScannerV22 = () => {
                const panel =
                    document.getElementById(
                        @json($crmScanPanelId)
                    );

                if (
                    ! panel
                    || panel.dataset.scannerV22Ready === '1'
                ) {
                    return;
                }

                panel.dataset.scannerV22Ready =
                    '1';

                const capture =
                    document.getElementById(
                        @json($crmScanCaptureId)
                    );

                const form =
                    document.getElementById(
                        @json($crmScanFormId)
                    );

                const hiddenValue =
                    document.getElementById(
                        @json($crmScanValueId)
                    );

                const status =
                    document.getElementById(
                        @json($crmScanStatusId)
                    );

                const message =
                    document.getElementById(
                        @json($crmScanMessageId)
                    );

                if (
                    ! capture
                    || ! form
                    || ! hiddenValue
                    || ! status
                    || ! message
                ) {
                    return;
                }

                /*
                 * Keyboard-wedge scanners need a focused input on some
                 * browsers/devices.  V2.1 only listened to document keydown;
                 * that can miss scans when the browser/device routes the scan
                 * to a focused form control. V2.2 gives the scanner a hidden
                 * focus target and keeps backend verification unchanged.
                 */
                const MAX_AVERAGE_MS = 90;
                const MAX_SINGLE_GAP_MS = 180;
                const IDLE_SUBMIT_MS = 260;
                const MIN_SCAN_LENGTH = 4;

                let buffer = '';
                let lastAt = 0;
                let intervals = [];
                let idleTimer = null;
                let submitting = false;

                const focusScanner = () => {
                    if (
                        submitting
                        || ! capture.isConnected
                    ) {
                        return;
                    }

                    try {
                        capture.focus({
                            preventScroll: true,
                        });
                    } catch (error) {
                        capture.focus();
                    }
                };

                const clearScan = () => {
                    buffer = '';
                    lastAt = 0;
                    intervals = [];
                    capture.value = '';

                    if (idleTimer) {
                        window.clearTimeout(
                            idleTimer
                        );

                        idleTimer = null;
                    }
                };

                const setWaiting = (
                    text = 'Scanner aktif otomatis. Scan QR / barcode fisik tanpa klik apa pun.'
                ) => {
                    status.textContent =
                        'WAITING FOR SCAN';

                    message.textContent =
                        text;

                    focusScanner();
                };

                const rejectSlowInput = () => {
                    clearScan();

                    status.textContent =
                        'SCAN NOT ACCEPTED';

                    message.textContent =
                        'Input tidak terlihat seperti burst dari scanner. Gunakan scanner QR / barcode fisik.';

                    window.setTimeout(
                        () => setWaiting(),
                        1600
                    );
                };

                const submitBuffer = () => {
                    if (submitting) {
                        return;
                    }

                    const value =
                        buffer.trim();

                    const average =
                        intervals.length > 0
                            ? intervals.reduce(
                                (sum, item) =>
                                    sum + item,
                                0
                            ) / intervals.length
                            : 0;

                    const maxGap =
                        intervals.length > 0
                            ? Math.max(
                                ...intervals
                            )
                            : 0;

                    if (
                        value.length < MIN_SCAN_LENGTH
                        || (
                            intervals.length > 0
                            && (
                                average > MAX_AVERAGE_MS
                                || maxGap > MAX_SINGLE_GAP_MS
                            )
                        )
                    ) {
                        rejectSlowInput();
                        return;
                    }

                    submitting = true;

                    hiddenValue.value =
                        value;

                    status.textContent =
                        'VERIFYING SCAN...';

                    message.textContent =
                        'Kode diterima. Memverifikasi dengan asset ini...';

                    /*
                     * Native form submission prevents any normal edit-form
                     * button from being involved. Backend still decides if
                     * the scanned QR is actually valid.
                     */
                    form.submit();
                };

                capture.addEventListener(
                    'keydown',
                    (event) => {
                        if (submitting) {
                            return;
                        }

                        const now =
                            performance.now();

                        if (event.key === 'Enter') {
                            event.preventDefault();

                            if (buffer !== '') {
                                submitBuffer();
                            }

                            return;
                        }

                        if (event.key === 'Tab') {
                            /*
                             * Some scanners use Tab as suffix.
                             */
                            event.preventDefault();

                            if (buffer !== '') {
                                submitBuffer();
                            }

                            return;
                        }

                        if (
                            event.ctrlKey
                            || event.altKey
                            || event.metaKey
                            || typeof event.key !== 'string'
                            || event.key.length !== 1
                        ) {
                            return;
                        }

                        if (lastAt > 0) {
                            intervals.push(
                                now - lastAt
                            );
                        }

                        lastAt = now;
                        buffer += event.key;

                        status.textContent =
                            'SCANNING...';

                        message.textContent =
                            'Membaca QR / barcode fisik...';

                        if (idleTimer) {
                            window.clearTimeout(
                                idleTimer
                            );
                        }

                        idleTimer =
                            window.setTimeout(
                                () => {
                                    if (
                                        buffer !== ''
                                        && ! submitting
                                    ) {
                                        submitBuffer();
                                    }
                                },
                                IDLE_SUBMIT_MS
                            );
                    }
                );

                capture.addEventListener(
                    'paste',
                    (event) => {
                        event.preventDefault();

                        clearScan();

                        status.textContent =
                            'PASTE DISABLED';

                        message.textContent =
                            'Gunakan scanner fisik.';

                        window.setTimeout(
                            () => setWaiting(),
                            1200
                        );
                    }
                );

                capture.addEventListener(
                    'blur',
                    () => {
                        /*
                         * Delay lets ordinary page lifecycle settle, then the
                         * scanner capture becomes ready again automatically.
                         */
                        window.setTimeout(
                            focusScanner,
                            80
                        );
                    }
                );

                window.addEventListener(
                    'focus',
                    () => {
                        window.setTimeout(
                            focusScanner,
                            60
                        );
                    }
                );

                /*
                 * Prevent ordinary clicks on the recovery panel from becoming
                 * a prerequisite.  Scanner focus is acquired automatically.
                 */
                document.addEventListener(
                    'visibilitychange',
                    () => {
                        if (
                            document.visibilityState === 'visible'
                        ) {
                            window.setTimeout(
                                focusScanner,
                                60
                            );
                        }
                    }
                );

                setWaiting();

                window.setTimeout(
                    focusScanner,
                    120
                );
            };

            if (
                document.readyState === 'loading'
            ) {
                document.addEventListener(
                    'DOMContentLoaded',
                    bootMissingScannerV22,
                    {
                        once: true,
                    }
                );
            } else {
                bootMissingScannerV22();
            }
        })();
    </script>
@endif
BLADE;

try {
    $patched = replaceRecoveryBlockV22(
        $source,
        'MISSING_ASSET_SCANNER_ONLY_V2_1',
        $block
    );

    if (file_put_contents($view, $patched) === false) {
        throw new RuntimeException('Gagal menulis view V2.2.');
    }

    /*
     * Add a dedicated success flash so the recovery result is explicit.
     */
    if (is_file($controller)) {
        $controllerSource = (string) file_get_contents($controller);

        if (
            str_contains($controllerSource, 'MISSING_ASSET_QR_RECOVERY_V2')
            && ! str_contains(
                $controllerSource,
                'missing_asset_scan_success'
            )
        ) {
            $controllerBackup =
                $controller
                .'.bak-missing-asset-scanner-focus-v2_2-'
                .date('Ymd-His');

            if (! copy($controller, $controllerBackup)) {
                throw new RuntimeException('Gagal backup QR recovery controller.');
            }

            $anchor = <<<'PHP_ANCHOR'
        session()->flash(
            'success',
            'QR benar. Asset ditemukan dan status kembali AVAILABLE.'
        );
PHP_ANCHOR;

            $replacement = <<<'PHP_REPLACEMENT'
        session()->flash(
            'success',
            'QR benar. Asset ditemukan dan status kembali AVAILABLE.'
        );

        session()->flash(
            'missing_asset_scan_success',
            'ASSET VERIFIED. Status MISSING → AVAILABLE dan movement recovery sudah dicatat.'
        );
PHP_REPLACEMENT;

            if (! str_contains($controllerSource, $anchor)) {
                throw new RuntimeException(
                    'Anchor success flash controller tidak ditemukan.'
                );
            }

            $controllerSource = str_replace(
                $anchor,
                $replacement,
                $controllerSource,
                $count
            );

            if ($count !== 1) {
                throw new RuntimeException(
                    'Replacement success flash controller tidak tepat satu kali.'
                );
            }

            if (file_put_contents($controller, $controllerSource) === false) {
                throw new RuntimeException('Gagal menulis controller.');
            }

            exec(
                escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($controller).' 2>&1',
                $lintOut,
                $lintCode
            );

            if ($lintCode !== 0) {
                @copy($controllerBackup, $controller);

                throw new RuntimeException(
                    "Controller lint gagal:\n".implode(PHP_EOL, $lintOut)
                );
            }

            echo "[OK] Explicit success feedback ditambahkan.\n";
        } else {
            echo "[OK] Controller feedback sudah tersedia / tidak perlu diubah.\n";
        }
    }

    exec(
        escapeshellarg(PHP_BINARY).' '
        .escapeshellarg($root.'/artisan')
        .' optimize:clear 2>&1',
        $clearOut,
        $clearCode
    );

    if ($clearCode !== 0) {
        throw new RuntimeException(
            "optimize:clear gagal:\n".implode(PHP_EOL, $clearOut)
        );
    }

    echo "[OK] Hidden scanner focus target dipasang.\n";
    echo "[OK] Auto-focus scanner aktif.\n";
    echo "[OK] Enter dan Tab suffix scanner didukung.\n";
    echo "[OK] Scan tanpa suffix diproses setelah idle gap.\n";
    echo "[OK] Slow manual typing guard tetap aktif.\n";
    echo "[OK] Scan error sekarang tampil jelas setelah reload.\n";
    echo "[OK] optimize:clear selesai.\n\n";
    echo "HASIL: PASS\n";
    echo "Lanjutkan:\n";
    echo "  php tools\\check_missing_asset_scanner_focus_fix_v2_2.php\n";
} catch (Throwable $e) {
    @copy($backup, $view);

    fwrite(
        STDERR,
        "\n[FAIL] ".$e->getMessage()."\n"
    );

    fwrite(
        STDERR,
        "View dipulihkan dari backup.\n"
    );

    exit(1);
}
