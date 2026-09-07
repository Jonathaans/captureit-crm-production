<?php

declare(strict_types=1);

const PATCH_NAME = 'INVENTORY MISSING RECOVERY BY BARCODE SCAN V1.1';
const PATCH_SLUG = 'inventory-missing-recovery-scan-v1';
const ROUTE_MARKER = 'INVENTORY_MISSING_RECOVERY_SCAN_ROUTES_V1';
const VIEW_MARKER = 'INVENTORY_MISSING_RECOVERY_SCAN_V1';
const DATAGRID_MARKER = 'INVENTORY_MISSING_RECOVERY_MOVEMENT_V1';

$root = dirname(__DIR__);
$payloadRoot = __DIR__.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR.PATCH_SLUG;
$writesStarted = false;
$backupDirectory = null;
$manifest = null;

function line(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function fail(string $message): never
{
    throw new RuntimeException($message);
}

function normalizePath(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function readNormalized(string $path): string
{
    $content = file_get_contents($path);

    if ($content === false) {
        fail('Tidak dapat membaca file: '.$path);
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function writeFile(string $path, string $content): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true)) {
        fail('Tidak dapat membuat folder: '.$directory);
    }

    if (file_put_contents($path, $content) === false) {
        fail('Tidak dapat menulis file: '.$path);
    }
}

function replaceOnce(string $content, string $search, string $replacement, string $label): string
{
    $count = substr_count($content, $search);

    if ($count !== 1) {
        fail('Preflight '.$label.' harus ditemukan tepat satu kali; count='.$count.'.');
    }

    return str_replace($search, $replacement, $content);
}

function replaceRegexOnce(
    string $content,
    string $pattern,
    callable $replacement,
    string $label
): string {
    $matchCount = preg_match_all($pattern, $content);

    if ($matchCount !== 1) {
        fail('Preflight '.$label.' harus ditemukan tepat satu kali; count='.(int) $matchCount.'.');
    }

    $updated = preg_replace_callback($pattern, $replacement, $content, 1, $replaceCount);

    if (! is_string($updated) || $replaceCount !== 1) {
        fail('Patch regex gagal: '.$label.'.');
    }

    return $updated;
}

function runCommand(string $root, array $arguments): int
{
    $command = escapeshellarg(PHP_BINARY);

    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg((string) $argument);
    }

    line('[RUN]   '.implode(' ', $arguments));
    $current = getcwd();
    chdir($root);
    passthru($command, $exitCode);
    chdir($current ?: $root);

    return (int) $exitCode;
}

function replaceBladeIfBlockContaining(string $content, string $needle, string $replacement): string
{
    $needlePosition = strpos($content, $needle);

    if ($needlePosition === false) {
        fail('Blok recovery manual tidak ditemukan.');
    }

    preg_match_all(
        '/@(if|endif)\b/',
        $content,
        $matches,
        PREG_OFFSET_CAPTURE
    );

    $openIfStack = [];

    foreach ($matches[1] as $index => $match) {
        $offset = $matches[0][$index][1];

        if ($offset >= $needlePosition) {
            break;
        }

        if ($match[0] === 'if') {
            $openIfStack[] = $index;
        } elseif ($openIfStack !== []) {
            array_pop($openIfStack);
        }
    }

    $startTokenIndex = $openIfStack === []
        ? null
        : $openIfStack[array_key_last($openIfStack)];

    if ($startTokenIndex === null) {
        fail('Pembuka @if untuk recovery manual tidak ditemukan.');
    }

    $startOffset = $matches[0][$startTokenIndex][1];

    if ($needlePosition - $startOffset > 3000) {
        fail('Blok recovery manual terlalu jauh dari pembuka @if; patch dihentikan agar aman.');
    }

    $depth = 0;
    $endOffset = null;

    for ($index = $startTokenIndex; $index < count($matches[1]); $index++) {
        $token = $matches[1][$index][0];

        if ($token === 'if') {
            $depth++;
        } else {
            $depth--;

            if ($depth === 0) {
                $endOffset = $matches[0][$index][1] + strlen($matches[0][$index][0]);
                break;
            }
        }
    }

    if ($endOffset === null || $needlePosition > $endOffset) {
        fail('Penutup @endif untuk recovery manual tidak ditemukan.');
    }

    return substr($content, 0, $startOffset)
        .$replacement
        .substr($content, $endOffset);
}

function restoreBackup(string $root, string $backupDirectory, array $manifest): void
{
    foreach ($manifest['files'] as $relative => $metadata) {
        $target = $root.DIRECTORY_SEPARATOR.normalizePath($relative);
        $source = $backupDirectory.DIRECTORY_SEPARATOR.'files'
            .DIRECTORY_SEPARATOR.normalizePath($relative);

        if (($metadata['existed'] ?? false) && is_file($source)) {
            writeFile($target, (string) file_get_contents($source));
        } elseif (is_file($target)) {
            unlink($target);
        }
    }
}

line(PATCH_NAME);
line(str_repeat('=', strlen(PATCH_NAME)));
line();

try {
    if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
        fail('Jalankan script ini dari root project Laravel CRM.');
    }

    $routePath = 'packages/Webkul/Admin/src/Routes/Admin/inventory-routes.php';
    $assetEditPath = 'packages/Webkul/Admin/src/Resources/views/inventory/assets/edit.blade.php';
    $dataGridPath = 'packages/Webkul/Admin/src/DataGrids/Inventory/InventoryMovementDataGrid.php';
    $servicePath = 'packages/Webkul/Admin/src/Services/InventoryMissingRecoveryService.php';
    $controllerPath = 'packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryMissingRecoveryScanController.php';
    $scannerViewPath = 'packages/Webkul/Admin/src/Resources/views/inventory/assets/recover-missing-scan.blade.php';

    $requiredExisting = [$routePath, $assetEditPath, $dataGridPath];
    $payloadFiles = [$servicePath, $controllerPath, $scannerViewPath];
    $managedFiles = array_merge($requiredExisting, $payloadFiles);

    foreach ($requiredExisting as $relative) {
        if (! is_file($root.DIRECTORY_SEPARATOR.normalizePath($relative))) {
            fail('Preflight file wajib tidak ditemukan: '.$relative);
        }
    }

    foreach ($payloadFiles as $relative) {
        if (! is_file($payloadRoot.DIRECTORY_SEPARATOR.normalizePath($relative))) {
            fail('Payload installer tidak lengkap: '.$relative);
        }
    }

    $routeContent = readNormalized($root.DIRECTORY_SEPARATOR.normalizePath($routePath));
    $assetEditContent = readNormalized($root.DIRECTORY_SEPARATOR.normalizePath($assetEditPath));
    $dataGridContent = readNormalized($root.DIRECTORY_SEPARATOR.normalizePath($dataGridPath));

    $fullyInstalled = str_contains($routeContent, ROUTE_MARKER)
        && str_contains($assetEditContent, VIEW_MARKER)
        && str_contains($dataGridContent, DATAGRID_MARKER)
        && is_file($root.DIRECTORY_SEPARATOR.normalizePath($servicePath))
        && is_file($root.DIRECTORY_SEPARATOR.normalizePath($controllerPath))
        && is_file($root.DIRECTORY_SEPARATOR.normalizePath($scannerViewPath));

    if ($fullyInstalled) {
        runCommand($root, ['artisan', 'route:clear']);
        runCommand($root, ['artisan', 'view:clear']);
        runCommand($root, ['artisan', 'view:cache']);
        runCommand($root, ['artisan', 'optimize:clear']);
        line('[OK] Patch sudah terpasang. Tidak ada source yang ditulis ulang.');
        line('Jalankan: php tools/check_inventory_missing_recovery_scan_v1.php');
        exit(0);
    }

    $partialCount = 0;
    $partialCount += str_contains($routeContent, ROUTE_MARKER) ? 1 : 0;
    $partialCount += str_contains($assetEditContent, VIEW_MARKER) ? 1 : 0;
    $partialCount += str_contains($dataGridContent, DATAGRID_MARKER) ? 1 : 0;

    if ($partialCount > 0) {
        fail('Instalasi parsial terdeteksi. Jalankan rollback V1 lalu apply ulang.');
    }

    $routeAnchor = '        Route::controller(InventoryMaintenanceController::class)';
    $routeBlock = <<<'PHP'
        /* INVENTORY_MISSING_RECOVERY_SCAN_ROUTES_V1 */
        Route::controller(\Webkul\Admin\Http\Controllers\Inventory\InventoryMissingRecoveryScanController::class)
            ->prefix('assets')
            ->group(function () {
                Route::get('{id}/recover-missing-scan', 'create')
                    ->name('admin.inventory.assets.missing-recovery.scan');

                Route::post('{id}/recover-missing-scan', 'store')
                    ->name('admin.inventory.assets.missing-recovery.store');
            });

PHP;

    $routeContent = replaceOnce(
        $routeContent,
        $routeAnchor,
        $routeBlock.$routeAnchor,
        'anchor route Inventory Maintenance'
    );

    $scannerCard = <<<'BLADE'
    {{-- INVENTORY_MISSING_RECOVERY_SCAN_V1 --}}
    @if (
        $asset->status === 'missing'
        && bouncer()->hasPermission('inventory.assets.edit')
    )
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-5 dark:border-red-900 dark:bg-red-950/30">
            <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
                <div>
                    <p class="text-lg font-bold text-red-800 dark:text-red-200">
                        Missing Asset Recovery
                    </p>

                    <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                        Recovery manual dinonaktifkan. Barang fisik wajib dipindai, lalu kondisi dan lokasi penemuan dikonfirmasi.
                    </p>
                </div>

                <a
                    href="{{ route('admin.inventory.assets.missing-recovery.scan', $asset->id) }}"
                    class="primary-button"
                >
                    Scan Barang Ditemukan
                </a>
            </div>
        </div>
    @endif
BLADE;

    if (str_contains($assetEditContent, 'Missing Asset Recovery')) {
        $assetEditContent = replaceBladeIfBlockContaining(
            $assetEditContent,
            'Missing Asset Recovery',
            $scannerCard
        );
    } else {
        $assetEditContent = replaceOnce(
            $assetEditContent,
            '</x-admin::layouts>',
            $scannerCard."\n</x-admin::layouts>",
            'penutup layout Asset Edit'
        );
    }

    if (! str_contains($dataGridContent, "'missing_recovered'")) {
        $dataGridContent = replaceOnce(
            $dataGridContent,
            "                ['label' => 'Stock Opname Found', 'value' => 'stock_opname_found'],",
            "                ['label' => 'Stock Opname Found', 'value' => 'stock_opname_found'],\n"
                ."                /* INVENTORY_MISSING_RECOVERY_MOVEMENT_V1 */\n"
                ."                ['label' => 'Missing Recovered', 'value' => 'missing_recovered'],",
            'filter movement Missing Recovered'
        );

        $dataGridContent = replaceOnce(
            $dataGridContent,
            "            'stock_opname_found'          => ['OPNAME FOUND', '#dcfce7', '#15803d'],",
            "            'stock_opname_found'          => ['OPNAME FOUND', '#dcfce7', '#15803d'],\n"
                ."            'missing_recovered'           => ['MISSING RECOVERED', '#dcfce7', '#15803d'],",
            'badge movement Missing Recovered'
        );
    } else {
        /*
         * Compatibility with the earlier movement/alert patch: it already
         * contains missing_recovered twice (one filter + one badge). V1
         * incorrectly expected the raw token to occur once. V1.1 patches
         * each structural line independently.
         */
        $movementFilterPattern = "/^([ \\t]*)\\['label'\\s*=>\\s*'[^'\\r\\n]*',\\s*'value'\\s*=>\\s*'missing_recovered'\\],\\s*$/m";

        if (preg_match($movementFilterPattern, $dataGridContent) === 1) {
            $dataGridContent = replaceRegexOnce(
                $dataGridContent,
                $movementFilterPattern,
                static fn (array $match): string => $match[1]
                    ."/* INVENTORY_MISSING_RECOVERY_MOVEMENT_V1 */\n"
                    .$match[1]."['label' => 'Missing Recovered', 'value' => 'missing_recovered'],",
                'filter existing missing_recovered'
            );
        } else {
            $dataGridContent = replaceOnce(
                $dataGridContent,
                "                ['label' => 'Stock Opname Found', 'value' => 'stock_opname_found'],",
                "                ['label' => 'Stock Opname Found', 'value' => 'stock_opname_found'],\n"
                    ."                /* INVENTORY_MISSING_RECOVERY_MOVEMENT_V1 */\n"
                    ."                ['label' => 'Missing Recovered', 'value' => 'missing_recovered'],",
                'tambahkan filter missing_recovered'
            );
        }

        $movementBadgePattern = "/^([ \\t]*)'missing_recovered'\\s*=>\\s*\\[[^\\r\\n]*\\],\\s*$/m";

        if (preg_match($movementBadgePattern, $dataGridContent) === 1) {
            $dataGridContent = replaceRegexOnce(
                $dataGridContent,
                $movementBadgePattern,
                static fn (array $match): string => $match[1]
                    ."'missing_recovered' => ['MISSING RECOVERED', '#dcfce7', '#15803d'],",
                'badge existing missing_recovered'
            );
        } else {
            $dataGridContent = replaceOnce(
                $dataGridContent,
                "            'stock_opname_found'          => ['OPNAME FOUND', '#dcfce7', '#15803d'],",
                "            'stock_opname_found'          => ['OPNAME FOUND', '#dcfce7', '#15803d'],\n"
                    ."            'missing_recovered' => ['MISSING RECOVERED', '#dcfce7', '#15803d'],",
                'tambahkan badge missing_recovered'
            );
        }
    }

    if (! str_contains($dataGridContent, "'missing_recovery'")) {
        $dataGridContent = replaceOnce(
            $dataGridContent,
            "                ['label' => 'Stock Opname', 'value' => 'stock_opname'],",
            "                ['label' => 'Stock Opname', 'value' => 'stock_opname'],\n"
                ."                ['label' => 'Missing Recovery', 'value' => 'missing_recovery'],",
            'filter reference Missing Recovery'
        );

        $dataGridContent = replaceOnce(
            $dataGridContent,
            "            'stock_opname'          => ['STOCK OPNAME', '#ecfeff', '#0e7490'],",
            "            'stock_opname'          => ['STOCK OPNAME', '#ecfeff', '#0e7490'],\n"
                ."            'missing_recovery'      => ['MISSING RECOVERY', '#dcfce7', '#15803d'],",
            'badge reference Missing Recovery'
        );
    }

    $timestamp = date('Ymd-His');
    $backupDirectory = $root.DIRECTORY_SEPARATOR.'tools'
        .DIRECTORY_SEPARATOR.'backups'
        .DIRECTORY_SEPARATOR.PATCH_SLUG.'-'.$timestamp;

    if (! mkdir($backupDirectory.DIRECTORY_SEPARATOR.'files', 0775, true)) {
        fail('Tidak dapat membuat folder backup patch.');
    }

    $manifest = [
        'patch' => PATCH_NAME,
        'created_at' => date(DATE_ATOM),
        'files' => [],
    ];

    foreach ($managedFiles as $relative) {
        $source = $root.DIRECTORY_SEPARATOR.normalizePath($relative);
        $existed = is_file($source);
        $manifest['files'][$relative] = ['existed' => $existed];

        if ($existed) {
            $backup = $backupDirectory.DIRECTORY_SEPARATOR.'files'
                .DIRECTORY_SEPARATOR.normalizePath($relative);
            writeFile($backup, (string) file_get_contents($source));
        }
    }

    writeFile(
        $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json',
        (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    writeFile(
        $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
            .DIRECTORY_SEPARATOR.PATCH_SLUG.'-latest.txt',
        $backupDirectory
    );

    $writesStarted = true;

    foreach ($payloadFiles as $relative) {
        $source = $payloadRoot.DIRECTORY_SEPARATOR.normalizePath($relative);
        $target = $root.DIRECTORY_SEPARATOR.normalizePath($relative);
        writeFile($target, (string) file_get_contents($source));
        line('[WRITE] '.$relative);
    }

    writeFile($root.DIRECTORY_SEPARATOR.normalizePath($routePath), $routeContent);
    line('[PATCH] '.$routePath);
    writeFile($root.DIRECTORY_SEPARATOR.normalizePath($assetEditPath), $assetEditContent);
    line('[PATCH] '.$assetEditPath);
    writeFile($root.DIRECTORY_SEPARATOR.normalizePath($dataGridPath), $dataGridContent);
    line('[PATCH] '.$dataGridPath);

    foreach ([$servicePath, $controllerPath, $dataGridPath] as $relative) {
        if (runCommand($root, ['-l', $relative]) !== 0) {
            fail('PHP lint gagal: '.$relative);
        }
    }

    if (runCommand($root, ['artisan', 'route:clear']) !== 0) {
        fail('Route cache tidak dapat dibersihkan.');
    }

    if (runCommand($root, ['artisan', 'view:clear']) !== 0) {
        fail('Compiled views tidak dapat dibersihkan.');
    }

    if (runCommand($root, ['artisan', 'view:cache']) !== 0) {
        fail('Blade compile gagal.');
    }

    runCommand($root, ['artisan', 'optimize:clear']);

    line();
    line('PATCH BERHASIL.');
    line('Backup source: '.$backupDirectory);
    line('Lanjutkan dengan:');
    line('php tools/check_inventory_missing_recovery_scan_v1.php');
} catch (Throwable $exception) {
    line();
    line('PATCH GAGAL: '.$exception->getMessage());

    if ($writesStarted && $backupDirectory && is_array($manifest)) {
        restoreBackup($root, $backupDirectory, $manifest);
        runCommand($root, ['artisan', 'route:clear']);
        runCommand($root, ['artisan', 'view:clear']);
        runCommand($root, ['artisan', 'optimize:clear']);
        line('Source dipulihkan otomatis dari backup patch.');
    }

    exit(1);
}
