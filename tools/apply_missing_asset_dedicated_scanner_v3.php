<?php
declare(strict_types=1);

echo "MISSING ASSET DEDICATED SCANNER V3\n";
echo "==================================\n\n";

$root = dirname(__DIR__);
chdir($root);

$routes = $root.'/routes/web.php';
$editView = $root.'/packages/Webkul/Admin/src/Resources/views/inventory/assets/edit.blade.php';
$pageController = $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/MissingAssetRecoveryScannerPageController.php';
$scanView = $root.'/packages/Webkul/Admin/src/Resources/views/inventory/assets/missing-recovery-scan.blade.php';
$movementGrid = $root.'/packages/Webkul/Admin/src/DataGrids/Inventory/InventoryMovementDataGrid.php';

$backups = [];
$newFiles = [];

function backupV3(string $file): string
{
    $backup = $file.'.bak-missing-asset-dedicated-scanner-v3-'.date('Ymd-His');

    if (! copy($file, $backup)) {
        throw new RuntimeException("Gagal backup {$file}");
    }

    return $backup;
}

function writeV3(string $file, string $content): void
{
    $dir = dirname($file);

    if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
        throw new RuntimeException("Gagal membuat folder {$dir}");
    }

    if (file_put_contents($file, $content) === false) {
        throw new RuntimeException("Gagal menulis {$file}");
    }
}

function lintV3(string $file): void
{
    exec(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1',
        $out,
        $code
    );

    if ($code !== 0) {
        throw new RuntimeException(
            "PHP lint gagal: {$file}\n".implode(PHP_EOL, $out)
        );
    }
}

function replaceRecoveryBlockV3(
    string $source,
    string $replacement
): string {
    $markers = [
        'MISSING_ASSET_SCANNER_GLOBAL_CAPTURE_V2_3',
        'MISSING_ASSET_SCANNER_FOCUS_FIX_V2_2',
        'MISSING_ASSET_SCANNER_ONLY_V2_1',
        'MISSING_ASSET_QR_RECOVERY_V2_UI',
    ];

    $markerPos = false;

    foreach ($markers as $marker) {
        $pos = strpos($source, $marker);

        if ($pos !== false) {
            $markerPos = $pos;
            break;
        }
    }

    if ($markerPos === false) {
        throw new RuntimeException(
            'Block Missing Asset Recovery lama tidak ditemukan di edit.blade.php.'
        );
    }

    /*
     * Duplicate comments from the previous experiments exist immediately
     * above the outer @if. Walk back through adjacent Blade comments.
     */
    $firstIf = strpos($source, '@if', $markerPos);

    if ($firstIf === false) {
        throw new RuntimeException('Outer @if recovery tidak ditemukan.');
    }

    $commentStart = $markerPos;

    while (true) {
        $candidate = strrpos(
            substr($source, 0, $commentStart),
            '{{--'
        );

        if ($candidate === false) {
            break;
        }

        $between = substr(
            $source,
            $candidate,
            $commentStart - $candidate
        );

        if (
            str_contains($between, 'MISSING_ASSET_')
            && trim(
                preg_replace('/\{\{--.*?--\}\}/s', '', $between) ?? ''
            ) === ''
        ) {
            $commentStart = $candidate;
            continue;
        }

        break;
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

try {
    foreach ([$routes, $editView] as $required) {
        if (! is_file($required)) {
            throw new RuntimeException("File wajib tidak ditemukan: {$required}");
        }
    }

    /*
     * --------------------------------------------------------------
     * 1. Dedicated scanner page controller
     * --------------------------------------------------------------
     */
    $controllerSource = <<<'PHP_CONTROLLER'
<?php

namespace Webkul\Admin\Http\Controllers\Inventory;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * MISSING_ASSET_DEDICATED_SCANNER_V3
 *
 * Scanner page intentionally mirrors the warehouse pattern:
 * one dedicated page, one autofocus scanner input, one POST endpoint.
 */
class MissingAssetRecoveryScannerPageController extends Controller
{
    public function __invoke(int $id): View|RedirectResponse
    {
        $asset = DB::table('inventory_assets')
            ->where('id', $id)
            ->first();

        abort_unless($asset, 404);

        if (
            strtolower((string) $asset->status)
            !== 'missing'
        ) {
            if (Route::has('admin.inventory.assets.edit')) {
                return redirect()
                    ->route(
                        'admin.inventory.assets.edit',
                        $asset->id
                    )
                    ->with(
                        'success',
                        'Asset sudah tidak berstatus MISSING.'
                    );
            }

            return redirect(
                '/admin/inventory/assets/'
                .$asset->id
                .'/edit'
            );
        }

        $missingAllocation = DB::table(
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

        $deliveryOrder = null;
        $referenceNumber = null;

        if ($missingAllocation) {
            $deliveryOrder = DB::table(
                'delivery_orders'
            )
                ->where(
                    'id',
                    $missingAllocation->delivery_order_id
                )
                ->first();

            if ($deliveryOrder) {
                $row = (array) $deliveryOrder;

                foreach ([
                    'delivery_order_number',
                    'do_number',
                    'sj_number',
                    'reference_number',
                    'number',
                ] as $column) {
                    if (
                        array_key_exists($column, $row)
                        && trim((string) $row[$column]) !== ''
                    ) {
                        $referenceNumber =
                            (string) $row[$column];

                        break;
                    }
                }
            }

            if (! $referenceNumber) {
                $referenceNumber =
                    'DO-'
                    .$missingAllocation->delivery_order_id;
            }
        }

        return view(
            'admin::inventory.assets.missing-recovery-scan',
            compact(
                'asset',
                'missingAllocation',
                'deliveryOrder',
                'referenceNumber'
            )
        );
    }
}
PHP_CONTROLLER;

    if (! is_file($pageController)) {
        writeV3($pageController, $controllerSource);
        $newFiles[] = $pageController;
        lintV3($pageController);

        echo "[OK] Dedicated scanner page controller dibuat.\n";
    } else {
        $existing = (string) file_get_contents($pageController);

        if (! str_contains(
            $existing,
            'MISSING_ASSET_DEDICATED_SCANNER_V3'
        )) {
            throw new RuntimeException(
                'Scanner page controller sudah ada tetapi bukan milik V3.'
            );
        }

        lintV3($pageController);
        echo "[OK] Dedicated scanner page controller sudah ada.\n";
    }

    /*
     * --------------------------------------------------------------
     * 2. GET route for dedicated scanner
     * --------------------------------------------------------------
     */
    $routeSource = (string) file_get_contents($routes);

    if (! str_contains(
        $routeSource,
        'admin.inventory.assets.missing-recovery.scanner'
    )) {
        $backups[$routes] = backupV3($routes);

        $routeBlock = <<<'PHP_ROUTE'


/*
|--------------------------------------------------------------------------
| MISSING_ASSET_DEDICATED_SCANNER_V3
|--------------------------------------------------------------------------
| Dedicated scanner page. POST verification still uses the hardened V2
| missing-recover-scan backend.
*/
\Illuminate\Support\Facades\Route::get(
    'admin/inventory/assets/{id}/missing-recovery-scanner',
    \Webkul\Admin\Http\Controllers\Inventory\MissingAssetRecoveryScannerPageController::class
)
    ->middleware([
        'web',
        'admin_locale',
        'user',
    ])
    ->name('admin.inventory.assets.missing-recovery.scanner');
PHP_ROUTE;

        if (preg_match('/\?>\s*$/', $routeSource) === 1) {
            $routeSource = preg_replace(
                '/\?>\s*$/',
                $routeBlock."\n?>",
                $routeSource,
                1
            );
        } else {
            $routeSource = rtrim($routeSource).$routeBlock."\n";
        }

        writeV3($routes, $routeSource);
        lintV3($routes);

        echo "[OK] Dedicated scanner GET route ditambahkan.\n";
    } else {
        echo "[OK] Dedicated scanner GET route sudah ada.\n";
    }

    /*
     * --------------------------------------------------------------
     * 3. Dedicated scanner Blade
     * --------------------------------------------------------------
     */
    $scannerView = <<<'BLADE'
<x-admin::layouts>
    <x-slot:title>
        Missing Asset Recovery
    </x-slot>

    {{-- MISSING_ASSET_DEDICATED_SCANNER_V3_VIEW --}}
    <div class="flex flex-col gap-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <a
                    href="{{ route('admin.inventory.assets.edit', $asset->id) }}"
                    class="text-sm text-gray-600 hover:text-brandColor dark:text-gray-300"
                >
                    &larr; Back to Asset
                </a>

                <p class="mt-2 text-xl font-bold text-gray-800 dark:text-white">
                    Missing Asset Recovery
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    Dedicated scanner mode. Sama seperti workflow warehouse: scanner diarahkan ke satu input aktif.
                </p>
            </div>

            <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">
                MISSING
            </span>
        </div>

        @error('scan_code')
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                ✕ SCAN REJECTED: {{ $message }}
            </div>
        @enderror

        <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <div class="grid gap-2 text-sm">
                <div>
                    <span class="font-semibold">Asset:</span>
                    {{ $asset->asset_code }}
                </div>

                @if ($asset->serial_number)
                    <div>
                        <span class="font-semibold">Serial:</span>
                        {{ $asset->serial_number }}
                    </div>
                @endif

                @if ($referenceNumber)
                    <div>
                        <span class="font-semibold">Missing From:</span>
                        {{ $referenceNumber }}
                    </div>
                @endif

                @if ($missingAllocation)
                    <div>
                        <span class="font-semibold">Tracking:</span>
                        Return allocation #{{ $missingAllocation->id }}
                    </div>
                @endif
            </div>
        </div>

        <div
            id="crm-recovery-scan-panel"
            class="rounded-lg border-2 border-dashed border-brandColor bg-white px-6 py-12 text-center shadow-sm dark:bg-gray-900"
            aria-live="polite"
        >
            <div
                id="crm-recovery-scan-status"
                class="text-lg font-bold tracking-wide text-brandColor"
            >
                WAITING FOR SCAN
            </div>

            <div
                id="crm-recovery-scan-message"
                class="mt-2 text-sm text-gray-500"
            >
                Scan QR / barcode fisik sekarang. Tidak perlu klik field.
            </div>

            <div class="mt-3 text-xs text-gray-400">
                QR salah tidak mengubah status inventory.
            </div>
        </div>

        <form
            id="crm-recovery-scan-form"
            method="POST"
            action="{{ route('admin.inventory.assets.missing-recover-scan', $asset->id) }}"
        >
            @csrf

            <!--
                This is deliberately a REAL text input with autofocus.
                It is visually off-screen, not display:none/hidden/readonly,
                because keyboard-wedge scanners need an actual focused input.
            -->
            <input
                id="crm-recovery-scan-input"
                type="text"
                name="scan_code"
                value=""
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
                autofocus
                aria-label="Scanner input"
                style="
                    position: fixed;
                    left: -10000px;
                    top: 0;
                    width: 2px;
                    height: 2px;
                    opacity: 0.01;
                "
            />
        </form>
    </div>

    @push('scripts')
        <script>
            (() => {
                const input =
                    document.getElementById(
                        'crm-recovery-scan-input'
                    );

                const form =
                    document.getElementById(
                        'crm-recovery-scan-form'
                    );

                const status =
                    document.getElementById(
                        'crm-recovery-scan-status'
                    );

                const message =
                    document.getElementById(
                        'crm-recovery-scan-message'
                    );

                if (
                    ! input
                    || ! form
                    || ! status
                    || ! message
                ) {
                    return;
                }

                let idleTimer = null;
                let submitting = false;

                const focusScanner = () => {
                    if (submitting) {
                        return;
                    }

                    try {
                        input.focus({
                            preventScroll: true,
                        });
                    } catch (error) {
                        input.focus();
                    }
                };

                /*
                 * Manage-allocation style:
                 * scanner types into one real focused input.
                 * No global keydown interception.
                 */
                input.addEventListener(
                    'input',
                    () => {
                        if (submitting) {
                            return;
                        }

                        const value =
                            input.value.trim();

                        status.textContent =
                            value === ''
                                ? 'WAITING FOR SCAN'
                                : 'SCANNING...';

                        message.textContent =
                            value === ''
                                ? 'Scan QR / barcode fisik sekarang.'
                                : 'Kode scanner diterima...';

                        if (idleTimer) {
                            window.clearTimeout(
                                idleTimer
                            );
                        }

                        /*
                         * Native Enter from the scanner submits the form.
                         * This idle fallback also supports scanners configured
                         * without Enter/Tab suffix.
                         */
                        if (value.length >= 4) {
                            idleTimer =
                                window.setTimeout(
                                    () => {
                                        if (
                                            ! submitting
                                            && input.value.trim().length >= 4
                                        ) {
                                            submitting = true;

                                            status.textContent =
                                                'VERIFYING SCAN...';

                                            message.textContent =
                                                'Memverifikasi QR / barcode dengan asset ini...';

                                            form.requestSubmit();
                                        }
                                    },
                                    350
                                );
                        }
                    }
                );

                form.addEventListener(
                    'submit',
                    () => {
                        submitting = true;

                        status.textContent =
                            'VERIFYING SCAN...';

                        message.textContent =
                            'Memverifikasi QR / barcode dengan asset ini...';
                    }
                );

                input.addEventListener(
                    'paste',
                    (event) => {
                        event.preventDefault();

                        input.value = '';

                        status.textContent =
                            'PASTE DISABLED';

                        message.textContent =
                            'Gunakan scanner QR / barcode fisik.';

                        window.setTimeout(
                            () => {
                                status.textContent =
                                    'WAITING FOR SCAN';

                                message.textContent =
                                    'Scan QR / barcode fisik sekarang.';

                                focusScanner();
                            },
                            1200
                        );
                    }
                );

                input.addEventListener(
                    'blur',
                    () => {
                        window.setTimeout(
                            focusScanner,
                            50
                        );
                    }
                );

                window.addEventListener(
                    'focus',
                    () => {
                        window.setTimeout(
                            focusScanner,
                            50
                        );
                    }
                );

                document.addEventListener(
                    'visibilitychange',
                    () => {
                        if (
                            document.visibilityState === 'visible'
                        ) {
                            window.setTimeout(
                                focusScanner,
                                50
                            );
                        }
                    }
                );

                /*
                 * Reassert focus because this page has one job only:
                 * wait for a physical scanner.
                 */
                window.setInterval(
                    () => {
                        if (
                            ! submitting
                            && document.activeElement !== input
                        ) {
                            focusScanner();
                        }
                    },
                    500
                );

                window.setTimeout(
                    focusScanner,
                    60
                );
            })();
        </script>
    @endpush
</x-admin::layouts>
BLADE;

    if (! is_file($scanView)) {
        writeV3($scanView, $scannerView);
        $newFiles[] = $scanView;

        echo "[OK] Dedicated scanner Blade dibuat.\n";
    } else {
        $existing = (string) file_get_contents($scanView);

        if (! str_contains(
            $existing,
            'MISSING_ASSET_DEDICATED_SCANNER_V3_VIEW'
        )) {
            throw new RuntimeException(
                'missing-recovery-scan.blade.php sudah ada tetapi bukan milik V3.'
            );
        }

        echo "[OK] Dedicated scanner Blade sudah ada.\n";
    }

    /*
     * --------------------------------------------------------------
     * 4. Replace failed scanner experiments on Edit Asset with
     *    one simple "Open Recovery Scanner" action.
     * --------------------------------------------------------------
     */
    $editSource = (string) file_get_contents($editView);

    if (! str_contains(
        $editSource,
        'MISSING_ASSET_DEDICATED_SCANNER_V3_LINK'
    )) {
        $backups[$editView] = backupV3($editView);

        $linkBlock = <<<'BLADE'
    {{-- MISSING_ASSET_DEDICATED_SCANNER_V3_LINK --}}
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

            $crmMissingReference = null;

            if ($crmMissingAllocation) {
                $crmMissingDo =
                    \Illuminate\Support\Facades\DB::table(
                        'delivery_orders'
                    )
                        ->where(
                            'id',
                            $crmMissingAllocation->delivery_order_id
                        )
                        ->first();

                if ($crmMissingDo) {
                    $crmMissingDoArray =
                        (array) $crmMissingDo;

                    foreach ([
                        'delivery_order_number',
                        'do_number',
                        'sj_number',
                        'reference_number',
                        'number',
                    ] as $crmRefColumn) {
                        if (
                            isset(
                                $crmMissingDoArray[
                                    $crmRefColumn
                                ]
                            )
                            && trim(
                                (string) $crmMissingDoArray[
                                    $crmRefColumn
                                ]
                            ) !== ''
                        ) {
                            $crmMissingReference =
                                (string) $crmMissingDoArray[
                                    $crmRefColumn
                                ];

                            break;
                        }
                    }
                }

                if (! $crmMissingReference) {
                    $crmMissingReference =
                        'DO-'
                        .$crmMissingAllocation->delivery_order_id;
                }
            }
        @endphp

        <div class="mb-6 rounded-lg border border-red-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                        Missing Asset Recovery
                    </p>

                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Asset {{ $asset->asset_code }} masih MISSING.
                        @if ($crmMissingReference)
                            Tracking: {{ $crmMissingReference }}.
                        @endif
                    </p>

                    <p class="mt-1 text-xs text-gray-500">
                        Gunakan dedicated scanner page seperti workflow Manage Allocation.
                    </p>
                </div>

                <a
                    href="{{ route('admin.inventory.assets.missing-recovery.scanner', $asset->id) }}"
                    class="primary-button"
                >
                    Open Recovery Scanner
                </a>
            </div>
        </div>
    @endif
BLADE;

        $editSource = replaceRecoveryBlockV3(
            $editSource,
            $linkBlock
        );

        writeV3($editView, $editSource);

        echo "[OK] Eksperimen scanner V2.1-V2.3 dibersihkan dari Edit Asset.\n";
        echo "[OK] Edit Asset sekarang hanya membuka dedicated scanner page.\n";
    } else {
        echo "[OK] Link dedicated scanner sudah ada.\n";
    }

    /*
     * --------------------------------------------------------------
     * 5. Clean duplicate Missing Recovered movement filter option.
     * --------------------------------------------------------------
     */
    if (is_file($movementGrid)) {
        $grid = (string) file_get_contents($movementGrid);

        $duplicateNeedle =
            "['label' => 'Missing Recovered', 'value' => 'missing_recovered'],";

        if (substr_count($grid, $duplicateNeedle) > 1) {
            $backups[$movementGrid] = backupV3($movementGrid);

            $seen = false;
            $lines = preg_split('/\R/', $grid) ?: [];
            $clean = [];

            foreach ($lines as $line) {
                if (str_contains($line, $duplicateNeedle)) {
                    if ($seen) {
                        continue;
                    }

                    $seen = true;
                }

                if (
                    str_contains(
                        $line,
                        'MISSING_ASSET_RECOVERY_V1_MOVEMENT'
                    )
                    || str_contains(
                        $line,
                        'MISSING_ASSET_QR_RECOVERY_V2_MOVEMENT'
                    )
                ) {
                    continue;
                }

                $clean[] = $line;
            }

            writeV3(
                $movementGrid,
                implode(PHP_EOL, $clean).PHP_EOL
            );

            lintV3($movementGrid);

            echo "[OK] Duplicate Missing Recovered movement filter dibersihkan.\n";
        }
    }

    /*
     * --------------------------------------------------------------
     * 6. Cache + route verification
     * --------------------------------------------------------------
     */
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
        .' route:list --name=admin.inventory.assets.missing-recovery.scanner 2>&1',
        $getRouteOut,
        $getRouteCode
    );

    if (
        $getRouteCode !== 0
        || ! str_contains(
            implode("\n", $getRouteOut),
            'admin.inventory.assets.missing-recovery.scanner'
        )
    ) {
        throw new RuntimeException(
            'Dedicated scanner GET route tidak aktif.'
        );
    }

    exec(
        escapeshellarg(PHP_BINARY).' '
        .escapeshellarg($root.'/artisan')
        .' route:list --name=admin.inventory.assets.missing-recover-scan 2>&1',
        $postRouteOut,
        $postRouteCode
    );

    if (
        $postRouteCode !== 0
        || ! str_contains(
            implode("\n", $postRouteOut),
            'admin.inventory.assets.missing-recover-scan'
        )
    ) {
        throw new RuntimeException(
            'Backend POST QR verification V2 tidak aktif.'
        );
    }

    echo "[OK] Cache dibersihkan.\n";
    echo "[OK] Dedicated scanner GET route aktif.\n";
    echo "[OK] Existing hardened QR POST verification tetap aktif.\n\n";
    echo "HASIL: PASS\n";
    echo "Lanjutkan:\n";
    echo "  php tools\\check_missing_asset_dedicated_scanner_v3.php\n";
} catch (Throwable $e) {
    fwrite(
        STDERR,
        "\n[FAIL] ".$e->getMessage()."\n"
    );

    foreach (
        array_reverse($backups, true)
        as $original => $backup
    ) {
        if (is_file($backup)) {
            @copy($backup, $original);

            fwrite(
                STDERR,
                "Rollback: {$original}\n"
            );
        }
    }

    foreach ($newFiles as $file) {
        if (is_file($file)) {
            @unlink($file);

            fwrite(
                STDERR,
                "Rollback: hapus {$file}\n"
            );
        }
    }

    exit(1);
}
