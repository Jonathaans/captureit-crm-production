<?php

declare(strict_types=1);

echo "ROLLBACK DELIVERY ORDER UNLIMITED ITEMS + PDF BOUNDARY V1\n";
echo "===========================================================\n\n";

$root = realpath(dirname(__DIR__));
if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "ROLLBACK GAGAL: Jalankan dari root Laravel.\n");
    exit(1);
}

$pattern = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private'
    .DIRECTORY_SEPARATOR.'patch-backups'.DIRECTORY_SEPARATOR.'delivery-order-unlimited-pdf-boundary-v1-*';
$backups = glob($pattern, GLOB_ONLYDIR) ?: [];
rsort($backups);
$backup = $backups[0] ?? null;

if ($backup === null || ! is_file($backup.DIRECTORY_SEPARATOR.'manifest.json')) {
    fwrite(STDERR, "ROLLBACK GAGAL: Backup patch V1 tidak ditemukan.\n");
    exit(1);
}

$manifest = json_decode((string) file_get_contents($backup.DIRECTORY_SEPARATOR.'manifest.json'), true);
$files = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];

if ($files === []) {
    fwrite(STDERR, "ROLLBACK GAGAL: Manifest tidak berisi file.\n");
    exit(1);
}

foreach ($files as $relative) {
    $relative = (string) $relative;
    $source = $backup.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (! is_file($source) || copy($source, $target) === false) {
        fwrite(STDERR, "ROLLBACK GAGAL saat memulihkan {$relative}.\n");
        exit(1);
    }

    echo '[RESTORE] '.$relative.PHP_EOL;
}

echo "\nROLLBACK BERHASIL dari {$backup}.\n";
echo "Jalankan: php artisan view:clear\n";

