<?php

declare(strict_types=1);

const TITLE = 'CHECK INVOICE FLEXIBLE BILLING UI HOTFIX V1.2';

$root = dirname(__DIR__);
$failures = 0;

function result(bool $condition, string $message): void
{
    global $failures;

    if ($condition) {
        echo '[OK]   '.$message.PHP_EOL;
    } else {
        $failures++;
        echo '[FAIL] '.$message.PHP_EOL;
    }
}

function containsAll(string $root, string $relative, array $needles, string $label): void
{
    $path = $root.DIRECTORY_SEPARATOR
        .str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

    if (! is_file($path)) {
        result(false, $label.' (file tidak ditemukan)');
        return;
    }

    $content = (string) file_get_contents($path);
    $missing = array_values(array_filter(
        $needles,
        fn (string $needle): bool => ! str_contains($content, $needle)
    ));

    result(
        $missing === [],
        $label.($missing === [] ? '' : ' kurang: '.implode(', ', $missing))
    );
}

echo TITLE.PHP_EOL;
echo str_repeat('=', strlen(TITLE)).PHP_EOL.PHP_EOL;

$controller = 'packages/Webkul/Admin/src/Http/Controllers/Invoice/FlexibleQuoteBillingController.php';
$view = 'packages/Webkul/Admin/src/Resources/views/invoices/billing-create.blade.php';

containsAll($root, $controller, [
    'CRM_INVOICE_FLEXIBLE_BILLING_UI_HOTFIX_V1_2',
    'required_if:billing_method,percentage',
    'required_if:billing_method,nominal',
    'exclude_unless:billing_type,down_payment',
    "(\$validated['billing_method'] ?? null) === 'percentage'",
    "* (float) \$validated['billing_percentage']",
], 'Fallback server untuk Persentase dan Nominal');

containsAll($root, $view, [
    'CRM_INVOICE_FLEXIBLE_BILLING_UI_HOTFIX_V1_2',
    'const refreshElements = () =>',
    "document.addEventListener('change'",
    "document.addEventListener('input'",
    "document.addEventListener('submit'",
    "target.id === 'crm-billing-method'",
    "target.id === 'crm-billing-percentage'",
    "target.id === 'crm-billing-amount'",
    'percentageInput.required = isDp && isPercentage;',
    'amountInput.required = isDp && ! isPercentage;',
    'submitButton.disabled = ! eligible;',
], 'Event UI DP tetap aktif setelah render ulang');

if (is_file($root.DIRECTORY_SEPARATOR.$view)) {
    $content = (string) file_get_contents($root.DIRECTORY_SEPARATOR.$view);

    foreach ([
        'crm-billing-method',
        'crm-billing-percentage',
        'crm-billing-amount',
        'crm-generate-button',
    ] as $id) {
        result(
            substr_count($content, 'id="'.$id.'"') === 1,
            'Elemen unik: '.$id
        );
    }
}

$lintCommand = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg(
    $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $controller)
).' 2>&1';
exec($lintCommand, $lintOutput, $lintExit);
result($lintExit === 0, 'PHP lint FlexibleQuoteBillingController');

if (! is_file($root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')) {
    result(false, 'Laravel vendor/autoload.php tersedia');
} else {
    try {
        require $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
        $app = require $root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        result(
            Illuminate\Support\Facades\Route::has('admin.invoices.billing.store'),
            'Route Generate Invoice tersedia'
        );

        Illuminate\Support\Facades\Artisan::call('view:clear');
        $viewResult = Illuminate\Support\Facades\Artisan::call('view:cache');
        result($viewResult === 0, 'Semua Blade berhasil dikompilasi');
    } catch (Throwable $exception) {
        result(false, 'Laravel runtime: '.$exception->getMessage());
    }
}

echo PHP_EOL;

if ($failures > 0) {
    echo '[FAIL] Checker menemukan '.$failures.' masalah.'.PHP_EOL;
    exit(1);
}

echo '[PASS] Field Persentase, Nominal, Live Preview, dan Generate siap diuji.'.PHP_EOL;
