<?php

declare(strict_types=1);

const ROLLBACK_TITLE = 'ROLLBACK CRM FINANCE & SALES DASHBOARD V1';

$root = dirname(__DIR__);
$pointer = $root.DIRECTORY_SEPARATOR.'tools'
    .DIRECTORY_SEPARATOR.'backups'
    .DIRECTORY_SEPARATOR.'finance-sales-dashboard-v1-latest.txt';

function fsdRollbackWrite(string $path, string $content): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        throw new RuntimeException('Tidak dapat membuat folder: '.$directory);
    }

    if (file_put_contents($path, $content, LOCK_EX) === false) {
        throw new RuntimeException('Tidak dapat menulis: '.$path);
    }
}

echo ROLLBACK_TITLE.PHP_EOL;
echo str_repeat('=', strlen(ROLLBACK_TITLE)).PHP_EOL.PHP_EOL;

try {
    if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
        throw new RuntimeException('Jalankan tool dari root project Laravel.');
    }

    if (! is_file($pointer)) {
        throw new RuntimeException('Pointer backup tidak ditemukan: '.$pointer);
    }

    $backupDirectory = trim((string) file_get_contents($pointer));
    $manifestPath = $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json';

    if (! is_file($manifestPath)) {
        throw new RuntimeException('Manifest backup tidak ditemukan.');
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);

    if (! is_array($manifest) || ! isset($manifest['files'])) {
        throw new RuntimeException('Manifest backup tidak valid.');
    }

    foreach ($manifest['files'] as $relative => $metadata) {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
        $target = $root.DIRECTORY_SEPARATOR.$normalized;
        $backup = $backupDirectory.DIRECTORY_SEPARATOR.'files'
            .DIRECTORY_SEPARATOR.$normalized;

        if (($metadata['existed'] ?? false) === true) {
            if (! is_file($backup)) {
                throw new RuntimeException('File backup tidak ditemukan: '.$relative);
            }

            fsdRollbackWrite($target, (string) file_get_contents($backup));
            echo '[RESTORE] '.$relative.PHP_EOL;
        } elseif (is_file($target)) {
            unlink($target);
            echo '[REMOVE]  '.$relative.PHP_EOL;
        }
    }

    $command = escapeshellarg(PHP_BINARY).' artisan optimize:clear';
    $previous = getcwd();
    chdir($root);
    passthru($command, $exitCode);

    if ($previous !== false) {
        chdir($previous);
    }

    if ((int) $exitCode !== 0) {
        throw new RuntimeException('Source pulih, tetapi optimize:clear gagal.');
    }

    echo PHP_EOL.'[PASS] Rollback selesai dari '.$backupDirectory.PHP_EOL;
} catch (Throwable $exception) {
    echo PHP_EOL.'ROLLBACK GAGAL: '.$exception->getMessage().PHP_EOL;
    exit(1);
}
