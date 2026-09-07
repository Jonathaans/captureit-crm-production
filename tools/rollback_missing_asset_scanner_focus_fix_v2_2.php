<?php
declare(strict_types=1);

echo "ROLLBACK MISSING ASSET SCANNER FOCUS FIX V2.2\n";
echo "=============================================\n\n";

$root = dirname(__DIR__);
$viewBase = $root.'/packages/Webkul/Admin/src/Resources/views/inventory';

$backups = [];

if (is_dir($viewBase)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $viewBase,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($it as $file) {
        if (
            $file->isFile()
            && str_contains(
                $file->getFilename(),
                '.bak-missing-asset-scanner-focus-v2_2-'
            )
        ) {
            $backups[] = $file->getPathname();
        }
    }
}

if ($backups === []) {
    fwrite(STDERR, "[FAIL] Backup view V2.2 tidak ditemukan.\n");
    exit(1);
}

usort(
    $backups,
    fn ($a, $b) => filemtime($b) <=> filemtime($a)
);

$backup = $backups[0];

$original = preg_replace(
    '/\.bak-missing-asset-scanner-focus-v2_2-\d{8}-\d{6}$/',
    '',
    $backup
);

if (! is_string($original) || ! copy($backup, $original)) {
    fwrite(STDERR, "[FAIL] Gagal restore view.\n");
    exit(1);
}

$controller =
    $root
    .'/packages/Webkul/Admin/src/Http/Controllers/Inventory/MissingAssetQrRecoveryController.php';

$controllerBackups =
    glob(
        $controller
        .'.bak-missing-asset-scanner-focus-v2_2-*'
    ) ?: [];

if ($controllerBackups !== []) {
    usort(
        $controllerBackups,
        fn ($a, $b) => filemtime($b) <=> filemtime($a)
    );

    @copy($controllerBackups[0], $controller);
    echo "[OK] Controller V2 restored.\n";
}

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' optimize:clear 2>&1'
);

echo "[OK] View restored:\n{$original}\n";
echo "[OK] optimize:clear selesai.\n";
echo "Rollback selesai.\n";
