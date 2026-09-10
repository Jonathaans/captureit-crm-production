<?php

declare(strict_types=1);

const CHECK_TITLE = 'CHECK SALES COMMISSION — PAID DEALS EXPORT V1';
const CHECK_MARKER = 'CRM_SALES_COMMISSION_PAID_DEALS_EXPORT_V1';

$root = dirname(__DIR__);
$failures = 0;

function sceCheck(bool $condition, string $message): void
{
    global $failures;

    echo ($condition ? '[OK]   ' : '[FAIL] ').$message.PHP_EOL;

    if (! $condition) {
        $failures++;
    }
}

function sceCheckPath(string $root, string $relative): string
{
    return $root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function sceCheckContent(string $root, string $relative, array $markers, string $label): void
{
    $path = sceCheckPath($root, $relative);
    $content = is_file($path) ? (string) file_get_contents($path) : '';
    $missing = array_values(array_filter(
        $markers,
        fn (string $marker): bool => ! str_contains($content, $marker),
    ));

    sceCheck(
        $content !== '' && $missing === [],
        $label.($missing === [] ? '' : ' — kurang: '.implode(', ', $missing)),
    );
}

function sceCheckLint(string $root, string $relative): void
{
    $path = sceCheckPath($root, $relative);

    if (! is_file($path)) {
        sceCheck(false, 'PHP lint '.$relative.' — file tidak tersedia');
        return;
    }

    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $output, $exitCode);
    sceCheck(
        $exitCode === 0,
        'PHP lint '.$relative.($exitCode === 0 ? '' : ' — '.implode(' ', $output)),
    );
}

echo CHECK_TITLE.PHP_EOL;
echo str_repeat('=', strlen(CHECK_TITLE)).PHP_EOL.PHP_EOL;

$service = 'packages/Webkul/Admin/src/Services/SalesCommissionExportService.php';
$controller = 'packages/Webkul/Admin/src/Http/Controllers/Invoice/SalesCommissionExportController.php';
$provider = 'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php';
$view = 'packages/Webkul/Admin/src/Resources/views/invoices/finance-sales-dashboard.blade.php';

foreach ([$service, $controller, $provider, $view] as $relative) {
    sceCheck(is_file(sceCheckPath($root, $relative)), 'File tersedia: '.$relative);
}

foreach ([$service, $controller, $provider] as $relative) {
    sceCheckLint($root, $relative);
}

sceCheckContent($root, $service, [
    CHECK_MARKER,
    'isCommissionEligible',
    'commissionBase',
    "'down_payment'",
    "'settlement'",
    "'full_payment'",
    'paid_completion_date',
    "->unique('deal_key')",
], 'Service mengakui komisi sekali per deal setelah lunas penuh');

sceCheckContent($root, $controller, [
    CHECK_MARKER,
    'authorizeDashboard',
    'sales-commission-paid-deals-',
    'DP saja tidak dihitung',
    'Dasar Komisi',
    '$safeCell',
    "fputcsv(\$output",
], 'Controller menghasilkan CSV aman dengan ringkasan dan detail');

sceCheckContent($root, $provider, [
    CHECK_MARKER,
    'SalesCommissionExportController::class',
    'admin.finance-sales-dashboard.commission-export',
], 'Route export komisi terpasang');

sceCheckContent($root, $view, [
    CHECK_MARKER,
    'Export Deal Lunas per Sales',
    "route('admin.finance-sales-dashboard.commission-export')",
    'paid_from',
    'paid_to',
    'Export CSV Komisi Sales',
], 'UI filter periode, Sales, Business Unit, dan tombol export terpasang');

$financialReportFiles = [
    'packages/Webkul/Admin/src/Http/Controllers/Invoice/FinancialReportController.php',
    'packages/Webkul/Admin/src/Resources/views/invoices/financial-report.blade.php',
];

foreach ($financialReportFiles as $relative) {
    $content = is_file(sceCheckPath($root, $relative))
        ? (string) file_get_contents(sceCheckPath($root, $relative))
        : '';
    sceCheck(
        $content !== '' && ! str_contains($content, CHECK_MARKER),
        'Financial Report tidak disentuh patch: '.$relative,
    );
}

if (! is_file($root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')) {
    sceCheck(false, 'Laravel vendor/autoload.php tersedia');
} else {
    try {
        require $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
        $app = require $root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        sceCheck(
            Illuminate\Support\Facades\Route::has('admin.finance-sales-dashboard.commission-export'),
            'Route terdaftar: admin.finance-sales-dashboard.commission-export',
        );

        foreach (['invoices', 'invoice_items', 'payments', 'quotes', 'quote_items', 'users', 'persons'] as $table) {
            sceCheck(Illuminate\Support\Facades\Schema::hasTable($table), 'Tabel tersedia: '.$table);
        }

        foreach (['quote_id', 'billing_type', 'quote_total_snapshot', 'grand_total', 'status', 'event_status'] as $column) {
            sceCheck(
                Illuminate\Support\Facades\Schema::hasColumn('invoices', $column),
                'Kolom invoices.'.$column.' tersedia',
            );
        }

        foreach (['invoice_id', 'amount', 'paid_at'] as $column) {
            sceCheck(
                Illuminate\Support\Facades\Schema::hasColumn('payments', $column),
                'Kolom payments.'.$column.' tersedia',
            );
        }

        /** @var Webkul\Admin\Services\SalesCommissionExportService $exportService */
        $exportService = app(Webkul\Admin\Services\SalesCommissionExportService::class);

        $dpOnly = [[
            'billing_type' => 'down_payment',
            'invoice_total' => 2000000,
            'paid_total' => 2000000,
            'status' => 'paid',
        ]];
        $dpAndSettlement = [
            ...$dpOnly,
            [
                'billing_type' => 'settlement',
                'invoice_total' => 3000000,
                'paid_total' => 3000000,
                'status' => 'paid',
            ],
        ];
        $fullPayment = [[
            'billing_type' => 'full_payment',
            'invoice_total' => 5000000,
            'paid_total' => 5000000,
            'status' => 'paid',
        ]];
        $partialSettlement = [
            $dpOnly[0],
            [
                'billing_type' => 'settlement',
                'invoice_total' => 3000000,
                'paid_total' => 1000000,
                'status' => 'partial',
            ],
        ];

        sceCheck(
            ! $exportService->isCommissionEligible($dpOnly, 5000000),
            'Simulasi: DP Rp2 juta yang sudah dibayar belum memperoleh komisi',
        );
        sceCheck(
            $exportService->isCommissionEligible($dpAndSettlement, 5000000)
                && $exportService->commissionBase($dpAndSettlement, 5000000) === 5000000.0,
            'Simulasi: DP Rp2 juta + Pelunasan Rp3 juta diakui satu deal Rp5 juta',
        );
        sceCheck(
            $exportService->isCommissionEligible($fullPayment, 5000000)
                && $exportService->commissionBase($fullPayment, 5000000) === 5000000.0,
            'Simulasi: Full Payment lunas diakui satu deal Rp5 juta',
        );
        sceCheck(
            ! $exportService->isCommissionEligible($partialSettlement, 5000000),
            'Simulasi: Pelunasan yang masih partial tidak memperoleh komisi',
        );

        $rows = $exportService->rows([
            'paid_from' => '2000-01-01',
            'paid_to' => '2100-12-31',
            'sales_user_id' => 0,
            'business_unit' => '',
        ]);

        sceCheck(
            $rows->pluck('deal_key')->unique()->count() === $rows->count(),
            'Runtime tidak memiliki duplicate deal',
        );

        $invalidRow = $rows->first(function (array $row): bool {
            return ($row['status'] ?? '') !== 'LUNAS'
                || (float) ($row['commission_base'] ?? 0) <= 0
                || (float) ($row['commission_base'] ?? 0) > (float) ($row['deal_value'] ?? 0) + 0.009
                || empty($row['invoice_numbers'])
                || empty($row['project_code'])
                || empty($row['product']);
        });
        sceCheck($invalidRow === null, 'Semua row runtime berstatus LUNAS dan dasar komisi valid');

        $dpOnlyQuoteIds = Webkul\Invoice\Models\Invoice::query()
            ->where('event_status', 'confirm')
            ->where('billing_type', 'down_payment')
            ->where('status', 'paid')
            ->whereNotNull('quote_id')
            ->pluck('quote_id')
            ->unique()
            ->filter(function ($quoteId): bool {
                return ! Webkul\Invoice\Models\Invoice::query()
                    ->where('quote_id', $quoteId)
                    ->where('event_status', 'confirm')
                    ->where('billing_type', 'settlement')
                    ->where('status', 'paid')
                    ->exists();
            })
            ->map(fn ($quoteId): string => 'quote:'.(int) $quoteId);

        sceCheck(
            $rows->pluck('deal_key')->intersect($dpOnlyQuoteIds)->isEmpty(),
            'Data nyata: Quote yang baru membayar DP tidak bocor ke export komisi',
        );

        $summary = $exportService->summary($rows);
        sceCheck(
            round((float) $summary->sum('commission_base'), 2)
                === round((float) $rows->sum('commission_base'), 2),
            'Ringkasan per Sales sama dengan total detail deal',
        );

        Illuminate\Support\Facades\Artisan::call('view:clear');
        $viewResult = Illuminate\Support\Facades\Artisan::call('view:cache');
        sceCheck($viewResult === 0, 'Semua Blade berhasil dikompilasi');
    } catch (Throwable $exception) {
        sceCheck(false, 'Laravel runtime: '.$exception->getMessage());
    }
}

echo PHP_EOL;

if ($failures > 0) {
    echo '[FAIL] Checker menemukan '.$failures.' masalah.'.PHP_EOL;
    exit(1);
}

echo '[PASS] Export komisi hanya berisi deal yang lunas penuh.'.PHP_EOL;
echo 'Buka Finance & Sales Dashboard lalu klik Export CSV Komisi Sales.'.PHP_EOL;
