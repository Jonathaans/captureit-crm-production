<?php
declare(strict_types=1);

echo "CHECK ROLLBACK TO PRE-V2\n";
echo "========================\n\n";

$root = dirname(__DIR__);
chdir($root);

$fails = 0;

function ck(bool $ok, string $label): void
{
    global $fails;

    echo ($ok ? '[OK]   ' : '[FAIL] ').$label."\n";

    if (! $ok) {
        $fails++;
    }
}

$targets = [
    $root.'/routes/web.php',
    $root.'/packages/Webkul/Admin/src/Resources/views/inventory/assets/edit.blade.php',
    $root.'/packages/Webkul/Admin/src/DataGrids/Inventory/InventoryMovementDataGrid.php',
];

$forbidden = [
    'MISSING_ASSET_QR_RECOVERY_V2',
    'MISSING_ASSET_SCANNER_ONLY_V2_1',
    'MISSING_ASSET_SCANNER_FOCUS_FIX_V2_2',
    'MISSING_ASSET_SCANNER_GLOBAL_CAPTURE_V2_3',
    'MISSING_ASSET_DEDICATED_SCANNER_V3',
    'admin.inventory.assets.missing-recover-scan',
    'admin.inventory.assets.missing-recovery.scanner',
];

foreach ($targets as $file) {
    if (! is_file($file)) {
        continue;
    }

    $text = (string) file_get_contents($file);

    foreach ($forbidden as $needle) {
        ck(
            ! str_contains($text, $needle),
            basename($file).' bebas dari '.$needle
        );
    }
}

$newFiles = [
    $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/MissingAssetQrRecoveryController.php',
    $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/MissingAssetRecoveryScannerPageController.php',
    $root.'/packages/Webkul/Admin/src/Resources/views/inventory/assets/missing-recovery-scan.blade.php',
];

foreach ($newFiles as $file) {
    ck(
        ! is_file($file),
        basename($file).' sudah tidak ada'
    );
}

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' route:list 2>&1',
    $routeOut,
    $routeCode
);

$routeText = implode("\n", $routeOut);

ck(
    $routeCode === 0,
    'Laravel route:list berjalan'
);

ck(
    ! str_contains(
        $routeText,
        'admin.inventory.assets.missing-recover-scan'
    ),
    'POST recovery route V2 sudah hilang'
);

ck(
    ! str_contains(
        $routeText,
        'admin.inventory.assets.missing-recovery.scanner'
    ),
    'Dedicated scanner route V3 sudah hilang'
);

echo "\n";

if ($fails > 0) {
    echo "HASIL: FAIL ({$fails} masalah)\n";
    exit(1);
}

echo "HASIL: PASS\n";
echo "Rollback source ke state pre-V2 terverifikasi.\n";
