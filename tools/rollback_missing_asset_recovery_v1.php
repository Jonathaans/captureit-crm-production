<?php
declare(strict_types=1);

echo "ROLLBACK MISSING ASSET RECOVERY V1\n";
echo "==================================\n\n";

$root = dirname(__DIR__);

$patterns = [
    $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryAssetController.php.bak-missing-asset-recovery-v1-*',
    $root.'/routes/web.php.bak-missing-asset-recovery-v1-*',
    $root.'/packages/Webkul/Admin/src/DataGrids/Inventory/InventoryMovementDataGrid.php.bak-missing-asset-recovery-v1-*',
    $root.'/packages/Webkul/Admin/src/Resources/views/inventory/**/*.bak-missing-asset-recovery-v1-*',
];

$restored = 0;

/*
 * Restore simple known paths.
 */
foreach (array_slice($patterns, 0, 3) as $pattern) {
    $files = glob($pattern) ?: [];

    if ($files === []) {
        continue;
    }

    usort(
        $files,
        fn ($a, $b) =>
            filemtime($b)
            <=>
            filemtime($a)
    );

    $backup = $files[0];
    $original = preg_replace(
        '/\.bak-missing-asset-recovery-v1-\d{8}-\d{6}$/',
        '',
        $backup
    );

    if (
        is_string($original)
        && copy(
            $backup,
            $original
        )
    ) {
        echo "[OK] {$original}\n";
        $restored++;
    }
}

/*
 * Find any patched Blade backup recursively.
 */
$viewBase =
    $root
    .'/packages/Webkul/Admin/src/Resources/views/inventory';

if (is_dir($viewBase)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $viewBase,
            FilesystemIterator::SKIP_DOTS
        )
    );

    $bladeBackups = [];

    foreach ($it as $file) {
        if (
            $file->isFile()
            && str_contains(
                $file->getFilename(),
                '.bak-missing-asset-recovery-v1-'
            )
        ) {
            $bladeBackups[] =
                $file->getPathname();
        }
    }

    usort(
        $bladeBackups,
        fn ($a, $b) =>
            filemtime($b)
            <=>
            filemtime($a)
    );

    foreach ($bladeBackups as $backup) {
        $original = preg_replace(
            '/\.bak-missing-asset-recovery-v1-\d{8}-\d{6}$/',
            '',
            $backup
        );

        if (
            is_string($original)
            && copy(
                $backup,
                $original
            )
        ) {
            echo "[OK] {$original}\n";
            $restored++;
            break;
        }
    }
}

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' optimize:clear 2>&1'
);

echo "\nRestored files: {$restored}\n";
echo "Rollback selesai.\n";
