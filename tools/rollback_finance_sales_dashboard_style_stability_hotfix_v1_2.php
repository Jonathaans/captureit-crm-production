<?php

declare(strict_types=1);

const ROLLBACK_TITLE = 'ROLLBACK FINANCE & SALES DASHBOARD STYLE STABILITY V1.2';

$root = dirname(__DIR__);
$pointer = $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
    .DIRECTORY_SEPARATOR.'finance-sales-dashboard-style-stability-v1_2-latest.txt';

function fsdsRollbackWrite(string $path, string $content): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        throw new RuntimeException('Folder tidak dapat dibuat: '.$directory);
    }

    if (file_put_contents($path, $content, LOCK_EX) === false) {
        throw new RuntimeException('File tidak dapat ditulis: '.$path);
    }
}

echo ROLLBACK_TITLE.PHP_EOL;
echo str_repeat('=', strlen(ROLLBACK_TITLE)).PHP_EOL.PHP_EOL;

try {
    if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
        throw new RuntimeException('Jalankan tool dari root project Laravel.');
    }

    if (! is_file($pointer)) {
        throw new RuntimeException('Pointer backup V1.2 tidak ditemukan.');
    }

    $backupDirectory = trim((string) file_get_contents($pointer));
    $manifestPath = $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json';
    $manifest = is_file($manifestPath)
        ? json_decode((string) file_get_contents($manifestPath), true)
        : null;

    if (! is_array($manifest) || ! isset($manifest['files'])) {
        throw new RuntimeException('Manifest backup V1.2 tidak valid.');
    }

    foreach ($manifest['files'] as $relative => $metadata) {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $relative);
        $target = $root.DIRECTORY_SEPARATOR.$normalized;
        $backup = $backupDirectory.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.$normalized;

        if (($metadata['existed'] ?? false) !== true || ! is_file($backup)) {
            throw new RuntimeException('File backup hilang: '.$relative);
        }

        fsdsRollbackWrite($target, (string) file_get_contents($backup));
        echo '[RESTORE] '.$relative.PHP_EOL;
    }

    $previous = getcwd();
    chdir($root);
    passthru(escapeshellarg(PHP_BINARY).' artisan optimize:clear', $exitCode);

    if ($previous !== false) {
        chdir($previous);
    }

    if ((int) $exitCode !== 0) {
        throw new RuntimeException('Source dipulihkan, tetapi optimize:clear gagal.');
    }

    echo PHP_EOL.'[PASS] Rollback selesai dari '.$backupDirectory.PHP_EOL;
} catch (Throwable $exception) {
    echo PHP_EOL.'ROLLBACK GAGAL: '.$exception->getMessage().PHP_EOL;
    exit(1);
}

