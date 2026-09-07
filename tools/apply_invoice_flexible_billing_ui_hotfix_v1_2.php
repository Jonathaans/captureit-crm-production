<?php

declare(strict_types=1);

const PATCH_NAME = 'INVOICE FLEXIBLE BILLING UI HOTFIX V1.2';
const MARKER = 'CRM_INVOICE_FLEXIBLE_BILLING_UI_HOTFIX_V1_2';

$root = dirname(__DIR__);
$payloadRoot = __DIR__.DIRECTORY_SEPARATOR
    .'invoice_flexible_billing_ui_hotfix_v1_2_payload';
$writesStarted = false;
$backupDirectory = null;
$manifest = null;

function line(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function fail(string $message): never
{
    throw new RuntimeException($message);
}

function normalize(string $path): string
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

function restoreBackup(
    string $root,
    string $backupDirectory,
    array $manifest
): void {
    foreach ($manifest['files'] as $relative => $metadata) {
        $target = $root.DIRECTORY_SEPARATOR.normalize($relative);
        $backup = $backupDirectory.DIRECTORY_SEPARATOR.'files'
            .DIRECTORY_SEPARATOR.normalize($relative);

        if (($metadata['existed'] ?? false) && is_file($backup)) {
            writeFile($target, (string) file_get_contents($backup));
        } elseif (is_file($target)) {
            unlink($target);
        }
    }
}

line(PATCH_NAME);
line(str_repeat('=', strlen(PATCH_NAME)));
line();

try {
    if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
        fail('Jalankan script dari root project Laravel.');
    }

    $targets = [
        'packages/Webkul/Admin/src/Http/Controllers/Invoice/FlexibleQuoteBillingController.php'
            => '73670cb07e5f9bda0ab4647ec0918509a44050bb447fdc3fbd63c2f5cbf61550',
        'packages/Webkul/Admin/src/Resources/views/invoices/billing-create.blade.php'
            => '05ac3eff92edc70ea3d604a318e7e4cf627a49eabf103003428ec81c02faf3a4',
    ];

    $baseMarkers = [
        'packages/Webkul/Admin/src/Http/Controllers/Invoice/FlexibleQuoteBillingController.php'
            => 'class FlexibleQuoteBillingController extends Controller',
        'packages/Webkul/Admin/src/Resources/views/invoices/billing-create.blade.php'
            => 'id="crm-flexible-billing-form"',
    ];

    foreach ($targets as $relative => $expectedHash) {
        $target = $root.DIRECTORY_SEPARATOR.normalize($relative);
        $payload = $payloadRoot.DIRECTORY_SEPARATOR.normalize($relative);

        if (! is_file($target)) {
            fail('Preflight file fitur V1.1 tidak ditemukan: '.$relative);
        }

        if (! is_file($payload)) {
            fail('Payload tidak lengkap: '.$relative);
        }

        if (hash_file('sha256', $payload) !== $expectedHash) {
            fail('Checksum payload tidak cocok: '.$relative);
        }

        $current = (string) file_get_contents($target);

        if (! str_contains($current, $baseMarkers[$relative])) {
            fail('File bukan berasal dari Flexible Billing V1.1: '.$relative);
        }
    }

    $installed = 0;

    foreach (array_keys($targets) as $relative) {
        $content = (string) file_get_contents(
            $root.DIRECTORY_SEPARATOR.normalize($relative)
        );
        $installed += str_contains($content, MARKER) ? 1 : 0;
    }

    if ($installed === count($targets)) {
        runCommand($root, ['artisan', 'view:clear']);
        runCommand($root, ['artisan', 'view:cache']);
        runCommand($root, ['artisan', 'optimize:clear']);
        line('[OK] Hotfix sudah terpasang. Tidak ada file yang ditulis ulang.');
        line('Jalankan: php tools/check_invoice_flexible_billing_ui_hotfix_v1_2.php');
        exit(0);
    }

    if ($installed !== 0) {
        fail('Instalasi parsial V1.2 terdeteksi. Jalankan rollback hotfix lalu apply ulang.');
    }

    $timestamp = date('Ymd-His');
    $backupDirectory = $root.DIRECTORY_SEPARATOR.'tools'
        .DIRECTORY_SEPARATOR.'backups'
        .DIRECTORY_SEPARATOR.'invoice-flexible-billing-ui-hotfix-v1_2-'.$timestamp;

    if (! mkdir($backupDirectory.DIRECTORY_SEPARATOR.'files', 0775, true)) {
        fail('Tidak dapat membuat folder backup hotfix.');
    }

    $manifest = [
        'patch' => PATCH_NAME,
        'created_at' => date(DATE_ATOM),
        'files' => [],
    ];

    foreach (array_keys($targets) as $relative) {
        $source = $root.DIRECTORY_SEPARATOR.normalize($relative);
        $backup = $backupDirectory.DIRECTORY_SEPARATOR.'files'
            .DIRECTORY_SEPARATOR.normalize($relative);

        $manifest['files'][$relative] = ['existed' => true];
        writeFile($backup, (string) file_get_contents($source));
    }

    writeFile(
        $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    writeFile(
        $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
            .DIRECTORY_SEPARATOR.'invoice-flexible-billing-ui-hotfix-v1_2-latest.txt',
        $backupDirectory
    );

    $writesStarted = true;

    foreach (array_keys($targets) as $relative) {
        $payload = $payloadRoot.DIRECTORY_SEPARATOR.normalize($relative);
        $target = $root.DIRECTORY_SEPARATOR.normalize($relative);
        writeFile($target, (string) file_get_contents($payload));
        line('[WRITE] '.$relative);
    }

    if (runCommand($root, [
        '-l',
        'packages/Webkul/Admin/src/Http/Controllers/Invoice/FlexibleQuoteBillingController.php',
    ]) !== 0) {
        fail('PHP lint Controller gagal.');
    }

    if (runCommand($root, ['artisan', 'view:clear']) !== 0) {
        fail('Tidak dapat membersihkan compiled views.');
    }

    if (runCommand($root, ['artisan', 'view:cache']) !== 0) {
        fail('Blade compile gagal.');
    }

    runCommand($root, ['artisan', 'optimize:clear']);

    line();
    line('HOTFIX BERHASIL.');
    line('Backup source: '.$backupDirectory);
    line('Lanjutkan dengan:');
    line('php tools/check_invoice_flexible_billing_ui_hotfix_v1_2.php');
} catch (Throwable $exception) {
    line();
    line('HOTFIX GAGAL: '.$exception->getMessage());

    if ($writesStarted && $backupDirectory && is_array($manifest)) {
        restoreBackup($root, $backupDirectory, $manifest);
        line('Source dipulihkan otomatis dari backup hotfix.');
    }

    exit(1);
}
