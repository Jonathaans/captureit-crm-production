<?php

declare(strict_types=1);

echo "ROLLBACK DELIVERY ORDER ADD ITEM HOTFIX V1.2\n";
echo "==============================================\n\n";

$root = realpath(dirname(__DIR__));
if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "ROLLBACK GAGAL: Jalankan dari root Laravel.\n");
    exit(1);
}

$pattern = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private'
    .DIRECTORY_SEPARATOR.'patch-backups'.DIRECTORY_SEPARATOR.'delivery-order-add-item-hotfix-v1-2-*';
$backups = glob($pattern, GLOB_ONLYDIR) ?: [];
rsort($backups);
$backup = $backups[0] ?? null;

if ($backup === null || ! is_file($backup.DIRECTORY_SEPARATOR.'manifest.json')) {
    fwrite(STDERR, "ROLLBACK GAGAL: Backup hotfix V1.2 tidak ditemukan.\n");
    exit(1);
}

$manifest = json_decode((string) file_get_contents($backup.DIRECTORY_SEPARATOR.'manifest.json'), true);
$files = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];

foreach ($files as $relative => $metadata) {
    $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) $relative);
    $source = $backup.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) $relative);
    $existed = (bool) ($metadata['existed'] ?? false);

    if ($existed) {
        if (! is_file($source) || copy($source, $target) === false) {
            fwrite(STDERR, "ROLLBACK GAGAL saat memulihkan {$relative}.\n");
            exit(1);
        }

        echo '[RESTORE] '.$relative.PHP_EOL;
        continue;
    }

    if (is_file($target) && ! unlink($target)) {
        fwrite(STDERR, "ROLLBACK GAGAL saat menghapus {$relative}.\n");
        exit(1);
    }

    echo '[REMOVE] '.$relative.PHP_EOL;
}

echo "\nROLLBACK BERHASIL dari {$backup}.\n";
echo "Jalankan: php artisan optimize:clear\n";
