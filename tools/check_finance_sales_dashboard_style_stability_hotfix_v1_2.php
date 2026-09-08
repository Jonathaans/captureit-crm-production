<?php

declare(strict_types=1);

const CHECK_TITLE = 'CHECK FINANCE & SALES DASHBOARD STYLE STABILITY V1.2';
const CHECK_MARKER = 'CRM_FINANCE_SALES_DASHBOARD_STYLE_STABILITY_V1_2';

$root = dirname(__DIR__);
$failures = 0;

function fsdsCheck(bool $condition, string $message, string $detail = ''): void
{
    global $failures;

    if (! $condition) {
        $failures++;
    }

    echo ($condition ? '[OK]   ' : '[FAIL] ').$message
        .($detail !== '' ? ' — '.$detail : '').PHP_EOL;
}

function fsdsCheckRead(string $root, string $relative): string
{
    $path = $root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

    return is_file($path)
        ? str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($path))
        : '';
}

function fsdsCheckRun(string $root, array $arguments): array
{
    $command = escapeshellarg(PHP_BINARY);

    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg((string) $argument);
    }

    $previous = getcwd();
    chdir($root);
    exec($command.' 2>&1', $output, $exitCode);

    if ($previous !== false) {
        chdir($previous);
    }

    return [(int) $exitCode, implode(PHP_EOL, $output)];
}

echo CHECK_TITLE.PHP_EOL;
echo str_repeat('=', strlen(CHECK_TITLE)).PHP_EOL.PHP_EOL;

$viewRelative = 'packages/Webkul/Admin/src/Resources/views/invoices/finance-sales-dashboard.blade.php';
$layoutRelative = 'packages/Webkul/Admin/src/Resources/views/components/layouts/index.blade.php';
$view = fsdsCheckRead($root, $viewRelative);
$layout = fsdsCheckRead($root, $layoutRelative);

fsdsCheck($view !== '', 'Finance & Sales Dashboard tersedia');
fsdsCheck($layout !== '', 'Layout Admin tersedia');
fsdsCheck(str_contains($view, CHECK_MARKER), 'Marker Style Stability V1.2 terpasang');
fsdsCheck(str_contains($layout, "@stack('styles')"), "Layout menyediakan @stack('styles')");
fsdsCheck(
    str_contains($view, "@push('styles')")
        && str_contains($view, '@endpush')
        && str_contains($view, 'id="crm-finance-sales-dashboard-styles-v1-2"'),
    'CSS Dashboard dikirim ke <head>',
);

$pushAt = strpos($view, "@push('styles')");
$styleAt = strpos($view, 'id="crm-finance-sales-dashboard-styles-v1-2"');
$endPushAt = strpos($view, '@endpush');
$appContentAt = strpos($view, '<div class="fsd-v11">');
$ordered = $pushAt !== false
    && $styleAt !== false
    && $endPushAt !== false
    && $appContentAt !== false
    && $pushAt < $styleAt
    && $styleAt < $endPushAt
    && $endPushAt < $appContentAt;
fsdsCheck($ordered, 'Style selesai dipush sebelum konten #app');

$appContent = $appContentAt === false ? $view : substr($view, $appContentAt);
fsdsCheck(! str_contains($appContent, '<style'), 'Tidak ada tag style di dalam konten Vue #app');

foreach ([
    '.fsd-v11',
    '.fsd-panel',
    '.fsd-filter-grid',
    '.fsd-kpi-grid',
    '.fsd-aging-grid',
    '.fsd-table',
    '@media (max-width: 1180px)',
    '@media (max-width: 680px)',
] as $selector) {
    fsdsCheck(str_contains($view, $selector), 'CSS tersedia: '.$selector);
}

[$viewCode, $viewOutput] = fsdsCheckRun($root, ['artisan', 'view:cache']);
fsdsCheck($viewCode === 0, 'Semua Blade berhasil dikompilasi', $viewCode === 0 ? '' : $viewOutput);

echo PHP_EOL;

if ($failures > 0) {
    echo '[FAIL] Checker menemukan '.$failures.' masalah.'.PHP_EOL;
    exit(1);
}

echo '[PASS] CSS Dashboard tetap aktif setelah Vue selesai mount.'.PHP_EOL;

