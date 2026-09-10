<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}
require __DIR__.'/crm_performance_completion_v2/common.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Admin\Http\Controllers\InternalCommunication\InternalChatController;
use Webkul\Admin\Models\InternalMessage;
use Webkul\Admin\Models\WorkflowNotification;
use Webkul\Admin\Services\InternalChatRealtimeService;

function crmPerfAssert(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException($label);
    }
    echo '[OK] '.$label.PHP_EOL;
}

function crmPerfMeasure(callable $callback): array
{
    $connection = DB::connection();
    $connection->flushQueryLog();
    $connection->enableQueryLog();
    $started = hrtime(true);
    try {
        $value = $callback();
        $log = $connection->getQueryLog();
        return ['value' => $value, 'queries' => count($log),
            'sql_ms' => array_sum(array_column($log, 'time')),
            'wall_ms' => (hrtime(true) - $started) / 1e6];
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }
}

echo 'CHECK CRM PERFORMANCE COMPLETION V2'.PHP_EOL;
try {
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (! preg_match('/^--(database|conversation)=(.+)$/D', $argument, $match)
            || isset($options[$match[1]])) {
            throw new RuntimeException('Gunakan --database=captureit_crm_performance dan opsional --conversation=25.');
        }
        $options[$match[1]] = $match[2];
    }
    $database = $options['database'] ?? '';
    if ($database !== 'captureit_crm_performance') {
        throw new RuntimeException('Checker testing ini memerlukan --database=captureit_crm_performance.');
    }
    $conversationId = $options['conversation'] ?? null;
    if ($conversationId !== null && (! ctype_digit($conversationId) || (int) $conversationId < 1)) {
        throw new RuntimeException('Conversation ID harus bilangan positif.');
    }
    $root = crmPerfRoot();
    $manifest = crmPerfManifest();
    $expected = $manifest['audited_files'];
    foreach ($manifest['files'] as $entry) {
        $expected[$entry['path']] = $entry['after_sha256'];
    }
    $fileFailures = 0;
    foreach ($expected as $relative => $hash) {
        try {
            $source = crmPerfRead(crmPerfPath($root, $relative));
            if (crmPerfHash($source) !== $hash) {
                throw new RuntimeException('Versi source berbeda: '.$relative);
            }
            if (str_ends_with($relative, '.php') && ! str_ends_with($relative, '.blade.php')) {
                token_get_all($source, TOKEN_PARSE);
            }
        } catch (Throwable $error) {
            echo '[FAIL] '.$error->getMessage().PHP_EOL;
            $fileFailures++;
        }
    }
    crmPerfAssert($fileFailures === 0, 'Source cocok dengan versi yang diaudit; sintaks PHP valid ('.count($expected).' file).');

    if (is_file($root.'/bootstrap/cache/config.php')) {
        throw new RuntimeException('Jalankan php artisan config:clear terlebih dahulu agar pilihan database tidak tertimpa cache.');
    }
    putenv('DB_DATABASE='.$database);
    $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $database;
    require_once crmPerfPath($root, 'vendor/autoload.php');
    crmPerfAssert(class_exists(Illuminate\Foundation\Application::class), 'Autoloader Laravel tersedia.');
    $app = require crmPerfPath($root, 'bootstrap/app.php');
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    crmPerfAssert(DB::connection()->getDatabaseName() === $database, 'Database konfigurasi: '.$database);
    crmPerfAssert(in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true), 'Driver MySQL/MariaDB.');
    $active = DB::selectOne('SELECT DATABASE() AS active_database')->active_database;
    crmPerfAssert($active === $database, 'Database koneksi aktual: '.$database);

    $prefix = DB::connection()->getTablePrefix();
    $tables = array_map(fn ($table) => $prefix.$table, array_keys($manifest['indexes']));
    $placeholders = implode(',', array_fill(0, count($tables), '?'));
    $indexRows = DB::select(
        'SELECT TABLE_NAME AS table_name, INDEX_NAME AS index_name, COLUMN_NAME AS column_name, SUB_PART AS sub_part '
        .'FROM information_schema.statistics WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ('.$placeholders.') '
        .'ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
        array_merge([$database], $tables),
    );
    $actual = [];
    foreach ($indexRows as $row) {
        $actual[$row->table_name][$row->index_name][] = [strtolower($row->column_name), $row->sub_part];
    }
    $indexFailures = 0;
    foreach ($manifest['indexes'] as $table => $indexes) {
        foreach ($indexes as $name => $columns) {
            $found = $actual[$prefix.$table][$name] ?? [];
            $wanted = array_map(fn ($column) => [strtolower($column), null], $columns);
            if ($found !== $wanted) {
                echo '[FAIL] Index '.$table.'.'.$name.' harus ('.implode(', ', $columns).'), tanpa prefix kolom.'.PHP_EOL;
                $indexFailures++;
            } else {
                echo '[OK] Index '.$table.'.'.$name.' ('.implode(', ', $columns).')'.PHP_EOL;
            }
        }
    }
    crmPerfAssert($indexFailures === 0, 'Definisi dan urutan kolom seluruh 13 indeks V1 sesuai.');
    crmPerfAssert(Schema::hasTable((new WorkflowNotification)->getTable()), 'Tabel notifikasi sesuai model runtime.');

    // Compile to memory only; do not render or write compiled views.
    foreach (['chat.blade.php', 'widget.blade.php', 'realtime-chat-listeners.blade.php', 'chat-unread-badge.blade.php'] as $view) {
        $source = crmPerfRead(crmPerfPath($root, 'packages/Webkul/Admin/src/Resources/views/internal-communication/'.$view));
        token_get_all(Illuminate\Support\Facades\Blade::compileString($source), TOKEN_PARSE);
    }
    echo '[OK] Sintaks hasil kompilasi 4 view chat valid.'.PHP_EOL;

    if ($conversationId !== null) {
        $conversationId = (int) $conversationId;
        $latest = crmPerfMeasure(fn () => InternalMessage::query()
            ->where('conversation_id', $conversationId)->whereNull('deleted_at')
            ->orderByDesc('id')->limit(50)->get(['id', 'conversation_id', 'user_id', 'body', 'deleted_at']));
        $messages = $latest['value'];
        echo '[INFO] SELECT 50 terbaru: '.count($messages).' baris; '.round($latest['wall_ms'], 2).' ms. Ini bukan waktu loading HTTP.'.PHP_EOL;
        crmPerfAssert($messages->isNotEmpty(), 'Percakapan pengujian berisi pesan.');

        $controller = (new ReflectionClass(InternalChatController::class))->newInstanceWithoutConstructor();
        $lookup = new ReflectionMethod($controller, 'replyPayloads');
        $format = new ReflectionMethod($controller, 'messagePayload');
        // Unsaved in-memory messages exercise actual batching on existing data.
        $probes = $messages->map(fn ($message) => (new InternalMessage([
            'conversation_id' => $conversationId, 'reply_to_message_id' => $message->id,
        ]))->setRelation('attachments', collect()));
        $one = crmPerfMeasure(fn () => $lookup->invoke($controller, $probes->take(1)));
        $batch = crmPerfMeasure(fn () => $lookup->invoke($controller, $probes));
        crmPerfAssert($one['queries'] === 2 && $batch['queries'] === 2,
            'Balasan: 2 query untuk 1 maupun '.count($probes).' referensi pesan.');
        crmPerfAssert(count($batch['value']) === count($probes), 'Semua referensi balasan terpetakan.');
        $formatted = crmPerfMeasure(function () use ($probes, $format, $controller, $batch) {
            return $probes->map(fn ($message) => $format->invoke($controller, $message, 'User', $batch['value']));
        });
        crmPerfAssert($formatted['queries'] === 0, 'Format seluruh payload balasan tidak menjalankan query tambahan.');
        $without = crmPerfMeasure(fn () => $lookup->invoke($controller, collect([new InternalMessage(['conversation_id' => $conversationId])])));
        crmPerfAssert($without['queries'] === 0 && $without['value'] === [], 'Pesan tanpa balasan: 0 query lookup.');
        $foreign = new InternalMessage(['conversation_id' => -1, 'reply_to_message_id' => $messages->first()->id]);
        crmPerfAssert($lookup->invoke($controller, collect([$foreign])) === [], 'Referensi dari luar percakapan tidak ditampilkan.');

        $userIds = DB::table('internal_conversation_members')->where('conversation_id', $conversationId)
            ->distinct()->limit(10)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        crmPerfAssert($userIds !== [], 'Anggota percakapan tersedia untuk pengujian batch unread.');
        $service = new InternalChatRealtimeService;
        $countsMethod = new ReflectionMethod($service, 'unreadCountsForUsers');
        $single = crmPerfMeasure(fn () => $countsMethod->invoke($service, [$userIds[0]]));
        $many = crmPerfMeasure(fn () => $countsMethod->invoke($service, $userIds));
        crmPerfAssert($single['queries'] === $many['queries'], 'Jumlah query unread tetap untuk 1 dan '.count($userIds).' pengguna.');
        $hasCursor = Schema::hasColumn('internal_conversation_members', 'last_read_message_id');
        foreach ($userIds as $userId) {
            $query = DB::table('internal_conversation_members as member')
                ->join('internal_messages as message', 'message.conversation_id', '=', 'member.conversation_id')
                ->where('member.user_id', $userId)->where('message.user_id', '<>', $userId)->whereNull('message.deleted_at');
            if ($hasCursor) {
                $query->whereRaw('message.id > COALESCE(member.last_read_message_id, 0)');
            } else {
                $query->where(fn ($nested) => $nested->whereNull('member.last_read_at')->orWhereColumn('message.created_at', '>', 'member.last_read_at'));
            }
            $reference = ['chat_unread' => (int) $query->count(), 'notification_unread' =>
                (int) WorkflowNotification::query()->where('user_id', $userId)->whereNull('read_at')->count()];
            crmPerfAssert($reference === $many['value'][$userId], 'Hasil batch sama dengan hitungan independen untuk user '.$userId.'.');
        }
        echo '[INFO] Pengujian di atas hanya SELECT; tidak membuka endpoint, markRead, atau broadcast.'.PHP_EOL;
    }

    $broadcastConnection = (string) config('broadcasting.default', 'null');
    $realtimeEnabled = (bool) config('internal_chat_realtime.enabled', true);
    if (! $realtimeEnabled || ! in_array($broadcastConnection, ['reverb', 'pusher'], true)
        || ! filled(config('broadcasting.connections.'.$broadcastConnection.'.key'))) {
        echo '[WARN] WebSocket belum aktif dalam konfigurasi runtime. Tidak ada fallback polling pesan.'.PHP_EOL;
    } elseif ($broadcastConnection === 'reverb') {
        $host = (string) config('broadcasting.connections.reverb.options.host', '');
        $port = (int) config('broadcasting.connections.reverb.options.port', 0);
        if ($host !== '' && $port > 0 && $port < 65536 && ! preg_match('/[\s\/]/', $host)) {
            $address = str_contains($host, ':') && ! str_starts_with($host, '[') ? '['.$host.']' : $host;
            $socket = @stream_socket_client('tcp://'.$address.':'.$port, $errno, $error, 0.75);
            if ($socket === false) {
                echo '[WARN] Port Reverb tidak terjangkau. Jalankan php artisan reverb:start --debug di terminal lain.'.PHP_EOL;
            } else {
                fclose($socket);
                echo '[OK] Port Reverb terjangkau. Tetap pastikan browser Connected; ini belum menguji autentikasi channel.'.PHP_EOL;
            }
        } else {
            echo '[WARN] Host/port Reverb belum dapat diperiksa.'.PHP_EOL;
        }
    }
    echo '[OK] Pemeriksaan kode dan indeks selesai. Kecepatan halaman dan realtime perlu diuji di browser.'.PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, '[FAIL] '.$error->getMessage().PHP_EOL);
    exit(1);
}
