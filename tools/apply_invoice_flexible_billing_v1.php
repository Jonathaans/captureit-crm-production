<?php

declare(strict_types=1);

const PATCH_NAME = 'CRM INVOICE FLEXIBLE DP + PELUNASAN V1.1';
const MARKER = 'CRM_INVOICE_FLEXIBLE_BILLING_V1';

$root = dirname(__DIR__);
$payloadRoot = __DIR__.DIRECTORY_SEPARATOR.'invoice_flexible_billing_v1_payload';

function line(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function fail(string $message): never
{
    throw new RuntimeException($message);
}

function relativePath(string $root, string $path): string
{
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
}

function readNormalized(string $path): string
{
    if (! is_file($path)) {
        fail('File tidak ditemukan: '.$path);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        fail('Tidak dapat membaca: '.$path);
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function writeFile(string $path, string $content): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        fail('Tidak dapat membuat folder: '.$directory);
    }

    if (file_put_contents($path, $content, LOCK_EX) === false) {
        fail('Tidak dapat menulis: '.$path);
    }
}

function replaceOnce(
    string $content,
    string $search,
    string $replacement,
    string $label
): string {
    $count = substr_count($content, $search);

    if ($count !== 1) {
        fail('Preflight '.$label.' gagal; count='.$count.'.');
    }

    return str_replace($search, $replacement, $content);
}

function replaceMethod(
    string $content,
    string $methodName,
    string $replacement
): string {
    $needle = 'public function '.$methodName.'(';
    $start = strpos($content, $needle);

    if ($start === false) {
        fail('Method '.$methodName.' tidak ditemukan.');
    }

    $second = strpos($content, $needle, $start + strlen($needle));

    if ($second !== false) {
        fail('Method '.$methodName.' ditemukan lebih dari satu kali.');
    }

    $brace = strpos($content, '{', $start);

    if ($brace === false) {
        fail('Opening brace method '.$methodName.' tidak ditemukan.');
    }

    $depth = 0;
    $length = strlen($content);
    $end = null;

    for ($i = $brace; $i < $length; $i++) {
        if ($content[$i] === '{') {
            $depth++;
        } elseif ($content[$i] === '}') {
            $depth--;

            if ($depth === 0) {
                $end = $i + 1;
                break;
            }
        }
    }

    if ($end === null) {
        fail('Closing brace method '.$methodName.' tidak ditemukan.');
    }

    return substr($content, 0, $start)
        .$replacement
        .substr($content, $end);
}

function runCommand(string $root, array $arguments): int
{
    $command = escapeshellarg(PHP_BINARY);

    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg($argument);
    }

    line('[RUN]   '.implode(' ', $arguments));

    $previous = getcwd();
    chdir($root);
    passthru($command, $exitCode);

    if ($previous !== false) {
        chdir($previous);
    }

    return (int) $exitCode;
}

function restoreBackup(string $root, string $backupDirectory, array $manifest): void
{
    foreach ($manifest['files'] as $relative => $meta) {
        $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if ($meta['existed']) {
            $backup = $backupDirectory.DIRECTORY_SEPARATOR.'files'
                .DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (is_file($backup)) {
                writeFile($target, (string) file_get_contents($backup));
            }
        } elseif (is_file($target)) {
            unlink($target);
        }
    }
}

line(PATCH_NAME);
line(str_repeat('=', strlen(PATCH_NAME)));
line();

try {
    if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
        fail('Jalankan script dari root project Laravel.');
    }

    if (! is_dir($payloadRoot)) {
        fail('Folder payload tidak ditemukan. Extract ZIP secara lengkap.');
    }

    $modified = [
        'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php',
        'packages/Webkul/Admin/src/Http/Controllers/Invoice/InvoiceController.php',
        'packages/Webkul/Admin/src/Resources/views/invoices/index.blade.php',
        'packages/Webkul/Admin/src/Resources/views/invoices/show.blade.php',
        'packages/Webkul/Admin/src/Resources/views/invoices/pdf.blade.php',
        'packages/Webkul/Admin/src/Http/Controllers/Invoice/FinancialReportController.php',
        'packages/Webkul/Admin/src/Resources/views/invoices/financial-report.blade.php',
    ];

    foreach ($modified as $relative) {
        if (! is_file($root.DIRECTORY_SEPARATOR.$relative)) {
            fail('Preflight file wajib tidak ditemukan: '.$relative);
        }
    }

    $providerPath = $root.DIRECTORY_SEPARATOR.$modified[0];
    $provider = readNormalized($providerPath);

    if (str_contains($provider, '/* '.MARKER.' */')) {
        $installedMarkers = [
            'packages/Webkul/Admin/src/Http/Controllers/Invoice/InvoiceController.php' =>
                'CRM_INVOICE_FLEXIBLE_BILLING_V1',
            'packages/Webkul/Admin/src/Resources/views/invoices/index.blade.php' =>
                'CRM_INVOICE_FLEXIBLE_BILLING_V1',
            'packages/Webkul/Admin/src/Resources/views/invoices/show.blade.php' =>
                'CRM_INVOICE_FLEXIBLE_BILLING_V1',
            'packages/Webkul/Admin/src/Resources/views/invoices/pdf.blade.php' =>
                'CRM_INVOICE_FLEXIBLE_BILLING_V1',
            'packages/Webkul/Admin/src/Http/Controllers/Invoice/FinancialReportController.php' =>
                'CRM_INVOICE_FLEXIBLE_BILLING_V1',
            'packages/Webkul/Admin/src/Resources/views/invoices/financial-report.blade.php' =>
                'CRM_INVOICE_FLEXIBLE_BILLING_V1',
        ];

        foreach ([
            'admin.invoices.billing.create',
            'admin.invoices.billing.summary',
            'admin.invoices.billing.store',
            "prefix('admin/invoice-billing')",
        ] as $providerRouteMarker) {
            if (! str_contains($provider, $providerRouteMarker)) {
                fail(
                    'Instalasi parsial: route billing tidak lengkap pada provider. '
                    .'Jalankan rollback V1, lalu apply ulang.'
                );
            }
        }

        foreach ($installedMarkers as $relative => $requiredMarker) {
            $path = $root.DIRECTORY_SEPARATOR.$relative;

            if (
                ! is_file($path)
                || ! str_contains((string) file_get_contents($path), $requiredMarker)
            ) {
                fail(
                    'Instalasi parsial terdeteksi pada '.$relative.'. '
                    .'Jalankan rollback V1, lalu apply ulang.'
                );
            }
        }

        foreach ([
            'packages/Webkul/Admin/src/Services/FlexibleQuoteBillingService.php',
            'packages/Webkul/Admin/src/Http/Controllers/Invoice/FlexibleQuoteBillingController.php',
            'packages/Webkul/Admin/src/Resources/views/invoices/billing-create.blade.php',
            'database/migrations/2026_09_07_100000_add_flexible_quote_billing_to_invoices.php',
        ] as $requiredFile) {
            if (! is_file($root.DIRECTORY_SEPARATOR.$requiredFile)) {
                fail(
                    'Instalasi parsial: file tidak tersedia '.$requiredFile.'. '
                    .'Jalankan rollback V1, lalu apply ulang.'
                );
            }
        }

        if (runCommand($root, ['artisan', 'migrate', '--force']) !== 0) {
            fail('Source sudah terpasang, tetapi migration masih gagal.');
        }

        runCommand($root, ['artisan', 'optimize:clear']);

        line('[OK] Patch sudah terpasang. Tidak ada file yang ditulis ulang.');
        line('Jalankan: php tools/check_invoice_flexible_billing_v1.php');
        exit(0);
    }

    /*
     * Complete preflight before writing anything.
     */
    if (! str_contains($provider, "if (\$this->app->runningInConsole()) {")) {
        fail('Anchor provider console tidak ditemukan.');
    }

    $invoiceIndex = readNormalized($root.DIRECTORY_SEPARATOR.$modified[2]);

    if (! str_contains($invoiceIndex, '{{-- EXPORT ALL EXPENSES CSV V1 --}}')) {
        fail('Anchor header Invoice tidak ditemukan.');
    }

    $invoicePdf = readNormalized($root.DIRECTORY_SEPARATOR.$modified[4]);

    foreach ([
        '$paymentStatus = match ($invoice->status)',
        '<div class="document-number">',
        '<span class="project-label">Payment Term</span>',
        '<!-- Invoice Summary -->',
    ] as $anchor) {
        if (! str_contains($invoicePdf, $anchor)) {
            fail('Anchor PDF Invoice tidak ditemukan: '.$anchor);
        }
    }

    $financialController = readNormalized(
        $root.DIRECTORY_SEPARATOR.$modified[5]
    );

    foreach ([
        'private function buildFinancialSummary(array $filters): array',
        "'revenue' => \$revenue,",
        "'cash_surplus' => \$received - \$expense,",
        'Revenue - Confirmed Invoice Value in Period',
    ] as $anchor) {
        if (! str_contains($financialController, $anchor)) {
            fail('Anchor Financial Report Controller tidak ditemukan: '.$anchor);
        }
    }

    $financialView = readNormalized(
        $root.DIRECTORY_SEPARATOR.$modified[6]
    );

    if (
        ! str_contains($financialView, '{{-- Summary cards --}}')
        || ! str_contains($financialView, '{{-- Invoice cohort --}}')
    ) {
        fail('Anchor kartu Financial Report tidak ditemukan.');
    }

    $showView = readNormalized($root.DIRECTORY_SEPARATOR.$modified[3]);

    $showStatusAnchor = <<<'BLADE'
                        {{ $invoice->project_code ?? '-' }}
                    </span>
                </div>
                @if ($invoice->status === 'paid')
BLADE;

    if (substr_count($showView, $showStatusAnchor) !== 1) {
        fail('Anchor status Invoice Show tidak ditemukan.');
    }

    $timestamp = date('Ymd-His');
    $backupDirectory = $root.DIRECTORY_SEPARATOR.'tools'
        .DIRECTORY_SEPARATOR.'backups'
        .DIRECTORY_SEPARATOR.'invoice-flexible-billing-v1-'.$timestamp;

    if (! mkdir($backupDirectory.DIRECTORY_SEPARATOR.'files', 0775, true)) {
        fail('Tidak dapat membuat folder backup.');
    }

    $manifest = [
        'patch' => PATCH_NAME,
        'created_at' => date(DATE_ATOM),
        'files' => [],
    ];

    /*
     * Include all payload destinations in the backup manifest.
     */
    $payloadIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $payloadRoot,
            FilesystemIterator::SKIP_DOTS
        )
    );

    $payloadFiles = [];

    foreach ($payloadIterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $relative = relativePath($payloadRoot, $file->getPathname());
        $payloadFiles[$relative] = $file->getPathname();
    }

    $allDestinations = array_values(
        array_unique(array_merge($modified, array_keys($payloadFiles)))
    );

    foreach ($allDestinations as $relative) {
        $source = $root.DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $exists = is_file($source);

        $manifest['files'][$relative] = [
            'existed' => $exists,
        ];

        if ($exists) {
            $backup = $backupDirectory.DIRECTORY_SEPARATOR.'files'
                .DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            writeFile($backup, (string) file_get_contents($source));
        }
    }

    writeFile(
        $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    writeFile(
        $root.DIRECTORY_SEPARATOR.'tools'
            .DIRECTORY_SEPARATOR.'backups'
            .DIRECTORY_SEPARATOR.'invoice-flexible-billing-v1-latest.txt',
        $backupDirectory
    );

    $writesStarted = true;

    /*
     * Copy new files.
     */
    foreach ($payloadFiles as $relative => $source) {
        $target = $root.DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, $relative);
        writeFile($target, (string) file_get_contents($source));
        line('[WRITE] '.$relative);
    }

    /*
     * Provider: collision-free routes + immutable billing listener.
     * The dedicated URI avoids depending on a version-specific Invoice route
     * file and cannot be swallowed by an existing /admin/invoices/{id} route.
     */
    $providerBlock = <<<'PHP'

        /* CRM_INVOICE_FLEXIBLE_BILLING_V1 */
        \Illuminate\Support\Facades\Route::middleware('web')
            ->prefix('admin/invoice-billing')
            ->controller(
                \Webkul\Admin\Http\Controllers\Invoice\FlexibleQuoteBillingController::class
            )
            ->group(function () {
                \Illuminate\Support\Facades\Route::get('create', 'create')
                    ->name('admin.invoices.billing.create');

                \Illuminate\Support\Facades\Route::get('quotes/{quoteId}', 'summary')
                    ->whereNumber('quoteId')
                    ->name('admin.invoices.billing.summary');

                \Illuminate\Support\Facades\Route::post('/', 'store')
                    ->name('admin.invoices.billing.store');
            });

        foreach (['creating', 'updating', 'deleting'] as $billingOperation) {
            Event::listen(
                'eloquent.'.$billingOperation.': *',
                function (
                    string $eventName,
                    array $data
                ) use ($billingOperation) {
                    $model = $data[0] ?? null;

                    if ($model instanceof Model) {
                        app(
                            \Webkul\Admin\Services\FlexibleQuoteBillingService::class
                        )->assertMutable(
                            $model,
                            $billingOperation === 'deleting'
                                ? 'delete'
                                : ($billingOperation === 'creating'
                                    ? 'create'
                                    : 'update')
                        );
                    }
                }
            );
        }

PHP;

    $provider = replaceOnce(
        $provider,
        "        if (\$this->app->runningInConsole()) {",
        $providerBlock."        if (\$this->app->runningInConsole()) {",
        'pasang provider billing'
    );

    writeFile($providerPath, $provider);
    line('[PATCH] '.$modified[0]);

    /*
     * Invoice list CTA.
     */
    $invoiceButton = <<<'BLADE'
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

    $invoiceIndex = replaceOnce(
        $invoiceIndex,
        '        {{-- EXPORT ALL EXPENSES CSV V1 --}}',
        $invoiceButton.'        {{-- EXPORT ALL EXPENSES CSV V1 --}}',
        'pasang tombol Generate Invoice'
    );

    writeFile($root.DIRECTORY_SEPARATOR.$modified[2], $invoiceIndex);
    line('[PATCH] '.$modified[2]);

    /*
     * Legacy Generate action now opens the controlled billing form.
     */
    $invoiceControllerPath = $root.DIRECTORY_SEPARATOR.$modified[1];
    $invoiceController = readNormalized($invoiceControllerPath);
    $invoiceController = replaceMethod(
        $invoiceController,
        'generate',
        <<<'PHP'
public function generate(
        int $quoteId
    ): RedirectResponse {
        /* CRM_INVOICE_FLEXIBLE_BILLING_V1 */
        return redirect()->route(
            'admin.invoices.billing.create',
            ['quote_id' => $quoteId]
        );
    }
PHP
    );

    writeFile($invoiceControllerPath, $invoiceController);
    line('[PATCH] '.$modified[1]);

    /*
     * Invoice Show billing badge.
     */
    $showBadge = <<<'BLADE'
                {{-- CRM_INVOICE_FLEXIBLE_BILLING_V1 --}}
                @if ($invoice->billing_locked_at)
                    @php
                        $billingTypeLabel = match ($invoice->billing_type) {
                            'down_payment' => 'DOWN PAYMENT',
                            'settlement' => 'PELUNASAN',
                            default => 'FULL PAYMENT',
                        };
                    @endphp
                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                        {{ $billingTypeLabel }}
                    </span>
                @endif

BLADE;

    $showView = replaceOnce(
        $showView,
        $showStatusAnchor,
        <<<'BLADE'
                        {{ $invoice->project_code ?? '-' }}
                    </span>
                </div>
BLADE
            ."\n"
            .$showBadge
            ."                @if (\$invoice->status === 'paid')",
        'pasang badge billing pada Invoice Show'
    );

    writeFile($root.DIRECTORY_SEPARATOR.$modified[3], $showView);
    line('[PATCH] '.$modified[3]);

    /*
     * PDF metadata and visible labels.
     */
    $paymentStatusStart = strpos(
        $invoicePdf,
        '        $paymentStatus = match ($invoice->status)'
    );
    $phpEnd = $paymentStatusStart === false
        ? false
        : strpos($invoicePdf, '    @endphp', $paymentStatusStart);

    if ($phpEnd === false) {
        fail('Posisi metadata PDF tidak ditemukan.');
    }

    $pdfVariables = <<<'BLADE'

        /* CRM_INVOICE_FLEXIBLE_BILLING_V1 */
        $billingType = $invoice->billing_type ?: 'full_payment';

        $billingLabel = match ($billingType) {
            'down_payment' => 'DOWN PAYMENT',
            'settlement' => 'PELUNASAN',
            default => 'FULL PAYMENT',
        };

        $dpInvoiceNumber = $invoice->dp_invoice_id
            ? \Illuminate\Support\Facades\DB::table('invoices')
                ->where('id', $invoice->dp_invoice_id)
                ->value('invoice_number')
            : null;

        $quoteTotalSnapshot = (float) (
            $invoice->quote_total_snapshot
            ?: $invoice->grand_total
        );

        $remainingAmountSnapshot = (float) (
            $invoice->remaining_amount_snapshot
            ?? 0
        );

        $billingPercentageLabel = $invoice->billing_percentage !== null
            ? rtrim(
                rtrim(
                    number_format(
                        (float) $invoice->billing_percentage,
                        4,
                        '.',
                        ''
                    ),
                    '0'
                ),
                '.'
            ).'%'
            : null;
BLADE;

    $invoicePdf = substr($invoicePdf, 0, $phpEnd)
        .$pdfVariables."\n"
        .substr($invoicePdf, $phpEnd);

    $numberBlock = <<<'BLADE'
        <div class="document-number">
            {{ $invoice->invoice_number }}
        </div>
BLADE;

    $billingPdfBadge = $numberBlock.<<<'BLADE'

        @if ($invoice->billing_locked_at)
            <div style="margin-top:5px; font-size:10px; font-weight:700; color:#1d4ed8;">
                {{ $billingLabel }}
                @if ($billingPercentageLabel)
                    &middot; {{ $billingPercentageLabel }}
                @endif
            </div>
        @endif
BLADE;

    $invoicePdf = replaceOnce(
        $invoicePdf,
        $numberBlock,
        $billingPdfBadge,
        'pasang label jenis Invoice pada PDF'
    );

    $paymentTermRow = <<<'BLADE'
                <div class="project-row">
                    <span class="project-label">Payment Term</span>
                    <span class="project-value">: {{ $invoice->payment_term ?? '-' }}</span>
                </div>
BLADE;

    $billingRows = <<<'BLADE'
                @if ($invoice->billing_locked_at)
                    <div class="project-row">
                        <span class="project-label">Billing Type</span>
                        <span class="project-value">: {{ $billingLabel }}</span>
                    </div>

                    @if ($dpInvoiceNumber)
                        <div class="project-row">
                            <span class="project-label">DP Reference</span>
                            <span class="project-value">: {{ $dpInvoiceNumber }}</span>
                        </div>
                    @endif
                @endif

BLADE;

    $invoicePdf = replaceOnce(
        $invoicePdf,
        $paymentTermRow,
        $billingRows.$paymentTermRow,
        'pasang referensi DP pada PDF'
    );

    $summaryOpen = <<<'BLADE'
        <table class="summary-table">
            <tr>
                <td class="summary-label">
                    Sub Total
BLADE;

    $summaryBilling = <<<'BLADE'
        <table class="summary-table">
            @if ($invoice->billing_locked_at && $billingType !== 'full_payment')
                <tr>
                    <td class="summary-label">
                        Quote Contract Total
                    </td>
                    <td class="summary-value">
                        Rp {{ number_format($quoteTotalSnapshot, 0, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td class="summary-label">
                        Remaining After Invoice
                    </td>
                    <td class="summary-value">
                        Rp {{ number_format($remainingAmountSnapshot, 0, ',', '.') }}
                    </td>
                </tr>
            @endif

            <tr>
                <td class="summary-label">
                    Sub Total
BLADE;

    $invoicePdf = replaceOnce(
        $invoicePdf,
        $summaryOpen,
        $summaryBilling,
        'pasang ringkasan kontrak pada PDF'
    );

    writeFile($root.DIRECTORY_SEPARATOR.$modified[4], $invoicePdf);
    line('[PATCH] '.$modified[4]);

    /*
     * Financial report: Invoice value, cash received, receivable and
     * unbilled remainder are separate metrics. Revenue follows cash received.
     */
    $financialController = replaceOnce(
        $financialController,
        <<<'PHP'
        return [
            'revenue' => $revenue,
            'received' => $received,
            'outstanding' => $outstanding,
            'expense' => $expense,
            'estimated_profit' => $estimatedProfit,
            'cash_surplus' => $received - $expense,
        ];
PHP,
        <<<'PHP'
        /* CRM_INVOICE_FLEXIBLE_BILLING_V1 */
        $invoiced = $revenue;

        $cohortQuoteIds = (
            ! $filters['event_status']
            || $filters['event_status'] === 'confirm'
        )
            ? (clone $revenueQuery)
                ->whereNotNull('quote_id')
                ->pluck('quote_id')
                ->unique()
            : collect();

        $unbilled = app(
            \Webkul\Admin\Services\FlexibleQuoteBillingService::class
        )->sumUnbilledForQuoteIds($cohortQuoteIds);

        return [
            /*
             * Revenue/Cash In is recognized from actual Payment rows.
             * Quote itself is never counted as revenue.
             */
            'revenue' => $received,
            'invoiced' => $invoiced,
            'received' => $received,
            'outstanding' => $outstanding,
            'unbilled' => $unbilled,
            'expense' => $expense,
            'estimated_profit' => $received - $expense,
            'cash_surplus' => $received - $expense,
        ];
PHP,
        'perbaiki ringkasan Financial Report'
    );

    $financialController = replaceOnce(
        $financialController,
        <<<'PHP'
                $writeRow($handle, [
                    'Revenue - Confirmed Invoice Value in Period',
                    $financialSummary['revenue'],
                ]);

                $writeRow($handle, [
                    'Payment Received - Cash In During Period',
                    $financialSummary['received'],
                ]);

                $writeRow($handle, [
                    'Outstanding - Current Balance of Confirmed Invoice Cohort',
                    $financialSummary['outstanding'],
                ]);

                $writeRow($handle, [
                    'Expense - Cash Out During Period',
                    $financialSummary['expense'],
                ]);

                $writeRow($handle, [
                    'Estimated Project Profit - Invoice Cohort minus All Project Expenses',
                    $financialSummary['estimated_profit'],
                ]);

                $writeRow($handle, [
                    'Cash Surplus - Cash In minus Cash Out During Period',
                    $financialSummary['cash_surplus'],
                ]);
PHP,
        <<<'PHP'
                $writeRow($handle, [
                    'Total Invoiced - Active Confirmed Invoice Value',
                    $financialSummary['invoiced'],
                ]);

                $writeRow($handle, [
                    'Revenue / Cash Received - Actual Payment in Period',
                    $financialSummary['received'],
                ]);

                $writeRow($handle, [
                    'Receivable - Current Confirmed Invoice Balance',
                    $financialSummary['outstanding'],
                ]);

                $writeRow($handle, [
                    'Unbilled - Quote Value Not Yet Invoiced',
                    $financialSummary['unbilled'],
                ]);

                $writeRow($handle, [
                    'Expense - Cash Out During Period',
                    $financialSummary['expense'],
                ]);

                $writeRow($handle, [
                    'Cash Margin - Actual Cash In minus Expense',
                    $financialSummary['cash_surplus'],
                ]);
PHP,
        'perbaiki export Financial Report'
    );

    $financialController = replaceOnce(
        $financialController,
        "                    'invoice_number' => \$invoice->invoice_number,",
        "                    'invoice_number' => \$invoice->invoice_number,\n"
            ."                    'billing_type' => \$invoice->billing_type ?: 'full_payment',",
        'tambahkan billing type ke performance'
    );

    $financialController = replaceOnce(
        $financialController,
        "                    'Invoice',\n                    'Invoice Date',",
        "                    'Invoice',\n                    'Billing Type',\n                    'Invoice Date',",
        'tambahkan billing type ke CSV header'
    );

    $financialController = replaceOnce(
        $financialController,
        "                        \$invoice['invoice_number'],\n                        \$invoice['issued_at']",
        "                        \$invoice['invoice_number'],\n"
            ."                        strtoupper(str_replace('_', ' ', \$invoice['billing_type'])),\n"
            ."                        \$invoice['issued_at']",
        'tambahkan billing type ke CSV row'
    );

    writeFile(
        $root.DIRECTORY_SEPARATOR.$modified[5],
        $financialController
    );
    line('[PATCH] '.$modified[5]);

    $summaryStart = strpos(
        $financialView,
        '        {{-- Summary cards --}}'
    );
    $summaryEnd = strpos(
        $financialView,
        '        {{-- Invoice cohort --}}',
        $summaryStart === false ? 0 : $summaryStart
    );

    if ($summaryStart === false || $summaryEnd === false) {
        fail('Batas kartu Financial Report tidak ditemukan.');
    }

    $newSummaryCards = <<<'BLADE'
        {{-- Summary cards --}}
        {{-- CRM_INVOICE_FLEXIBLE_BILLING_V1 --}}
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Invoiced</div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $rupiah($financialSummary['invoiced']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">DP + Pelunasan, atau Full Payment; Cancel dikecualikan</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Revenue / Cash Received</div>
                <div class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">{{ $rupiah($financialSummary['received']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pembayaran aktual yang diterima pada periode</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Receivable</div>
                <div class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">{{ $rupiah($financialSummary['outstanding']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Invoice aktif dikurangi seluruh payment</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Unbilled Remainder</div>
                <div class="mt-2 text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $rupiah($financialSummary['unbilled']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Grand Total Quote dikurangi Invoice aktif</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Expense</div>
                <div class="mt-2 text-3xl font-bold text-orange-600 dark:text-orange-400">{{ $rupiah($financialSummary['expense']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cash out aktual pada periode</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cash Margin</div>
                <div class="mt-2 text-3xl font-bold {{ (float) $financialSummary['cash_surplus'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $rupiah($financialSummary['cash_surplus']) }}
                </div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cash Received dikurangi Expense</div>
            </div>
        </div>

BLADE;

    $financialView = substr($financialView, 0, $summaryStart)
        .$newSummaryCards
        .substr($financialView, $summaryEnd);

    $financialView = replaceOnce(
        $financialView,
        'Revenue, payment, expense, estimated profit, and cash position per invoice.',
        'Invoice value, cash received, receivable, unbilled remainder, expense, and cash margin.',
        'perbaiki deskripsi Financial Report'
    );

    $financialView = replaceOnce(
        $financialView,
        '>Revenue</th>',
        '>Total Invoiced</th>',
        'perbaiki header monthly invoiced'
    );

    $financialView = replaceOnce(
        $financialView,
        '<div class="font-semibold text-gray-800 dark:text-white">Revenue</div>',
        '<div class="font-semibold text-gray-800 dark:text-white">Total Invoiced</div>',
        'perbaiki legend monthly invoiced'
    );

    $invoiceLink = <<<'BLADE'
                                    <a href="{{ route('admin.invoices.show', $invoice['id']) }}" class="text-base font-semibold text-blue-600 hover:underline dark:text-blue-400">
                                        {{ $invoice['invoice_number'] }}
                                    </a>
BLADE;

    $billingTypeBadge = $invoiceLink.<<<'BLADE'

                                    <div class="mt-2">
                                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold uppercase text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                                            {{ str_replace('_', ' ', $invoice['billing_type']) }}
                                        </span>
                                    </div>
BLADE;

    $financialView = replaceOnce(
        $financialView,
        $invoiceLink,
        $billingTypeBadge,
        'pasang billing type pada tabel Financial Report'
    );

    writeFile($root.DIRECTORY_SEPARATOR.$modified[6], $financialView);
    line('[PATCH] '.$modified[6]);

    /*
     * Syntax validation before touching the database.
     */
    $phpFiles = [
        'packages/Webkul/Admin/src/Services/FlexibleQuoteBillingService.php',
        'packages/Webkul/Admin/src/Http/Controllers/Invoice/FlexibleQuoteBillingController.php',
        'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php',
        'packages/Webkul/Admin/src/Http/Controllers/Invoice/InvoiceController.php',
        'packages/Webkul/Admin/src/Http/Controllers/Invoice/FinancialReportController.php',
        'database/migrations/2026_09_07_100000_add_flexible_quote_billing_to_invoices.php',
        'tests/Unit/FlexibleQuoteBillingServiceTest.php',
    ];

    foreach ($phpFiles as $relative) {
        if (runCommand($root, ['-l', $relative]) !== 0) {
            fail('PHP lint gagal: '.$relative);
        }
    }

    if (runCommand($root, ['artisan', 'view:clear']) !== 0) {
        fail('Tidak dapat membersihkan compiled views.');
    }

    if (runCommand($root, ['artisan', 'view:cache']) !== 0) {
        fail('Blade compile gagal.');
    }

    /*
     * Schema change is additive except removal of the old quote_id unique
     * index. Existing rows are preserved and classified as full_payment.
     */
    if (runCommand($root, ['artisan', 'migrate', '--force']) !== 0) {
        fail(
            'Migration gagal. Source dibiarkan terpasang agar dapat diperiksa; '
            .'backup berada di '.$backupDirectory
        );
    }

    runCommand($root, ['artisan', 'optimize:clear']);

    line();
    line('PATCH BERHASIL.');
    line('Backup source: '.$backupDirectory);
    line('Lanjutkan dengan:');
    line('php tools/check_invoice_flexible_billing_v1.php');
} catch (Throwable $exception) {
    line();
    line('PATCH GAGAL: '.$exception->getMessage());

    if (
        isset($writesStarted, $backupDirectory, $manifest)
        && $writesStarted
        && ! str_contains(
            $exception->getMessage(),
            'Migration gagal'
        )
    ) {
        restoreBackup($root, $backupDirectory, $manifest);
        line('Source dipulihkan dari backup karena gagal sebelum migration.');
    }

    exit(1);
}
