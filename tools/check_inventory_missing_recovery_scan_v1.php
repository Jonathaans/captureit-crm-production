<?php

declare(strict_types=1);

const TITLE = 'CHECK INVENTORY MISSING RECOVERY BY BARCODE SCAN V1.1';

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

function projectPath(string $root, string $relative): string
{
    return $root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function containsAll(string $root, string $relative, array $needles, string $label): void
{
    $path = projectPath($root, $relative);

    if (! is_file($path)) {
        result(false, $label.' — file tidak ditemukan');
        return;
    }

    $content = (string) file_get_contents($path);
    $missing = array_values(array_filter(
        $needles,
        fn (string $needle): bool => ! str_contains($content, $needle)
    ));

    result(
        $missing === [],
        $label.($missing === [] ? '' : ' — marker kurang: '.implode(', ', $missing))
    );
}

function lintFile(string $root, string $relative): void
{
    $command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg(projectPath($root, $relative)).' 2>&1';
    exec($command, $output, $exitCode);
    result($exitCode === 0, 'PHP lint: '.$relative);
}

echo TITLE.PHP_EOL;
echo str_repeat('=', strlen(TITLE)).PHP_EOL.PHP_EOL;

$service = 'packages/Webkul/Admin/src/Services/InventoryMissingRecoveryService.php';
$controller = 'packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryMissingRecoveryScanController.php';
$scannerView = 'packages/Webkul/Admin/src/Resources/views/inventory/assets/recover-missing-scan.blade.php';
$assetEdit = 'packages/Webkul/Admin/src/Resources/views/inventory/assets/edit.blade.php';
$routes = 'packages/Webkul/Admin/src/Routes/Admin/inventory-routes.php';
$dataGrid = 'packages/Webkul/Admin/src/DataGrids/Inventory/InventoryMovementDataGrid.php';

foreach ([$service, $controller, $scannerView, $assetEdit, $routes, $dataGrid] as $relative) {
    result(is_file(projectPath($root, $relative)), 'File tersedia: '.$relative);
}

containsAll($root, $service, [
    'DB::transaction',
    'lockForUpdate()',
    'barcodeMatches',
    "'movement_type'          => 'missing_recovered'",
    "'reference_type'         => 'missing_recovery'",
    "'from_status'            => 'missing'",
    "'to_status'              => \$targetStatus",
    "\$condition === 'damaged' ? 'damaged' : 'available'",
], 'Service memvalidasi scan dan mencatat recovery secara atomik');

containsAll($root, $controller, [
    "bouncer()->hasPermission('inventory.assets.edit')",
    "'scanned_barcode' => ['required'",
    "'damage_reason' => ['nullable', 'required_if:condition,damaged'",
    "'confirmed' => ['accepted']",
    "session()->pull",
    "hash_equals",
    "auth()->guard('user')->id()",
    "admin.inventory.movements.index",
], 'Controller mengunci akses, token satu-kali, dan redirect ke Movement');

containsAll($root, $scannerView, [
    'id="scanned-barcode"',
    'type="hidden"',
    'Tidak tersedia input kode manual',
    'BarcodeDetector',
    'getUserMedia',
    'Aktifkan Scanner USB',
    'expectedValues.includes(value)',
    'id="damage-reason"',
    'damageReason.required = isDamaged',
    'id="submit-recovery"',
], 'UI hanya menerima kamera/scanner dan mewajibkan kondisi');

containsAll($root, $assetEdit, [
    'INVENTORY_MISSING_RECOVERY_SCAN_V1',
    'Scan Barang Ditemukan',
    'admin.inventory.assets.missing-recovery.scan',
    'Recovery manual dinonaktifkan',
], 'Asset Edit mengarahkan Missing Recovery ke halaman scan');

if (is_file(projectPath($root, $assetEdit))) {
    $assetEditContent = (string) file_get_contents(projectPath($root, $assetEdit));
    result(
        ! str_contains($assetEditContent, '>Confirm Found<')
            && ! str_contains($assetEditContent, 'name="found_condition"'),
        'Form recovery manual lama sudah tidak aktif'
    );
}

containsAll($root, $routes, [
    'INVENTORY_MISSING_RECOVERY_SCAN_ROUTES_V1',
    "Route::get('{id}/recover-missing-scan', 'create')",
    "Route::post('{id}/recover-missing-scan', 'store')",
    "admin.inventory.assets.missing-recovery.scan",
    "admin.inventory.assets.missing-recovery.store",
], 'Route GET/POST recovery scan terpasang');

containsAll($root, $dataGrid, [
    'INVENTORY_MISSING_RECOVERY_MOVEMENT_V1',
    'Missing Recovered',
    "'missing_recovered'",
    "'missing_recovery'",
    'MISSING RECOVERED',
], 'Inventory Movement mengenali recovery missing');

foreach ([$service, $controller, $dataGrid, $routes] as $relative) {
    if (is_file(projectPath($root, $relative))) {
        lintFile($root, $relative);
    }
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

        result(
            Illuminate\Support\Facades\Route::has('admin.inventory.assets.missing-recovery.scan'),
            'Route terdaftar: admin.inventory.assets.missing-recovery.scan'
        );
        result(
            Illuminate\Support\Facades\Route::has('admin.inventory.assets.missing-recovery.store'),
            'Route terdaftar: admin.inventory.assets.missing-recovery.store'
        );

        $requiredTables = [
            'inventory_assets',
            'inventory_items',
            'inventory_stock_movements',
            'warehouses',
        ];

        foreach ($requiredTables as $table) {
            result(
                Illuminate\Support\Facades\Schema::hasTable($table),
                'Tabel tersedia: '.$table
            );
        }

        if (Illuminate\Support\Facades\Schema::hasTable('inventory_assets')) {
            foreach (['asset_code', 'barcode_value', 'status', 'condition', 'warehouse_id', 'notes'] as $column) {
                result(
                    Illuminate\Support\Facades\Schema::hasColumn('inventory_assets', $column),
                    'Kolom inventory_assets.'.$column.' tersedia'
                );
            }
        }

        if (Illuminate\Support\Facades\Schema::hasTable('inventory_stock_movements')) {
            foreach (['inventory_asset_id', 'movement_type', 'from_status', 'to_status', 'reference_type', 'performed_by', 'occurred_at'] as $column) {
                result(
                    Illuminate\Support\Facades\Schema::hasColumn('inventory_stock_movements', $column),
                    'Kolom movement.'.$column.' tersedia'
                );
            }

            $recoveryCount = Illuminate\Support\Facades\DB::table('inventory_stock_movements')
                ->where('movement_type', 'missing_recovered')
                ->count();
            echo '[INFO] Recovery movement yang sudah tercatat: '.$recoveryCount.PHP_EOL;
        }

        $testAsset = new Webkul\Warehouse\Models\InventoryAsset([
            'asset_code' => 'CHECK-ASSET-001',
            'barcode_value' => 'QR-CHECK-001',
        ]);
        $serviceInstance = $app->make(Webkul\Admin\Services\InventoryMissingRecoveryService::class);
        result(
            $serviceInstance->barcodeMatches($testAsset, ' qr-check-001 ')
                && $serviceInstance->barcodeMatches($testAsset, 'check-asset-001')
                && ! $serviceInstance->barcodeMatches($testAsset, 'WRONG-CODE'),
            'Simulasi pencocokan barcode benar dan kode salah ditolak'
        );

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

echo '[PASS] Missing Asset Recovery wajib scan, kondisi terkonfirmasi, dan movement terlacak.'.PHP_EOL;
echo 'Uji browser: buka asset MISSING → Scan Barang Ditemukan → scan label yang sama → konfirmasi.'.PHP_EOL;
