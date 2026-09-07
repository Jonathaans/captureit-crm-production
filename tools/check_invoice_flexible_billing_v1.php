<?php

declare(strict_types=1);

const TITLE = 'CHECK INVOICE FLEXIBLE DP + PELUNASAN V1.1.1';

$root = dirname(__DIR__);
$failures = 0;
$warnings = 0;

function output(string $status, string $message): void
{
    echo '['.$status.'] '.str_pad('', max(1, 5 - strlen($status))).$message.PHP_EOL;
}

function ok(string $message): void
{
    output('OK', $message);
}

function failCheck(string $message): void
{
    global $failures;
    $failures++;
    output('FAIL', $message);
}

function warn(string $message): void
{
    global $warnings;
    $warnings++;
    output('WARN', $message);
}

function containsAll(string $path, array $needles, string $label): void
{
    global $root;

    if (! is_file($root.DIRECTORY_SEPARATOR.$path)) {
        failCheck($label.' tidak tersedia.');
        return;
    }

    $content = (string) file_get_contents(
        $root.DIRECTORY_SEPARATOR.$path
    );

    $missing = array_values(array_filter(
        $needles,
        fn ($needle) => ! str_contains($content, $needle)
    ));

    if ($missing === []) {
        ok($label);
    } else {
        failCheck(
            $label.' kurang marker: '.implode(', ', $missing)
        );
    }
}

function lint(string $relative): void
{
    global $root;

    $path = $root.DIRECTORY_SEPARATOR.$relative;

    if (! is_file($path)) {
        failCheck('File tidak tersedia: '.$relative);
        return;
    }

    $command = escapeshellarg(PHP_BINARY)
        .' -l '
        .escapeshellarg($path)
        .' 2>&1';

    exec($command, $lines, $exitCode);

    if ($exitCode === 0) {
        ok('PHP lint: '.$relative);
    } else {
        failCheck(
            'PHP lint '.$relative.': '.implode(' ', $lines)
        );
    }
}

echo TITLE.PHP_EOL;
echo str_repeat('=', strlen(TITLE)).PHP_EOL.PHP_EOL;

$files = [
    'packages/Webkul/Admin/src/Services/FlexibleQuoteBillingService.php',
    'packages/Webkul/Admin/src/Http/Controllers/Invoice/FlexibleQuoteBillingController.php',
    'database/migrations/2026_09_07_100000_add_flexible_quote_billing_to_invoices.php',
    'packages/Webkul/Admin/src/Resources/views/invoices/billing-create.blade.php',
    'tests/Unit/FlexibleQuoteBillingServiceTest.php',
];

foreach ($files as $file) {
    if (is_file($root.DIRECTORY_SEPARATOR.$file)) {
        ok('File tersedia: '.$file);
    } else {
        failCheck('File tidak tersedia: '.$file);
    }
}

foreach ([
    'packages/Webkul/Admin/src/Services/FlexibleQuoteBillingService.php',
    'packages/Webkul/Admin/src/Http/Controllers/Invoice/FlexibleQuoteBillingController.php',
    'database/migrations/2026_09_07_100000_add_flexible_quote_billing_to_invoices.php',
    'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php',
    'packages/Webkul/Admin/src/Http/Controllers/Invoice/InvoiceController.php',
    'packages/Webkul/Admin/src/Http/Controllers/Invoice/FinancialReportController.php',
    'tests/Unit/FlexibleQuoteBillingServiceTest.php',
] as $file) {
    lint($file);
}

containsAll(
    'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php',
    [
        'CRM_INVOICE_FLEXIBLE_BILLING_V1',
        'FlexibleQuoteBillingService::class',
        'assertMutable',
        "prefix('admin/invoice-billing')",
        'admin.invoices.billing.create',
        'admin.invoices.billing.summary',
        'admin.invoices.billing.store',
    ],
    'Provider route billing dan commercial lock'
);

containsAll(
    'packages/Webkul/Admin/src/Http/Controllers/Invoice/FlexibleQuoteBillingController.php',
    [
        "auth()->guard('user')->check()",
        "bouncer()->hasPermission('invoices')",
    ],
    'Route billing dilindungi login Admin dan ACL Invoice'
);

containsAll(
    'packages/Webkul/Admin/src/Resources/views/invoices/billing-create.blade.php',
    [
        'down_payment',
        'full_payment',
        'settlement',
        'billing_method',
        'billing_percentage',
        'billing_amount',
        'Live Preview',
        'Sisa Setelah Generate',
    ],
    'Form DP fleksibel dan live preview'
);

containsAll(
    'packages/Webkul/Admin/src/Services/FlexibleQuoteBillingService.php',
    [
        'Nominal is authoritative',
        "status === 'paid'",
        'remaining_amount_snapshot',
        'dp_invoice_id',
        'lockForUpdate',
        'GET_LOCK',
        'orWhereNotIn',
        'calculateDownPaymentPosition',
    ],
    'Aturan DP, PAID, Pelunasan, Cancel, dan concurrency'
);

containsAll(
    'packages/Webkul/Admin/src/Resources/views/invoices/pdf.blade.php',
    [
        'CRM_INVOICE_FLEXIBLE_BILLING_V1',
        'DOWN PAYMENT',
        'PELUNASAN',
        'DP Reference',
        'Quote Contract Total',
        'Remaining After Invoice',
    ],
    'PDF Invoice menampilkan tahap billing'
);

containsAll(
    'packages/Webkul/Admin/src/Resources/views/invoices/show.blade.php',
    [
        'CRM_INVOICE_FLEXIBLE_BILLING_V1',
        'DOWN PAYMENT',
        'PELUNASAN',
        'FULL PAYMENT',
    ],
    'Invoice Show menampilkan jenis billing'
);

containsAll(
    'packages/Webkul/Admin/src/Http/Controllers/Invoice/FinancialReportController.php',
    [
        'CRM_INVOICE_FLEXIBLE_BILLING_V1',
        "'invoiced' => \$invoiced",
        "'revenue' => \$received",
        "'unbilled' => \$unbilled",
        "'cash_surplus' => \$received - \$expense",
        'sumUnbilledForQuoteIds',
    ],
    'Financial Report tidak double-count Quote'
);

containsAll(
    'packages/Webkul/Admin/src/Resources/views/invoices/financial-report.blade.php',
    [
        'CRM_INVOICE_FLEXIBLE_BILLING_V1',
        'Total Invoiced',
        'Revenue / Cash Received',
        'Receivable',
        'Unbilled Remainder',
        'Cash Margin',
        "invoice['billing_type']",
    ],
    'Kartu Financial Report billing-aware'
);

if (! is_file($root.DIRECTORY_SEPARATOR.'vendor/autoload.php')) {
    failCheck('vendor/autoload.php tidak tersedia.');
} else {
    try {
        require $root.DIRECTORY_SEPARATOR.'vendor/autoload.php';

        $app = require $root.DIRECTORY_SEPARATOR.'bootstrap/app.php';

        $app->make(
            Illuminate\Contracts\Console\Kernel::class
        )->bootstrap();

        ok('Laravel bootstrap berhasil.');

        foreach ([
            'admin.invoices.billing.create',
            'admin.invoices.billing.summary',
            'admin.invoices.billing.store',
        ] as $routeName) {
            if (Illuminate\Support\Facades\Route::has($routeName)) {
                ok('Route terdaftar: '.$routeName);
            } else {
                failCheck('Route tidak terdaftar: '.$routeName);
            }
        }

        $requiredColumns = [
            'billing_type',
            'billing_method',
            'billing_percentage',
            'quote_total_snapshot',
            'billing_amount',
            'remaining_amount_snapshot',
            'dp_invoice_id',
            'billing_created_by',
            'billing_locked_at',
        ];

        if (! Illuminate\Support\Facades\Schema::hasTable('invoices')) {
            failCheck('Tabel invoices tidak tersedia.');
        } else {
            $missingColumns = array_values(array_filter(
                $requiredColumns,
                fn ($column) =>
                    ! Illuminate\Support\Facades\Schema::hasColumn(
                        'invoices',
                        $column
                    )
            ));

            if ($missingColumns === []) {
                ok('Semua kolom flexible billing tersedia.');
            } else {
                failCheck(
                    'Kolom flexible billing kurang: '
                    .implode(', ', $missingColumns)
                );
            }

            $indexes = Illuminate\Support\Facades\Schema::getIndexes(
                'invoices'
            );

            $quoteUnique = collect($indexes)
                ->contains(function ($index) {
                    return ($index['unique'] ?? false)
                        && array_values(
                            $index['columns'] ?? []
                        ) === ['quote_id'];
                });

            if ($quoteUnique) {
                failCheck(
                    'Unique index invoices.quote_id masih aktif; '
                    .'DP + Pelunasan belum dapat coexist.'
                );
            } else {
                ok('invoices.quote_id menerima DP + Pelunasan.');
            }

            $indexNames = collect($indexes)->pluck('name');

            foreach ([
                'crm_invoices_quote_idx',
                'crm_invoices_quote_billing_idx',
                'crm_invoices_dp_reference_idx',
            ] as $indexName) {
                if ($indexNames->contains($indexName)) {
                    ok('Index tersedia: '.$indexName);
                } else {
                    failCheck('Index tidak tersedia: '.$indexName);
                }
            }

            if (
                Illuminate\Support\Facades\Schema::hasColumn(
                    'invoices',
                    'billing_type'
                )
            ) {
                $duplicates = Illuminate\Support\Facades\DB::table('invoices')
                    ->whereNotNull('quote_id')
                    ->whereIn(
                        'billing_type',
                        ['down_payment', 'settlement', 'full_payment']
                    )
                    ->where(function ($query) {
                        $query
                            ->whereNull('event_status')
                            ->orWhereNotIn(
                                'event_status',
                                ['cancel', 'cancelled', 'canceled']
                            );
                    })
                    ->selectRaw(
                        'quote_id, billing_type, COUNT(*) as aggregate'
                    )
                    ->groupBy('quote_id', 'billing_type')
                    ->havingRaw('COUNT(*) > 1')
                    ->count();

                if ($duplicates === 0) {
                    ok('Tidak ada duplicate active billing stage.');
                } else {
                    failCheck(
                        'Ditemukan '.$duplicates
                        .' duplicate active billing stage.'
                    );
                }

                $overbilledQuotes = Illuminate\Support\Facades\DB::table(
                    'invoices as billing_invoices'
                )
                    ->join(
                        'quotes as billing_quotes',
                        'billing_quotes.id',
                        '=',
                        'billing_invoices.quote_id'
                    )
                    ->where(function ($query) {
                        $query
                            ->whereNull('billing_invoices.event_status')
                            ->orWhereNotIn(
                                'billing_invoices.event_status',
                                ['cancel', 'cancelled', 'canceled']
                            );
                    })
                    ->select('billing_quotes.id')
                    ->groupBy('billing_quotes.id')
                    ->havingRaw(
                        'SUM(billing_invoices.grand_total) '
                        .'> MAX(billing_quotes.grand_total) + 0.01'
                    )
                    ->get()
                    ->count();

                if ($overbilledQuotes === 0) {
                    ok('Tidak ada Quote dengan active invoice melebihi Grand Total.');
                } else {
                    failCheck(
                        'Ditemukan '.$overbilledQuotes
                        .' Quote yang overbilled.'
                    );
                }

                $invalidSettlements = Illuminate\Support\Facades\DB::table(
                    'invoices as settlement_invoices'
                )
                    ->leftJoin(
                        'invoices as dp_invoices',
                        'dp_invoices.id',
                        '=',
                        'settlement_invoices.dp_invoice_id'
                    )
                    ->where(
                        'settlement_invoices.billing_type',
                        'settlement'
                    )
                    ->where(function ($query) {
                        $query
                            ->whereNull('settlement_invoices.event_status')
                            ->orWhereNotIn(
                                'settlement_invoices.event_status',
                                ['cancel', 'cancelled', 'canceled']
                            );
                    })
                    ->where(function ($query) {
                        $query
                            ->whereNull('dp_invoices.id')
                            ->orWhere(
                                'dp_invoices.billing_type',
                                '!=',
                                'down_payment'
                            )
                            ->orWhere('dp_invoices.status', '!=', 'paid')
                            ->orWhereIn(
                                'dp_invoices.event_status',
                                ['cancel', 'cancelled', 'canceled']
                            );
                    })
                    ->count();

                if ($invalidSettlements === 0) {
                    ok('Semua Pelunasan aktif mereferensikan DP yang PAID.');
                } else {
                    failCheck(
                        'Ditemukan '.$invalidSettlements
                        .' Pelunasan dengan referensi DP tidak valid.'
                    );
                }

                $amountMismatch = Illuminate\Support\Facades\DB::table(
                    'invoices'
                )
                    ->whereNotNull('billing_locked_at')
                    ->whereRaw(
                        'ABS(COALESCE(billing_amount, grand_total) - grand_total) > 0.01'
                    )
                    ->count();

                if ($amountMismatch === 0) {
                    ok('Billing amount dan Grand Total Invoice konsisten.');
                } else {
                    failCheck(
                        'Ditemukan '.$amountMismatch
                        .' Invoice dengan nominal billing tidak konsisten.'
                    );
                }
            }
        }

        $migration = Illuminate\Support\Facades\DB::table('migrations')
            ->where(
                'migration',
                '2026_09_07_100000_add_flexible_quote_billing_to_invoices'
            )
            ->exists();

        if ($migration) {
            ok('Migration flexible billing tercatat.');
        } else {
            failCheck('Migration flexible billing belum dijalankan.');
        }

        $position = app(
            Webkul\Admin\Services\FlexibleQuoteBillingService::class
        )->calculateDownPaymentPosition(
            5_000_000,
            2_000_000
        );

        if (
            (float) $position['amount'] === 2_000_000.0
            && (float) $position['percentage'] === 40.0
            && (float) $position['remaining'] === 3_000_000.0
        ) {
            ok('Simulasi Quote 5jt, DP 2jt, sisa 3jt benar.');
        } else {
            failCheck(
                'Simulasi DP tidak sesuai: '.json_encode($position)
            );
        }

        $pest = $root.DIRECTORY_SEPARATOR.'vendor'
            .DIRECTORY_SEPARATOR.'bin'
            .DIRECTORY_SEPARATOR.'pest';

        if (is_file($pest)) {
            $testCommand = escapeshellarg(PHP_BINARY)
                .' '
                .escapeshellarg($pest)
                .' '
                .escapeshellarg(
                    $root.DIRECTORY_SEPARATOR.'tests'
                    .DIRECTORY_SEPARATOR.'Unit'
                    .DIRECTORY_SEPARATOR.'FlexibleQuoteBillingServiceTest.php'
                )
                .' --colors=never 2>&1';

            exec($testCommand, $testOutput, $testExitCode);

            if ($testExitCode === 0) {
                ok('Pest test kalkulasi DP non-50% berhasil.');
            } else {
                failCheck(
                    'Pest test gagal: '.implode(' ', $testOutput)
                );
            }
        } else {
            warn('vendor/bin/pest tidak tersedia; test otomatis dilewati.');
        }

        Illuminate\Support\Facades\Artisan::call('view:clear');
        $viewResult = Illuminate\Support\Facades\Artisan::call(
            'view:cache'
        );

        if ($viewResult === 0) {
            ok('Semua Blade berhasil dikompilasi.');
        } else {
            failCheck('Blade compile gagal.');
        }
    } catch (Throwable $exception) {
        failCheck('Laravel runtime: '.$exception->getMessage());
    }
}

echo PHP_EOL;

if ($failures > 0) {
    echo '[FAIL] Checker menemukan '.$failures.' masalah';
    echo $warnings > 0 ? ' dan '.$warnings.' warning.' : '.';
    echo PHP_EOL;
    exit(1);
}

echo '[PASS] Flexible DP, Full Payment, Pelunasan, PDF, dan Financial Report siap.';
echo $warnings > 0 ? ' Warning: '.$warnings.'.' : '';
echo PHP_EOL;
