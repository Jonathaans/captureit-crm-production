<?php

declare(strict_types=1);

const TITLE = 'CHECK INVENTORY MISSING RECOVERY STATION V2';

$root = dirname(__DIR__);
$failures = 0;

function result(bool $condition, string $message): void
{
    global $failures;

    if ($condition) {
        echo '[OK]   '.$message.PHP_EOL;
    } else {
        $failures++;
        echo '[FAIL] '.$message.PHP_EOL;
    }
}

function contentOf(string $root, string $relative): string
{
    $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

    return is_file($path) ? (string) file_get_contents($path) : '';
}

function lintFile(string $root, string $relative): bool
{
    $command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($relative).' 2>&1';
    $current = getcwd();
    chdir($root);
    exec($command, $output, $exitCode);
    chdir($current ?: $root);

    return $exitCode === 0;
}

echo TITLE.PHP_EOL;
echo str_repeat('=', strlen(TITLE)).PHP_EOL.PHP_EOL;

$route = 'packages/Webkul/Admin/src/Routes/Admin/inventory-routes.php';
$controller = 'packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryMissingRecoveryScanController.php';
$service = 'packages/Webkul/Admin/src/Services/InventoryMissingRecoveryService.php';
$view = 'packages/Webkul/Admin/src/Resources/views/inventory/assets/recover-missing-station.blade.php';
$dataGrid = 'packages/Webkul/Admin/src/DataGrids/Inventory/InventoryMovementDataGrid.php';

foreach ([$route, $controller, $service, $view, $dataGrid] as $relative) {
    result(
        is_file($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative)),
        'File tersedia: '.$relative
    );
}

$routeContent = contentOf($root, $route);
$controllerContent = contentOf($root, $controller);
$serviceContent = contentOf($root, $service);
$viewContent = contentOf($root, $view);
$dataGridContent = contentOf($root, $dataGrid);

$routeChecks = [
    'Marker route Recovery Station V2' => 'INVENTORY_MISSING_RECOVERY_STATION_ROUTES_V2',
    'Route station' => "->name('admin.inventory.missing-recovery.station')",
    'Route verifikasi scan' => "->name('admin.inventory.missing-recovery.verify')",
    'Route konfirmasi recovery' => "->name('admin.inventory.missing-recovery.complete')",
    'Route pembatalan verifikasi' => "->name('admin.inventory.missing-recovery.clear')",
];

foreach ($routeChecks as $label => $needle) {
    result(str_contains($routeContent, $needle), $label);
}

result(
    substr_count($routeContent, 'INVENTORY_MISSING_RECOVERY_STATION_ROUTES_V2') === 1,
    'Route V2 tidak terduplikasi'
);

$controllerChecks = [
    'Entry lama mengarah ke Recovery Station' => "public function create(int \$id): RedirectResponse",
    'Verifikasi scan dilakukan di server' => 'public function verify(',
    'Konfirmasi recovery terpisah' => 'public function complete(',
    'Verifikasi berlaku 30 menit' => 'VERIFICATION_TTL_SECONDS = 1800',
    'Session scan terikat server' => 'inventory.missing_recovery_station.verification',
    'Token dibandingkan secara aman' => 'hash_equals($storedTokenHash, $submittedTokenHash)',
    'ACL Asset Edit tetap diterapkan' => "bouncer()->hasPermission('inventory.assets.edit')",
];

foreach ($controllerChecks as $label => $needle) {
    result(str_contains($controllerContent, $needle), $label);
}

$serviceChecks = [
    'Barcode dicari dari master asset' => 'public function findByBarcode(',
    'Pencarian menerima asset code' => 'UPPER(TRIM(asset_code))',
    'Pencarian menerima barcode value' => 'UPPER(TRIM(barcode_value))',
    'Recovery memakai transaction' => 'DB::transaction(',
    'Asset dikunci untuk mencegah double recovery' => 'lockForUpdate()',
    'Movement Missing Recovered dibuat' => "'movement_type'          => 'missing_recovered'",
    'Kondisi rusak tetap butuh alasan' => "\$condition === 'damaged'",
];

foreach ($serviceChecks as $label => $needle) {
    result(str_contains($serviceContent, $needle), $label);
}

$viewChecks = [
    'Marker UI Recovery Station V2' => 'INVENTORY_MISSING_RECOVERY_STATION_VIEW_V2',
    'Input scanner nyata tersedia' => 'id="recovery-scanner-input"',
    'Input scanner otomatis fokus' => 'autofocus',
    'Fokus scanner diperkuat setelah halaman tampil' => 'requestAnimationFrame(focusScanner)',
    'Browser submit native saat Enter' => 'enterkeyhint="done"',
    'Suffix Tab juga mengirim form scanner' => 'scannerForm.requestSubmit();',
    'Form POST ke verifikasi server' => "route('admin.inventory.missing-recovery.verify')",
    'Tahap kondisi hanya setelah scan cocok' => '@if (! $verifiedAsset)',
    'Form konfirmasi menulis recovery' => "route('admin.inventory.missing-recovery.complete'",
    'Kondisi damaged memiliki alasan' => 'id="damage-reason"',
    'Konfirmasi fisik diwajibkan' => 'id="confirmed"',
];

foreach ($viewChecks as $label => $needle) {
    result(str_contains($viewContent, $needle), $label);
}

result(
    ! str_contains($viewContent, "document.addEventListener('keydown'"),
    'Tidak ada listener scanner keyboard global'
);
result(
    ! str_contains($viewContent, 'usb-button'),
    'Tidak ada tombol aktivasi scanner lama'
);
result(
    str_contains($dataGridContent, "'missing_recovered'")
        && str_contains($dataGridContent, 'MISSING RECOVERED'),
    'Inventory Movement tetap mengenali Missing Recovered'
);

foreach ([$route, $controller, $service] as $relative) {
    result(lintFile($root, $relative), 'PHP lint: '.$relative);
}

$autoload = $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
$bootstrap = $root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';

if (! is_file($autoload) || ! is_file($bootstrap)) {
    result(false, 'Laravel bootstrap tersedia');
} else {
    try {
        require $autoload;
        $app = require $bootstrap;
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        foreach ([
            'admin.inventory.missing-recovery.station',
            'admin.inventory.missing-recovery.verify',
            'admin.inventory.missing-recovery.complete',
            'admin.inventory.missing-recovery.clear',
            'admin.inventory.assets.missing-recovery.scan',
        ] as $routeName) {
            result(Illuminate\Support\Facades\Route::has($routeName), 'Route terdaftar: '.$routeName);
        }

        foreach (['inventory_assets', 'inventory_stock_movements', 'warehouses'] as $table) {
            result(Illuminate\Support\Facades\Schema::hasTable($table), 'Tabel tersedia: '.$table);
        }

        Illuminate\Support\Facades\Artisan::call('view:clear');
        $viewResult = Illuminate\Support\Facades\Artisan::call('view:cache');
        result($viewResult === 0, 'Semua Blade berhasil dikompilasi');
    } catch (Throwable $exception) {
        result(false, 'Laravel runtime: '.$exception->getMessage());
    }
}

echo PHP_EOL;

if ($failures > 0) {
    echo '[FAIL] Checker menemukan '.$failures.' masalah.'.PHP_EOL;
    exit(1);
}

echo '[PASS] Missing Recovery Station V2 siap digunakan.'.PHP_EOL;
echo 'Uji: buka asset MISSING, klik Scan Barang Ditemukan, lalu langsung scan tanpa tombol aktivasi.'.PHP_EOL;
