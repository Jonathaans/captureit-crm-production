<?php

declare(strict_types=1);

echo "ROLLBACK CRM PRODUCTION OPERATIONS V2\n";
echo "=====================================\n\n";

$root = realpath(__DIR__.DIRECTORY_SEPARATOR.'..');
if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "ROLLBACK GAGAL: Jalankan dari root Laravel; file harus berada di tools.\n");
    exit(1);
}

$pattern = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'patch-backups'.DIRECTORY_SEPARATOR.'crm-production-operations-v2-*';
$directories = array_values(array_filter(glob($pattern) ?: [], 'is_dir'));
rsort($directories, SORT_STRING);
$backup = $directories[0] ?? null;

if (! $backup || ! is_file($backup.DIRECTORY_SEPARATOR.'manifest.json')) {
    fwrite(STDERR, "ROLLBACK GAGAL: backup manifest V2 tidak ditemukan.\n");
    exit(1);
}

$manifest = json_decode((string) file_get_contents($backup.DIRECTORY_SEPARATOR.'manifest.json'), true);
if (! is_array($manifest)) {
    fwrite(STDERR, "ROLLBACK GAGAL: manifest tidak valid.\n");
    exit(1);
}

foreach ((array) ($manifest['overwritten'] ?? []) as $relative) {
    $source = $backup.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) $relative);
    $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) $relative);
    if (! is_file($source)) {
        fwrite(STDERR, "Backup file hilang: {$relative}\n");
        exit(1);
    }
    if (! is_dir(dirname($target))) {
        mkdir(dirname($target), 0775, true);
    }
    copy($source, $target);
    echo "[RESTORE] {$relative}\n";
}

foreach ((array) ($manifest['created'] ?? []) as $relative) {
    $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) $relative);
    if (is_file($target)) {
        unlink($target);
        echo "[DELETE] {$relative}\n";
    }
}

echo "\nFile aplikasi dipulihkan dari {$backup}.\n";
echo "Migration database dan indeks TIDAK dihapus demi keamanan data.\n";
echo "Jalankan: php artisan optimize:clear\n";
