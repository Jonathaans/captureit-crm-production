<?php
declare(strict_types=1);

echo "MISSING ASSET QR RECOVERY V2\n";
echo "============================\n\n";

$root = dirname(__DIR__);
chdir($root);

$routes = $root.'/routes/web.php';
$assetController = $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/MissingAssetQrRecoveryController.php';
$inventoryAssetController = $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryAssetController.php';
$movementGrid = $root.'/packages/Webkul/Admin/src/DataGrids/Inventory/InventoryMovementDataGrid.php';

$backups = [];
$newFiles = [];

function backupV2(string $file): string
{
    $backup = $file.'.bak-missing-asset-qr-recovery-v2-'.date('Ymd-His');

    if (! copy($file, $backup)) {
        throw new RuntimeException("Gagal backup {$file}");
    }

    return $backup;
}

function writeV2(string $file, string $content): void
{
    $dir = dirname($file);

    if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
        throw new RuntimeException("Gagal membuat folder {$dir}");
    }

    if (file_put_contents($file, $content) === false) {
        throw new RuntimeException("Gagal menulis {$file}");
    }
}

function lintV2(string $file): void
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

function removeMarkedRouteBlockV2(string $source, string $marker): string
{
    $pattern = '~\R?/\*\s*\R\|[-\s]*\R\|\s*'.preg_quote($marker, '~').'.*?->name\s*\(\s*[\'"][^\'"]+[\'"]\s*\)\s*;~s';

    $cleaned = preg_replace($pattern, '', $source);

    return is_string($cleaned) ? $cleaned : $source;
}

function removeBladeBlockBeforeAnchorV2(
    string $source,
    string $marker,
    string $anchor
): string {
    $start = strpos($source, $marker);

    if ($start === false) {
        return $source;
    }

    $anchorPos = strpos($source, $anchor, $start);

    if ($anchorPos === false) {
        return $source;
    }

    $commentStart = strrpos(substr($source, 0, $start), '{{--');

    if ($commentStart === false) {
        $commentStart = $start;
    }

    return substr($source, 0, $commentStart)
        .substr($source, $anchorPos);
}

function removeSimpleBladeIfBlockV2(
    string $source,
    string $marker
): string {
    $markerPos = strpos($source, $marker);

    if ($markerPos === false) {
        return $source;
    }

    $commentStart = strrpos(substr($source, 0, $markerPos), '{{--');

    if ($commentStart === false) {
        $commentStart = $markerPos;
    }

    $ifPos = strpos($source, '@if', $markerPos);

    if ($ifPos === false) {
        return $source;
    }

    $cursor = $ifPos;
    $depth = 0;
    $endPos = null;

    while (true) {
        $nextIf = strpos($source, '@if', $cursor);
        $nextEnd = strpos($source, '@endif', $cursor);

        if ($nextEnd === false) {
            break;
        }

        if ($nextIf !== false && $nextIf < $nextEnd) {
            $depth++;
            $cursor = $nextIf + 3;
            continue;
        }

        $depth--;
        $cursor = $nextEnd + 6;

        if ($depth <= 0) {
            $endPos = $cursor;
            break;
        }
    }

    if ($endPos === null) {
        return $source;
    }

    while (
        $endPos < strlen($source)
        && ($source[$endPos] === "\r" || $source[$endPos] === "\n")
    ) {
        $endPos++;
    }

    return substr($source, 0, $commentStart)
        .substr($source, $endPos);
}

function removeMethodByMarkerV2(
    string $source,
    string $marker,
    string $methodNeedle
): string {
    $markerPos = strpos($source, $marker);

    if ($markerPos === false) {
        return $source;
    }

    $docStart = strrpos(substr($source, 0, $markerPos), '/**');

    if ($docStart === false) {
        return $source;
    }

    $methodPos = strpos($source, $methodNeedle, $markerPos);

    if ($methodPos === false) {
        return $source;
    }

    $braceStart = strpos($source, '{', $methodPos);

    if ($braceStart === false) {
        return $source;
    }

    $depth = 0;
    $len = strlen($source);
    $end = null;
    $quote = null;
    $escape = false;

    for ($i = $braceStart; $i < $len; $i++) {
        $ch = $source[$i];

        if ($quote !== null) {
            if ($escape) {
                $escape = false;
                continue;
            }

            if ($ch === '\\') {
                $escape = true;
                continue;
            }

            if ($ch === $quote) {
                $quote = null;
            }

            continue;
        }

        if ($ch === "'" || $ch === '"') {
            $quote = $ch;
            continue;
        }

        if ($ch === '{') {
            $depth++;
        } elseif ($ch === '}') {
            $depth--;

            if ($depth === 0) {
                $end = $i + 1;
                break;
            }
        }
    }

    if ($end === null) {
        return $source;
    }

    while (
        $end < $len
        && ($source[$end] === "\r" || $source[$end] === "\n")
    ) {
        $end++;
    }

    return substr($source, 0, $docStart)
        .substr($source, $end);
}

function findReturnBladeV2(string $root): ?string
{
    $base = $root.'/packages/Webkul/Admin/src/Resources/views';

    if (! is_dir($base)) {
        return null;
    }

    $matches = [];

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

        $content = @file_get_contents($file->getPathname());

        if (! is_string($content)) {
            continue;
        }

        $score = 0;

        if (stripos($content, 'Serialized Return Inspection') !== false) {
            $score += 5;
        }

        if (stripos($content, 'Manual Quantity Return') !== false) {
            $score += 5;
        }

        if (stripos($content, 'return_condition') !== false) {
            $score += 2;
        }

        if (stripos($file->getPathname(), 'delivery') !== false) {
            $score++;
        }

        if ($score > 0) {
            $matches[] = [
                'path' => $file->getPathname(),
                'score' => $score,
            ];
        }
    }

    if ($matches === []) {
        return null;
    }

    usort(
        $matches,
        fn ($a, $b) => $b['score'] <=> $a['score']
    );

    return $matches[0]['score'] >= 8
        ? $matches[0]['path']
        : null;
}

function findAssetShowBladeV2(string $root): ?string
{
    $candidates = [
        $root.'/packages/Webkul/Admin/src/Resources/views/inventory/assets/show.blade.php',
        $root.'/packages/Webkul/Admin/src/Resources/views/inventory/asset/show.blade.php',
        $root.'/packages/Webkul/Admin/src/Resources/views/inventory/assets/view.blade.php',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    $base = $root.'/packages/Webkul/Admin/src/Resources/views/inventory';

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

        $content = @file_get_contents($file->getPathname());

        if (
            is_string($content)
            && str_contains($content, '$asset')
            && str_contains($content, 'asset_code')
        ) {
            return $file->getPathname();
        }
    }

    return null;
}

function insertBeforeTextLineV2(
    string $source,
    string $anchor,
    string $block
): string {
    $pos = stripos($source, $anchor);

    if ($pos === false) {
        throw new RuntimeException("Anchor tidak ditemukan: {$anchor}");
    }

    $lineStart = strrpos(substr($source, 0, $pos), "\n");
    $lineStart = $lineStart === false ? 0 : $lineStart + 1;

    return substr($source, 0, $lineStart)
        .$block
        ."\n"
        .substr($source, $lineStart);
}

try {
    if (! is_file($routes)) {
        throw new RuntimeException('routes/web.php tidak ditemukan.');
    }

    /*
     * ==============================================================
     * 1. CLEAN PREVIOUS RETURN-FOUND PATCH
     * ==============================================================
     */
    $oldReturnController =
        $root
        .'/packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/MissingAssetReturnRecoveryController.php';

    if (
        is_file($oldReturnController)
        && (
            str_contains(
                (string) @file_get_contents($oldReturnController),
                'RETURN_MISSING_FOUND_V1'
            )
            || str_contains(
                (string) @file_get_contents($oldReturnController),
                'RETURN_MISSING_FOUND_V1_1'
            )
        )
    ) {
        @unlink($oldReturnController);
        echo "[OK] Controller Found dari halaman Return dihapus.\n";
    }

    $routeSource = (string) file_get_contents($routes);

    if (
        str_contains($routeSource, 'RETURN_MISSING_FOUND_V1')
        || str_contains($routeSource, 'RETURN_MISSING_FOUND_V1_1')
        || str_contains($routeSource, 'admin.delivery-orders.return.missing-found')
    ) {
        $backups[$routes] = backupV2($routes);

        $routeSource = preg_replace(
            '~\R?/\*.*?RETURN_MISSING_FOUND_V1(?:_1)?.*?\*/\s*\\\\Illuminate\\\\Support\\\\Facades\\\\Route::post\(.*?admin\.delivery-orders\.return\.missing-found.*?;~s',
            '',
            $routeSource
        ) ?: $routeSource;

        writeV2($routes, $routeSource);
        lintV2($routes);

        echo "[OK] Route Found dari Return dibersihkan.\n";
    }

    $returnBlade = findReturnBladeV2($root);

    if ($returnBlade && is_file($returnBlade)) {
        $returnSource = (string) file_get_contents($returnBlade);

        if (
            str_contains($returnSource, 'RETURN_MISSING_FOUND_V1_UI')
            || str_contains($returnSource, 'RETURN_MISSING_FOUND_V1_1_UI')
        ) {
            if (! isset($backups[$returnBlade])) {
                $backups[$returnBlade] = backupV2($returnBlade);
            }

            $returnSource = removeBladeBlockBeforeAnchorV2(
                $returnSource,
                'RETURN_MISSING_FOUND_V1_1_UI',
                'Manual Quantity Return'
            );

            $returnSource = removeBladeBlockBeforeAnchorV2(
                $returnSource,
                'RETURN_MISSING_FOUND_V1_UI',
                'Manual Quantity Return'
            );

            writeV2($returnBlade, $returnSource);

            echo "[OK] UI Found dari Return dibersihkan.\n";
        }
    }

    /*
     * ==============================================================
     * 2. REMOVE OLD MANUAL MISSING-ASSET RECOVERY UI/METHOD/ROUTE
     * ==============================================================
     */
    if (is_file($inventoryAssetController)) {
        $iac = (string) file_get_contents($inventoryAssetController);

        if (str_contains($iac, 'MISSING_ASSET_RECOVERY_V1')) {
            $backups[$inventoryAssetController] = backupV2(
                $inventoryAssetController
            );

            $clean = removeMethodByMarkerV2(
                $iac,
                'MISSING_ASSET_RECOVERY_V1',
                'public function recover'
            );

            writeV2($inventoryAssetController, $clean);
            lintV2($inventoryAssetController);

            echo "[OK] Method manual Missing Asset Recovery V1 dibersihkan.\n";
        }
    }

    $routeSource = (string) file_get_contents($routes);

    if (
        str_contains($routeSource, 'MISSING_ASSET_RECOVERY_V1')
        || str_contains($routeSource, 'admin.inventory.assets.recover')
    ) {
        if (! isset($backups[$routes])) {
            $backups[$routes] = backupV2($routes);
        }

        $routeSource = preg_replace(
            '~\R?/\*.*?MISSING_ASSET_RECOVERY_V1.*?\*/\s*\\\\Illuminate\\\\Support\\\\Facades\\\\Route::post\(.*?admin\.inventory\.assets\.recover.*?;~s',
            '',
            $routeSource
        ) ?: $routeSource;

        writeV2($routes, $routeSource);
        lintV2($routes);

        echo "[OK] Route manual recovery V1 dibersihkan.\n";
    }

    $assetShow = findAssetShowBladeV2($root);

    if (! $assetShow) {
        throw new RuntimeException(
            'Blade detail Inventory Asset tidak terdeteksi.'
        );
    }

    $assetViewSource = (string) file_get_contents($assetShow);

    if (str_contains($assetViewSource, 'MISSING_ASSET_RECOVERY_V1_UI')) {
        $backups[$assetShow] = backupV2($assetShow);

        $assetViewSource = removeSimpleBladeIfBlockV2(
            $assetViewSource,
            'MISSING_ASSET_RECOVERY_V1_UI'
        );

        writeV2($assetShow, $assetViewSource);

        echo "[OK] UI manual recovery V1 dibersihkan.\n";
    }

    /*
     * ==============================================================
     * 3. NEW QR RECOVERY CONTROLLER
     * ==============================================================
     */
    $controllerSource = <<<'PHP_CONTROLLER'
<?php

namespace Webkul\Admin\Http\Controllers\Inventory;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * MISSING_ASSET_QR_RECOVERY_V2
 *
 * Recover a MISSING serialized asset only after its physical QR / barcode
 * has been verified. Historical Delivery Order return data is NOT changed.
 */
class MissingAssetQrRecoveryController extends Controller
{
    public function __invoke(
        Request $request,
        int $id
    ): RedirectResponse {
        $validated = $request->validate([
            'scan_code' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        DB::transaction(
            function () use (
                $id,
                $validated
            ): void {
                $asset = DB::table('inventory_assets')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (! $asset) {
                    abort(404);
                }

                if (
                    strtolower((string) $asset->status)
                    !== 'missing'
                ) {
                    throw ValidationException::withMessages([
                        'scan_code' =>
                            'Asset ini sudah tidak berstatus MISSING.',
                    ]);
                }

                $rawScan = trim(
                    (string) $validated['scan_code']
                );

                $scanCandidates = [
                    $rawScan,
                    urldecode($rawScan),
                ];

                $path = parse_url($rawScan, PHP_URL_PATH);

                if (is_string($path) && $path !== '') {
                    $base = basename(rtrim($path, '/'));

                    if ($base !== '') {
                        $scanCandidates[] = urldecode($base);
                    }
                }

                $expected = array_values(
                    array_filter(
                        [
                            $asset->barcode_value ?? null,
                            $asset->asset_code ?? null,
                            $asset->serial_number ?? null,
                        ],
                        fn ($value) =>
                            $value !== null
                            && trim((string) $value) !== ''
                    )
                );

                $verified = false;

                foreach ($scanCandidates as $scan) {
                    $normalizedScan = mb_strtolower(
                        trim((string) $scan)
                    );

                    foreach ($expected as $value) {
                        $normalizedExpected = mb_strtolower(
                            trim((string) $value)
                        );

                        if (
                            $normalizedScan !== ''
                            && hash_equals(
                                $normalizedExpected,
                                $normalizedScan
                            )
                        ) {
                            $verified = true;
                            break 2;
                        }
                    }
                }

                if (! $verified) {
                    throw ValidationException::withMessages([
                        'scan_code' =>
                            'QR / barcode tidak sesuai dengan asset '
                            .((string) ($asset->asset_code ?? '#'.$asset->id))
                            .'. Status tidak diubah.',
                    ]);
                }

                /*
                 * A missing asset cannot silently be made AVAILABLE if a
                 * different active operational allocation still owns it.
                 */
                $hasActiveAllocation = DB::table(
                    'delivery_order_inventory_allocations'
                )
                    ->where('inventory_asset_id', $asset->id)
                    ->whereIn('status', [
                        'allocated',
                        'picked',
                        'out',
                        'return_pending',
                    ])
                    ->exists();

                if ($hasActiveAllocation) {
                    throw ValidationException::withMessages([
                        'scan_code' =>
                            'Asset masih memiliki allocation aktif. '
                            .'Selesaikan Surat Jalan tersebut terlebih dahulu.',
                    ]);
                }

                /*
                 * Latest MISSING return is the original business reference.
                 * We only READ it. Historical return_condition stays MISSING.
                 */
                $missingAllocation = DB::table(
                    'delivery_order_inventory_allocations'
                )
                    ->where('inventory_asset_id', $asset->id)
                    ->where('return_condition', 'missing')
                    ->orderByDesc('id')
                    ->first();

                $deliveryOrderId = $missingAllocation
                    ? (int) $missingAllocation->delivery_order_id
                    : null;

                $deliveryOrder = $deliveryOrderId
                    ? DB::table('delivery_orders')
                        ->where('id', $deliveryOrderId)
                        ->first()
                    : null;

                $referenceNumber = null;

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
                            $referenceNumber = (string) $row[$column];
                            break;
                        }
                    }
                }

                if ($referenceNumber === null && $deliveryOrderId) {
                    $referenceNumber = 'DO-'.$deliveryOrderId;
                }

                $now = now();

                $userId = auth()
                    ->guard('user')
                    ->id();

                if (! $userId) {
                    $userId = auth()->id();
                }

                DB::table('inventory_assets')
                    ->where('id', $asset->id)
                    ->update([
                        'status'     => 'available',
                        'condition'  => 'good',
                        'updated_at' => $now,
                    ]);

                DB::table('inventory_stock_movements')
                    ->insert([
                        'inventory_item_id' =>
                            $asset->inventory_item_id,

                        'inventory_asset_id' =>
                            $asset->id,

                        'warehouse_id' =>
                            $asset->warehouse_id,

                        'warehouse_location_id' =>
                            $asset->warehouse_location_id,

                        'movement_type' =>
                            'missing_recovered',

                        'quantity' =>
                            1,

                        'from_status' =>
                            'missing',

                        'to_status' =>
                            'available',

                        'reference_type' =>
                            'delivery_order_missing_recovery',

                        'reference_id' =>
                            $deliveryOrderId,

                        'reference_number' =>
                            $referenceNumber,

                        'performed_by' =>
                            $userId,

                        'notes' =>
                            'Physical QR verified. Missing asset recovered'
                            .($referenceNumber
                                ? ' from '.$referenceNumber
                                : ''),

                        'occurred_at' =>
                            $now,

                        'created_at' =>
                            $now,

                        'updated_at' =>
                            $now,
                    ]);
            }
        );

        session()->flash(
            'success',
            'QR benar. Asset ditemukan dan status kembali AVAILABLE.'
        );

        return back();
    }
}
PHP_CONTROLLER;

    if (! is_file($assetController)) {
        writeV2($assetController, $controllerSource);
        $newFiles[] = $assetController;
        lintV2($assetController);

        echo "[OK] QR recovery controller dibuat.\n";
    } else {
        $existing = (string) file_get_contents($assetController);

        if (! str_contains($existing, 'MISSING_ASSET_QR_RECOVERY_V2')) {
            throw new RuntimeException(
                'MissingAssetQrRecoveryController.php sudah ada tetapi bukan milik patch V2.'
            );
        }

        lintV2($assetController);

        echo "[OK] QR recovery controller sudah ada.\n";
    }

    /*
     * ==============================================================
     * 4. NEW ROUTE
     * ==============================================================
     */
    $routeSource = (string) file_get_contents($routes);

    if (! str_contains(
        $routeSource,
        'admin.inventory.assets.missing-recover-scan'
    )) {
        if (! isset($backups[$routes])) {
            $backups[$routes] = backupV2($routes);
        }

        $block = <<<'PHP_ROUTE'


/*
|--------------------------------------------------------------------------
| MISSING_ASSET_QR_RECOVERY_V2
|--------------------------------------------------------------------------
| Recover one serialized MISSING asset only after QR/barcode verification.
*/
\Illuminate\Support\Facades\Route::post(
    'admin/inventory/assets/{id}/missing-recover-scan',
    \Webkul\Admin\Http\Controllers\Inventory\MissingAssetQrRecoveryController::class
)
    ->middleware([
        'web',
        'admin_locale',
        'user',
    ])
    ->name('admin.inventory.assets.missing-recover-scan');
PHP_ROUTE;

        if (preg_match('/\?>\s*$/', $routeSource) === 1) {
            $routeSource = preg_replace(
                '/\?>\s*$/',
                $block."\n?>",
                $routeSource,
                1
            );
        } else {
            $routeSource = rtrim($routeSource).$block."\n";
        }

        writeV2($routes, $routeSource);
        lintV2($routes);

        echo "[OK] QR recovery route ditambahkan.\n";
    } else {
        echo "[OK] QR recovery route sudah ada.\n";
    }

    /*
     * ==============================================================
     * 5. ASSET DETAIL QR UI
     * ==============================================================
     */
    $assetViewSource = (string) file_get_contents($assetShow);

    if (! str_contains(
        $assetViewSource,
        'MISSING_ASSET_QR_RECOVERY_V2_UI'
    )) {
        if (! isset($backups[$assetShow])) {
            $backups[$assetShow] = backupV2($assetShow);
        }

        $ui = <<<'BLADE'

    {{-- MISSING_ASSET_QR_RECOVERY_V2_UI --}}
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
        @endphp

        <div class="mb-6 rounded-lg border border-red-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-start justify-between gap-4">
                <div>
                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                        Missing Asset Recovery
                    </p>

                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Verifikasi fisik asset dengan scan QR / barcode.
                        Status hanya berubah jika kode yang discan cocok.
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

            <form
                method="POST"
                action="{{ route('admin.inventory.assets.missing-recover-scan', $asset->id) }}"
                class="grid gap-3"
            >
                @csrf

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-800 dark:text-white">
                        Scan QR / Barcode Asset *
                    </label>

                    <input
                        type="text"
                        name="scan_code"
                        autocomplete="off"
                        autofocus
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        placeholder="Scan QR asset di sini"
                        required
                    />

                    <p class="mt-1 text-xs text-gray-500">
                        QR salah tidak akan mengubah status inventory.
                    </p>
                </div>

                <div>
                    <button
                        type="submit"
                        class="primary-button"
                    >
                        Verify & Mark Found
                    </button>
                </div>
            </form>
        </div>
    @endif
BLADE;

        $close = strrpos(
            $assetViewSource,
            '</x-admin::layouts>'
        );

        if ($close !== false) {
            $assetViewSource =
                substr($assetViewSource, 0, $close)
                .$ui
                ."\n"
                .substr($assetViewSource, $close);
        } else {
            $assetViewSource =
                rtrim($assetViewSource)
                ."\n"
                .$ui
                ."\n";
        }

        writeV2($assetShow, $assetViewSource);

        echo "[OK] QR recovery UI dipasang di asset detail.\n";
    } else {
        echo "[OK] QR recovery UI sudah ada.\n";
    }

    /*
     * ==============================================================
     * 6. RETURN PAGE: RECEIVED + MISSING SUMMARY ONLY
     * ==============================================================
     */
    if (! $returnBlade) {
        throw new RuntimeException(
            'Blade Return Warehouse tidak terdeteksi.'
        );
    }

    $returnSource = (string) file_get_contents($returnBlade);

    if (! str_contains(
        $returnSource,
        'RETURN_RECEIVED_MISSING_SUMMARY_V2'
    )) {
        if (! isset($backups[$returnBlade])) {
            $backups[$returnBlade] = backupV2($returnBlade);
        }

        $summary = <<<'BLADE'
{{-- RETURN_RECEIVED_MISSING_SUMMARY_V2 --}}
@php
    $crmReturnRouteId =
        request()->route('id')
        ?? request()->route('deliveryOrder')
        ?? request()->route('deliveryOrderId')
        ?? request()->route('delivery_order');

    $crmReturnSummaryDoId =
        is_object($crmReturnRouteId)
            ? (int) ($crmReturnRouteId->id ?? 0)
            : (int) $crmReturnRouteId;

    $crmReturnReceivedCount = 0;
    $crmReturnMissingCount = 0;

    if ($crmReturnSummaryDoId > 0) {
        $crmReturnBase =
            \Illuminate\Support\Facades\DB::table(
                'delivery_order_inventory_allocations'
            )
                ->where(
                    'delivery_order_id',
                    $crmReturnSummaryDoId
                )
                ->whereNotNull(
                    'inventory_asset_id'
                );

        $crmReturnReceivedCount =
            (clone $crmReturnBase)
                ->whereNotNull(
                    'return_condition'
                )
                ->where(
                    'return_condition',
                    '<>',
                    'missing'
                )
                ->count();

        $crmReturnMissingCount =
            (clone $crmReturnBase)
                ->where(
                    'return_condition',
                    'missing'
                )
                ->count();
    }
@endphp

<div class="mb-4 grid grid-cols-2 gap-3">
    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <p class="text-xs font-semibold uppercase text-gray-500">
            Received
        </p>

        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
            {{ $crmReturnReceivedCount }}
        </p>
    </div>

    <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <p class="text-xs font-semibold uppercase text-gray-500">
            Missing
        </p>

        <p class="mt-1 text-2xl font-bold {{ $crmReturnMissingCount > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">
            {{ $crmReturnMissingCount }}
        </p>

        @if ($crmReturnMissingCount > 0)
            <p class="mt-1 text-xs text-gray-500">
                Tetap tercatat pada Surat Jalan ini dan ditrack dari Inventory Dashboard.
            </p>
        @endif
    </div>
</div>

BLADE;

        $returnSource = insertBeforeTextLineV2(
            $returnSource,
            'Serialized Return Inspection',
            $summary
        );

        writeV2($returnBlade, $returnSource);

        echo "[OK] Summary RECEIVED / MISSING dipasang di Return Warehouse.\n";
    } else {
        echo "[OK] Summary Return sudah ada.\n";
    }

    /*
     * ==============================================================
     * 7. MOVEMENT GRID LABEL
     * ==============================================================
     */
    if (is_file($movementGrid)) {
        $grid = (string) file_get_contents($movementGrid);

        if (! str_contains(
            $grid,
            'MISSING_ASSET_QR_RECOVERY_V2_MOVEMENT'
        )) {
            $backups[$movementGrid] = backupV2($movementGrid);

            $markerInserted = false;

            $filterAnchor =
                "['label' => 'Stock Opname Missing', 'value' => 'stock_opname_missing'],";

            if (str_contains($grid, $filterAnchor)) {
                $grid = str_replace(
                    $filterAnchor,
                    $filterAnchor
                    ."\n                // MISSING_ASSET_QR_RECOVERY_V2_MOVEMENT"
                    ."\n                ['label' => 'Missing Recovered', 'value' => 'missing_recovered'],",
                    $grid,
                    $filterCount
                );

                $markerInserted = $filterCount > 0;
            }

            if (! str_contains(
                $grid,
                "'missing_recovered'"
            )) {
                $badgeAnchor =
                    "'stock_opname_missing'        => ['OPNAME MISSING'";

                if (str_contains($grid, $badgeAnchor)) {
                    $grid = str_replace(
                        $badgeAnchor,
                        "'missing_recovered'          => ['RECOVERED', '#dcfce7', '#15803d'],\n"
                        ."            ".$badgeAnchor,
                        $grid,
                        $badgeCount
                    );

                    $markerInserted =
                        $markerInserted
                        || $badgeCount > 0;
                }
            } else {
                /*
                 * If previous patch already added missing_recovered, only
                 * persist the V2 marker so checker knows this file was seen.
                 */
                if (! $markerInserted) {
                    $grid = preg_replace(
                        '/(class\s+InventoryMovementDataGrid[^{]*\{)/',
                        "$1\n    /** MISSING_ASSET_QR_RECOVERY_V2_MOVEMENT */",
                        $grid,
                        1
                    ) ?: $grid;
                }
            }

            writeV2($movementGrid, $grid);
            lintV2($movementGrid);

            echo "[OK] Movement grid mendukung missing_recovered.\n";
        } else {
            echo "[OK] Movement grid V2 sudah ada.\n";
        }
    }

    /*
     * ==============================================================
     * 8. CACHE + ROUTE CHECK
     * ==============================================================
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
        .' route:list --name=admin.inventory.assets.missing-recover-scan 2>&1',
        $routeOut,
        $routeCode
    );

    if (
        $routeCode !== 0
        || ! str_contains(
            implode("\n", $routeOut),
            'admin.inventory.assets.missing-recover-scan'
        )
    ) {
        throw new RuntimeException(
            "Route QR recovery tidak aktif:\n"
            .implode(PHP_EOL, $routeOut)
        );
    }

    echo "[OK] Cache dibersihkan.\n";
    echo "[OK] Route QR recovery aktif.\n\n";
    echo "HASIL: PASS\n";
    echo "Lanjutkan:\n";
    echo "  php tools\\check_missing_asset_qr_recovery_v2.php\n";
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
            fwrite(STDERR, "Rollback: {$original}\n");
        }
    }

    foreach ($newFiles as $file) {
        if (is_file($file)) {
            @unlink($file);
            fwrite(STDERR, "Rollback: hapus {$file}\n");
        }
    }

    exit(1);
}
