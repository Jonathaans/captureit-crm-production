<?php

declare(strict_types=1);

const PATCH_NAME = 'FINANCIAL REPORT + EXPENSE EXPORT HOTFIX V1.3';
const MARKER = 'CRM_FINANCIAL_REPORT_EXPENSE_HOTFIX_V1_3';

$root = dirname(__DIR__);
$writesStarted = false;
$backupDirectory = null;
$manifest = null;

function line(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function fail(string $message): never
{
    throw new RuntimeException($message);
}

function normalizePath(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function readNormalized(string $path): string
{
    $content = file_get_contents($path);

    if ($content === false) {
        fail('Tidak dapat membaca file: '.$path);
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function writeFile(string $path, string $content): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true)) {
        fail('Tidak dapat membuat folder: '.$directory);
    }

    if (file_put_contents($path, $content) === false) {
        fail('Tidak dapat menulis file: '.$path);
    }
}

function replaceOnce(
    string $content,
    string $search,
    string $replacement,
    string $label
): string {
    if (substr_count($content, $search) !== 1) {
        fail('Preflight '.$label.' harus ditemukan tepat satu kali.');
    }

    return str_replace($search, $replacement, $content);
}

function runCommand(string $root, array $arguments): int
{
    $command = escapeshellarg(PHP_BINARY);

    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg((string) $argument);
    }

    line('[RUN]   '.implode(' ', $arguments));
    $current = getcwd();
    chdir($root);
    passthru($command, $exitCode);
    chdir($current ?: $root);

    return (int) $exitCode;
}

function restoreBackup(
    string $root,
    string $backupDirectory,
    array $manifest
): void {
    foreach ($manifest['files'] as $relative => $metadata) {
        $target = $root.DIRECTORY_SEPARATOR.normalizePath($relative);
        $source = $backupDirectory.DIRECTORY_SEPARATOR.'files'
            .DIRECTORY_SEPARATOR.normalizePath($relative);

        if (($metadata['existed'] ?? false) && is_file($source)) {
            writeFile($target, (string) file_get_contents($source));
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

    $files = [
        'packages/Webkul/Admin/src/Services/FlexibleQuoteBillingService.php',
        'packages/Webkul/Admin/src/Resources/views/invoices/index.blade.php',
        'packages/Webkul/Admin/src/Resources/views/invoices/financial-report.blade.php',
    ];

    foreach ($files as $relative) {
        if (! is_file($root.DIRECTORY_SEPARATOR.normalizePath($relative))) {
            fail('Preflight file wajib tidak ditemukan: '.$relative);
        }
    }

    $contents = [];
    $installed = 0;

    foreach ($files as $relative) {
        $contents[$relative] = readNormalized(
            $root.DIRECTORY_SEPARATOR.normalizePath($relative)
        );
        $installed += str_contains($contents[$relative], MARKER) ? 1 : 0;
    }

    if ($installed === count($files)) {
        runCommand($root, ['artisan', 'view:clear']);
        runCommand($root, ['artisan', 'view:cache']);
        runCommand($root, ['artisan', 'optimize:clear']);
        line('[OK] Hotfix sudah terpasang. Tidak ada file yang ditulis ulang.');
        line('Jalankan: php tools/check_financial_report_expense_hotfix_v1_3.php');
        exit(0);
    }

    if ($installed !== 0) {
        fail('Instalasi parsial V1.3 terdeteksi. Jalankan rollback V1.3 lalu apply ulang.');
    }

    $servicePath = $files[0];
    $indexPath = $files[1];
    $reportPath = $files[2];

    $oldUnbilled = <<<'PHP'
        return round(
            (float) $quoteTotals->sum(function ($total, $quoteId) use ($invoiceTotals) {
                return max(
                    0,
                    (float) $total - (float) $invoiceTotals->get($quoteId, 0)
                );
            }),
            self::SCALE
        );
PHP;

    $newUnbilled = <<<'PHP'
        /* CRM_FINANCIAL_REPORT_EXPENSE_HOTFIX_V1_3 */
        $unbilled = 0.0;

        foreach ($quoteTotals as $quoteId => $total) {
            $unbilled += max(
                0,
                (float) $total - (float) $invoiceTotals->get($quoteId, 0)
            );
        }

        return round($unbilled, self::SCALE);
PHP;

    $contents[$servicePath] = replaceOnce(
        $contents[$servicePath],
        $oldUnbilled,
        $newUnbilled,
        'perhitungan Unbilled Financial Report'
    );

    $oldInvoiceExport = <<<'BLADE'
        {{-- EXPORT ALL EXPENSES CSV V1 --}}
        @if (bouncer()->hasPermission('invoices.expense.export-all'))
            <div class="flex items-center gap-2 max-sm:w-full">
                <a
                    href="{{ route('admin.invoices.expenses.export-all') }}"
                    class="primary-button max-sm:w-full max-sm:justify-center"
                >
                    Export All Expenses
                </a>
            </div>
        @endif
BLADE;

    $contents[$indexPath] = replaceOnce(
        $contents[$indexPath],
        $oldInvoiceExport,
        '        {{-- CRM_FINANCIAL_REPORT_EXPENSE_HOTFIX_V1_3: moved to Financial Report --}}',
        'hapus tombol Export All Expenses dari Invoice'
    );

    $oldReportExports = <<<'BLADE'
                @if (bouncer()->hasPermission('invoices.financial-report.export'))
                    <a
                        href="{{ route('admin.invoices.financial-report.export', ['year' => $year, 'month' => $month, 'business_unit' => $businessUnit, 'event_status' => $eventStatus, 'product' => $product]) }}"
                        class="secondary-button rounded-lg px-4 py-2.5 text-sm"
                    >
                        Export CSV
                    </a>

            {{-- FINANCIAL REPORT EXPORT ALL EXPENSES V1 --}}
            <a href="{{ route('admin.invoices.financial-report.expenses.export') }}" class="primary-button">
                Export All Expenses
            </a>
                @endif
BLADE;

    $newReportExports = <<<'BLADE'
                @if (bouncer()->hasPermission('invoices.financial-report.export'))
                    <a
                        href="{{ route('admin.invoices.financial-report.export', ['year' => $year, 'month' => $month, 'business_unit' => $businessUnit, 'event_status' => $eventStatus, 'product' => $product]) }}"
                        class="secondary-button rounded-lg px-4 py-2.5 text-sm"
                    >
                        Export Financial Report
                    </a>
                @endif

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

    $contents[$reportPath] = replaceOnce(
        $contents[$reportPath],
        $oldReportExports,
        $newReportExports,
        'pindahkan Export All Expenses ke Financial Report'
    );

    $timestamp = date('Ymd-His');
    $backupDirectory = $root.DIRECTORY_SEPARATOR.'tools'
        .DIRECTORY_SEPARATOR.'backups'
        .DIRECTORY_SEPARATOR.'financial-report-expense-hotfix-v1_3-'.$timestamp;

    if (! mkdir($backupDirectory.DIRECTORY_SEPARATOR.'files', 0775, true)) {
        fail('Tidak dapat membuat folder backup hotfix.');
    }

    $manifest = [
        'patch' => PATCH_NAME,
        'created_at' => date(DATE_ATOM),
        'files' => [],
    ];

    foreach ($files as $relative) {
        $source = $root.DIRECTORY_SEPARATOR.normalizePath($relative);
        $backup = $backupDirectory.DIRECTORY_SEPARATOR.'files'
            .DIRECTORY_SEPARATOR.normalizePath($relative);
        $manifest['files'][$relative] = ['existed' => true];
        writeFile($backup, (string) file_get_contents($source));
    }

    writeFile(
        $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    writeFile(
        $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
            .DIRECTORY_SEPARATOR.'financial-report-expense-hotfix-v1_3-latest.txt',
        $backupDirectory
    );

    $writesStarted = true;

    foreach ($contents as $relative => $content) {
        writeFile(
            $root.DIRECTORY_SEPARATOR.normalizePath($relative),
            $content
        );
        line('[PATCH] '.$relative);
    }

    if (runCommand($root, ['-l', $servicePath]) !== 0) {
        fail('PHP lint service gagal.');
    }

    if (runCommand($root, ['artisan', 'view:clear']) !== 0) {
        fail('Tidak dapat membersihkan compiled views.');
    }

    if (runCommand($root, ['artisan', 'view:cache']) !== 0) {
        fail('Blade compile gagal.');
    }

    runCommand($root, ['artisan', 'optimize:clear']);

    line();
    line('HOTFIX BERHASIL.');
    line('Backup source: '.$backupDirectory);
    line('Lanjutkan dengan:');
    line('php tools/check_financial_report_expense_hotfix_v1_3.php');
} catch (Throwable $exception) {
    line();
    line('HOTFIX GAGAL: '.$exception->getMessage());

    if ($writesStarted && $backupDirectory && is_array($manifest)) {
        restoreBackup($root, $backupDirectory, $manifest);
        line('Source dipulihkan otomatis dari backup hotfix.');
    }

    exit(1);
}
