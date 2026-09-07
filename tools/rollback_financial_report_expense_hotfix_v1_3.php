<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$pointer = $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
    .DIRECTORY_SEPARATOR.'financial-report-expense-hotfix-v1_3-latest.txt';

echo 'ROLLBACK FINANCIAL REPORT + EXPENSE EXPORT HOTFIX V1.3'.PHP_EOL;
echo '======================================================='.PHP_EOL.PHP_EOL;

if (! is_file($pointer)) {
    fwrite(STDERR, 'ROLLBACK GAGAL: Pointer backup tidak ditemukan.'.PHP_EOL);
    exit(1);
}

$backupDirectory = trim((string) file_get_contents($pointer));
$manifestPath = $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json';

if (! is_file($manifestPath)) {
    fwrite(STDERR, 'ROLLBACK GAGAL: Manifest backup tidak ditemukan.'.PHP_EOL);
    exit(1);
}

$manifest = json_decode((string) file_get_contents($manifestPath), true);

if (! is_array($manifest) || ! isset($manifest['files'])) {
    fwrite(STDERR, 'ROLLBACK GAGAL: Manifest backup tidak valid.'.PHP_EOL);
    exit(1);
}

foreach ($manifest['files'] as $relative => $metadata) {
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    $source = $backupDirectory.DIRECTORY_SEPARATOR.'files'
        .DIRECTORY_SEPARATOR.$normalized;
    $target = $root.DIRECTORY_SEPARATOR.$normalized;

    if (! is_file($source)) {
        fwrite(STDERR, 'ROLLBACK GAGAL: Backup hilang: '.$relative.PHP_EOL);
        exit(1);
    }

    $directory = dirname($target);
    if (! is_dir($directory) && ! mkdir($directory, 0775, true)) {
        fwrite(STDERR, 'ROLLBACK GAGAL: Tidak dapat membuat folder.'.PHP_EOL);
        exit(1);
    }

    if (file_put_contents($target, (string) file_get_contents($source)) === false) {
        fwrite(STDERR, 'ROLLBACK GAGAL: Tidak dapat memulihkan '.$relative.PHP_EOL);
        exit(1);
    }

    echo '[RESTORE] '.$relative.PHP_EOL;
}

$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg('artisan')
    .' '.escapeshellarg('optimize:clear');
$current = getcwd();
chdir($root);
passthru($command);
chdir($current ?: $root);

echo PHP_EOL.'ROLLBACK BERHASIL.'.PHP_EOL;
