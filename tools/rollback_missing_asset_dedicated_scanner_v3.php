<?php
declare(strict_types=1);

echo "ROLLBACK MISSING ASSET DEDICATED SCANNER V3\n";
echo "===========================================\n\n";

$root = dirname(__DIR__);
$restored = 0;

$scanRoots = [
    $root.'/routes',
    $root.'/packages/Webkul/Admin/src/Resources/views/inventory',
    $root.'/packages/Webkul/Admin/src/DataGrids/Inventory',
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
                '.bak-missing-asset-dedicated-scanner-v3-'
            )
        ) {
            continue;
        }

        $backup = $file->getPathname();

        $original = preg_replace(
            '/\.bak-missing-asset-dedicated-scanner-v3-\d{8}-\d{6}$/',
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

$newFiles = [
    $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/MissingAssetRecoveryScannerPageController.php',
    $root.'/packages/Webkul/Admin/src/Resources/views/inventory/assets/missing-recovery-scan.blade.php',
];

foreach ($newFiles as $file) {
    if (
        is_file($file)
        && str_contains(
            (string) file_get_contents($file),
            'MISSING_ASSET_DEDICATED_SCANNER_V3'
        )
    ) {
        unlink($file);
        echo "[OK] Hapus {$file}\n";
    }
}

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' optimize:clear 2>&1'
);

echo "\nRestored files: {$restored}\n";
echo "Rollback selesai.\n";
