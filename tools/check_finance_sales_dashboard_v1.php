<?php

declare(strict_types=1);

const CHECK_TITLE = 'CHECK CRM FINANCE & SALES DASHBOARD V1';

$root = dirname(__DIR__);
$failures = 0;

function fsdCheck(bool $condition, string $message): void
{
    global $failures;

    echo ($condition ? '[OK]   ' : '[FAIL] ').$message.PHP_EOL;

    if (! $condition) {
        $failures++;
    }
}

function fsdCheckContent(
    string $root,
    string $relative,
    array $markers,
    string $label
): void {
    $path = $root.DIRECTORY_SEPARATOR
        .str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    $content = is_file($path) ? (string) file_get_contents($path) : '';
    $missing = array_values(array_filter(
        $markers,
        fn ($marker) => ! str_contains($content, $marker)
    ));

    fsdCheck(
        $content !== '' && $missing === [],
        $label.($missing === [] ? '' : ' — kurang: '.implode(', ', $missing))
    );
}

function fsdCheckLint(string $root, string $relative): void
{
    $path = $root.DIRECTORY_SEPARATOR
        .str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

    if (! is_file($path)) {
        fsdCheck(false, 'PHP lint '.$relative.' — file tidak tersedia');
        return;
    }

    exec(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1',
        $output,
        $exitCode
    );

    fsdCheck(
        $exitCode === 0,
        'PHP lint '.$relative.($exitCode === 0 ? '' : ' — '.implode(' ', $output))
    );
}

echo CHECK_TITLE.PHP_EOL;
echo str_repeat('=', strlen(CHECK_TITLE)).PHP_EOL.PHP_EOL;

$files = [
    'packages/Webkul/Admin/src/Services/FinanceSalesDashboardService.php',
    'packages/Webkul/Admin/src/Http/Controllers/Invoice/FinanceSalesDashboardController.php',
    'packages/Webkul/Admin/src/Resources/views/invoices/finance-sales-dashboard.blade.php',
    'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php',
    'packages/Webkul/Admin/src/Resources/views/invoices/index.blade.php',
    'packages/Webkul/Admin/src/Resources/views/invoices/financial-report.blade.php',
];

foreach ($files as $relative) {
    fsdCheck(
        is_file($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative)),
        'File tersedia: '.$relative
    );
}

foreach (array_slice($files, 0, 2) as $relative) {
    fsdCheckLint($root, $relative);
}

fsdCheckLint($root, $files[3]);

fsdCheckContent(
    $root,
    $files[0],
    [
        'CRM_FINANCE_SALES_DASHBOARD_V1',
        "withSum('payments as dashboard_paid_total'",
        'applyOutstandingConstraint',
        "'down_payment'",
        "'settlement'",
        'readySettlements',
        'salesPortfolio',
        'private function aging',
    ],
    'Service memakai payment history, DP/Pelunasan, aging, dan portfolio Sales'
);

fsdCheckContent(
    $root,
    $files[1],
    [
        'CRM_FINANCE_SALES_DASHBOARD_V1',
        'authorizeDashboard',
        "hasPermission('invoices')",
        'streamDownload',
        '$safeCell',
        'FinanceSalesDashboardService',
    ],
    'Controller dilindungi ACL Invoice dan menyediakan export CSV'
);

fsdCheckContent(
    $root,
    $files[2],
    [
        'CRM_FINANCE_SALES_DASHBOARD_V1',
        'Total Outstanding',
        'Belum Dibayar',
        'Partial',
        'DP Belum Lunas',
        'Siap Pelunasan',
        'Priority Collection',
        'Aging Outstanding',
        'Portfolio Sales',
        'Generate Pelunasan',
    ],
    'UI Finance & Sales lengkap dan action-oriented'
);

fsdCheckContent(
    $root,
    $files[3],
    [
        'CRM_FINANCE_SALES_DASHBOARD_V1',
        "prefix('admin/finance-sales-dashboard')",
        'admin.finance-sales-dashboard.index',
        'admin.finance-sales-dashboard.export',
    ],
    'Route dashboard terpasang pada provider'
);

foreach ([$files[4], $files[5]] as $relative) {
    fsdCheckContent(
        $root,
        $relative,
        ['CRM_FINANCE_SALES_DASHBOARD_V1', 'admin.finance-sales-dashboard.index'],
        'Tombol dashboard terpasang: '.$relative
    );
}

if (! is_file($root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')) {
    fsdCheck(false, 'Laravel vendor/autoload.php tersedia');
} else {
    try {
        require $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
        $app = require $root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        foreach ([
            'admin.finance-sales-dashboard.index',
            'admin.finance-sales-dashboard.export',
            'admin.invoices.billing.create',
            'admin.invoices.show',
            'admin.invoices.financial-report',
        ] as $routeName) {
            fsdCheck(
                Illuminate\Support\Facades\Route::has($routeName),
                'Route terdaftar: '.$routeName
            );
        }

        foreach (['invoices', 'payments', 'quotes', 'users', 'persons'] as $table) {
            fsdCheck(
                Illuminate\Support\Facades\Schema::hasTable($table),
                'Tabel tersedia: '.$table
            );
        }

        foreach ([
            'quote_id',
            'billing_type',
            'remaining_amount_snapshot',
            'grand_total',
            'event_status',
            'status',
            'due_at',
            'user_id',
            'person_id',
        ] as $column) {
            fsdCheck(
                Illuminate\Support\Facades\Schema::hasColumn('invoices', $column),
                'Kolom invoices.'.$column.' tersedia'
            );
        }

        fsdCheck(
            Illuminate\Support\Facades\Schema::hasColumn('payments', 'invoice_id')
                && Illuminate\Support\Facades\Schema::hasColumn('payments', 'amount'),
            'Payment history memiliki invoice_id dan amount'
        );

        $service = app(Webkul\Admin\Services\FinanceSalesDashboardService::class);
        $data = $service->build([
            'search' => '',
            'sales_user_id' => 0,
            'business_unit' => '',
            'focus' => 'all',
        ], 10);

        fsdCheck(
            isset(
                $data['metrics']['outstanding_total'],
                $data['metrics']['unpaid_count'],
                $data['metrics']['partial_count'],
                $data['metrics']['ready_settlement_count'],
                $data['aging'],
                $data['actionInvoices'],
                $data['salesPortfolio']
            ),
            'Runtime dashboard menghasilkan KPI, aging, collection, dan portfolio'
        );

        $invalidReady = $data['readySettlements']->first(function (array $row) {
            return ($row['remaining'] ?? 0) <= 0
                || ($row['quote_id'] ?? 0) <= 0;
        });
        fsdCheck(
            $invalidReady === null,
            'Semua kandidat Pelunasan memiliki Quote dan sisa tagihan positif'
        );

        Illuminate\Support\Facades\Artisan::call('view:clear');
        $viewResult = Illuminate\Support\Facades\Artisan::call('view:cache');
        fsdCheck($viewResult === 0, 'Semua Blade berhasil dikompilasi');
    } catch (Throwable $exception) {
        fsdCheck(false, 'Laravel runtime: '.$exception->getMessage());
    }
}

echo PHP_EOL;

if ($failures > 0) {
    echo '[FAIL] Checker menemukan '.$failures.' masalah.'.PHP_EOL;
    exit(1);
}

echo '[PASS] Finance & Sales Dashboard siap digunakan.'.PHP_EOL;
