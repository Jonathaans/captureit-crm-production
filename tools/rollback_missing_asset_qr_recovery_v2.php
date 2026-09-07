<?php
declare(strict_types=1);

echo "ROLLBACK MISSING ASSET QR RECOVERY V2\n";
echo "=====================================\n\n";

$root = dirname(__DIR__);
$restored = 0;

$scanRoots = [
    $root.'/routes',
    $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory',
    $root.'/packages/Webkul/Admin/src/DataGrids/Inventory',
    $root.'/packages/Webkul/Admin/src/Resources/views',
];

$groups = [];

foreach ($scanRoots as $scanRoot) {
    if (! is_dir($scanRoot)) {
        continue;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $scanRoot,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($it as $file) {
        if (
            ! $file->isFile()
            || ! str_contains(
                $file->getFilename(),
                '.bak-missing-asset-qr-recovery-v2-'
            )
        ) {
            continue;
        }

        $backup = $file->getPathname();

        $original = preg_replace(
            '/\.bak-missing-asset-qr-recovery-v2-\d{8}-\d{6}$/',
            '',
            $backup
        );

        if (is_string($original)) {
            $groups[$original][] = $backup;
        }
    }
}

foreach ($groups as $original => $backups) {
    usort(
        $backups,
        fn ($a, $b) => filemtime($b) <=> filemtime($a)
    );

    if (copy($backups[0], $original)) {
        echo "[OK] Restore {$original}\n";
        $restored++;
    }
}

$newController =
    $root
    .'/packages/Webkul/Admin/src/Http/Controllers/Inventory/MissingAssetQrRecoveryController.php';

if (
    is_file($newController)
    && str_contains(
        (string) file_get_contents($newController),
        'MISSING_ASSET_QR_RECOVERY_V2'
    )
) {
    unlink($newController);
    echo "[OK] QR recovery controller dihapus.\n";
}

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' optimize:clear 2>&1'
);

echo "\nRestored files: {$restored}\n";
echo "Rollback selesai.\n";
