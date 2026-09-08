<?php

declare(strict_types=1);

const PATCH_TITLE = 'FINANCE & SALES DASHBOARD STYLE STABILITY HOTFIX V1.2';
const PATCH_MARKER = 'CRM_FINANCE_SALES_DASHBOARD_STYLE_STABILITY_V1_2';
const DEPENDENCY_MARKER = 'CRM_FINANCE_SALES_DASHBOARD_UI_HOTFIX_V1_1';

$root = dirname(__DIR__);
$relative = 'packages/Webkul/Admin/src/Resources/views/invoices/finance-sales-dashboard.blade.php';
$layoutRelative = 'packages/Webkul/Admin/src/Resources/views/components/layouts/index.blade.php';
$backupDirectory = null;
$writesStarted = false;
$original = '';

function fsdsLine(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function fsdsFail(string $message): never
{
    throw new RuntimeException($message);
}

function fsdsPath(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function fsdsRead(string $path): string
{
    if (! is_file($path)) {
        fsdsFail('File tidak ditemukan: '.$path);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        fsdsFail('File tidak dapat dibaca: '.$path);
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function fsdsWrite(string $path, string $content): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        fsdsFail('Folder tidak dapat dibuat: '.$directory);
    }

    $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        fsdsFail('File sementara tidak dapat ditulis: '.$temporary);
    }

    if (is_file($path) && ! unlink($path)) {
        @unlink($temporary);
        fsdsFail('File target tidak dapat diganti: '.$path);
    }

    if (! rename($temporary, $path)) {
        @unlink($temporary);
        fsdsFail('File target tidak dapat disimpan: '.$path);
    }
}

function fsdsReplaceOnce(string $content, string $search, string $replacement, string $label): string
{
    $count = substr_count($content, $search);

    if ($count !== 1) {
        fsdsFail('Preflight '.$label.' harus ditemukan tepat satu kali; count='.$count.'.');
    }

    return str_replace($search, $replacement, $content);
}

function fsdsRun(string $root, array $arguments): int
{
    $command = escapeshellarg(PHP_BINARY);

    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg((string) $argument);
    }

    fsdsLine('[RUN]   '.implode(' ', $arguments));
    $previous = getcwd();
    chdir($root);
    passthru($command, $exitCode);

    if ($previous !== false) {
        chdir($previous);
    }

    return (int) $exitCode;
}

fsdsLine(PATCH_TITLE);
fsdsLine(str_repeat('=', strlen(PATCH_TITLE)));
fsdsLine();

try {
    if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
        fsdsFail('Jalankan tool dari root project Laravel.');
    }

    $viewPath = $root.DIRECTORY_SEPARATOR.fsdsPath($relative);
    $layoutPath = $root.DIRECTORY_SEPARATOR.fsdsPath($layoutRelative);
    $view = fsdsRead($viewPath);
    $layout = fsdsRead($layoutPath);

    if (str_contains($view, PATCH_MARKER)) {
        fsdsLine('[OK] Hotfix V1.2 sudah terpasang.');
        fsdsLine('Jalankan checker V1.2.');
        exit(0);
    }

    if (! str_contains($view, DEPENDENCY_MARKER)) {
        fsdsFail('Dependency UI Hotfix V1.1 tidak ditemukan pada Dashboard.');
    }

    if (! str_contains($layout, "@stack('styles')")) {
        fsdsFail("Layout Admin tidak menyediakan @stack('styles').");
    }

    $view = fsdsReplaceOnce(
        $view,
        "    <style>\n",
        "    {{-- ".PATCH_MARKER." --}}\n    @push('styles')\n        <style id=\"crm-finance-sales-dashboard-styles-v1-2\">\n",
        'opening style dashboard',
    );
    $view = fsdsReplaceOnce(
        $view,
        "    </style>\n\n    <div class=\"fsd-v11\">",
        "        </style>\n    @endpush\n\n    <div class=\"fsd-v11\">",
        'closing style dashboard',
    );

    $timestamp = date('Ymd-His');
    $backupDirectory = $root.DIRECTORY_SEPARATOR.'tools'
        .DIRECTORY_SEPARATOR.'backups'
        .DIRECTORY_SEPARATOR.'finance-sales-dashboard-style-stability-v1_2-'.$timestamp;
    $backupPath = $backupDirectory.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.fsdsPath($relative);
    $pointerPath = $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
        .DIRECTORY_SEPARATOR.'finance-sales-dashboard-style-stability-v1_2-latest.txt';

    fsdsWrite($backupPath, fsdsRead($viewPath));
    fsdsWrite(
        $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json',
        json_encode([
            'patch' => PATCH_TITLE,
            'created_at' => date(DATE_ATOM),
            'files' => [$relative => ['existed' => true]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
    );
    fsdsWrite($pointerPath, $backupDirectory.PHP_EOL);

    $original = fsdsRead($viewPath);
    $writesStarted = true;
    fsdsWrite($viewPath, $view);
    fsdsLine('[PATCH] '.$relative);

    if (fsdsRun($root, ['artisan', 'optimize:clear']) !== 0) {
        fsdsFail('artisan optimize:clear gagal.');
    }

    if (fsdsRun($root, ['artisan', 'view:cache']) !== 0) {
        fsdsFail('Blade gagal dikompilasi.');
    }

    fsdsLine();
    fsdsLine('PATCH BERHASIL. CSS Dashboard sekarang berada di <head>.');
    fsdsLine('Backup source: '.$backupDirectory);
    fsdsLine('Lanjutkan: php tools/check_finance_sales_dashboard_style_stability_hotfix_v1_2.php');
} catch (Throwable $exception) {
    if ($writesStarted && $original !== '') {
        try {
            fsdsWrite($root.DIRECTORY_SEPARATOR.fsdsPath($relative), $original);
            fsdsLine('Dashboard dipulihkan dari backup memori.');
        } catch (Throwable $restoreException) {
            fsdsLine('PERINGATAN: rollback otomatis gagal: '.$restoreException->getMessage());
        }
    }

    fsdsLine();
    fsdsLine('PATCH GAGAL: '.$exception->getMessage());
    exit(1);
}

