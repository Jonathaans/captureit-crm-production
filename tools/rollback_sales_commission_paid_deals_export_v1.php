<?php

declare(strict_types=1);

const ROLLBACK_TITLE = 'ROLLBACK SALES COMMISSION — PAID DEALS EXPORT V1';

$root = dirname(__DIR__);
$pointer = $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
    .DIRECTORY_SEPARATOR.'sales-commission-paid-deals-export-v1-latest.txt';
$allowed = [
    'packages/Webkul/Admin/src/Services/SalesCommissionExportService.php',
    'packages/Webkul/Admin/src/Http/Controllers/Invoice/SalesCommissionExportController.php',
    'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php',
    'packages/Webkul/Admin/src/Resources/views/invoices/finance-sales-dashboard.blade.php',
];

function sceRollbackPath(string $root, string $relative): string
{
    return $root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function sceRollbackWrite(string $path, string $content): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        throw new RuntimeException('Folder tidak dapat dibuat: '.$directory);
    }

    $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        throw new RuntimeException('File sementara tidak dapat ditulis: '.$temporary);
    }

    if (is_file($path) && ! unlink($path)) {
        @unlink($temporary);
        throw new RuntimeException('File target tidak dapat diganti: '.$path);
    }

    if (! rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('File target tidak dapat disimpan: '.$path);
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

    if ($backupDirectory === '' || ! is_dir($backupDirectory) || ! is_file($manifestPath)) {
        throw new RuntimeException('Backup terakhir tidak valid.');
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
    $files = $manifest['files'] ?? null;

    if (! is_array($files) || array_keys($files) !== $allowed) {
        throw new RuntimeException('Manifest backup tidak sesuai target patch V1.');
    }

    foreach (array_reverse($allowed) as $relative) {
        $target = sceRollbackPath($root, $relative);
        $existed = (bool) ($files[$relative]['existed'] ?? false);

        if ($existed) {
            $backup = sceRollbackPath($backupDirectory.DIRECTORY_SEPARATOR.'files', $relative);

            if (! is_file($backup)) {
                throw new RuntimeException('File backup tidak ditemukan: '.$backup);
            }

            sceRollbackWrite($target, (string) file_get_contents($backup));
            echo '[RESTORE] '.$relative.PHP_EOL;
        } elseif (is_file($target)) {
            if (! unlink($target)) {
                throw new RuntimeException('File baru tidak dapat dihapus: '.$target);
            }

            echo '[REMOVE]  '.$relative.PHP_EOL;
        }
    }

    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg('artisan').' '.escapeshellarg('optimize:clear');
    $previous = getcwd();
    chdir($root);
    passthru($command, $exitCode);

    if ($previous !== false) {
        chdir($previous);
    }

    if ($exitCode !== 0) {
        throw new RuntimeException('Source sudah dipulihkan, tetapi artisan optimize:clear gagal.');
    }

    echo PHP_EOL.'ROLLBACK BERHASIL dari: '.$backupDirectory.PHP_EOL;
} catch (Throwable $exception) {
    echo PHP_EOL.'ROLLBACK GAGAL: '.$exception->getMessage().PHP_EOL;
    exit(1);
}
