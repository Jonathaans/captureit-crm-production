<?php
declare(strict_types=1);

echo "MISSING ASSET SCANNER GLOBAL CAPTURE V2.3\n";
echo "=========================================\n\n";

$root = dirname(__DIR__);
chdir($root);

$viewBase = $root.'/packages/Webkul/Admin/src/Resources/views/inventory';

function findViewV23(string $base): ?string
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
                str_contains($text, 'MISSING_ASSET_SCANNER_FOCUS_FIX_V2_2')
                || str_contains($text, 'MISSING_ASSET_SCANNER_GLOBAL_CAPTURE_V2_3')
            )
        ) {
            return $file->getPathname();
        }
    }

    return null;
}

function replaceRecoveryBlockV23(
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

$view = findViewV23($viewBase);

if (! $view) {
    fwrite(STDERR, "[FAIL] View scanner V2.2 tidak ditemukan.\n");
    exit(1);
}

echo "Target view:\n{$view}\n\n";

$source = (string) file_get_contents($view);

if (str_contains($source, 'MISSING_ASSET_SCANNER_GLOBAL_CAPTURE_V2_3')) {
    echo "[OK] V2.3 sudah terpasang.\n";
    exit(0);
}

if (! str_contains($source, 'MISSING_ASSET_SCANNER_FOCUS_FIX_V2_2')) {
    fwrite(STDERR, "[FAIL] V2.2 belum terpasang pada view target.\n");
    exit(1);
}

$backup =
    $view
    .'.bak-missing-asset-scanner-global-v2_3-'
    .date('Ymd-His');

if (! copy($view, $backup)) {
    fwrite(STDERR, "[FAIL] Gagal membuat backup view.\n");
    exit(1);
}

echo "Backup:\n{$backup}\n\n";

$block = <<<'BLADE'
{{-- MISSING_ASSET_QR_RECOVERY_V2_UI --}}
{{-- MISSING_ASSET_SCANNER_GLOBAL_CAPTURE_V2_3 --}}
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
                Scan QR / barcode fisik. Tidak perlu klik kolom atau tombol.
            </div>

            <div class="mt-3 text-xs text-gray-500">
                Scanner dibaca dari keyboard event global halaman ini.
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
            const bootMissingScannerV23 = () => {
                const panel =
                    document.getElementById(
                        @json($crmScanPanelId)
                    );

                if (
                    ! panel
                    || panel.dataset.scannerV23Ready === '1'
                ) {
                    return;
                }

                panel.dataset.scannerV23Ready =
                    '1';

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
                 * V2.3 no longer depends on focus.
                 * Capture keyboard events at document level before links,
                 * buttons, or form controls can swallow scanner input.
                 */
                const MAX_AVERAGE_MS = 95;
                const MAX_SINGLE_GAP_MS = 190;
                const IDLE_SUBMIT_MS = 280;
                const MIN_SCAN_LENGTH = 4;

                let buffer = '';
                let lastAt = 0;
                let intervals = [];
                let idleTimer = null;
                let submitting = false;

                const resetScan = () => {
                    buffer = '';
                    lastAt = 0;
                    intervals = [];

                    if (idleTimer) {
                        window.clearTimeout(
                            idleTimer
                        );

                        idleTimer = null;
                    }
                };

                const setWaiting = (
                    text = 'Scan QR / barcode fisik. Tidak perlu klik kolom atau tombol.'
                ) => {
                    status.textContent =
                        'WAITING FOR SCAN';

                    message.textContent =
                        text;
                };

                const rejectSlow = () => {
                    resetScan();

                    status.textContent =
                        'SCAN NOT ACCEPTED';

                    message.textContent =
                        'Input tidak terlihat seperti burst dari scanner.';

                    window.setTimeout(
                        () => setWaiting(),
                        1500
                    );
                };

                const submitScan = () => {
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
                        rejectSlow();
                        return;
                    }

                    submitting = true;

                    hiddenValue.value =
                        value;

                    status.textContent =
                        'VERIFYING SCAN...';

                    message.textContent =
                        'Kode diterima. Memverifikasi dengan asset ini...';

                    form.submit();
                };

                document.addEventListener(
                    'keydown',
                    (event) => {
                        if (
                            submitting
                            || event.ctrlKey
                            || event.altKey
                            || event.metaKey
                        ) {
                            return;
                        }

                        const now =
                            performance.now();

                        if (
                            event.key === 'Enter'
                            || event.key === 'Tab'
                        ) {
                            if (buffer !== '') {
                                event.preventDefault();
                                event.stopImmediatePropagation();
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
                            lastAt > 0
                            && now - lastAt > 450
                        ) {
                            resetScan();
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

                        /*
                         * Prevent scanner burst from typing into whatever
                         * element currently owns focus.
                         */
                        event.preventDefault();
                        event.stopImmediatePropagation();

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
                                        submitScan();
                                    }
                                },
                                IDLE_SUBMIT_MS
                            );
                    },
                    true
                );

                document.addEventListener(
                    'paste',
                    (event) => {
                        if (submitting) {
                            return;
                        }

                        event.preventDefault();
                        event.stopImmediatePropagation();

                        resetScan();

                        status.textContent =
                            'PASTE DISABLED';

                        message.textContent =
                            'Gunakan scanner fisik.';

                        window.setTimeout(
                            () => setWaiting(),
                            1200
                        );
                    },
                    true
                );

                setWaiting();
            };

            if (document.readyState === 'loading') {
                document.addEventListener(
                    'DOMContentLoaded',
                    bootMissingScannerV23,
                    {
                        once: true,
                    }
                );
            } else {
                bootMissingScannerV23();
            }
        })();
    </script>
@endif
BLADE;

try {
    $patched = replaceRecoveryBlockV23(
        $source,
        'MISSING_ASSET_SCANNER_FOCUS_FIX_V2_2',
        $block
    );

    if (file_put_contents($view, $patched) === false) {
        throw new RuntimeException('Gagal menulis view V2.3.');
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

    echo "[OK] Ketergantungan focus scanner dihapus.\n";
    echo "[OK] Global document keydown capture dipasang.\n";
    echo "[OK] Event scanner ditangkap sebelum link/input lain.\n";
    echo "[OK] Enter / Tab suffix didukung.\n";
    echo "[OK] Scanner tanpa suffix tetap auto-submit.\n";
    echo "[OK] Backend QR validation tidak diubah.\n";
    echo "[OK] optimize:clear selesai.\n\n";
    echo "HASIL: PASS\n";
    echo "Lanjutkan:\n";
    echo "  php tools\\check_missing_asset_scanner_global_capture_v2_3.php\n";
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
