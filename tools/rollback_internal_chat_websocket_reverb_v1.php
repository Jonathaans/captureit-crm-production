<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$backupBase = __DIR__.'/backups';
$requested = $argv[1] ?? null;

echo "ROLLBACK INTERNAL CHAT WEBSOCKET REVERB V1\n";
echo "==========================================\n\n";

if ($requested !== null) {
    $backup = realpath($requested) ?: realpath($backupBase.'/'.$requested);
} else {
    $candidates = glob($backupBase.'/internal-chat-websocket-reverb-v1-*', GLOB_ONLYDIR) ?: [];
    rsort($candidates, SORT_STRING);
    $backup = $candidates[0] ?? false;
}

if (! $backup || ! is_file($backup.'/manifest.json')) {
    fwrite(STDERR, "Backup rollback tidak ditemukan.\n");
    exit(1);
}

$manifest = json_decode((string) file_get_contents($backup.'/manifest.json'), true);

if (! is_array($manifest)) {
    fwrite(STDERR, "Manifest backup tidak valid.\n");
    exit(1);
}

foreach ($manifest as $relative => $entry) {
    $target = $root.'/'.$relative;
    $source = $backup.'/'.$relative;

    if (! empty($entry['existed'])) {
        if (! is_file($source)) {
            fwrite(STDERR, "Backup file hilang: {$relative}\n");
            exit(1);
        }

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        copy($source, $target);
        echo '[RESTORE] '.$relative.PHP_EOL;
    } elseif (is_file($target)) {
        unlink($target);
        echo '[DELETE]  '.$relative.PHP_EOL;
    }
}

echo "\nROLLBACK BERHASIL dari {$backup}.\n";
echo "composer.json, composer.lock, dan package-lock.json telah dipulihkan sesuai manifest.\n";
echo "Folder vendor/node_modules tidak dihapus otomatis; jalankan composer install dan npm install bila perlu.\n";
