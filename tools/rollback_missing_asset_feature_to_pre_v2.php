<?php
declare(strict_types=1);

echo "ROLLBACK MISSING ASSET FEATURE TO PRE-V2\n";
echo "========================================\n\n";

$root = dirname(__DIR__);
chdir($root);

$marker = '.bak-missing-asset-qr-recovery-v2-';
$restored = [];
$removed = [];
$warnings = [];

function norm(string $path): string
{
    return str_replace('\\', '/', $path);
}

function collectPreV2Backups(string $root, string $marker): array
{
    $groups = [];

    $scanRoots = [
        $root.'/routes',
        $root.'/packages/Webkul/Admin/src',
    ];

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
            if (! $file->isFile()) {
                continue;
            }

            $name = $file->getFilename();

            if (! str_contains($name, $marker)) {
                continue;
            }

            $backup = $file->getPathname();

            $original = preg_replace(
                '/\.bak-missing-asset-qr-recovery-v2-\d{8}-\d{6}$/',
                '',
                $backup
            );

            if (! is_string($original) || $original === $backup) {
                continue;
            }

            $groups[$original][] = $backup;
        }
    }

    return $groups;
}

function earliestBackup(array $backups): string
{
    usort(
        $backups,
        function (string $a, string $b): int {
            $ta = filemtime($a) ?: PHP_INT_MAX;
            $tb = filemtime($b) ?: PHP_INT_MAX;

            if ($ta === $tb) {
                return strcmp($a, $b);
            }

            return $ta <=> $tb;
        }
    );

    return $backups[0];
}

$groups = collectPreV2Backups($root, $marker);

if ($groups === []) {
    fwrite(
        STDERR,
        "[FAIL] Backup pre-V2 tidak ditemukan.\n"
        ."Jangan lanjutkan rollback manual karena state awal tidak bisa dipastikan.\n"
    );

    exit(1);
}

echo "Backup pre-V2 yang ditemukan:\n";

foreach ($groups as $original => $backups) {
    $chosen = earliestBackup($backups);

    echo " - ".norm($original)."\n";
    echo "   <= ".norm($chosen)."\n";
}

echo "\nMemulihkan state sebelum Missing Asset QR Recovery V2...\n\n";

foreach ($groups as $original => $backups) {
    $chosen = earliestBackup($backups);

    $dir = dirname($original);

    if (
        ! is_dir($dir)
        && ! mkdir($dir, 0775, true)
        && ! is_dir($dir)
    ) {
        $warnings[] = "Gagal membuat folder: {$dir}";
        continue;
    }

    if (! copy($chosen, $original)) {
        $warnings[] = "Gagal restore: {$original}";
        continue;
    }

    $restored[] = $original;

    echo "[RESTORE] ".norm($original)."\n";
}

/*
 * Files introduced only after the quoted design discussion.
 * Remove them after restoring pre-V2 source files.
 */
$newFiles = [
    $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/MissingAssetQrRecoveryController.php',
    $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/MissingAssetRecoveryScannerPageController.php',
    $root.'/packages/Webkul/Admin/src/Resources/views/inventory/assets/missing-recovery-scan.blade.php',
];

foreach ($newFiles as $file) {
    if (! is_file($file)) {
        continue;
    }

    $text = (string) @file_get_contents($file);

    $ours =
        str_contains($text, 'MISSING_ASSET_QR_RECOVERY_V2')
        || str_contains($text, 'MISSING_ASSET_DEDICATED_SCANNER_V3');

    if (! $ours) {
        $warnings[] =
            "Tidak menghapus file karena marker patch tidak ditemukan: {$file}";
        continue;
    }

    if (@unlink($file)) {
        $removed[] = $file;

        echo "[REMOVE]  ".norm($file)."\n";
    } else {
        $warnings[] = "Gagal hapus: {$file}";
    }
}

/*
 * Clear Laravel caches. No database rows are touched by this rollback.
 */
exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' optimize:clear 2>&1',
    $clearOut,
    $clearCode
);

echo "\n";

if ($clearCode === 0) {
    echo "[OK] Laravel cache dibersihkan.\n";
} else {
    $warnings[] =
        "optimize:clear gagal:\n"
        .implode(PHP_EOL, $clearOut);
}

/*
 * Sanity check: none of the post-design patch markers/routes should remain.
 */
$scanFiles = [
    $root.'/routes/web.php',
    $root.'/packages/Webkul/Admin/src/Resources/views/inventory/assets/edit.blade.php',
    $root.'/packages/Webkul/Admin/src/DataGrids/Inventory/InventoryMovementDataGrid.php',
];

$leftovers = [];

foreach ($scanFiles as $file) {
    if (! is_file($file)) {
        continue;
    }

    $text = (string) file_get_contents($file);

    foreach ([
        'MISSING_ASSET_QR_RECOVERY_V2',
        'MISSING_ASSET_SCANNER_ONLY_V2_1',
        'MISSING_ASSET_SCANNER_FOCUS_FIX_V2_2',
        'MISSING_ASSET_SCANNER_GLOBAL_CAPTURE_V2_3',
        'MISSING_ASSET_DEDICATED_SCANNER_V3',
        'admin.inventory.assets.missing-recover-scan',
        'admin.inventory.assets.missing-recovery.scanner',
    ] as $needle) {
        if (str_contains($text, $needle)) {
            $leftovers[] =
                norm($file).' contains '.$needle;
        }
    }
}

echo "\nSUMMARY\n";
echo "-------\n";
echo "Restored files : ".count($restored)."\n";
echo "Removed files  : ".count($removed)."\n";
echo "DB changes     : NONE\n";

if ($warnings !== []) {
    echo "\nWARNINGS\n";

    foreach ($warnings as $warning) {
        echo " - {$warning}\n";
    }
}

if ($leftovers !== []) {
    echo "\n[FAIL] Post-design patch leftovers masih ditemukan:\n";

    foreach ($leftovers as $leftover) {
        echo " - {$leftover}\n";
    }

    exit(1);
}

echo "\nHASIL: PASS\n";
echo "State source dikembalikan ke backup sebelum Missing Asset QR Recovery V2 pertama kali dipasang.\n";
echo "Tidak ada data inventory/database yang diubah oleh rollback ini.\n";
