<?php

declare(strict_types=1);

const PATCH_TITLE = 'INTERNAL COMMUNICATION FLOATING LAYER FIX V1';
const PATCH_MARKER = 'INTERNAL_COMMUNICATION_FLOATING_LAYER_FIX_V1';
const TARGET_Z_INDEX = 10000;

$root = dirname(__DIR__);
$relative = 'packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php';
$target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
$backupRoot = $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
    .DIRECTORY_SEPARATOR.'internal-communication-floating-layer-fix-v1-'.date('Ymd-His');
$backup = $backupRoot.DIRECTORY_SEPARATOR.'widget.blade.php';
$written = false;

function layerLine(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function layerFail(string $message): never
{
    throw new RuntimeException($message);
}

function layerRead(string $path): string
{
    if (! is_file($path)) {
        layerFail('File tidak ditemukan: '.$path);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        layerFail('File tidak dapat dibaca: '.$path);
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function layerWrite(string $path, string $content): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        layerFail('Folder tidak dapat dibuat: '.$directory);
    }

    $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        layerFail('File sementara tidak dapat ditulis: '.$temporary);
    }

    if (is_file($path) && ! unlink($path)) {
        @unlink($temporary);
        layerFail('File target tidak dapat diganti: '.$path);
    }

    if (! rename($temporary, $path)) {
        @unlink($temporary);
        layerFail('File target tidak dapat disimpan: '.$path);
    }
}

function layerReplaceZIndex(string $content, string $selector): string
{
    $pattern = '~('.preg_quote($selector, '~').'\s*\{(?:(?!\}).)*?\bz-index\s*:\s*)\d+(\s*;)~s';
    $count = preg_match_all($pattern, $content);

    if ($count !== 1) {
        layerFail('Preflight z-index '.$selector.' harus ditemukan tepat satu kali; count='.$count.'.');
    }

    $result = preg_replace_callback(
        $pattern,
        static fn (array $match): string => $match[1].TARGET_Z_INDEX.$match[2],
        $content,
        1,
        $replaced,
    );

    if (! is_string($result) || $replaced !== 1) {
        layerFail('Gagal memperbarui z-index '.$selector.'.');
    }

    return $result;
}

function layerRunArtisan(string $root, string $command): int
{
    $arguments = array_merge([$root.DIRECTORY_SEPARATOR.'artisan'], explode(' ', $command));
    $shell = escapeshellarg(PHP_BINARY);

    foreach ($arguments as $argument) {
        $shell .= ' '.escapeshellarg($argument);
    }

    layerLine('[RUN]   php artisan '.$command);
    passthru($shell, $exitCode);

    return (int) $exitCode;
}

layerLine(PATCH_TITLE);
layerLine(str_repeat('=', strlen(PATCH_TITLE)));
layerLine();

try {
    if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
        layerFail('Jalankan tool dari root project Laravel.');
    }

    $content = layerRead($target);

    if (str_contains($content, PATCH_MARKER)) {
        layerLine('[OK] Patch sudah terpasang. Tidak ada perubahan ulang.');
        exit(0);
    }

    $anchor = '<!-- CRM_INTERNAL_COMMUNICATION_WIDGET -->';

    if (substr_count($content, $anchor) !== 1) {
        layerFail('Widget Internal Communication tidak dikenali atau marker utama tidak unik.');
    }

    $content = layerReplaceZIndex($content, '#crm-comm-floating');
    $content = layerReplaceZIndex($content, '#crm-comm-toasts');
    $content = str_replace(
        $anchor,
        $anchor."\n{{-- ".PATCH_MARKER.' --}}',
        $content,
    );

    layerWrite($backup, layerRead($target));
    layerWrite($target, $content);
    $written = true;

    layerLine('[PATCH] '.$relative);
    layerLine('[BACKUP] '.$backup);

    if (
        layerRunArtisan($root, 'view:clear') !== 0
        || layerRunArtisan($root, 'view:cache') !== 0
    ) {
        layerFail('Blade gagal dikompilasi.');
    }

    layerLine();
    layerLine('[OK] Tombol Notification, Chat, dan toast sekarang berada di bawah drawer/filter.');
    layerLine('Tidak ada migration dan tidak ada perubahan database.');
} catch (Throwable $exception) {
    if ($written && is_file($backup)) {
        try {
            layerWrite($target, layerRead($backup));
            layerLine('[ROLLBACK] File target dipulihkan dari backup.');
        } catch (Throwable $rollbackException) {
            layerLine('[WARNING] Rollback otomatis gagal: '.$rollbackException->getMessage());
        }
    }

    layerLine();
    layerLine('PATCH GAGAL: '.$exception->getMessage());
    exit(1);
}
