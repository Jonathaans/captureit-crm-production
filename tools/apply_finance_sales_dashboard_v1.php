<?php

declare(strict_types=1);

const PATCH_NAME = 'CRM FINANCE & SALES DASHBOARD V1';
const MARKER = 'CRM_FINANCE_SALES_DASHBOARD_V1';

$root = dirname(__DIR__);
$payloadRoot = __DIR__.DIRECTORY_SEPARATOR.'finance_sales_dashboard_v1_payload';
$writesStarted = false;
$backupDirectory = null;
$manifest = null;

function fsdLine(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function fsdFail(string $message): never
{
    throw new RuntimeException($message);
}

function fsdNormalizePath(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function fsdRead(string $path): string
{
    if (! is_file($path)) {
        fsdFail('File tidak ditemukan: '.$path);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        fsdFail('Tidak dapat membaca: '.$path);
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function fsdWrite(string $path, string $content): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        fsdFail('Tidak dapat membuat folder: '.$directory);
    }

    if (file_put_contents($path, $content, LOCK_EX) === false) {
        fsdFail('Tidak dapat menulis file: '.$path);
    }
}

function fsdReplaceOnce(
    string $content,
    string $search,
    string $replacement,
    string $label
): string {
    $count = substr_count($content, $search);

    if ($count !== 1) {
        fsdFail('Preflight '.$label.' harus ditemukan tepat satu kali; count='.$count.'.');
    }

    return str_replace($search, $replacement, $content);
}

function fsdRun(string $root, array $arguments): int
{
    $command = escapeshellarg(PHP_BINARY);

    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg((string) $argument);
    }

    fsdLine('[RUN]   '.implode(' ', $arguments));
    $previous = getcwd();
    chdir($root);
    passthru($command, $exitCode);

    if ($previous !== false) {
        chdir($previous);
    }

    return (int) $exitCode;
}

function fsdRestore(
    string $root,
    string $backupDirectory,
    array $manifest
): void {
    foreach ($manifest['files'] as $relative => $metadata) {
        $target = $root.DIRECTORY_SEPARATOR.fsdNormalizePath($relative);
        $backup = $backupDirectory.DIRECTORY_SEPARATOR.'files'
            .DIRECTORY_SEPARATOR.fsdNormalizePath($relative);

        if (($metadata['existed'] ?? false) && is_file($backup)) {
            fsdWrite($target, (string) file_get_contents($backup));
        } elseif (is_file($target)) {
            unlink($target);
        }
    }
}

fsdLine(PATCH_NAME);
fsdLine(str_repeat('=', strlen(PATCH_NAME)));
fsdLine();

try {
    if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
        fsdFail('Jalankan tool dari root project Laravel.');
    }

    if (! is_dir($payloadRoot)) {
        fsdFail('Folder payload tidak ditemukan. Extract ZIP secara lengkap.');
    }

    $providerRelative = 'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php';
    $invoiceIndexRelative = 'packages/Webkul/Admin/src/Resources/views/invoices/index.blade.php';
    $financialReportRelative = 'packages/Webkul/Admin/src/Resources/views/invoices/financial-report.blade.php';
    $billingServiceRelative = 'packages/Webkul/Admin/src/Services/FlexibleQuoteBillingService.php';

    foreach ([
        $providerRelative,
        $invoiceIndexRelative,
        $financialReportRelative,
        $billingServiceRelative,
    ] as $relative) {
        if (! is_file($root.DIRECTORY_SEPARATOR.fsdNormalizePath($relative))) {
            fsdFail('Preflight file wajib tidak ditemukan: '.$relative);
        }
    }

    $provider = fsdRead($root.DIRECTORY_SEPARATOR.fsdNormalizePath($providerRelative));
    $invoiceIndex = fsdRead($root.DIRECTORY_SEPARATOR.fsdNormalizePath($invoiceIndexRelative));
    $financialReport = fsdRead($root.DIRECTORY_SEPARATOR.fsdNormalizePath($financialReportRelative));

    $destinationFiles = [
        'packages/Webkul/Admin/src/Services/FinanceSalesDashboardService.php',
        'packages/Webkul/Admin/src/Http/Controllers/Invoice/FinanceSalesDashboardController.php',
        'packages/Webkul/Admin/src/Resources/views/invoices/finance-sales-dashboard.blade.php',
    ];

    if (str_contains($provider, '/* '.MARKER.' */')) {
        foreach ($destinationFiles as $relative) {
            if (
                ! is_file($root.DIRECTORY_SEPARATOR.fsdNormalizePath($relative))
                || ! str_contains(
                    (string) file_get_contents($root.DIRECTORY_SEPARATOR.fsdNormalizePath($relative)),
                    MARKER
                )
            ) {
                fsdFail('Instalasi parsial terdeteksi pada '.$relative.'. Jalankan rollback lalu apply ulang.');
            }
        }

        if (
            ! str_contains($invoiceIndex, MARKER)
            || ! str_contains($financialReport, MARKER)
        ) {
            fsdFail('Instalasi parsial terdeteksi pada tombol dashboard.');
        }

        fsdRun($root, ['artisan', 'optimize:clear']);
        fsdLine('[OK] Dashboard sudah terpasang. Tidak ada file yang ditulis ulang.');
        fsdLine('Jalankan: php tools/check_finance_sales_dashboard_v1.php');
        exit(0);
    }

    foreach ($destinationFiles as $relative) {
        $payload = $payloadRoot.DIRECTORY_SEPARATOR.fsdNormalizePath($relative);

        if (! is_file($payload)) {
            fsdFail('Payload tidak lengkap: '.$relative);
        }
    }

    foreach ([
        "if (\$this->app->runningInConsole()) {",
        "prefix('admin/invoice-billing')",
        'admin.invoices.billing.create',
    ] as $anchor) {
        if (! str_contains($provider, $anchor)) {
            fsdFail('Dependency Flexible Billing belum lengkap pada provider: '.$anchor);
        }
    }

    if (! str_contains($invoiceIndex, '{{-- CRM_INVOICE_FLEXIBLE_BILLING_V1 --}}')) {
        fsdFail('Anchor tombol Generate Invoice tidak ditemukan pada Invoice index.');
    }

    if (! str_contains($financialReport, '{{-- CRM_FINANCIAL_REPORT_EXPENSE_HOTFIX_V1_3 --}}')) {
        fsdFail('Financial Report Hotfix V1.3 wajib dipasang terlebih dahulu.');
    }

    $timestamp = date('Ymd-His');
    $backupDirectory = $root.DIRECTORY_SEPARATOR.'tools'
        .DIRECTORY_SEPARATOR.'backups'
        .DIRECTORY_SEPARATOR.'finance-sales-dashboard-v1-'.$timestamp;

    if (! mkdir($backupDirectory.DIRECTORY_SEPARATOR.'files', 0775, true)) {
        fsdFail('Tidak dapat membuat folder backup source.');
    }

    $allFiles = array_values(array_unique(array_merge(
        [$providerRelative, $invoiceIndexRelative, $financialReportRelative],
        $destinationFiles
    )));

    $manifest = [
        'patch' => PATCH_NAME,
        'created_at' => date(DATE_ATOM),
        'files' => [],
    ];

    foreach ($allFiles as $relative) {
        $source = $root.DIRECTORY_SEPARATOR.fsdNormalizePath($relative);
        $existed = is_file($source);
        $manifest['files'][$relative] = ['existed' => $existed];

        if ($existed) {
            $backup = $backupDirectory.DIRECTORY_SEPARATOR.'files'
                .DIRECTORY_SEPARATOR.fsdNormalizePath($relative);
            fsdWrite($backup, (string) file_get_contents($source));
        }
    }

    fsdWrite(
        $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json',
        (string) json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        )
    );
    fsdWrite(
        $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
            .DIRECTORY_SEPARATOR.'finance-sales-dashboard-v1-latest.txt',
        $backupDirectory
    );

    $writesStarted = true;

    foreach ($destinationFiles as $relative) {
        $source = $payloadRoot.DIRECTORY_SEPARATOR.fsdNormalizePath($relative);
        $target = $root.DIRECTORY_SEPARATOR.fsdNormalizePath($relative);
        fsdWrite($target, (string) file_get_contents($source));
        fsdLine('[WRITE] '.$relative);
    }

    $providerBlock = <<<'PHP'

        /* CRM_FINANCE_SALES_DASHBOARD_V1 */
        \Illuminate\Support\Facades\Route::middleware('web')
            ->prefix('admin/finance-sales-dashboard')
            ->controller(
                \Webkul\Admin\Http\Controllers\Invoice\FinanceSalesDashboardController::class
            )
            ->group(function () {
                \Illuminate\Support\Facades\Route::get('/', 'index')
                    ->name('admin.finance-sales-dashboard.index');

                \Illuminate\Support\Facades\Route::get('export', 'export')
                    ->name('admin.finance-sales-dashboard.export');
            });

PHP;

    $provider = fsdReplaceOnce(
        $provider,
        "        if (\$this->app->runningInConsole()) {",
        $providerBlock."        if (\$this->app->runningInConsole()) {",
        'anchor route provider'
    );

    fsdWrite(
        $root.DIRECTORY_SEPARATOR.fsdNormalizePath($providerRelative),
        $provider
    );
    fsdLine('[PATCH] '.$providerRelative);

    $dashboardButton = <<<'BLADE'
        {{-- CRM_FINANCE_SALES_DASHBOARD_V1 --}}
        <div class="flex items-center gap-2 max-sm:w-full">
            <a
                href="{{ route('admin.finance-sales-dashboard.index') }}"
                class="secondary-button max-sm:w-full max-sm:justify-center"
            >
                Finance & Sales Dashboard
            </a>
        </div>

BLADE;

    $invoiceIndex = fsdReplaceOnce(
        $invoiceIndex,
        '        {{-- CRM_INVOICE_FLEXIBLE_BILLING_V1 --}}',
        $dashboardButton.'        {{-- CRM_INVOICE_FLEXIBLE_BILLING_V1 --}}',
        'tombol dashboard pada Invoice'
    );

    fsdWrite(
        $root.DIRECTORY_SEPARATOR.fsdNormalizePath($invoiceIndexRelative),
        $invoiceIndex
    );
    fsdLine('[PATCH] '.$invoiceIndexRelative);

    $reportButton = <<<'BLADE'
                {{-- CRM_FINANCE_SALES_DASHBOARD_V1 --}}
                <a
                    href="{{ route('admin.finance-sales-dashboard.index') }}"
                    class="secondary-button rounded-lg px-4 py-2.5 text-sm"
                >
                    Finance & Sales Dashboard
                </a>

BLADE;

    $financialReport = fsdReplaceOnce(
        $financialReport,
        '                {{-- CRM_FINANCIAL_REPORT_EXPENSE_HOTFIX_V1_3 --}}',
        $reportButton.'                {{-- CRM_FINANCIAL_REPORT_EXPENSE_HOTFIX_V1_3 --}}',
        'tombol dashboard pada Financial Report'
    );

    fsdWrite(
        $root.DIRECTORY_SEPARATOR.fsdNormalizePath($financialReportRelative),
        $financialReport
    );
    fsdLine('[PATCH] '.$financialReportRelative);

    foreach ([
        'packages/Webkul/Admin/src/Services/FinanceSalesDashboardService.php',
        'packages/Webkul/Admin/src/Http/Controllers/Invoice/FinanceSalesDashboardController.php',
        $providerRelative,
    ] as $relative) {
        if (fsdRun($root, ['-l', $relative]) !== 0) {
            fsdFail('PHP lint gagal: '.$relative);
        }
    }

    if (fsdRun($root, ['artisan', 'optimize:clear']) !== 0) {
        fsdFail('Tidak dapat membersihkan cache Laravel.');
    }

    if (fsdRun($root, ['artisan', 'view:cache']) !== 0) {
        fsdFail('Blade compile gagal.');
    }

    if (
        fsdRun($root, [
            'artisan',
            'route:list',
            '--name=admin.finance-sales-dashboard',
        ]) !== 0
    ) {
        fsdFail('Route dashboard gagal dimuat.');
    }

    fsdLine();
    fsdLine('PATCH BERHASIL.');
    fsdLine('Backup source: '.$backupDirectory);
    fsdLine('Lanjutkan dengan:');
    fsdLine('php tools/check_finance_sales_dashboard_v1.php');
} catch (Throwable $exception) {
    fsdLine();
    fsdLine('PATCH GAGAL: '.$exception->getMessage());

    if ($writesStarted && $backupDirectory && is_array($manifest)) {
        fsdRestore($root, $backupDirectory, $manifest);
        fsdLine('Source dipulihkan otomatis dari backup.');
    }

    exit(1);
}
