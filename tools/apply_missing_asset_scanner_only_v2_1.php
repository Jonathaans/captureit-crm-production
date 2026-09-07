<?php
declare(strict_types=1);

echo "MISSING ASSET SCANNER-ONLY V2.1\n";
echo "===============================\n\n";

$root = dirname(__DIR__);
chdir($root);

$routeName = 'admin.inventory.assets.missing-recover-scan';
$viewBase = $root.'/packages/Webkul/Admin/src/Resources/views/inventory';

function findAssetView(string $base): ?string
{
    if (! is_dir($base)) {
        return null;
    }

    $candidates = [
        $base.'/assets/edit.blade.php',
        $base.'/assets/show.blade.php',
        $base.'/asset/edit.blade.php',
        $base.'/asset/show.blade.php',
    ];

    foreach ($candidates as $candidate) {
        if (
            is_file($candidate)
            && str_contains(
                (string) file_get_contents($candidate),
                'MISSING_ASSET_QR_RECOVERY_V2_UI'
            )
        ) {
            return $candidate;
        }
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
            || ! str_ends_with(
                strtolower($file->getFilename()),
                '.blade.php'
            )
        ) {
            continue;
        }

        $text = @file_get_contents($file->getPathname());

        if (
            is_string($text)
            && str_contains(
                $text,
                'MISSING_ASSET_QR_RECOVERY_V2_UI'
            )
        ) {
            return $file->getPathname();
        }
    }

    return null;
}

function replaceMarkedBladeIfBlock(
    string $source,
    string $marker,
    string $replacement
): string {
    $markerPos = strpos($source, $marker);

    if ($markerPos === false) {
        throw new RuntimeException(
            "Marker {$marker} tidak ditemukan."
        );
    }

    $commentStart = strrpos(
        substr($source, 0, $markerPos),
        '{{--'
    );

    if ($commentStart === false) {
        $commentStart = $markerPos;
    }

    $firstIf = strpos($source, '@if', $markerPos);

    if ($firstIf === false) {
        throw new RuntimeException(
            'Pembuka @if recovery tidak ditemukan.'
        );
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
        throw new RuntimeException(
            'Penutup @endif recovery tidak ditemukan.'
        );
    }

    while (
        $end < strlen($source)
        && (
            $source[$end] === "\r"
            || $source[$end] === "\n"
        )
    ) {
        $end++;
    }

    return substr($source, 0, $commentStart)
        .$replacement
        ."\n"
        .substr($source, $end);
}

$view = findAssetView($viewBase);

if (! $view) {
    fwrite(
        STDERR,
        "[FAIL] View asset dengan MISSING_ASSET_QR_RECOVERY_V2_UI tidak ditemukan.\n"
    );
    exit(1);
}

echo "Target view:\n{$view}\n\n";

$source = (string) file_get_contents($view);

if (
    str_contains(
        $source,
        'MISSING_ASSET_SCANNER_ONLY_V2_1'
    )
) {
    echo "[OK] Scanner-only V2.1 sudah terpasang.\n";
    exit(0);
}

$backup =
    $view
    .'.bak-missing-asset-scanner-only-v2_1-'
    .date('Ymd-His');

if (! copy($view, $backup)) {
    fwrite(
        STDERR,
        "[FAIL] Gagal membuat backup view.\n"
    );
    exit(1);
}

echo "Backup:\n{$backup}\n\n";

$block = <<<'BLADE'
{{-- MISSING_ASSET_QR_RECOVERY_V2_UI --}}
{{-- MISSING_ASSET_SCANNER_ONLY_V2_1 --}}
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
            $crmMissingDoArray =
                (array) $crmMissingDeliveryOrder;

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
                'DO-'
                .$crmMissingAllocation->delivery_order_id;
        }

        $crmScanPanelId =
            'crm-missing-scan-panel-'
            .$asset->id;

        $crmScanFormId =
            'crm-missing-scan-form-'
            .$asset->id;

        $crmScanValueId =
            'crm-missing-scan-value-'
            .$asset->id;

        $crmScanStatusId =
            'crm-missing-scan-status-'
            .$asset->id;

        $crmScanMessageId =
            'crm-missing-scan-message-'
            .$asset->id;
    @endphp

    <div
        id="{{ $crmScanPanelId }}"
        class="mb-6 rounded-lg border border-red-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        data-crm-scanner-only="1"
    >
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <p class="text-base font-semibold text-gray-900 dark:text-white">
                    Missing Asset Recovery
                </p>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Recovery hanya dilakukan melalui scan QR / barcode fisik asset.
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
                Scan QR / barcode pada asset fisik. Tidak perlu klik kolom atau tombol.
            </div>

            <div class="mt-3 text-xs text-gray-500">
                Input manual dan paste tidak tersedia.
            </div>
        </div>

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
            const initScannerOnlyRecovery = () => {
                const panel = document.getElementById(@json($crmScanPanelId));

                if (
                    ! panel
                    || panel.dataset.scannerReady === '1'
                ) {
                    return;
                }

                panel.dataset.scannerReady = '1';

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
                    ! form
                    || ! hiddenValue
                    || ! status
                    || ! message
                ) {
                    return;
                }

                /*
                 * USB / Bluetooth barcode scanners normally behave as a
                 * keyboard wedge and finish the scan with Enter.
                 *
                 * We keep no visible text box. Characters are buffered only
                 * while they arrive as a fast burst. Slow human typing is
                 * discarded and never submitted.
                 */
                const MAX_INTER_KEY_MS = 120;
                const RESET_GAP_MS = 260;
                const MIN_SCAN_LENGTH = 4;

                let buffer = '';
                let lastKeyAt = 0;
                let intervals = [];
                let submitting = false;
                let resetTimer = null;

                const setWaiting = (
                    text = 'Scan QR / barcode pada asset fisik. Tidak perlu klik kolom atau tombol.'
                ) => {
                    status.textContent =
                        'WAITING FOR SCAN';

                    message.textContent =
                        text;
                };

                const clearBuffer = () => {
                    buffer = '';
                    lastKeyAt = 0;
                    intervals = [];

                    if (resetTimer) {
                        window.clearTimeout(
                            resetTimer
                        );

                        resetTimer = null;
                    }
                };

                const rejectManual = () => {
                    status.textContent =
                        'WAITING FOR SCAN';

                    message.textContent =
                        'Input terlalu lambat untuk dianggap sebagai scan. Gunakan scanner QR / barcode fisik.';

                    clearBuffer();

                    window.setTimeout(
                        () => setWaiting(),
                        1800
                    );
                };

                const submitScan = () => {
                    if (submitting) {
                        return;
                    }

                    const value =
                        buffer.trim();

                    const averageInterval =
                        intervals.length > 0
                            ? intervals.reduce(
                                (total, interval) =>
                                    total + interval,
                                0
                            ) / intervals.length
                            : 0;

                    if (
                        value.length < MIN_SCAN_LENGTH
                        || (
                            intervals.length > 0
                            && averageInterval > MAX_INTER_KEY_MS
                        )
                    ) {
                        rejectManual();
                        return;
                    }

                    submitting = true;

                    hiddenValue.value =
                        value;

                    status.textContent =
                        'VERIFYING SCAN...';

                    message.textContent =
                        'Memverifikasi QR / barcode dengan asset ini.';

                    /*
                     * requestSubmit is intentionally not used because there
                     * is no visible submit control. Backend still validates
                     * the scanned code before changing inventory state.
                     */
                    form.submit();
                };

                document.addEventListener(
                    'paste',
                    (event) => {
                        if (
                            ! panel.isConnected
                            || submitting
                        ) {
                            return;
                        }

                        const target =
                            event.target;

                        if (
                            target
                            && (
                                target.matches?.(
                                    'input, textarea, [contenteditable="true"]'
                                )
                            )
                        ) {
                            return;
                        }

                        event.preventDefault();

                        status.textContent =
                            'PASTE DISABLED';

                        message.textContent =
                            'Recovery harus menggunakan scanner fisik.';

                        clearBuffer();

                        window.setTimeout(
                            () => setWaiting(),
                            1400
                        );
                    },
                    true
                );

                document.addEventListener(
                    'keydown',
                    (event) => {
                        if (
                            ! panel.isConnected
                            || submitting
                            || event.ctrlKey
                            || event.altKey
                            || event.metaKey
                        ) {
                            return;
                        }

                        /*
                         * Do not hijack normal editing elsewhere on the Asset
                         * form. Scanner mode is automatically active whenever
                         * focus is not inside an editable control.
                         */
                        const target =
                            event.target;

                        const editingElsewhere =
                            target
                            && target !== document.body
                            && (
                                target.matches?.(
                                    'input:not([type="hidden"]), textarea, select, [contenteditable="true"]'
                                )
                            );

                        if (editingElsewhere) {
                            return;
                        }

                        const now =
                            performance.now();

                        if (event.key === 'Escape') {
                            clearBuffer();
                            setWaiting();
                            return;
                        }

                        if (event.key === 'Enter') {
                            if (buffer !== '') {
                                event.preventDefault();
                                event.stopPropagation();
                                submitScan();
                            }

                            return;
                        }

                        if (
                            typeof event.key !== 'string'
                            || event.key.length !== 1
                        ) {
                            return;
                        }

                        if (
                            lastKeyAt > 0
                            && now - lastKeyAt > RESET_GAP_MS
                        ) {
                            clearBuffer();
                        }

                        if (lastKeyAt > 0) {
                            intervals.push(
                                now - lastKeyAt
                            );
                        }

                        lastKeyAt = now;
                        buffer += event.key;

                        event.preventDefault();
                        event.stopPropagation();

                        status.textContent =
                            'SCANNING...';

                        message.textContent =
                            'Membaca QR / barcode fisik.';

                        if (resetTimer) {
                            window.clearTimeout(
                                resetTimer
                            );
                        }

                        resetTimer =
                            window.setTimeout(
                                () => {
                                    /*
                                     * Most scanners send Enter. If a scanner
                                     * is configured without Enter, the burst
                                     * is still submitted after a short idle
                                     * gap.
                                     */
                                    if (
                                        buffer !== ''
                                        && ! submitting
                                    ) {
                                        submitScan();
                                    }
                                },
                                RESET_GAP_MS
                            );
                    },
                    true
                );

                /*
                 * Start in scanner mode without focusing any visible input.
                 */
                if (
                    document.activeElement
                    && document.activeElement !== document.body
                    && ! document.activeElement.matches?.(
                        'input, textarea, select, [contenteditable="true"]'
                    )
                ) {
                    document.activeElement.blur?.();
                }

                setWaiting();
            };

            if (
                document.readyState === 'loading'
            ) {
                document.addEventListener(
                    'DOMContentLoaded',
                    initScannerOnlyRecovery,
                    {
                        once: true,
                    }
                );
            } else {
                initScannerOnlyRecovery();
            }
        })();
    </script>
@endif
BLADE;

try {
    $patched = replaceMarkedBladeIfBlock(
        $source,
        'MISSING_ASSET_QR_RECOVERY_V2_UI',
        $block
    );

    if (file_put_contents($view, $patched) === false) {
        throw new RuntimeException(
            'Gagal menulis view.'
        );
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
            "optimize:clear gagal:\n"
            .implode(PHP_EOL, $clearOut)
        );
    }

    exec(
        escapeshellarg(PHP_BINARY).' '
        .escapeshellarg($root.'/artisan')
        .' route:list --name='.escapeshellarg($routeName).' 2>&1',
        $routeOut,
        $routeCode
    );

    if (
        $routeCode !== 0
        || ! str_contains(
            implode("\n", $routeOut),
            $routeName
        )
    ) {
        throw new RuntimeException(
            'Route backend QR recovery V2 tidak aktif.'
        );
    }

    echo "[OK] Visible barcode field dihapus.\n";
    echo "[OK] Tombol Verify & Mark Found dihapus.\n";
    echo "[OK] Mode WAITING FOR SCAN dipasang.\n";
    echo "[OK] Scanner auto-submit setelah Enter / scan burst.\n";
    echo "[OK] Slow manual typing ditolak oleh scan listener.\n";
    echo "[OK] Backend V2 tetap melakukan verifikasi kode.\n";
    echo "[OK] optimize:clear selesai.\n\n";
    echo "HASIL: PASS\n";
    echo "Lanjutkan:\n";
    echo "  php tools\\check_missing_asset_scanner_only_v2_1.php\n";
} catch (Throwable $e) {
    @copy($backup, $view);

    fwrite(
        STDERR,
        "\n[FAIL] "
        .$e->getMessage()
        ."\n"
    );

    fwrite(
        STDERR,
        "View dipulihkan dari backup.\n"
    );

    exit(1);
}
