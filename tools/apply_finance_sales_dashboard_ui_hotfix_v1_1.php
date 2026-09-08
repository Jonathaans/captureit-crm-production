<?php

declare(strict_types=1);

const PATCH_TITLE = 'FINANCE & SALES DASHBOARD UI HOTFIX V1.1';
const PATCH_MARKER = 'CRM_FINANCE_SALES_DASHBOARD_UI_HOTFIX_V1_1';

$root = dirname(__DIR__);
$payloadRoot = __DIR__.DIRECTORY_SEPARATOR
    .'finance_sales_dashboard_ui_hotfix_v1_1_payload';
$backupDirectory = null;
$manifest = null;
$writesStarted = false;

function fsduLine(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function fsduFail(string $message): never
{
    throw new RuntimeException($message);
}

function fsduPath(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function fsduRead(string $path): string
{
    if (! is_file($path)) {
        fsduFail('File tidak ditemukan: '.$path);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        fsduFail('Tidak dapat membaca: '.$path);
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function fsduWrite(string $path, string $content): void
{
    $directory = dirname($path);

    if (
        ! is_dir($directory)
        && ! mkdir($directory, 0775, true)
        && ! is_dir($directory)
    ) {
        fsduFail('Tidak dapat membuat folder: '.$directory);
    }

    if (file_put_contents($path, $content, LOCK_EX) === false) {
        fsduFail('Tidak dapat menulis: '.$path);
    }
}

function fsduReplaceOnce(
    string $content,
    string $search,
    string $replacement,
    string $label
): string {
    $count = substr_count($content, $search);

    if ($count !== 1) {
        fsduFail(
            'Preflight '.$label.' harus ditemukan tepat satu kali; count='.$count.'.'
        );
    }

    return str_replace($search, $replacement, $content);
}

function fsduRun(string $root, array $arguments): int
{
    $command = escapeshellarg(PHP_BINARY);

    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg((string) $argument);
    }

    fsduLine('[RUN]   '.implode(' ', $arguments));
    $previous = getcwd();
    chdir($root);
    passthru($command, $exitCode);

    if ($previous !== false) {
        chdir($previous);
    }

    return (int) $exitCode;
}

function fsduRestore(
    string $root,
    string $backupDirectory,
    array $manifest
): void {
    foreach ($manifest['files'] as $relative => $metadata) {
        $target = $root.DIRECTORY_SEPARATOR.fsduPath($relative);
        $backup = $backupDirectory.DIRECTORY_SEPARATOR.'files'
            .DIRECTORY_SEPARATOR.fsduPath($relative);

        if (($metadata['existed'] ?? false) === true && is_file($backup)) {
            fsduWrite($target, (string) file_get_contents($backup));
        } elseif (is_file($target)) {
            unlink($target);
        }
    }
}

fsduLine(PATCH_TITLE);
fsduLine(str_repeat('=', strlen(PATCH_TITLE)));
fsduLine();

try {
    if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
        fsduFail('Jalankan tool dari root project Laravel.');
    }

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
        if (! is_file($root.DIRECTORY_SEPARATOR.fsduPath($relative))) {
            fsduFail('Preflight file wajib tidak ditemukan: '.$relative);
        }
    }

    $payloadView = $payloadRoot.DIRECTORY_SEPARATOR.fsduPath($dashboardRelative);

    if (! is_file($payloadView)) {
        fsduFail('Payload view tidak ditemukan. Extract ZIP secara lengkap.');
    }

    $contents = [];

    foreach ($files as $relative) {
        $contents[$relative] = fsduRead(
            $root.DIRECTORY_SEPARATOR.fsduPath($relative)
        );
    }

    $installedCount = 0;

    foreach ($files as $relative) {
        if (str_contains($contents[$relative], PATCH_MARKER)) {
            $installedCount++;
        }
    }

    if ($installedCount === count($files)) {
        fsduRun($root, ['artisan', 'optimize:clear']);
        fsduRun($root, ['artisan', 'view:cache']);
        fsduLine('[OK] Hotfix V1.1 sudah terpasang lengkap.');
        fsduLine('Jalankan: php tools/check_finance_sales_dashboard_ui_hotfix_v1_1.php');
        exit(0);
    }

    if ($installedCount !== 0) {
        fsduFail(
            'Instalasi parsial terdeteksi; marker V1.1 ditemukan pada '
            .$installedCount.' dari '.count($files).' file.'
        );
    }

    foreach ([
        $serviceRelative => [
            'CRM_FINANCE_SALES_DASHBOARD_V1',
            'class FinanceSalesDashboardService',
            'use Webkul\\Core\\Support\\BusinessUnit;',
        ],
        $dashboardRelative => ['CRM_FINANCE_SALES_DASHBOARD_V1'],
        $invoiceIndexRelative => [
            'CRM_FINANCE_SALES_DASHBOARD_V1',
            'CRM_INVOICE_FLEXIBLE_BILLING_V1',
        ],
        $financialReportRelative => [
            'CRM_FINANCE_SALES_DASHBOARD_V1',
            'CRM_FINANCIAL_REPORT_EXPENSE_HOTFIX_V1_3',
        ],
    ] as $relative => $markers) {
        foreach ($markers as $marker) {
            if (! str_contains($contents[$relative], $marker)) {
                fsduFail('Dependency/anchor kurang pada '.$relative.': '.$marker);
            }
        }
    }

    $timestamp = date('Ymd-His');
    $backupDirectory = $root.DIRECTORY_SEPARATOR.'tools'
        .DIRECTORY_SEPARATOR.'backups'
        .DIRECTORY_SEPARATOR.'finance-sales-dashboard-ui-hotfix-v1_1-'
        .$timestamp;

    if (! mkdir($backupDirectory.DIRECTORY_SEPARATOR.'files', 0775, true)) {
        fsduFail('Tidak dapat membuat folder backup source.');
    }

    $manifest = [
        'patch' => PATCH_TITLE,
        'created_at' => date(DATE_ATOM),
        'files' => [],
    ];

    foreach ($files as $relative) {
        $source = $root.DIRECTORY_SEPARATOR.fsduPath($relative);
        $backup = $backupDirectory.DIRECTORY_SEPARATOR.'files'
            .DIRECTORY_SEPARATOR.fsduPath($relative);
        $manifest['files'][$relative] = ['existed' => true];
        fsduWrite($backup, (string) file_get_contents($source));
    }

    fsduWrite(
        $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json',
        (string) json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        )
    );
    fsduWrite(
        $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
            .DIRECTORY_SEPARATOR
            .'finance-sales-dashboard-ui-hotfix-v1_1-latest.txt',
        $backupDirectory
    );

    $writesStarted = true;

    $oldBusinessUnits = <<<'PHP'
            'businessUnits' => Invoice::query()
                ->whereNotNull('business_unit')
                ->where('business_unit', '!=', '')
                ->distinct()
                ->orderBy('business_unit')
                ->pluck('business_unit')
                ->map(fn ($unit) => [
                    'value' => (string) $unit,
                    'label' => BusinessUnit::label((string) $unit),
                ])
                ->values(),
PHP;

    $contents[$serviceRelative] = fsduReplaceOnce(
        $contents[$serviceRelative],
        $oldBusinessUnits,
        "            'businessUnits' => \$this->businessUnitOptions(),",
        'sumber dropdown Business Unit'
    );

    $businessUnitMethod = <<<'PHP'
    /* CRM_FINANCE_SALES_DASHBOARD_UI_HOTFIX_V1_1 */
    private function businessUnitOptions(): Collection
    {
        $masterOptions = collect(BusinessUnit::options())
            ->map(fn ($label, $value) => [
                'value' => (string) $value,
                'label' => (string) $label,
            ])
            ->values();

        $knownValues = $masterOptions->pluck('value')->all();

        $historicalOptions = Invoice::query()
            ->whereNotNull('business_unit')
            ->where('business_unit', '!=', '')
            ->distinct()
            ->pluck('business_unit')
            ->map(fn ($value) => (string) $value)
            ->filter(fn ($value) => ! in_array($value, $knownValues, true))
            ->map(fn ($value) => [
                'value' => $value,
                'label' => BusinessUnit::label($value) ?: $value,
            ]);

        return $masterOptions
            ->concat($historicalOptions)
            ->unique('value')
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

PHP;

    $contents[$serviceRelative] = fsduReplaceOnce(
        $contents[$serviceRelative],
        '    private function metricInvoices(array $filters): Collection',
        $businessUnitMethod
            .'    private function metricInvoices(array $filters): Collection',
        'lokasi method Business Unit master'
    );

    $oldInvoiceActions = <<<'BLADE'
        {{-- CRM_FINANCE_SALES_DASHBOARD_V1 --}}
        <div class="flex items-center gap-2 max-sm:w-full">
            <a
                href="{{ route('admin.finance-sales-dashboard.index') }}"
                class="secondary-button max-sm:w-full max-sm:justify-center"
            >
                Finance & Sales Dashboard
            </a>
        </div>
        {{-- CRM_INVOICE_FLEXIBLE_BILLING_V1 --}}
        <div class="flex items-center gap-2 max-sm:w-full">
            <a
                href="{{ route('admin.invoices.billing.create') }}"
                class="primary-button max-sm:w-full max-sm:justify-center"
            >
                + Generate dari Quote
            </a>
        </div>
BLADE;

    $newInvoiceActions = <<<'BLADE'
        {{-- CRM_FINANCE_SALES_DASHBOARD_UI_HOTFIX_V1_1 --}}
        {{-- CRM_FINANCE_SALES_DASHBOARD_V1 --}}
        {{-- CRM_INVOICE_FLEXIBLE_BILLING_V1 --}}
        <div
            class="max-sm:w-full"
            style="display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:8px;margin-left:auto"
        >
            <a
                href="{{ route('admin.finance-sales-dashboard.index') }}"
                class="secondary-button max-sm:w-full max-sm:justify-center"
            >
                Finance & Sales Dashboard
            </a>

            <a
                href="{{ route('admin.invoices.billing.create') }}"
                class="primary-button max-sm:w-full max-sm:justify-center"
            >
                + Generate dari Quote
            </a>
        </div>
BLADE;

    $contents[$invoiceIndexRelative] = fsduReplaceOnce(
        $contents[$invoiceIndexRelative],
        $oldInvoiceActions,
        $newInvoiceActions,
        'kelompok tombol Invoice'
    );

    $oldReportActions = <<<'BLADE'
                @if (bouncer()->hasPermission('invoices.financial-report.export'))
                    <a
                        href="{{ route('admin.invoices.financial-report.export', ['year' => $year, 'month' => $month, 'business_unit' => $businessUnit, 'event_status' => $eventStatus, 'product' => $product]) }}"
                        class="secondary-button rounded-lg px-4 py-2.5 text-sm"
                    >
                        Export Financial Report
                    </a>
                @endif

                {{-- CRM_FINANCE_SALES_DASHBOARD_V1 --}}
                <a
                    href="{{ route('admin.finance-sales-dashboard.index') }}"
                    class="secondary-button rounded-lg px-4 py-2.5 text-sm"
                >
                    Finance & Sales Dashboard
                </a>
                {{-- CRM_FINANCIAL_REPORT_EXPENSE_HOTFIX_V1_3 --}}
                @if (bouncer()->hasPermission('invoices.expense.export-all'))
                    <a
                        href="{{ route('admin.invoices.expenses.export-all') }}"
                        class="primary-button rounded-lg px-4 py-2.5 text-sm"
                    >
                        Export All Expenses
                    </a>
                @endif
BLADE;

    $newReportActions = <<<'BLADE'
                {{-- CRM_FINANCE_SALES_DASHBOARD_UI_HOTFIX_V1_1 --}}
                {{-- CRM_FINANCE_SALES_DASHBOARD_V1 --}}
                {{-- CRM_FINANCIAL_REPORT_EXPENSE_HOTFIX_V1_3 --}}
                <div style="display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:8px;margin-left:auto">
                    @if (bouncer()->hasPermission('invoices.financial-report.export'))
                        <a
                            href="{{ route('admin.invoices.financial-report.export', ['year' => $year, 'month' => $month, 'business_unit' => $businessUnit, 'event_status' => $eventStatus, 'product' => $product]) }}"
                            class="secondary-button rounded-lg px-4 py-2.5 text-sm"
                        >
                            Export Financial Report
                        </a>
                    @endif

                    <a
                        href="{{ route('admin.finance-sales-dashboard.index') }}"
                        class="secondary-button rounded-lg px-4 py-2.5 text-sm"
                    >
                        Finance & Sales Dashboard
                    </a>

                    @if (bouncer()->hasPermission('invoices.expense.export-all'))
                        <a
                            href="{{ route('admin.invoices.expenses.export-all') }}"
                            class="primary-button rounded-lg px-4 py-2.5 text-sm"
                        >
                            Export All Expenses
                        </a>
                    @endif
                </div>
BLADE;

    $contents[$financialReportRelative] = fsduReplaceOnce(
        $contents[$financialReportRelative],
        $oldReportActions,
        $newReportActions,
        'kelompok tombol Financial Report'
    );

    $oldReportFilterGrid = <<<'BLADE'
            <form method="GET" action="{{ route('admin.invoices.financial-report') }}">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
BLADE;

    $newReportFilterGrid = <<<'BLADE'
            <style>
                .crm-fr-v11-filter-grid {
                    display: grid;
                    grid-template-columns: repeat(5, minmax(0, 1fr));
                    gap: 16px;
                }

                @media (max-width: 1180px) {
                    .crm-fr-v11-filter-grid {
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                    }
                }

                @media (max-width: 680px) {
                    .crm-fr-v11-filter-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>

            <form method="GET" action="{{ route('admin.invoices.financial-report') }}">
                <div class="crm-fr-v11-filter-grid">
BLADE;

    $contents[$financialReportRelative] = fsduReplaceOnce(
        $contents[$financialReportRelative],
        $oldReportFilterGrid,
        $newReportFilterGrid,
        'grid filter Financial Report'
    );

    $contents[$dashboardRelative] = fsduRead($payloadView);

    foreach ($files as $relative) {
        fsduWrite(
            $root.DIRECTORY_SEPARATOR.fsduPath($relative),
            $contents[$relative]
        );
        fsduLine('[PATCH] '.$relative);
    }

    if (fsduRun($root, ['-l', $serviceRelative]) !== 0) {
        fsduFail('PHP lint Service gagal.');
    }

    if (fsduRun($root, ['artisan', 'optimize:clear']) !== 0) {
        fsduFail('Tidak dapat membersihkan cache Laravel.');
    }

    if (fsduRun($root, ['artisan', 'view:cache']) !== 0) {
        fsduFail('Blade compile gagal.');
    }

    fsduLine();
    fsduLine('HOTFIX BERHASIL.');
    fsduLine('Backup source: '.$backupDirectory);
    fsduLine('Lanjutkan dengan:');
    fsduLine('php tools/check_finance_sales_dashboard_ui_hotfix_v1_1.php');
} catch (Throwable $exception) {
    fsduLine();
    fsduLine('HOTFIX GAGAL: '.$exception->getMessage());

    if ($writesStarted && $backupDirectory && is_array($manifest)) {
        fsduRestore($root, $backupDirectory, $manifest);
        fsduLine('Source dipulihkan otomatis dari backup.');
    }

    exit(1);
}
