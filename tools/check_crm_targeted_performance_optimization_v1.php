<?php

declare(strict_types=1);

echo "CHECK CRM TARGETED PERFORMANCE OPTIMIZATION V1\n";
echo "==============================================".PHP_EOL.PHP_EOL;

$root = dirname(__DIR__);
$database = optionValue($argv, 'database');

if ($database !== null) {
    putenv('DB_DATABASE='.$database);
    $_ENV['DB_DATABASE'] = $database;
    $_SERVER['DB_DATABASE'] = $database;
}

$checks = [
    'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php' => [
        'CRM_TARGETED_PERFORMANCE_OPTIMIZATION_V1',
        '$otherUsersByConversation',
        '$lastMessagesByConversation',
        '$unreadCountsByConversation',
        "->groupBy('message.conversation_id')",
    ],
    'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatConversationController.php' => [
        'CRM_TARGETED_PERFORMANCE_OPTIMIZATION_V1',
        '$presenceLastSeenByUser',
        '$attachmentMessageIds',
        '$knownLastSeenLoaded',
    ],
    'packages/Webkul/Admin/src/DataGrids/Invoice/InvoiceDataGrid.php' => [
        'CRM_TARGETED_PERFORMANCE_OPTIMIZATION_V1',
        'CRM_INVOICE_DATAGRID_FAST_PRODUCTS_V1',
        '$productFilterActive',
        "Cache::remember(",
        "protected \$sortColumn = 'invoices.id'",
    ],
    'packages/Webkul/Admin/src/DataGrids/Quote/QuoteDataGrid.php' => [
        'CRM_TARGETED_PERFORMANCE_OPTIMIZATION_V1',
        "protected \$sortColumn = 'quotes.id'",
    ],
    'database/migrations/2026_09_09_200000_add_crm_targeted_performance_indexes_v1.php' => [
        'CRM_TARGETED_PERFORMANCE_OPTIMIZATION_V1',
        'crm_chat_latest_v1_idx',
        'crm_invoice_item_product_v1_idx',
        'crm_quote_business_v1_idx',
    ],
];

$failures = 0;

foreach ($checks as $relative => $markers) {
    $path = $root.'/'.$relative;

    if (! is_file($path)) {
        fail("File tersedia: {$relative} — file tidak ditemukan");
        $failures++;
        continue;
    }

    ok("File tersedia: {$relative}");
    $contents = (string) file_get_contents($path);
    $missing = array_values(array_filter(
        $markers,
        fn ($marker) => ! str_contains($contents, $marker)
    ));

    if ($missing !== []) {
        fail("Marker {$relative} — kurang: ".implode(', ', $missing));
        $failures++;
    } else {
        ok("Marker optimasi lengkap: {$relative}");
    }

    $command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        fail("PHP lint: {$relative} — ".implode(' ', $output));
        $failures++;
    } else {
        ok("PHP lint: {$relative}");
    }
}

$chat = readOptional($root.'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php');
$sidebar = readOptional($root.'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatConversationController.php');
$invoice = readOptional($root.'/packages/Webkul/Admin/src/DataGrids/Invoice/InvoiceDataGrid.php');

if (str_contains($chat, "\$conversationRows\n            ->map(")) {
    fail('Pola N+1 initial chat lama sudah dilepas');
    $failures++;
} else {
    ok('Pola N+1 initial chat lama sudah dilepas');
}

if (str_contains($sidebar, "\$rows\n                ->map(\n                    function (\$row) use (")) {
    fail('Pola N+1 sidebar chat lama sudah dilepas');
    $failures++;
} else {
    ok('Pola N+1 sidebar chat lama sudah dilepas');
}

if (str_contains($invoice, "->leftJoin(\n                'invoice_items as invoice_product_items'")) {
    fail('Join invoice_items tanpa syarat sudah dilepas');
    $failures++;
} else {
    ok('Join invoice_items tanpa syarat sudah dilepas');
}

if (! str_contains($invoice, 'if ($productFilterActive)')) {
    fail('Join product hanya ketika filter aktif');
    $failures++;
} else {
    ok('Join product hanya ketika filter aktif');
}

if (is_file($root.'/bootstrap/app.php') && is_file($root.'/vendor/autoload.php')) {
    try {
        // CRM_CHAT_BACKEND_PERFORMANCE_V2: match artisan's bootstrap order.
        require_once $root.'/vendor/autoload.php';
        if (! class_exists(Illuminate\Foundation\Application::class)) {
            throw new RuntimeException('Composer autoloader tidak menyediakan Laravel Application.');
        }
        $app = require $root.'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        $activeDatabase = Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        echo '[INFO] Database runtime: '.$activeDatabase.PHP_EOL;

        if ($database !== null && $activeDatabase !== $database) {
            throw new RuntimeException('Database runtime berbeda dari --database; jalankan php artisan config:clear.');
        }

        $expectedIndexes = [
            'internal_messages' => ['crm_chat_latest_v1_idx', 'crm_chat_unread_v1_idx', 'crm_chat_edited_v1_idx'],
            'internal_conversation_members' => ['crm_chat_member_user_v1_idx'],
            'invoice_items' => ['crm_invoice_item_product_v1_idx'],
            'invoices' => [
                'crm_invoice_business_v1_idx',
                'crm_invoice_owner_v1_idx',
                'crm_invoice_person_v1_idx',
                'crm_invoice_issued_v1_idx',
                'crm_invoice_event_v1_idx',
            ],
            'quotes' => ['crm_quote_business_v1_idx', 'crm_quote_person_v1_idx', 'crm_quote_expired_v1_idx'],
        ];

        foreach ($expectedIndexes as $table => $indexes) {
            foreach ($indexes as $index) {
                $exists = Illuminate\Support\Facades\DB::table('information_schema.statistics')
                    ->where('table_schema', $activeDatabase)
                    ->where('table_name', $table)
                    ->where('index_name', $index)
                    ->exists();

                if ($exists) {
                    ok("Index database: {$table}.{$index}");
                } else {
                    fail("Index database: {$table}.{$index} — jalankan php artisan migrate");
                    $failures++;
                }
            }
        }
    } catch (Throwable $exception) {
        fail('Laravel runtime: '.$exception->getMessage());
        $failures++;
    }
} else {
    fail('Laravel runtime tidak tersedia (vendor/autoload.php atau bootstrap/app.php tidak ditemukan)');
    $failures++;
}

echo PHP_EOL;

if ($failures > 0) {
    echo "[FAIL] Checker menemukan {$failures} masalah.".PHP_EOL;
    exit(1);
}

echo "[OK] Seluruh optimasi kode dan indeks database terpasang.".PHP_EOL;
exit(0);

function optionValue(array $arguments, string $name): ?string
{
    $prefix = '--'.$name.'=';

    foreach ($arguments as $argument) {
        if (str_starts_with($argument, $prefix)) {
            $value = trim(substr($argument, strlen($prefix)));

            return $value !== '' ? $value : null;
        }
    }

    return null;
}

function readOptional(string $path): string
{
    return is_file($path) ? (string) file_get_contents($path) : '';
}

function ok(string $message): void
{
    echo '[OK]   '.$message.PHP_EOL;
}

function fail(string $message): void
{
    echo '[FAIL] '.$message.PHP_EOL;
}
