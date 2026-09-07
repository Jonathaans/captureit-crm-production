<?php

declare(strict_types=1);

const TITLE = 'ROLLBACK INVENTORY MISSING RECOVERY BY BARCODE SCAN V1';
const PATCH_SLUG = 'inventory-missing-recovery-scan-v1';

$root = dirname(__DIR__);

function line(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function fail(string $message): never
{
    throw new RuntimeException($message);
}

function normalizePath(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function writeFile(string $path, string $content): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true)) {
        fail('Tidak dapat membuat folder: '.$directory);
    }

    if (file_put_contents($path, $content) === false) {
        fail('Tidak dapat menulis file: '.$path);
    }
}

function runCommand(string $root, array $arguments): int
{
    $command = escapeshellarg(PHP_BINARY);

    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg((string) $argument);
    }

    line('[RUN]   '.implode(' ', $arguments));
    $current = getcwd();
    chdir($root);
    passthru($command, $exitCode);
    chdir($current ?: $root);

    return (int) $exitCode;
}

line(TITLE);
line(str_repeat('=', strlen(TITLE)));
line();

try {
    if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
        fail('Jalankan script ini dari root project Laravel CRM.');
    }

    $pointer = $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
        .DIRECTORY_SEPARATOR.PATCH_SLUG.'-latest.txt';

    if (! is_file($pointer)) {
        fail('Pointer backup tidak ditemukan: '.$pointer);
    }

    $backupDirectory = trim((string) file_get_contents($pointer));
    $backupReal = realpath($backupDirectory);
    $allowedRoot = realpath($root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups');

    if (
        $backupReal === false
        || $allowedRoot === false
        || ! str_starts_with($backupReal, $allowedRoot.DIRECTORY_SEPARATOR)
        || ! str_starts_with(basename($backupReal), PATCH_SLUG.'-')
    ) {
        fail('Lokasi backup tidak valid atau berada di luar tools/backups.');
    }

    $manifestPath = $backupReal.DIRECTORY_SEPARATOR.'manifest.json';

    if (! is_file($manifestPath)) {
        fail('Manifest backup tidak ditemukan.');
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);

    if (! is_array($manifest) || ! is_array($manifest['files'] ?? null)) {
        fail('Manifest backup tidak valid.');
    }

    foreach ($manifest['files'] as $relative => $metadata) {
        $target = $root.DIRECTORY_SEPARATOR.normalizePath((string) $relative);
        $source = $backupReal.DIRECTORY_SEPARATOR.'files'
            .DIRECTORY_SEPARATOR.normalizePath((string) $relative);

        if (($metadata['existed'] ?? false) === true) {
            if (! is_file($source)) {
                fail('File backup hilang: '.$relative);
            }

            writeFile($target, (string) file_get_contents($source));
            line('[RESTORE] '.$relative);
        } elseif (is_file($target)) {
            if (! unlink($target)) {
                fail('Tidak dapat menghapus file baru: '.$relative);
            }

            line('[DELETE]  '.$relative);
        }
    }

    runCommand($root, ['artisan', 'route:clear']);
    runCommand($root, ['artisan', 'view:clear']);
    runCommand($root, ['artisan', 'optimize:clear']);

    line();
    line('ROLLBACK BERHASIL. Source kembali ke kondisi sebelum patch V1.');
} catch (Throwable $exception) {
    line();
    line('ROLLBACK GAGAL: '.$exception->getMessage());
    exit(1);
}
