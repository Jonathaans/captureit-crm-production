<?php

declare(strict_types=1);

const TITLE = 'CHECK FINANCIAL REPORT + EXPENSE EXPORT HOTFIX V1.3';

$root = dirname(__DIR__);
$failures = 0;

function checkResult(bool $condition, string $message): void
{
    global $failures;

    if ($condition) {
        echo '[OK]   '.$message.PHP_EOL;
    } else {
        $failures++;
        echo '[FAIL] '.$message.PHP_EOL;
    }
}

function content(string $root, string $relative): string
{
    $path = $root.DIRECTORY_SEPARATOR
        .str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

    return is_file($path) ? (string) file_get_contents($path) : '';
}

echo TITLE.PHP_EOL;
echo str_repeat('=', strlen(TITLE)).PHP_EOL.PHP_EOL;

$servicePath = 'packages/Webkul/Admin/src/Services/FlexibleQuoteBillingService.php';
$indexPath = 'packages/Webkul/Admin/src/Resources/views/invoices/index.blade.php';
$reportPath = 'packages/Webkul/Admin/src/Resources/views/invoices/financial-report.blade.php';

$service = content($root, $servicePath);
$index = content($root, $indexPath);
$report = content($root, $reportPath);

checkResult($service !== '', 'FlexibleQuoteBillingService tersedia');
checkResult(
    str_contains($service, 'CRM_FINANCIAL_REPORT_EXPENSE_HOTFIX_V1_3')
        && str_contains($service, 'foreach ($quoteTotals as $quoteId => $total)')
        && ! str_contains($service, '$quoteTotals->sum(function'),
    'Perhitungan Unbilled kompatibel dengan Laravel'
);
checkResult(
    str_contains($index, 'CRM_FINANCIAL_REPORT_EXPENSE_HOTFIX_V1_3: moved')
        && ! str_contains($index, 'Export All Expenses'),
    'Tombol Export All Expenses tidak lagi berada di Invoice'
);
checkResult(
    str_contains($report, 'CRM_FINANCIAL_REPORT_EXPENSE_HOTFIX_V1_3')
        && str_contains($report, "route('admin.invoices.expenses.export-all')")
        && str_contains($report, "hasPermission('invoices.expense.export-all')")
        && str_contains($report, 'Export All Expenses')
        && ! str_contains($report, 'admin.invoices.financial-report.expenses.export'),
    'Export All Expenses berada di Financial Report dengan route yang benar'
);

$lintCommand = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg(
    $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $servicePath)
).' 2>&1';
exec($lintCommand, $lintOutput, $lintExit);
checkResult($lintExit === 0, 'PHP lint FlexibleQuoteBillingService');

if (! is_file($root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')) {
    checkResult(false, 'Laravel vendor/autoload.php tersedia');
} else {
    try {
        require $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
        $app = require $root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        checkResult(
            Illuminate\Support\Facades\Route::has('admin.invoices.financial-report'),
            'Route Financial Report tersedia'
        );
        checkResult(
            Illuminate\Support\Facades\Route::has('admin.invoices.expenses.export-all'),
            'Route Export All Expenses tersedia'
        );

        $quoteIds = Webkul\Quote\Models\Quote::query()
            ->limit(10)
            ->pluck('id');
        $unbilled = app(
            Webkul\Admin\Services\FlexibleQuoteBillingService::class
        )->sumUnbilledForQuoteIds($quoteIds);
        checkResult(is_float($unbilled), 'Kalkulasi Unbilled runtime berhasil');

        Illuminate\Support\Facades\Artisan::call('view:clear');
        $viewResult = Illuminate\Support\Facades\Artisan::call('view:cache');
        checkResult($viewResult === 0, 'Semua Blade berhasil dikompilasi');
    } catch (Throwable $exception) {
        checkResult(false, 'Laravel runtime: '.$exception->getMessage());
    }
}

echo PHP_EOL;

if ($failures > 0) {
    echo '[FAIL] Checker menemukan '.$failures.' masalah.'.PHP_EOL;
    exit(1);
}

echo '[PASS] Financial Report pulih dan Export All Expenses sudah dipindahkan.'.PHP_EOL;
