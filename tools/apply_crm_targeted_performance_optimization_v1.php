<?php

declare(strict_types=1);

/*
 * CRM TARGETED PERFORMANCE OPTIMIZATION V1
 *
 * Optimizes the known hot paths without changing their public routes or UI:
 * - Internal Chat initial conversation list
 * - Internal Chat realtime sidebar summary
 * - Invoice product summary/filter path
 * - Default Quote/Invoice sorting
 * - Supporting composite indexes through a Laravel migration
 */

echo "CRM TARGETED PERFORMANCE OPTIMIZATION V1\n";
echo "=========================================".PHP_EOL.PHP_EOL;

$root = dirname(__DIR__);

$targets = [
    'chat' => $root.'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php',
    'sidebar' => $root.'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatConversationController.php',
    'invoice' => $root.'/packages/Webkul/Admin/src/DataGrids/Invoice/InvoiceDataGrid.php',
    'quote' => $root.'/packages/Webkul/Admin/src/DataGrids/Quote/QuoteDataGrid.php',
];

$migrationRelative = 'database/migrations/2026_09_09_200000_add_crm_targeted_performance_indexes_v1.php';
$migration = $root.'/'.$migrationRelative;
$marker = 'CRM_TARGETED_PERFORMANCE_OPTIMIZATION_V1';

try {
    if (! is_file($root.'/artisan')) {
        throw new RuntimeException('Jalankan script dari folder tools di root project Laravel CRM.');
    }

    foreach ($targets as $label => $path) {
        if (! is_file($path)) {
            throw new RuntimeException("File target tidak ditemukan [{$label}]: {$path}");
        }
    }

    $sources = [];

    foreach ($targets as $label => $path) {
        $sources[$label] = readFileStrict($path);
    }

    $installedCount = 0;

    foreach ($sources as $source) {
        $installedCount += str_contains($source, $marker) ? 1 : 0;
    }

    if ($installedCount === count($targets) && is_file($migration) && str_contains(readFileStrict($migration), $marker)) {
        echo "[OK] Patch sudah terpasang; tidak ada file yang diubah.".PHP_EOL;
        echo "Lanjutkan dengan migrate dan checker sesuai README.".PHP_EOL;
        exit(0);
    }

    if ($installedCount > 0 || is_file($migration)) {
        throw new RuntimeException('Instalasi parsial terdeteksi. Pulihkan backup terakhir atau selesaikan secara manual sebelum mengulang.');
    }

    preflight($sources);

    $updated = $sources;
    $updated['chat'] = optimizeChatIndex($updated['chat']);
    $updated['sidebar'] = optimizeSidebarSummary($updated['sidebar']);
    $updated['invoice'] = optimizeInvoiceDataGrid($updated['invoice']);
    $updated['quote'] = optimizeQuoteDataGrid($updated['quote']);

    $timestamp = date('Ymd_His');
    $backupRoot = $root.'/tools/backups/crm_targeted_performance_optimization_v1_'.$timestamp;

    foreach ($targets as $label => $path) {
        $relative = relativePath($root, $path);
        $backup = $backupRoot.'/'.$relative;
        ensureDirectory(dirname($backup));

        if (! copy($path, $backup)) {
            throw new RuntimeException("Gagal membuat backup: {$backup}");
        }
    }

    foreach ($targets as $label => $path) {
        writeFileStrict($path, $updated[$label]);
        echo '[OK] Dioptimasi: '.relativePath($root, $path).PHP_EOL;
    }

    ensureDirectory(dirname($migration));
    writeFileStrict($migration, migrationSource());
    echo '[OK] Migration dibuat: '.$migrationRelative.PHP_EOL;

    $lintTargets = array_values($targets);
    $lintTargets[] = $migration;

    foreach ($lintTargets as $path) {
        lintPhp($path);
        echo '[OK] PHP lint: '.relativePath($root, $path).PHP_EOL;
    }

    echo PHP_EOL.'PATCH BERHASIL.'.PHP_EOL;
    echo 'Backup: '.relativePath($root, $backupRoot).PHP_EOL.PHP_EOL;
    echo "Langkah berikutnya (database performance):\n";
    echo '$env:DB_DATABASE="captureit_crm_performance"'.PHP_EOL;
    echo "php artisan optimize:clear\n";
    echo "php artisan migrate --force\n";
    echo "php tools/check_crm_targeted_performance_optimization_v1.php --database=captureit_crm_performance\n";
    echo "Remove-Item Env:DB_DATABASE -ErrorAction SilentlyContinue\n";
} catch (Throwable $exception) {
    fwrite(STDERR, PHP_EOL.'PATCH GAGAL: '.$exception->getMessage().PHP_EOL);
    exit(1);
}

function preflight(array $sources): void
{
    assertExactlyOnce($sources['chat'], '        $conversationList = $conversationRows', 'Chat conversation-list start');
    assertExactlyOnce($sources['chat'], '        $senderIds = $messages', 'Chat conversation-list end');

    assertExactlyOnce($sources['sidebar'], "        \$conversations =\n            \$rows\n                ->map(", 'Sidebar N+1 start');
    assertExactlyOnce(
        $sources['sidebar'],
        "\n\n        return response()->json([\n            'conversations' =>",
        'Sidebar N+1 end'
    );
    assertExactlyOnce($sources['sidebar'], "    private function presenceState(\n        int \$userId,\n        bool \$hasPresenceTable\n    ): array {", 'Presence signature');

    assertExactlyOnce($sources['invoice'], 'class InvoiceDataGrid extends DataGrid', 'Invoice class');
    assertExactlyOnce($sources['invoice'], '         * Product reporting/filter dimension.', 'Invoice product query start');
    assertExactlyOnce($sources['invoice'], "\$this->addFilter(\n            'invoice_number',", 'Invoice product query end');
    assertExactlyOnce($sources['invoice'], "            'filterable_options' => DB::table('invoice_items')", 'Invoice product options');

    assertExactlyOnce($sources['quote'], 'class QuoteDataGrid extends DataGrid', 'Quote class');
}

function optimizeChatIndex(string $source): string
{
    $replacement = <<<'PHP'
        /* CRM_TARGETED_PERFORMANCE_OPTIMIZATION_V1: batched initial chat list */
        $conversationIds = $conversationRows
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $otherUsersByConversation = $conversationIds->isEmpty()
            ? collect()
            : DB::table('internal_conversation_members as member')
                ->join('users', 'users.id', '=', 'member.user_id')
                ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->whereIn('member.conversation_id', $conversationIds->all())
                ->where('member.user_id', '<>', $user->id)
                ->select([
                    'member.conversation_id',
                    'users.id',
                    'users.name',
                    'users.email',
                    'roles.name as role_name',
                ])
                ->get()
                ->keyBy('conversation_id');

        $lastMessageIds = $conversationIds->isEmpty()
            ? collect()
            : DB::table('internal_messages')
                ->whereIn('conversation_id', $conversationIds->all())
                ->whereNull('deleted_at')
                ->groupBy('conversation_id')
                ->selectRaw('conversation_id, MAX(id) as last_message_id')
                ->pluck('last_message_id');

        $lastMessagesByConversation = $lastMessageIds->isEmpty()
            ? collect()
            : DB::table('internal_messages')
                ->whereIn('id', $lastMessageIds->map(fn ($id) => (int) $id)->all())
                ->get()
                ->keyBy('conversation_id');

        $hasReadCursor = Schema::hasColumn(
            'internal_conversation_members',
            'last_read_message_id'
        );

        $unreadCountsByConversation = collect();

        if ($conversationIds->isNotEmpty()) {
            $unreadQuery = DB::table('internal_messages as message')
                ->join('internal_conversation_members as self_member', function ($join) use ($user) {
                    $join->on('self_member.conversation_id', '=', 'message.conversation_id')
                        ->where('self_member.user_id', '=', (int) $user->id);
                })
                ->whereIn('message.conversation_id', $conversationIds->all())
                ->where('message.user_id', '<>', $user->id)
                ->whereNull('message.deleted_at');

            if ($hasReadCursor) {
                $unreadQuery->whereRaw(
                    'message.id > COALESCE(self_member.last_read_message_id, 0)'
                );
            } else {
                $unreadQuery->where(function ($query) {
                    $query->whereNull('self_member.last_read_at')
                        ->orWhereColumn('message.created_at', '>', 'self_member.last_read_at');
                });
            }

            $unreadCountsByConversation = $unreadQuery
                ->groupBy('message.conversation_id')
                ->selectRaw('message.conversation_id, COUNT(*) as unread_count')
                ->get()
                ->mapWithKeys(fn ($row) => [
                    (int) $row->conversation_id => (int) $row->unread_count,
                ]);
        }

        $conversationList = $conversationRows->map(
            fn ($row) => (object) [
                'id' => (int) $row->id,
                'other' => $otherUsersByConversation->get((int) $row->id),
                'last_message' => $lastMessagesByConversation->get((int) $row->id),
                'unread_count' => (int) $unreadCountsByConversation->get((int) $row->id, 0),
            ]
        );

PHP;

    return replaceBetween(
        $source,
        '        $conversationList = $conversationRows',
        '        $senderIds = $messages',
        $replacement.'        $senderIds = $messages'
    );
}

function optimizeSidebarSummary(string $source): string
{
    $replacement = <<<'PHP'
        /* CRM_TARGETED_PERFORMANCE_OPTIMIZATION_V1: batched realtime sidebar */
        $conversationIds = $rows
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $otherUsersByConversation = $conversationIds->isEmpty()
            ? collect()
            : DB::table('internal_conversation_members as member')
                ->join('users', 'users.id', '=', 'member.user_id')
                ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->whereIn('member.conversation_id', $conversationIds->all())
                ->where('member.user_id', '<>', $user->id)
                ->select([
                    'member.conversation_id',
                    'users.id',
                    'users.name',
                    'users.email',
                    'roles.name as role_name',
                ])
                ->get()
                ->keyBy('conversation_id');

        $lastMessageIds = $conversationIds->isEmpty()
            ? collect()
            : DB::table('internal_messages')
                ->whereIn('conversation_id', $conversationIds->all())
                ->whereNull('deleted_at')
                ->groupBy('conversation_id')
                ->selectRaw('conversation_id, MAX(id) as last_message_id')
                ->pluck('last_message_id');

        $lastMessagesByConversation = $lastMessageIds->isEmpty()
            ? collect()
            : DB::table('internal_messages')
                ->whereIn('id', $lastMessageIds->map(fn ($id) => (int) $id)->all())
                ->get(['id', 'conversation_id', 'user_id', 'body', 'created_at'])
                ->keyBy('conversation_id');

        $attachmentMessageIds = $lastMessagesByConversation->isEmpty()
            ? collect()
            : DB::table('internal_message_attachments')
                ->whereIn(
                    'message_id',
                    $lastMessagesByConversation->pluck('id')->map(fn ($id) => (int) $id)->all()
                )
                ->distinct()
                ->pluck('message_id')
                ->mapWithKeys(fn ($id) => [(int) $id => true]);

        $unreadCountsByConversation = collect();

        if ($conversationIds->isNotEmpty()) {
            $unreadQuery = DB::table('internal_messages as message')
                ->join('internal_conversation_members as self_member', function ($join) use ($user) {
                    $join->on('self_member.conversation_id', '=', 'message.conversation_id')
                        ->where('self_member.user_id', '=', (int) $user->id);
                })
                ->whereIn('message.conversation_id', $conversationIds->all())
                ->where('message.user_id', '<>', $user->id)
                ->whereNull('message.deleted_at');

            if ($hasCursor) {
                $unreadQuery->whereRaw(
                    'message.id > COALESCE(self_member.last_read_message_id, 0)'
                );
            } else {
                $unreadQuery->where(function ($query) {
                    $query->whereNull('self_member.last_read_at')
                        ->orWhereColumn('message.created_at', '>', 'self_member.last_read_at');
                });
            }

            $unreadCountsByConversation = $unreadQuery
                ->groupBy('message.conversation_id')
                ->selectRaw('message.conversation_id, COUNT(*) as unread_count')
                ->get()
                ->mapWithKeys(fn ($row) => [
                    (int) $row->conversation_id => (int) $row->unread_count,
                ]);
        }

        $otherUserIds = $otherUsersByConversation
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $presenceLastSeenByUser = ! $hasPresenceTable || $otherUserIds->isEmpty()
            ? collect()
            : DB::table('internal_chat_user_states')
                ->whereIn('user_id', $otherUserIds->all())
                ->pluck('last_seen_at', 'user_id');

        $conversations = $rows
            ->map(function ($row) use (
                $user,
                $hasPresenceTable,
                $hasCursor,
                $otherUsersByConversation,
                $lastMessagesByConversation,
                $attachmentMessageIds,
                $unreadCountsByConversation,
                $presenceLastSeenByUser
            ) {
                $conversationId = (int) $row->id;
                $other = $otherUsersByConversation->get($conversationId);
                $lastMessage = $lastMessagesByConversation->get($conversationId);
                $readCursor = $hasCursor
                    ? max(0, (int) ($row->last_read_message_id ?? 0))
                    : 0;
                $unread = (int) $unreadCountsByConversation->get($conversationId, 0);

                $preview = trim((string) ($lastMessage?->body ?? ''));

                if ($preview === '' && $lastMessage) {
                    $preview = $attachmentMessageIds->get((int) $lastMessage->id, false)
                        ? '📎 Attachment'
                        : 'Pesan';
                }

                if ($preview === '') {
                    $preview = 'Belum ada pesan.';
                }

                $lastAt = $lastMessage?->created_at ?? $row->updated_at;
                $timeLabel = '';

                if ($lastAt) {
                    $time = Carbon::parse($lastAt);
                    $timeLabel = $time->isToday() ? $time->format('H:i') : $time->format('d M');
                }

                $muted = $this->isMuted($row->muted_until, (bool) $row->mute_forever);
                $otherUserId = (int) ($other?->id ?? 0);
                $presence = $this->presenceState(
                    $otherUserId,
                    $hasPresenceTable,
                    $presenceLastSeenByUser->get($otherUserId),
                    true
                );

                return [
                    'id' => $conversationId,
                    'name' => (string) ($other?->name ?: 'User'),
                    'email' => (string) ($other?->email ?: ''),
                    'role' => (string) ($other?->role_name ?: 'Internal User'),
                    'initials' => $this->initials((string) ($other?->name ?: 'User')),
                    'preview' => mb_strimwidth(
                        preg_replace('/\s+/', ' ', $preview),
                        0,
                        56,
                        '…'
                    ),
                    'time' => $timeLabel,
                    'unread' => $unread,
                    'read_cursor' => $readCursor,
                    'pinned' => ! empty($row->pinned_at),
                    'muted' => $muted,
                    'mute_label' => $this->muteLabel(
                        $row->muted_until,
                        (bool) $row->mute_forever
                    ),
                    'online' => $presence['state'] === 'online',
                    'idle' => $presence['state'] === 'idle',
                    'in_chat' => $presence['in_chat'],
                    'presence_state' => $presence['state'],
                    'presence' => $presence['label'],
                    'sort_at' => $lastAt
                        ? Carbon::parse($lastAt)->format('Y-m-d H:i:s.u')
                        : '',
                ];
            })
            ->sort(function (array $a, array $b) {
                if ($a['pinned'] !== $b['pinned']) {
                    return $a['pinned'] ? -1 : 1;
                }

                return strcmp($b['sort_at'], $a['sort_at']);
            })
            ->values();
PHP;

    $source = replaceBetween(
        $source,
        "        \$conversations =\n            \$rows\n                ->map(",
        "\n\n        return response()->json([\n            'conversations' =>",
        $replacement."\n\n        return response()->json([\n            'conversations' =>"
    );

    $oldSignature = <<<'PHP'
    private function presenceState(
        int $userId,
        bool $hasPresenceTable
    ): array {
PHP;

    $newSignature = <<<'PHP'
    private function presenceState(
        int $userId,
        bool $hasPresenceTable,
        mixed $knownLastSeen = null,
        bool $knownLastSeenLoaded = false
    ): array {
PHP;

    $source = replaceExactly($source, $oldSignature, $newSignature, 'presenceState signature');

    $oldLookup = <<<'PHP'
        $lastSeen =
            null;

        if ($hasPresenceTable) {
            $lastSeen =
                DB::table(
                    'internal_chat_user_states'
                )
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->value(
                        'last_seen_at'
                    );
        }
PHP;

    $newLookup = <<<'PHP'
        $lastSeen = $knownLastSeen;

        if ($hasPresenceTable && ! $knownLastSeenLoaded) {
            $lastSeen = DB::table('internal_chat_user_states')
                ->where('user_id', $userId)
                ->value('last_seen_at');
        }
PHP;

    return replaceExactly($source, $oldLookup, $newLookup, 'presenceState database fallback');
}

function optimizeInvoiceDataGrid(string $source): string
{
    $source = replaceExactly(
        $source,
        "use Illuminate\\Support\\Facades\\DB;\n",
        "use Illuminate\\Support\\Facades\\Cache;\nuse Illuminate\\Support\\Facades\\DB;\n",
        'Invoice Cache import'
    );

    $source = replaceExactly(
        $source,
        "class InvoiceDataGrid extends DataGrid\n{",
        "class InvoiceDataGrid extends DataGrid\n{\n    /* CRM_TARGETED_PERFORMANCE_OPTIMIZATION_V1 */\n    protected \$sortColumn = 'invoices.id';",
        'Invoice class marker'
    );

    $queryReplacement = <<<'PHP'
        /*
         * CRM_INVOICE_DATAGRID_FAST_PRODUCTS_V1
         *
         * The expensive invoice_items join is only needed while the product
         * filter is active. The normal paginated list keeps one row per invoice,
         * so its COUNT query no longer needs DISTINCT over multiplied item rows.
         */
        $productFilters = (array) request()->input('filters', []);
        $productFilterActive = array_key_exists('products', $productFilters)
            && $productFilters['products'] !== null
            && $productFilters['products'] !== ''
            && $productFilters['products'] !== [];

        $queryBuilder->addSelect(
            DB::raw(
                "(SELECT GROUP_CONCAT(DISTINCT ii_product.name ORDER BY ii_product.name SEPARATOR ', ')"
                ." FROM invoice_items ii_product"
                ." WHERE ii_product.invoice_id = invoices.id"
                .") as products"
            )
        );

        if ($productFilterActive) {
            $queryBuilder
                ->join(
                    'invoice_items as invoice_product_filter_items',
                    'invoices.id',
                    '=',
                    'invoice_product_filter_items.invoice_id'
                )
                ->distinct();
        }

        $this->addFilter(
            'products',
            $productFilterActive
                ? 'invoice_product_filter_items.name'
                : 'products'
        );

PHP;

    $start = strpos($source, "        /*\n         * Product reporting/filter dimension.");

    if ($start === false) {
        throw new RuntimeException('Marker awal query produk Invoice tidak ditemukan.');
    }

    $endMarker = "\$this->addFilter(\n            'invoice_number',";
    $end = strpos($source, $endMarker, $start);

    if ($end === false) {
        throw new RuntimeException('Marker akhir query produk Invoice tidak ditemukan.');
    }

    $source = substr($source, 0, $start)
        .$queryReplacement
        .substr($source, $end);

    $oldOptions = <<<'PHP'
            'filterable_options' => DB::table('invoice_items')
                ->whereNotNull('name')
                ->where('name', '<>', '')
                ->select('name')
                ->distinct()
                ->orderBy('name')
                ->pluck('name')
                ->map(
                    fn ($name) => [
                        'label' => (string) $name,
                        'value' => (string) $name,
                    ]
                )
                ->values()
                ->all(),
PHP;

    $newOptions = <<<'PHP'
            'filterable_options' => Cache::remember(
                'invoice.datagrid.product-options.v1.'
                    .sha1(DB::connection()->getDatabaseName()),
                now()->addMinutes(5),
                fn () => DB::table('invoice_items')
                    ->whereNotNull('name')
                    ->where('name', '<>', '')
                    ->select('name')
                    ->distinct()
                    ->orderBy('name')
                    ->pluck('name')
                    ->map(fn ($name) => [
                        'label' => (string) $name,
                        'value' => (string) $name,
                    ])
                    ->values()
                    ->all()
            ),
PHP;

    return replaceExactly($source, $oldOptions, $newOptions, 'Invoice cached product options');
}

function optimizeQuoteDataGrid(string $source): string
{
    return replaceExactly(
        $source,
        "class QuoteDataGrid extends DataGrid\n{",
        "class QuoteDataGrid extends DataGrid\n{\n    /* CRM_TARGETED_PERFORMANCE_OPTIMIZATION_V1 */\n    protected \$sortColumn = 'quotes.id';",
        'Quote qualified default sort'
    );
}

function migrationSource(): string
{
    return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* CRM_TARGETED_PERFORMANCE_OPTIMIZATION_V1 */
return new class extends Migration
{
    /** @var array<string, array<string, array<int, string>>> */
    private array $indexes = [
        'internal_messages' => [
            'crm_chat_latest_v1_idx' => ['conversation_id', 'deleted_at', 'id'],
            'crm_chat_unread_v1_idx' => ['conversation_id', 'deleted_at', 'user_id', 'id'],
            'crm_chat_edited_v1_idx' => ['conversation_id', 'deleted_at', 'edited_at', 'id'],
        ],
        'internal_conversation_members' => [
            'crm_chat_member_user_v1_idx' => ['user_id', 'conversation_id'],
        ],
        'invoice_items' => [
            'crm_invoice_item_product_v1_idx' => ['invoice_id', 'name'],
        ],
        'invoices' => [
            'crm_invoice_business_v1_idx' => ['business_unit', 'id'],
            'crm_invoice_owner_v1_idx' => ['user_id', 'id'],
            'crm_invoice_person_v1_idx' => ['person_id', 'id'],
            'crm_invoice_issued_v1_idx' => ['issued_at', 'id'],
            'crm_invoice_event_v1_idx' => ['event_status', 'id'],
        ],
        'quotes' => [
            'crm_quote_business_v1_idx' => ['business_unit', 'id'],
            'crm_quote_person_v1_idx' => ['person_id', 'id'],
            'crm_quote_expired_v1_idx' => ['expired_at', 'id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if (! $this->allColumnsExist($table, $columns) || $this->indexExists($table, $name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                    $blueprint->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                if ($this->indexExists($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }

    private function allColumnsExist(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function indexExists(string $table, string $name): bool
    {
        $database = DB::connection()->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }
};
PHP;
}

function replaceBetween(string $source, string $startMarker, string $endMarker, string $replacement): string
{
    $start = strpos($source, $startMarker);
    $end = $start === false ? false : strpos($source, $endMarker, $start + strlen($startMarker));

    if ($start === false || $end === false) {
        throw new RuntimeException('Marker rentang patch tidak ditemukan.');
    }

    return substr($source, 0, $start).$replacement.substr($source, $end + strlen($endMarker));
}

function replaceExactly(string $source, string $old, string $new, string $label): string
{
    $count = substr_count($source, $old);

    if ($count !== 1) {
        throw new RuntimeException("Preflight {$label} harus ditemukan tepat satu kali; count={$count}.");
    }

    return str_replace($old, $new, $source);
}

function assertExactlyOnce(string $source, string $needle, string $label): void
{
    $count = substr_count($source, $needle);

    if ($count !== 1) {
        throw new RuntimeException("Preflight {$label} harus ditemukan tepat satu kali; count={$count}.");
    }
}

function readFileStrict(string $path): string
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Gagal membaca file: {$path}");
    }

    return str_replace("\r\n", "\n", $contents);
}

function writeFileStrict(string $path, string $contents): void
{
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException("Gagal menulis file: {$path}");
    }
}

function ensureDirectory(string $path): void
{
    if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
        throw new RuntimeException("Gagal membuat direktori: {$path}");
    }
}

function relativePath(string $root, string $path): string
{
    return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
}

function lintPhp(string $path): void
{
    $command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException("PHP lint gagal untuk {$path}: ".implode(PHP_EOL, $output));
    }
}
