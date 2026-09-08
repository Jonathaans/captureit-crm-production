<?php

declare(strict_types=1);

const TITLE = 'INVENTORY MISSING RECOVERY STATION V2';
const PATCH_SLUG = 'inventory-missing-recovery-station-v2';
const ROUTE_MARKER = 'INVENTORY_MISSING_RECOVERY_STATION_ROUTES_V2';
const VIEW_MARKER = 'INVENTORY_MISSING_RECOVERY_STATION_VIEW_V2';

$root = dirname(__DIR__);
$payloadRoot = __DIR__.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR.PATCH_SLUG;
$backupDirectory = null;
$manifest = [];
$writesStarted = false;

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

function readFileStrict(string $path): string
{
    $content = file_get_contents($path);

    if ($content === false) {
        fail('Tidak dapat membaca file: '.$path);
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function writeFileStrict(string $path, string $content): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true)) {
        fail('Tidak dapat membuat folder: '.$directory);
    }

    if (file_put_contents($path, $content) === false) {
        fail('Tidak dapat menulis file: '.$path);
    }
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

function restoreFiles(string $root, string $backupDirectory, array $manifest): void
{
    foreach ($manifest as $entry) {
        $relative = (string) $entry['path'];
        $target = $root.DIRECTORY_SEPARATOR.normalizePath($relative);

        if ((bool) $entry['existed']) {
            $source = $backupDirectory.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.normalizePath($relative);

            if (is_file($source)) {
                writeFileStrict($target, readFileStrict($source));
            }
        } elseif (is_file($target)) {
            unlink($target);
        }
    }
}

line(TITLE);
line(str_repeat('=', strlen(TITLE)));
line();

try {
    if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
        fail('Jalankan script ini dari root project Laravel CRM.');
    }

    $routePath = 'packages/Webkul/Admin/src/Routes/Admin/inventory-routes.php';
    $controllerPath = 'packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryMissingRecoveryScanController.php';
    $servicePath = 'packages/Webkul/Admin/src/Services/InventoryMissingRecoveryService.php';
    $stationViewPath = 'packages/Webkul/Admin/src/Resources/views/inventory/assets/recover-missing-station.blade.php';
    $managedFiles = [$routePath, $controllerPath, $servicePath, $stationViewPath];

    foreach ([$routePath, $controllerPath, $servicePath] as $relative) {
        if (! is_file($root.DIRECTORY_SEPARATOR.normalizePath($relative))) {
            fail('Preflight file wajib tidak ditemukan: '.$relative.'. Pasang Missing Recovery V1.1 terlebih dahulu.');
        }
    }

    foreach ([$controllerPath, $servicePath, $stationViewPath] as $relative) {
        if (! is_file($payloadRoot.DIRECTORY_SEPARATOR.normalizePath($relative))) {
            fail('Payload tidak lengkap: '.$relative.'. Extract seluruh ZIP ke root project.');
        }
    }

    $routeTarget = $root.DIRECTORY_SEPARATOR.normalizePath($routePath);
    $routeContent = readFileStrict($routeTarget);
    $alreadyInstalled = str_contains($routeContent, ROUTE_MARKER);

    if ($alreadyInstalled) {
        foreach ([
            "->prefix('missing-recovery')",
            "->name('admin.inventory.missing-recovery.station')",
            "->name('admin.inventory.missing-recovery.verify')",
            "->name('admin.inventory.missing-recovery.complete')",
            "->name('admin.inventory.missing-recovery.clear')",
        ] as $needle) {
            if (! str_contains($routeContent, $needle)) {
                fail('Route V2 terdeteksi parsial. Marker kurang: '.$needle);
            }
        }
    } else {
        $anchor = '        /* INVENTORY_MISSING_RECOVERY_SCAN_ROUTES_V1 */';

        if (substr_count($routeContent, $anchor) !== 1) {
            fail('Anchor route Missing Recovery V1 harus ditemukan tepat satu kali.');
        }

        $routeBlock = <<<'PHP'
        /* INVENTORY_MISSING_RECOVERY_STATION_ROUTES_V2 */
        Route::controller(\Webkul\Admin\Http\Controllers\Inventory\InventoryMissingRecoveryScanController::class)
            ->prefix('missing-recovery')
            ->group(function () {
                Route::get('/', 'station')
                    ->name('admin.inventory.missing-recovery.station');

                Route::post('verify', 'verify')
                    ->name('admin.inventory.missing-recovery.verify');

                Route::post('{id}/complete', 'complete')
                    ->name('admin.inventory.missing-recovery.complete');

                Route::delete('verified', 'clear')
                    ->name('admin.inventory.missing-recovery.clear');
            });

PHP;

        $routeContent = str_replace($anchor, $routeBlock.$anchor, $routeContent);
    }

    $payloadController = readFileStrict($payloadRoot.DIRECTORY_SEPARATOR.normalizePath($controllerPath));
    $payloadService = readFileStrict($payloadRoot.DIRECTORY_SEPARATOR.normalizePath($servicePath));
    $payloadView = readFileStrict($payloadRoot.DIRECTORY_SEPARATOR.normalizePath($stationViewPath));

    foreach ([
        'public function station(Request $request): View',
        'public function verify(',
        'public function complete(',
        'VERIFICATION_TTL_SECONDS = 1800',
        'inventory.missing_recovery_station.verification',
    ] as $needle) {
        if (! str_contains($payloadController, $needle)) {
            fail('Payload controller tidak lengkap: '.$needle);
        }
    }

    foreach ([
        'public function findByBarcode(',
        'lockForUpdate()',
        "'movement_type'          => 'missing_recovered'",
        'Missing asset recovered at Recovery Station',
    ] as $needle) {
        if (! str_contains($payloadService, $needle)) {
            fail('Payload service tidak lengkap: '.$needle);
        }
    }

    foreach ([
        VIEW_MARKER,
        'id="recovery-scanner-input"',
        'autofocus',
        'requestAnimationFrame(focusScanner)',
        'scannerForm.requestSubmit();',
        "route('admin.inventory.missing-recovery.verify')",
        "route('admin.inventory.missing-recovery.complete'",
    ] as $needle) {
        if (! str_contains($payloadView, $needle)) {
            fail('Payload view tidak lengkap: '.$needle);
        }
    }

    if (str_contains($payloadView, "document.addEventListener('keydown'")) {
        fail('Payload station tidak boleh memakai listener keyboard global.');
    }

    $timestamp = date('Ymd-His');
    $backupDirectory = $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
        .DIRECTORY_SEPARATOR.PATCH_SLUG.'-'.$timestamp;

    foreach ($managedFiles as $relative) {
        $target = $root.DIRECTORY_SEPARATOR.normalizePath($relative);
        $existed = is_file($target);
        $manifest[] = ['path' => $relative, 'existed' => $existed];

        if ($existed) {
            $backupPath = $backupDirectory.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.normalizePath($relative);
            writeFileStrict($backupPath, readFileStrict($target));
        }
    }

    writeFileStrict(
        $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json',
        (string) json_encode([
            'patch' => TITLE,
            'created_at' => date(DATE_ATOM),
            'files' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    writeFileStrict(
        $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
            .DIRECTORY_SEPARATOR.PATCH_SLUG.'-latest.txt',
        $backupDirectory
    );

    $writesStarted = true;
    writeFileStrict($routeTarget, $routeContent);
    line('[PATCH] '.$routePath);
    writeFileStrict($root.DIRECTORY_SEPARATOR.normalizePath($controllerPath), $payloadController);
    line('[WRITE] '.$controllerPath);
    writeFileStrict($root.DIRECTORY_SEPARATOR.normalizePath($servicePath), $payloadService);
    line('[WRITE] '.$servicePath);
    writeFileStrict($root.DIRECTORY_SEPARATOR.normalizePath($stationViewPath), $payloadView);
    line('[WRITE] '.$stationViewPath);

    foreach ([$routePath, $controllerPath, $servicePath] as $relative) {
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
    line('PATCH BERHASIL. Missing Recovery sekarang memakai Recovery Station server-driven.');
    line('Buka Asset MISSING > Scan Barang Ditemukan, lalu langsung scan pada input autofocus.');
    line('Backup source: '.$backupDirectory);
    line('Lanjutkan dengan:');
    line('php tools/check_inventory_missing_recovery_station_v2.php');
} catch (Throwable $exception) {
    line();
    line('PATCH GAGAL: '.$exception->getMessage());

    if ($writesStarted && is_string($backupDirectory)) {
        restoreFiles($root, $backupDirectory, $manifest);
        runCommand($root, ['artisan', 'route:clear']);
        runCommand($root, ['artisan', 'view:clear']);
        runCommand($root, ['artisan', 'optimize:clear']);
        line('Semua file yang berubah dipulihkan otomatis.');
    }

    exit(1);
}
