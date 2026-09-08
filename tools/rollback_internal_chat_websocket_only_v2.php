<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$latest = $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.'internal-chat-websocket-only-v2-latest.txt';

echo "ROLLBACK INTERNAL CHAT WEBSOCKET-ONLY V2\n";
echo "=========================================\n\n";

if (! is_file($latest)) {
    fwrite(STDERR, "Backup V2 tidak ditemukan.\n");
    exit(1);
}

$backupRoot = trim((string) file_get_contents($latest));
$manifestPath = $backupRoot.DIRECTORY_SEPARATOR.'manifest.json';

if ($backupRoot === '' || ! is_dir($backupRoot) || ! is_file($manifestPath)) {
    fwrite(STDERR, "Folder backup/manifest V2 tidak valid.\n");
    exit(1);
}

$manifest = json_decode((string) file_get_contents($manifestPath), true);

if (! is_array($manifest) || ! is_array($manifest['files'] ?? null)) {
    fwrite(STDERR, "Manifest backup V2 rusak.\n");
    exit(1);
}

foreach ($manifest['files'] as $relative => $metadata) {
    $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $relative);
    $target = $root.DIRECTORY_SEPARATOR.$relativePath;
    $backup = $backupRoot.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.$relativePath;

    if (($metadata['existed'] ?? false) === true) {
        if (! is_file($backup)) {
            fwrite(STDERR, "Backup file hilang: {$relative}\n");
            exit(1);
        }

        if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
            fwrite(STDERR, "Folder target tidak dapat dibuat: {$relative}\n");
            exit(1);
        }

        if (! copy($backup, $target)) {
            fwrite(STDERR, "Gagal memulihkan: {$relative}\n");
            exit(1);
        }
    } elseif (is_file($target) && ! unlink($target)) {
        fwrite(STDERR, "Gagal menghapus file baru: {$relative}\n");
        exit(1);
    }

    echo '[RESTORE] '.$relative.PHP_EOL;
}

$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.DIRECTORY_SEPARATOR.'artisan').' optimize:clear';
passthru($command, $exitCode);

echo "\nROLLBACK SOURCE SELESAI.\n";
echo "Build ulang asset Admin agar JavaScript V1 kembali:\n";
echo "cd packages\\Webkul\\Admin\n";
echo "npm run build\n";
echo "cd ..\\..\\..\n";

exit((int) $exitCode);
