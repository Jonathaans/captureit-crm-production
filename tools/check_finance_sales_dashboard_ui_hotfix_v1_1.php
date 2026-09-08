<?php

declare(strict_types=1);

const CHECK_TITLE = 'CHECK FINANCE & SALES DASHBOARD UI HOTFIX V1.1';
const CHECK_MARKER = 'CRM_FINANCE_SALES_DASHBOARD_UI_HOTFIX_V1_1';

$root = dirname(__DIR__);
$failures = 0;

function fsduCheck(bool $condition, string $message): void
{
    global $failures;

    echo ($condition ? '[OK]   ' : '[FAIL] ').$message.PHP_EOL;

    if (! $condition) {
        $failures++;
    }
}

function fsduCheckContent(
    string $root,
    string $relative,
    array $markers,
    string $label
): string {
    $path = $root.DIRECTORY_SEPARATOR
        .str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    $content = is_file($path) ? (string) file_get_contents($path) : '';
    $missing = array_values(array_filter(
        $markers,
        fn ($marker) => ! str_contains($content, $marker)
    ));

    fsduCheck(
        $content !== '' && $missing === [],
        $label.($missing === [] ? '' : ' — kurang: '.implode(', ', $missing))
    );

    return $content;
}

function fsduCheckLint(string $root, string $relative): void
{
    $path = $root.DIRECTORY_SEPARATOR
        .str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

    if (! is_file($path)) {
        fsduCheck(false, 'PHP lint '.$relative.' — file tidak tersedia');
        return;
    }

    exec(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1',
        $output,
        $exitCode
    );

    fsduCheck(
        $exitCode === 0,
        'PHP lint '.$relative.($exitCode === 0 ? '' : ' — '.implode(' ', $output))
    );
}

echo CHECK_TITLE.PHP_EOL;
echo str_repeat('=', strlen(CHECK_TITLE)).PHP_EOL.PHP_EOL;

$serviceRelative = 'packages/Webkul/Admin/src/Services/FinanceSalesDashboardService.php';
$dashboardRelative = 'packages/Webkul/Admin/src/Resources/views/invoices/finance-sales-dashboard.blade.php';
$invoiceIndexRelative = 'packages/Webkul/Admin/src/Resources/views/invoices/index.blade.php';
$financialReportRelative = 'packages/Webkul/Admin/src/Resources/views/invoices/financial-report.blade.php';
$files = [
    $serviceRelative,
    $dashboardRelative,
    $invoiceIndexRelative,
    $financialReportRelative,
];

foreach ($files as $relative) {
    fsduCheck(
        is_file(
            $root.DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, $relative)
        ),
        'File tersedia: '.$relative
    );
}

fsduCheckLint($root, $serviceRelative);

$service = fsduCheckContent(
    $root,
    $serviceRelative,
    [
        CHECK_MARKER,
        'private function businessUnitOptions(): Collection',
        'BusinessUnit::options()',
        '$historicalOptions',
        "->unique('value')",
        "->sortBy('label'",
    ],
    'Business Unit memakai master lengkap dan nilai historis Invoice'
);

fsduCheck(
    ! str_contains(
        $service,
        "'businessUnits' => Invoice::query()\n"
    ),
    'Dashboard tidak lagi membatasi dropdown pada unit yang sudah bertransaksi'
);

$dashboard = fsduCheckContent(
    $root,
    $dashboardRelative,
    [
        CHECK_MARKER,
        '<style>',
        '.fsd-filter-grid',
        '.fsd-kpi-grid',
        'grid-template-columns: repeat(4, minmax(0, 1fr))',
        '@media (max-width: 1180px)',
        '@media (max-width: 680px)',
        'Business Unit berasal dari master',
        'Priority Collection',
        'DP Lunas — Siap Generate Pelunasan',
    ],
    'Dashboard memakai CSS scoped dan layout responsif mandiri'
);

fsduCheck(
    ! str_contains($dashboard, 'lg:grid-cols-5'),
    'Dashboard tidak bergantung pada utility grid Tailwind baru'
);

$invoiceIndex = fsduCheckContent(
    $root,
    $invoiceIndexRelative,
    [
        CHECK_MARKER,
        'CRM_FINANCE_SALES_DASHBOARD_V1',
        'CRM_INVOICE_FLEXIBLE_BILLING_V1',
        'admin.finance-sales-dashboard.index',
        'admin.invoices.billing.create',
        'justify-content:flex-end',
        'margin-left:auto',
    ],
    'Tombol Invoice berada dalam satu kelompok action'
);

fsduCheck(
    substr_count($invoiceIndex, CHECK_MARKER) === 1,
    'Marker UI Invoice tepat satu kali'
);

$financialReport = fsduCheckContent(
    $root,
    $financialReportRelative,
    [
        CHECK_MARKER,
        'CRM_FINANCE_SALES_DASHBOARD_V1',
        'CRM_FINANCIAL_REPORT_EXPENSE_HOTFIX_V1_3',
        'admin.invoices.financial-report.export',
        'admin.finance-sales-dashboard.index',
        'admin.invoices.expenses.export-all',
        'justify-content:flex-end',
        'margin-left:auto',
        '.crm-fr-v11-filter-grid',
        'grid-template-columns: repeat(5, minmax(0, 1fr))',
    ],
    'Tiga action Financial Report rapi dalam satu kelompok'
);

fsduCheck(
    substr_count($financialReport, CHECK_MARKER) === 1,
    'Marker UI Financial Report tepat satu kali'
);

if (! is_file($root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')) {
    fsduCheck(false, 'Laravel vendor/autoload.php tersedia');
} else {
    try {
        require $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
        $app = require $root.DIRECTORY_SEPARATOR.'bootstrap'
            .DIRECTORY_SEPARATOR.'app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        foreach ([
            'admin.finance-sales-dashboard.index',
            'admin.finance-sales-dashboard.export',
            'admin.invoices.index',
            'admin.invoices.billing.create',
            'admin.invoices.financial-report',
            'admin.invoices.financial-report.export',
            'admin.invoices.expenses.export-all',
        ] as $routeName) {
            fsduCheck(
                Illuminate\Support\Facades\Route::has($routeName),
                'Route terdaftar: '.$routeName
            );
        }

        $dashboardService = app(
            Webkul\Admin\Services\FinanceSalesDashboardService::class
        );
        $data = $dashboardService->build([
            'search' => '',
            'sales_user_id' => 0,
            'business_unit' => '',
            'focus' => 'all',
        ], 10);

        $actualUnits = collect($data['businessUnits'] ?? [])
            ->pluck('value')
            ->map(fn ($value) => (string) $value)
            ->values();
        $masterUnits = collect(Webkul\Core\Support\BusinessUnit::options())
            ->keys()
            ->map(fn ($value) => (string) $value)
            ->values();
        $historicalUnits = Webkul\Invoice\Models\Invoice::query()
            ->whereNotNull('business_unit')
            ->where('business_unit', '!=', '')
            ->distinct()
            ->pluck('business_unit')
            ->map(fn ($value) => (string) $value)
            ->values();

        fsduCheck(
            $masterUnits->diff($actualUnits)->isEmpty(),
            'Seluruh Business Unit master tampil pada filter Dashboard'
        );
        fsduCheck(
            $historicalUnits->diff($actualUnits)->isEmpty(),
            'Business Unit historis Invoice tetap tampil pada filter Dashboard'
        );
        fsduCheck(
            $actualUnits->count() === $actualUnits->unique()->count(),
            'Dropdown Business Unit tidak memiliki pilihan duplikat'
        );

        Illuminate\Support\Facades\Artisan::call('view:clear');
        $viewResult = Illuminate\Support\Facades\Artisan::call('view:cache');
        fsduCheck($viewResult === 0, 'Semua Blade berhasil dikompilasi');
    } catch (Throwable $exception) {
        fsduCheck(false, 'Laravel runtime: '.$exception->getMessage());
    }
}

echo PHP_EOL;

if ($failures > 0) {
    echo '[FAIL] Checker menemukan '.$failures.' masalah.'.PHP_EOL;
    exit(1);
}

echo '[PASS] UI Dashboard, Invoice, Financial Report, dan Business Unit siap.'.PHP_EOL;
