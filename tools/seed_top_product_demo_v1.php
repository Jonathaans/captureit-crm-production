<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const DEMO_PRODUCT_SKU_PREFIX = 'DEMO-TOP-';
const DEMO_QUOTE_PREFIX = 'DEMO-TOP-QT-';
const DEMO_INVOICE_PREFIX = 'DEMO-TOP-INV-';
const DEMO_PERSON_UNIQUE_ID = 'demo-top-product-v1';
const DEMO_PERSON_EMAIL = 'demo.top.product@example.test';

$options = getopt('', [
    'apply',
    'cleanup',
    'date:',
]);

$apply = array_key_exists('apply', $options);
$cleanupOnly = array_key_exists('cleanup', $options);
$dateInput = trim((string) ($options['date'] ?? now()->toDateString()));

if (! app()->environment('local')) {
    fwrite(
        STDERR,
        'DITOLAK: script demo hanya boleh dijalankan saat APP_ENV=local.'.PHP_EOL
    );

    exit(1);
}

$baseDate = parseDemoDate($dateInput);

assertDemoSchemaReady();

$connection = (string) config('database.default');
$database = (string) config("database.connections.{$connection}.database");

echo PHP_EOL;
echo 'TOP PRODUCT DEMO DATA V1'.PHP_EOL;
echo '========================'.PHP_EOL;
echo 'Environment : '.app()->environment().PHP_EOL;
echo 'Connection  : '.$connection.PHP_EOL;
echo 'Database    : '.$database.PHP_EOL;
echo 'Tanggal     : '.$baseDate->toDateString().PHP_EOL;
echo 'Mode        : '.($apply ? 'APPLY' : 'DRY RUN').PHP_EOL;
echo 'Operasi     : '.($cleanupOnly ? 'CLEANUP' : 'SEED / RESET DEMO').PHP_EOL;
echo PHP_EOL;

if (! $apply) {
    if ($cleanupOnly) {
        echo 'Belum ada data yang dihapus.'.PHP_EOL;
        echo 'Untuk menghapus data demo, jalankan:'.PHP_EOL;
        echo 'php tools/seed_top_product_demo_v1.php --cleanup --apply'.PHP_EOL;
    } else {
        printDemoPlan();
        echo PHP_EOL;
        echo 'Belum ada data yang dibuat.'.PHP_EOL;
        echo 'Untuk membuat data demo, jalankan:'.PHP_EOL;
        echo 'php tools/seed_top_product_demo_v1.php --apply --date='.$baseDate->toDateString().PHP_EOL;
    }

    exit(0);
}

if ($cleanupOnly) {
    $removed = DB::transaction(fn (): array => cleanupDemoData());

    printCleanupResult($removed);

    echo PHP_EOL.'DONE. Data Top Product demo sudah dibersihkan.'.PHP_EOL;
    exit(0);
}

$user = DB::table('users')
    ->where('status', 1)
    ->orderBy('id')
    ->first(['id', 'name']);

if ($user === null) {
    $user = DB::table('users')
        ->orderBy('id')
        ->first(['id', 'name']);
}

if ($user === null) {
    fwrite(
        STDERR,
        'DITOLAK: tidak ada user CRM. Login/installer harus dibuat terlebih dahulu.'.PHP_EOL
    );

    exit(1);
}

$result = DB::transaction(function () use ($baseDate, $user): array {
    $removed = cleanupDemoData();
    $created = seedDemoData(
        $baseDate,
        (int) $user->id
    );

    return compact('removed', 'created');
});

printCleanupResult($result['removed'], 'Reset data demo lama');

echo PHP_EOL;
echo 'DATA DIBUAT'.PHP_EOL;
echo '------------'.PHP_EOL;
echo 'Sales/User  : '.$user->name.' (ID '.$user->id.')'.PHP_EOL;
echo 'Customer    : DEMO Top Product Customer'.PHP_EOL;
echo 'Products    : '.$result['created']['products'].PHP_EOL;
echo 'Quotes      : '.$result['created']['quotes'].PHP_EOL;
echo 'Invoices    : '.$result['created']['invoices'].' (termasuk DP + Pelunasan)'.PHP_EOL;
echo 'Payments    : '.$result['created']['payments'].PHP_EOL;

echo PHP_EOL;
echo 'HASIL TOP PRODUCT YANG DIHARAPKAN'.PHP_EOL;
echo '--------------------------------'.PHP_EOL;
echo '1. DEMO Classic Photobooth : 3 proyek | Qty 3 | Deal Rp15.000.000 | Diterima Rp12.500.000'.PHP_EOL;
echo '2. DEMO 360 Booth          : 2 proyek | Qty 2 | Deal Rp14.000.000 | Diterima Rp14.000.000'.PHP_EOL;
echo '3. DEMO Slow Motion        : 1 proyek | Qty 1 | Deal Rp 9.000.000 | Diterima Rp         0'.PHP_EOL;

echo PHP_EOL;
echo 'Buka Financial Report, pilih:'.PHP_EOL;
echo '- Year  : '.$baseDate->year.PHP_EOL;
echo '- Month : '.$baseDate->format('F').PHP_EOL;
echo '- Event : Confirm atau All Events'.PHP_EOL;
echo '- Unit  : Capture It - Photobooth'.PHP_EOL;

echo PHP_EOL;
echo 'Setelah selesai testing, hapus data demo dengan:'.PHP_EOL;
echo 'php tools/seed_top_product_demo_v1.php --cleanup --apply'.PHP_EOL;

/**
 * Parse an exact Y-m-d value without silently accepting an invalid date.
 */
function parseDemoDate(string $value): CarbonImmutable
{
    if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
        fwrite(STDERR, 'Tanggal harus berformat YYYY-MM-DD.'.PHP_EOL);
        exit(1);
    }

    $year = (int) $matches[1];
    $month = (int) $matches[2];
    $day = (int) $matches[3];

    if (! checkdate($month, $day, $year)) {
        fwrite(STDERR, 'Tanggal tidak valid: '.$value.PHP_EOL);
        exit(1);
    }

    return CarbonImmutable::create(
        $year,
        $month,
        $day,
        0,
        0,
        0,
        config('app.timezone')
    );
}

/**
 * Fail early when the local database has not received the required migrations.
 */
function assertDemoSchemaReady(): void
{
    $requiredTables = [
        'users',
        'persons',
        'products',
        'quotes',
        'quote_items',
        'invoices',
        'invoice_items',
        'payments',
    ];

    $missingTables = array_values(array_filter(
        $requiredTables,
        fn (string $table): bool => ! Schema::hasTable($table)
    ));

    $requiredColumns = [
        'persons' => ['unique_id', 'user_id'],
        'quotes' => ['quote_number', 'project_code', 'business_unit', 'event_date'],
        'quote_items' => ['description', 'day', 'product_id'],
        'invoices' => [
            'project_code',
            'business_unit',
            'event_status',
            'billing_type',
            'billing_method',
            'billing_percentage',
            'quote_total_snapshot',
            'billing_amount',
            'remaining_amount_snapshot',
            'dp_invoice_id',
            'billing_created_by',
            'billing_locked_at',
        ],
        'invoice_items' => ['description', 'day', 'product_id'],
    ];

    $missingColumns = [];

    foreach ($requiredColumns as $table => $columns) {
        if (! Schema::hasTable($table)) {
            continue;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                $missingColumns[] = $table.'.'.$column;
            }
        }
    }

    if ($missingTables !== [] || $missingColumns !== []) {
        fwrite(STDERR, 'Database local belum mengikuti migration terbaru.'.PHP_EOL);

        if ($missingTables !== []) {
            fwrite(STDERR, 'Missing tables: '.implode(', ', $missingTables).PHP_EOL);
        }

        if ($missingColumns !== []) {
            fwrite(STDERR, 'Missing columns: '.implode(', ', $missingColumns).PHP_EOL);
        }

        fwrite(STDERR, 'Jalankan: php artisan migrate'.PHP_EOL);
        exit(1);
    }

    $hasLegacyQuoteUnique = collect(Schema::getIndexes('invoices'))
        ->contains(function (array $index): bool {
            return ($index['unique'] ?? false)
                && array_values($index['columns'] ?? []) === ['quote_id'];
        });

    if ($hasLegacyQuoteUnique) {
        fwrite(
            STDERR,
            'Database masih membatasi satu invoice per quote. Jalankan: php artisan migrate'.PHP_EOL
        );

        exit(1);
    }
}

function printDemoPlan(): void
{
    echo 'Rencana data demo:'.PHP_EOL;
    echo '- 3 Products'.PHP_EOL;
    echo '- 1 Contact/Customer'.PHP_EOL;
    echo '- 5 Quotes / Projects confirmed'.PHP_EOL;
    echo '- 6 Invoices (1 DP + 1 Pelunasan + 4 Full Payment)'.PHP_EOL;
    echo '- 5 Payment records'.PHP_EOL;
    echo '- Ranking expected: Classic 3, 360 Booth 2, Slow Motion 1'.PHP_EOL;
}

/**
 * Remove only rows carrying the exact demo prefixes used by this script.
 *
 * @return array<string, int>
 */
function cleanupDemoData(): array
{
    $quoteIds = DB::table('quotes')
        ->where('quote_number', 'like', DEMO_QUOTE_PREFIX.'%')
        ->pluck('id')
        ->map(fn ($id): int => (int) $id)
        ->all();

    $invoiceQuery = DB::table('invoices')
        ->where('invoice_number', 'like', DEMO_INVOICE_PREFIX.'%');

    if ($quoteIds !== []) {
        $invoiceQuery->orWhereIn('quote_id', $quoteIds);
    }

    $invoiceIds = $invoiceQuery
        ->pluck('id')
        ->map(fn ($id): int => (int) $id)
        ->all();

    $removed = [
        'payments' => deleteByIds('payments', 'invoice_id', $invoiceIds),
        'invoice_items' => deleteByIds('invoice_items', 'invoice_id', $invoiceIds),
        'invoices' => deletePrimaryIds('invoices', $invoiceIds),
        'quote_items' => deleteByIds('quote_items', 'quote_id', $quoteIds),
        'quotes' => deletePrimaryIds('quotes', $quoteIds),
        'products' => 0,
        'products_preserved' => 0,
        'persons' => 0,
    ];

    $productIds = DB::table('products')
        ->where('sku', 'like', DEMO_PRODUCT_SKU_PREFIX.'%')
        ->pluck('id')
        ->map(fn ($id): int => (int) $id)
        ->all();

    foreach ($productIds as $productId) {
        $referenced = DB::table('quote_items')
            ->where('product_id', $productId)
            ->exists()
            || DB::table('invoice_items')
                ->where('product_id', $productId)
                ->exists();

        if ($referenced) {
            $removed['products_preserved']++;
            continue;
        }

        $removed['products'] += DB::table('products')
            ->where('id', $productId)
            ->delete();
    }

    $personId = DB::table('persons')
        ->where('unique_id', DEMO_PERSON_UNIQUE_ID)
        ->value('id');

    if ($personId !== null && ! personIsReferenced((int) $personId)) {
        $removed['persons'] = DB::table('persons')
            ->where('id', $personId)
            ->delete();
    }

    return $removed;
}

function deleteByIds(string $table, string $column, array $ids): int
{
    if ($ids === []) {
        return 0;
    }

    return DB::table($table)
        ->whereIn($column, $ids)
        ->delete();
}

function deletePrimaryIds(string $table, array $ids): int
{
    return deleteByIds($table, 'id', $ids);
}

function personIsReferenced(int $personId): bool
{
    foreach (['quotes', 'invoices', 'leads'] as $table) {
        if (Schema::hasTable($table)
            && Schema::hasColumn($table, 'person_id')
            && DB::table($table)->where('person_id', $personId)->exists()) {
            return true;
        }
    }

    return false;
}

/**
 * @return array<string, int>
 */
function seedDemoData(CarbonImmutable $baseDate, int $userId): array
{
    $now = $baseDate->setTime(8, 0);
    $personId = createDemoPerson($userId, $now);
    $products = createDemoProducts($now);

    $deals = [
        [
            'code' => '01',
            'subject' => '[DEMO TOP PRODUCT] Classic - DP + Pelunasan',
            'items' => [
                ['sku' => 'DEMO-TOP-CLASSIC', 'quantity' => 1, 'total' => 5_000_000],
            ],
            'invoices' => [
                ['suffix' => 'DP', 'type' => 'down_payment', 'amount' => 2_000_000, 'paid' => 2_000_000, 'hour' => 9],
                ['suffix' => 'SETTLEMENT', 'type' => 'settlement', 'amount' => 3_000_000, 'paid' => 3_000_000, 'hour' => 10],
            ],
        ],
        [
            'code' => '02',
            'subject' => '[DEMO TOP PRODUCT] Classic - Partial Payment',
            'items' => [
                ['sku' => 'DEMO-TOP-CLASSIC', 'quantity' => 1, 'total' => 5_000_000],
            ],
            'invoices' => [
                ['suffix' => 'FULL', 'type' => 'full_payment', 'amount' => 5_000_000, 'paid' => 2_500_000, 'hour' => 11],
            ],
        ],
        [
            'code' => '03',
            'subject' => '[DEMO TOP PRODUCT] Classic + 360 Booth',
            'items' => [
                ['sku' => 'DEMO-TOP-CLASSIC', 'quantity' => 1, 'total' => 5_000_000],
                ['sku' => 'DEMO-TOP-360', 'quantity' => 1, 'total' => 7_000_000],
            ],
            'invoices' => [
                ['suffix' => 'FULL', 'type' => 'full_payment', 'amount' => 12_000_000, 'paid' => 12_000_000, 'hour' => 12],
            ],
        ],
        [
            'code' => '04',
            'subject' => '[DEMO TOP PRODUCT] 360 Booth',
            'items' => [
                ['sku' => 'DEMO-TOP-360', 'quantity' => 1, 'total' => 7_000_000],
            ],
            'invoices' => [
                ['suffix' => 'FULL', 'type' => 'full_payment', 'amount' => 7_000_000, 'paid' => 7_000_000, 'hour' => 13],
            ],
        ],
        [
            'code' => '05',
            'subject' => '[DEMO TOP PRODUCT] Slow Motion - Unpaid',
            'items' => [
                ['sku' => 'DEMO-TOP-SLOWMO', 'quantity' => 1, 'total' => 9_000_000],
            ],
            'invoices' => [
                ['suffix' => 'FULL', 'type' => 'full_payment', 'amount' => 9_000_000, 'paid' => 0, 'hour' => 14],
            ],
        ],
    ];

    $counts = [
        'products' => count($products),
        'quotes' => 0,
        'invoices' => 0,
        'payments' => 0,
    ];

    foreach ($deals as $deal) {
        $created = createDemoDeal(
            $deal,
            $products,
            $personId,
            $userId,
            $baseDate
        );

        $counts['quotes']++;
        $counts['invoices'] += $created['invoices'];
        $counts['payments'] += $created['payments'];
    }

    return $counts;
}

function createDemoPerson(int $userId, CarbonImmutable $timestamp): int
{
    $data = [
        'name' => 'DEMO Top Product Customer',
        'emails' => json_encode([
            ['label' => 'work', 'value' => DEMO_PERSON_EMAIL],
        ], JSON_THROW_ON_ERROR),
        'contact_numbers' => json_encode([
            ['label' => 'work', 'value' => '080000000001'],
        ], JSON_THROW_ON_ERROR),
        'user_id' => $userId,
        'organization_id' => null,
        'updated_at' => $timestamp,
    ];

    $personId = DB::table('persons')
        ->where('unique_id', DEMO_PERSON_UNIQUE_ID)
        ->value('id');

    if ($personId !== null) {
        DB::table('persons')
            ->where('id', $personId)
            ->update($data);

        return (int) $personId;
    }

    return (int) DB::table('persons')->insertGetId([
        ...$data,
        'unique_id' => DEMO_PERSON_UNIQUE_ID,
        'created_at' => $timestamp,
    ]);
}

/**
 * @return array<string, array<string, mixed>>
 */
function createDemoProducts(CarbonImmutable $timestamp): array
{
    $definitions = [
        'DEMO-TOP-CLASSIC' => [
            'name' => 'DEMO Classic Photobooth',
            'description' => 'Data testing Top Product - Classic',
            'price' => 5_000_000,
        ],
        'DEMO-TOP-360' => [
            'name' => 'DEMO 360 Booth',
            'description' => 'Data testing Top Product - 360 Booth',
            'price' => 7_000_000,
        ],
        'DEMO-TOP-SLOWMO' => [
            'name' => 'DEMO Slow Motion',
            'description' => 'Data testing Top Product - Slow Motion',
            'price' => 9_000_000,
        ],
    ];

    $products = [];

    foreach ($definitions as $sku => $definition) {
        DB::table('products')->updateOrInsert(
            ['sku' => $sku],
            [
                ...$definition,
                'quantity' => 99,
                'updated_at' => $timestamp,
                'created_at' => $timestamp,
            ]
        );

        $products[$sku] = [
            ...$definition,
            'id' => (int) DB::table('products')
                ->where('sku', $sku)
                ->value('id'),
            'sku' => $sku,
        ];
    }

    return $products;
}

/**
 * @param  array<string, mixed>  $deal
 * @param  array<string, array<string, mixed>>  $products
 * @return array{invoices: int, payments: int}
 */
function createDemoDeal(
    array $deal,
    array $products,
    int $personId,
    int $userId,
    CarbonImmutable $baseDate
): array {
    $quoteTotal = (float) collect($deal['items'])->sum('total');
    $quoteNumber = DEMO_QUOTE_PREFIX.$deal['code'];
    $projectCode = 'DEMO-TOP-PRJ-'.$deal['code'];
    $timestamp = $baseDate->setTime(8, 0);

    $quoteId = (int) DB::table('quotes')->insertGetId([
        'quote_number' => $quoteNumber,
        'project_code' => $projectCode,
        'business_unit' => 'capture_it',
        'subject' => $deal['subject'],
        'description' => 'Data demo lokal untuk pengujian Top Product Report.',
        'billing_address' => null,
        'shipping_address' => null,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'adjustment_amount' => 0,
        'sub_total' => $quoteTotal,
        'grand_total' => $quoteTotal,
        'expired_at' => $baseDate->addDays(30),
        'event_date' => $baseDate->addDays(14)->toDateString(),
        'location' => 'DEMO Local Test',
        'payment_term' => 'Demo testing only',
        'person_id' => $personId,
        'user_id' => $userId,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    foreach ($deal['items'] as $item) {
        $product = $products[$item['sku']];
        $quantity = max(1, (int) $item['quantity']);

        DB::table('quote_items')->insert([
            'quote_id' => $quoteId,
            'product_id' => $product['id'],
            'sku' => $product['sku'],
            'name' => $product['name'],
            'description' => 'Snapshot produk demo untuk Top Product Report.',
            'day' => 1,
            'quantity' => $quantity,
            'price' => (float) $item['total'] / $quantity,
            'coupon_code' => null,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => $item['total'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    $dpInvoiceId = null;
    $invoicedToDate = 0.0;
    $counts = ['invoices' => 0, 'payments' => 0];

    foreach ($deal['invoices'] as $invoiceDefinition) {
        $issuedAt = $baseDate->setTime((int) $invoiceDefinition['hour'], 0);
        $invoiceAmount = (float) $invoiceDefinition['amount'];
        $paidAmount = min($invoiceAmount, max(0.0, (float) $invoiceDefinition['paid']));
        $balanceDue = max(0.0, $invoiceAmount - $paidAmount);
        $paymentStatus = $paidAmount <= 0
            ? 'unpaid'
            : ($paidAmount < $invoiceAmount ? 'partial' : 'paid');
        $billingType = (string) $invoiceDefinition['type'];
        $percentage = $quoteTotal > 0
            ? ($invoiceAmount / $quoteTotal) * 100
            : 0;
        $invoicedToDate += $invoiceAmount;

        $invoiceId = (int) DB::table('invoices')->insertGetId([
            'invoice_number' => DEMO_INVOICE_PREFIX.$deal['code'].'-'.$invoiceDefinition['suffix'],
            'project_code' => $projectCode,
            'business_unit' => 'capture_it',
            'quote_id' => $quoteId,
            'billing_type' => $billingType,
            'billing_method' => match ($billingType) {
                'down_payment' => 'nominal',
                'settlement' => 'balance',
                default => 'full',
            },
            'billing_percentage' => $percentage,
            'quote_total_snapshot' => $quoteTotal,
            'billing_amount' => $invoiceAmount,
            'remaining_amount_snapshot' => max(0, $quoteTotal - $invoicedToDate),
            'dp_invoice_id' => $billingType === 'settlement' ? $dpInvoiceId : null,
            'billing_created_by' => $userId,
            'billing_locked_at' => $issuedAt,
            'event_date' => $baseDate->addDays(14)->toDateString(),
            'location' => 'DEMO Local Test',
            'payment_term' => 'Demo testing only',
            'person_id' => $personId,
            'user_id' => $userId,
            'subject' => $deal['subject'],
            'description' => 'Invoice demo lokal untuk pengujian Top Product Report.',
            'billing_address' => null,
            'shipping_address' => null,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'adjustment_amount' => 0,
            'sub_total' => $invoiceAmount,
            'grand_total' => $invoiceAmount,
            'paid_amount' => $paidAmount,
            'balance_due' => $balanceDue,
            'status' => $paymentStatus,
            'event_status' => 'confirm',
            'issued_at' => $issuedAt,
            'due_at' => $issuedAt->addDays(7),
            'created_at' => $issuedAt,
            'updated_at' => $issuedAt,
        ]);

        if ($billingType === 'down_payment') {
            $dpInvoiceId = $invoiceId;
        }

        $allocation = $quoteTotal > 0
            ? $invoiceAmount / $quoteTotal
            : 0;

        foreach ($deal['items'] as $item) {
            $product = $products[$item['sku']];
            $quantity = max(1, (int) $item['quantity']);
            $lineTotal = round((float) $item['total'] * $allocation, 4);

            DB::table('invoice_items')->insert([
                'invoice_id' => $invoiceId,
                'product_id' => $product['id'],
                'sku' => $product['sku'],
                'name' => $product['name'],
                'description' => strtoupper(str_replace('_', ' ', $billingType)).' - demo Top Product.',
                'day' => 1,
                'quantity' => $quantity,
                'price' => $lineTotal / $quantity,
                'coupon_code' => null,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'tax_percent' => 0,
                'tax_amount' => 0,
                'total' => $lineTotal,
                'created_at' => $issuedAt,
                'updated_at' => $issuedAt,
            ]);
        }

        if ($paidAmount > 0) {
            DB::table('payments')->insert([
                'invoice_id' => $invoiceId,
                'amount' => $paidAmount,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'DEMO-TOP-PAY-'.$deal['code'].'-'.$invoiceDefinition['suffix'],
                'notes' => 'Data demo lokal Top Product Report.',
                'paid_at' => $issuedAt->addMinutes(30),
                'created_by' => $userId,
                'created_at' => $issuedAt->addMinutes(30),
                'updated_at' => $issuedAt->addMinutes(30),
            ]);

            $counts['payments']++;
        }

        $counts['invoices']++;
    }

    return $counts;
}

/**
 * @param  array<string, int>  $removed
 */
function printCleanupResult(array $removed, string $title = 'DATA DIHAPUS'): void
{
    echo PHP_EOL;
    echo $title.PHP_EOL;
    echo str_repeat('-', strlen($title)).PHP_EOL;

    foreach ($removed as $label => $count) {
        echo str_pad($label, 20).': '.$count.PHP_EOL;
    }
}
