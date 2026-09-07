<?php
declare(strict_types=1);

echo "ROLLBACK RETURN MISSING -> FOUND V1.1\n";
echo "=====================================\n\n";

$root = dirname(__DIR__);
$restored = 0;

$scanRoots = [
    $root.'/routes',
    $root.'/packages/Webkul/Admin/src/Resources/views',
];

foreach ($scanRoots as $scanRoot) {
    if (! is_dir($scanRoot)) {
        continue;
    }

    $groups = [];

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
                '.bak-return-missing-found-v1_1-'
            )
        ) {
            continue;
        }

        $backup = $file->getPathname();

        $original = preg_replace(
            '/\.bak-return-missing-found-v1_1-\d{8}-\d{6}$/',
            '',
            $backup
        );

        if (is_string($original)) {
            $groups[$original][] = $backup;
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
}

$controller =
    $root
    .'/packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/MissingAssetReturnRecoveryController.php';

if (
    is_file($controller)
    && str_contains(
        (string) file_get_contents($controller),
        'RETURN_MISSING_FOUND_V1_1'
    )
) {
    unlink($controller);
    echo "[OK] Controller V1.1 dihapus.\n";
}

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' optimize:clear 2>&1'
);

echo "\nRestored files: {$restored}\n";
echo "Rollback selesai.\n";
