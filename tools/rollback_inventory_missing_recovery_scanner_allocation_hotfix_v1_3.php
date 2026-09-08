<?php

declare(strict_types=1);

const TITLE = 'ROLLBACK INVENTORY MISSING RECOVERY SCANNER ALLOCATION HOTFIX V1.3';
const BACKUP_SLUG = 'inventory-missing-recovery-scanner-allocation-hotfix-v1_3';

$root = dirname(__DIR__);

function line(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function fail(string $message): never
{
    throw new RuntimeException($message);
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
        .DIRECTORY_SEPARATOR.BACKUP_SLUG.'-latest.txt';

    if (! is_file($pointer)) {
        fail('Pointer backup V1.3 tidak ditemukan.');
    }

    $backupDirectory = trim((string) file_get_contents($pointer));
    $backupReal = realpath($backupDirectory);
    $allowedRoot = realpath($root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups');

    if (
        $backupReal === false
        || $allowedRoot === false
        || ! str_starts_with($backupReal, $allowedRoot.DIRECTORY_SEPARATOR)
        || ! str_starts_with(basename($backupReal), BACKUP_SLUG.'-')
    ) {
        fail('Lokasi backup V1.3 tidak valid.');
    }

    $source = $backupReal.DIRECTORY_SEPARATOR.'recover-missing-scan.blade.php';
    $relative = 'packages/Webkul/Admin/src/Resources/views/inventory/assets/recover-missing-scan.blade.php';
    $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (! is_file($source)) {
        fail('File backup halaman recovery tidak ditemukan.');
    }

    if (file_put_contents($target, (string) file_get_contents($source)) === false) {
        fail('Tidak dapat memulihkan halaman recovery.');
    }

    line('[RESTORE] '.$relative);
    runCommand($root, ['artisan', 'view:clear']);
    runCommand($root, ['artisan', 'optimize:clear']);
    line();
    line('ROLLBACK V1.3 BERHASIL.');
} catch (Throwable $exception) {
    line();
    line('ROLLBACK GAGAL: '.$exception->getMessage());
    exit(1);
}
