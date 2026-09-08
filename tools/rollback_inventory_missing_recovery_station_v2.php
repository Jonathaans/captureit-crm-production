<?php

declare(strict_types=1);

const TITLE = 'ROLLBACK INVENTORY MISSING RECOVERY STATION V2';
const PATCH_SLUG = 'inventory-missing-recovery-station-v2';

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

function writeFileStrict(string $path, string $content): void
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
        fail('Pointer backup V2 tidak ditemukan.');
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
        fail('Lokasi backup V2 tidak valid.');
    }

    $manifestPath = $backupReal.DIRECTORY_SEPARATOR.'manifest.json';

    if (! is_file($manifestPath)) {
        fail('Manifest backup V2 tidak ditemukan.');
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    $files = is_array($manifest) ? ($manifest['files'] ?? null) : null;

    if (! is_array($files) || $files === []) {
        fail('Manifest backup V2 tidak valid.');
    }

    foreach ($files as $entry) {
        if (! is_array($entry) || ! isset($entry['path'], $entry['existed'])) {
            fail('Entry manifest backup V2 tidak valid.');
        }

        $relative = (string) $entry['path'];

        if (
            str_contains($relative, '..')
            || str_starts_with($relative, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $relative)
        ) {
            fail('Path backup tidak aman: '.$relative);
        }

        $target = $root.DIRECTORY_SEPARATOR.normalizePath($relative);

        if ((bool) $entry['existed']) {
            $source = $backupReal.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.normalizePath($relative);

            if (! is_file($source)) {
                fail('File backup tidak ditemukan: '.$relative);
            }

            writeFileStrict($target, (string) file_get_contents($source));
            line('[RESTORE] '.$relative);
        } elseif (is_file($target)) {
            if (! unlink($target)) {
                fail('Tidak dapat menghapus file baru: '.$relative);
            }

            line('[REMOVE] '.$relative);
        }
    }

    runCommand($root, ['artisan', 'route:clear']);
    runCommand($root, ['artisan', 'view:clear']);
    runCommand($root, ['artisan', 'optimize:clear']);

    line();
    line('ROLLBACK V2 BERHASIL. Source kembali ke kondisi sebelum Recovery Station.');
} catch (Throwable $exception) {
    line();
    line('ROLLBACK GAGAL: '.$exception->getMessage());
    exit(1);
}
