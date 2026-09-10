<?php

declare(strict_types=1);

const CHECK_TITLE = 'CHECK INTERNAL COMMUNICATION FLOATING LAYER FIX V1';
const CHECK_MARKER = 'INTERNAL_COMMUNICATION_FLOATING_LAYER_FIX_V1';

$root = dirname(__DIR__);
$widgetRelative = 'packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php';
$drawerRelative = 'packages/Webkul/Admin/src/Resources/views/components/drawer/index.blade.php';
$failed = 0;

function checkLayerLine(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function checkLayerResult(bool $ok, string $message): void
{
    global $failed;

    checkLayerLine(($ok ? '[OK]   ' : '[FAIL] ').$message);

    if (! $ok) {
        $failed++;
    }
}

function checkLayerRead(string $path): string
{
    if (! is_file($path)) {
        return '';
    }

    $content = file_get_contents($path);

    return is_string($content) ? $content : '';
}

function checkLayerZIndex(string $content, string $selector): ?int
{
    $pattern = '~'.preg_quote($selector, '~').'\s*\{(?:(?!\}).)*?\bz-index\s*:\s*(\d+)\s*;~s';

    return preg_match($pattern, $content, $matches) === 1
        ? (int) $matches[1]
        : null;
}

function checkLayerRunArtisan(string $root, string $command): int
{
    $arguments = array_merge([$root.DIRECTORY_SEPARATOR.'artisan'], explode(' ', $command));
    $shell = escapeshellarg(PHP_BINARY);

    foreach ($arguments as $argument) {
        $shell .= ' '.escapeshellarg($argument);
    }

    passthru($shell, $exitCode);

    return (int) $exitCode;
}

checkLayerLine(CHECK_TITLE);
checkLayerLine(str_repeat('=', strlen(CHECK_TITLE)));
checkLayerLine();

$widgetPath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $widgetRelative);
$drawerPath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $drawerRelative);
$widget = checkLayerRead($widgetPath);
$drawer = checkLayerRead($drawerPath);

checkLayerResult($widget !== '', 'Widget Internal Communication tersedia');
checkLayerResult($drawer !== '', 'Komponen drawer tersedia');
checkLayerResult(str_contains($widget, CHECK_MARKER), 'Marker patch V1 terpasang');
checkLayerResult(checkLayerZIndex($widget, '#crm-comm-floating') === 10000, 'Layer tombol Notification dan Chat = 10000');
checkLayerResult(checkLayerZIndex($widget, '#crm-comm-toasts') === 10000, 'Layer toast = 10000');
checkLayerResult(
    str_contains($drawer, 'z-[10002]') && str_contains($drawer, 'z-[10003]'),
    'Drawer/overlay tetap berada di atas floating action',
);

if (is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    checkLayerResult(checkLayerRunArtisan($root, 'view:clear') === 0, 'Blade cache berhasil dibersihkan');
    checkLayerResult(checkLayerRunArtisan($root, 'view:cache') === 0, 'Semua Blade berhasil dikompilasi');
} else {
    checkLayerResult(false, 'File artisan tersedia');
}

checkLayerLine();

if ($failed > 0) {
    checkLayerLine('[FAIL] Checker menemukan '.$failed.' masalah.');
    exit(1);
}

checkLayerLine('[OK] Floating action tidak akan menghalangi drawer Filter.');
