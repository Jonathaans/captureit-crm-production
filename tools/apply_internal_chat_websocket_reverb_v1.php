<?php

declare(strict_types=1);

const PATCH_MARKER = 'INTERNAL_CHAT_WEBSOCKET_REVERB_V1';

$root = dirname(__DIR__);
$payloadRoot = __DIR__.'/internal-chat-websocket-reverb-v1/payload';
$backupRoot = __DIR__.'/backups/internal-chat-websocket-reverb-v1-'.date('Ymd-His');

echo "INTERNAL CHAT WEBSOCKET REVERB V1\n";
echo "==================================\n\n";

$required = [
    'composer.json',
    'composer.lock',
    'config/broadcasting.php',
    'routes/channels.php',
    '.env.example',
    'packages/Webkul/Admin/package.json',
    'packages/Webkul/Admin/src/Resources/assets/js/app.js',
    'packages/Webkul/Admin/src/Providers/InternalCommunicationServiceProvider.php',
    'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php',
    'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatExperienceController.php',
    'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/WorkflowNotificationController.php',
    'packages/Webkul/Admin/src/Services/WorkflowNotificationService.php',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/chat-unread-badge.blade.php',
];

$optionalTargets = [
    'packages/Webkul/Admin/package-lock.json',
];

$payloadFiles = [
    'config/reverb.php',
    'deploy/nginx/crm-reverb.conf.example',
    'deploy/supervisor/crm-reverb.conf.example',
    'docs/INTERNAL_CHAT_WEBSOCKET_REVERB_V1.md',
    'packages/Webkul/Admin/src/Config/internal_chat_realtime.php',
    'packages/Webkul/Admin/src/Events/InternalChatRealtimeEvent.php',
    'packages/Webkul/Admin/src/Services/InternalChatRealtimeService.php',
    'packages/Webkul/Admin/src/Resources/assets/js/internal-chat-realtime.js',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/realtime-config.blade.php',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/realtime-chat-listeners.blade.php',
    'tests/Unit/InternalChatRealtimeEventTest.php',
];

$fail = static function (string $message): never {
    throw new RuntimeException($message);
};

$read = static function (string $path) use ($fail): string {
    if (! is_file($path)) {
        $fail('File tidak ditemukan: '.$path);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        $fail('File tidak dapat dibaca: '.$path);
    }

    return $content;
};

$write = static function (string $path, string $content) use ($fail): void {
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        $fail('Folder tidak dapat dibuat: '.$directory);
    }

    $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        $fail('File sementara tidak dapat ditulis: '.$temporary);
    }

    if (is_file($path) && ! unlink($path)) {
        @unlink($temporary);
        $fail('File target tidak dapat diganti: '.$path);
    }

    if (! rename($temporary, $path)) {
        @unlink($temporary);
        $fail('File target tidak dapat disimpan: '.$path);
    }
};

$replace = static function (
    string $path,
    string $needle,
    string $replacement,
    int $expected = 1,
) use ($read, $write, $fail): void {
    $content = $read($path);
    $count = substr_count($content, $needle);

    if ($count !== $expected) {
        $fail('Marker pada '.str_replace('\\', '/', $path).' harus '.$expected.'; count='.$count.'.');
    }

    $write($path, str_replace($needle, $replacement, $content));
};

$replaceRegex = static function (
    string $path,
    string $pattern,
    string $replacement,
    int $expected = 1,
) use ($read, $write, $fail): void {
    $content = $read($path);
    $changed = 0;
    $result = preg_replace($pattern, $replacement, $content, -1, $changed);

    if ($result === null) {
        $fail('Regex tidak valid untuk '.str_replace('\\', '/', $path).'.');
    }

    if ($changed !== $expected) {
        $fail('Regex pada '.str_replace('\\', '/', $path).' harus '.$expected.'; count='.$changed.'.');
    }

    $write($path, $result);
};

$manifest = [];

try {
    foreach ($required as $relative) {
        if (! is_file($root.'/'.$relative)) {
            $fail('Preflight file wajib tidak ditemukan: '.$relative);
        }
    }

    foreach ($payloadFiles as $relative) {
        if (! is_file($payloadRoot.'/'.$relative)) {
            $fail('Payload tidak lengkap: '.$relative);
        }
    }

    $providerPath = $root.'/packages/Webkul/Admin/src/Providers/InternalCommunicationServiceProvider.php';

    if (str_contains($read($providerPath), PATCH_MARKER)) {
        echo "[OK] Hotfix sudah terpasang; tidak ada file yang diubah.\n";
        echo "Jalankan: php tools/check_internal_chat_websocket_reverb_v1.php\n";
        exit(0);
    }

    $allTargets = array_values(array_unique(array_merge($required, $optionalTargets, $payloadFiles)));

    if (! mkdir($backupRoot, 0775, true) && ! is_dir($backupRoot)) {
        $fail('Folder backup tidak dapat dibuat: '.$backupRoot);
    }

    foreach ($allTargets as $relative) {
        $source = $root.'/'.$relative;
        $exists = is_file($source);
        $manifest[$relative] = ['existed' => $exists];

        if (! $exists) {
            continue;
        }

        $backup = $backupRoot.'/'.$relative;

        if (! is_dir(dirname($backup)) && ! mkdir(dirname($backup), 0775, true) && ! is_dir(dirname($backup))) {
            $fail('Folder backup tidak dapat dibuat: '.dirname($backup));
        }

        if (! copy($source, $backup)) {
            $fail('Backup gagal: '.$relative);
        }
    }

    $write(
        $backupRoot.'/manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
    );

    foreach ($payloadFiles as $relative) {
        $write($root.'/'.$relative, $read($payloadRoot.'/'.$relative));
        echo '[WRITE] '.$relative.PHP_EOL;
    }

    $broadcasting = $root.'/config/broadcasting.php';
    $replace(
        $broadcasting,
        "    'default' => env('BROADCAST_DRIVER', 'null'),",
        "    'default' => env('BROADCAST_CONNECTION', env('BROADCAST_DRIVER', 'null')),",
    );

    $reverbConnection = <<<'PHP'
        /* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [],
        ],

PHP;
    $replace($broadcasting, "        'pusher' => [", $reverbConnection."        'pusher' => [");

    $channels = $root.'/routes/channels.php';
    $replace(
        $channels,
        "use Illuminate\\Support\\Facades\\Broadcast;",
        "use Illuminate\\Support\\Facades\\Broadcast;\nuse Illuminate\\Support\\Facades\\DB;",
    );
    $channelRules = <<<'PHP'

/* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */
Broadcast::channel('internal-chat.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
}, ['guards' => ['user']]);

Broadcast::channel('internal-chat.conversation.{conversationId}', function ($user, $conversationId) {
    return DB::table('internal_conversation_members')
        ->where('conversation_id', (int) $conversationId)
        ->where('user_id', (int) $user->id)
        ->exists();
}, ['guards' => ['user']]);
PHP;
    $write($channels, rtrim($read($channels)).$channelRules.PHP_EOL);

    $env = $root.'/.env.example';
    $replace(
        $env,
        'BROADCAST_DRIVER=log',
        "BROADCAST_CONNECTION=log\nBROADCAST_DRIVER=log",
    );
    $envBlock = <<<'ENV'

# Internal Chat WebSocket / Laravel Reverb
CRM_INTERNAL_CHAT_REALTIME_ENABLED=false
CRM_INTERNAL_CHAT_FALLBACK_POLL_MS=30000
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=localhost
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_ALLOWED_ORIGINS=localhost
ENV;
    $write($env, rtrim($read($env)).$envBlock.PHP_EOL);

    $packagePath = $root.'/packages/Webkul/Admin/package.json';
    $package = json_decode($read($packagePath), true, 512, JSON_THROW_ON_ERROR);
    $package['dependencies']['laravel-echo'] = '^2.2.0';
    $package['dependencies']['pusher-js'] = '^8.4.0';
    ksort($package['dependencies']);
    $write(
        $packagePath,
        json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
    );

    $appJs = $root.'/packages/Webkul/Admin/src/Resources/assets/js/app.js';
    $replace(
        $appJs,
        'import.meta.glob(["../images/**", "../fonts/**"]);',
        "import.meta.glob([\"../images/**\", \"../fonts/**\"]);\n\n/* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */\nimport \"./internal-chat-realtime\";",
    );

    $providerRegister = <<<'PHP'
    /* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/internal_chat_realtime.php',
            'internal_chat_realtime'
        );
    }

PHP;
    $replace(
        $providerPath,
        "class InternalCommunicationServiceProvider extends ServiceProvider\n{\n    public function boot(",
        "class InternalCommunicationServiceProvider extends ServiceProvider\n{\n".$providerRegister."    public function boot(",
    );
    $ackRoute = <<<'PHP'
                    /* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */
                    Route::post(
                        'internal-notifications/{id}/popup-ack',
                        [
                            WorkflowNotificationController::class,
                            'popupAck',
                        ]
                    )->name(
                        'admin.internal-notifications.popup-ack'
                    );

PHP;
    $replace(
        $providerPath,
        "                    Route::get(\n                        'internal-notifications/{id}/open',",
        $ackRoute."                    Route::get(\n                        'internal-notifications/{id}/open',",
    );

    $controller = $root.'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php';
    $replace(
        $controller,
        "use Webkul\\Admin\\Services\\InternalChatService;",
        "use Webkul\\Admin\\Services\\InternalChatService;\nuse Webkul\\Admin\\Services\\InternalChatRealtimeService;",
    );
    $replaceRegex(
        $controller,
        '~\\$chat->markRead\\(\\$conversationId, \\$user->id\\);\\R\\s*/\\* INTERNAL CHAT V3\\.3\\.9 READ CURSOR SYNC \\*/\\R\\s*\\$this->syncReadMessageCursor\\(\\R\\s*\\$conversationId,\\R\\s*\\(int\\) \\$user->id\\R\\s*\\);~',
        "\$this->markConversationRead(\n                \$chat,\n                \$conversationId,\n                (int) \$user->id\n            );",
        2,
    );
    $replace(
        $controller,
        "        if (\$request->expectsJson()) {",
        "        /* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */\n        app(InternalChatRealtimeService::class)->conversationChanged(\n            \$conversationId,\n            'message.created',\n            (int) \$user->id\n        );\n\n        if (\$request->expectsJson()) {",
    );
    $replace(
        $controller,
        "        \$message->edited_at = now();\n        \$message->save();\n\n        return response()->json(",
        "        \$message->edited_at = now();\n        \$message->save();\n\n        app(InternalChatRealtimeService::class)->conversationChanged(\n            \$conversationId,\n            'message.updated',\n            (int) \$user->id\n        );\n\n        return response()->json(",
    );
    $replace(
        $controller,
        "        \$message->deleted_at = now();\n        \$message->save();\n\n        return response()->json([",
        "        \$message->deleted_at = now();\n        \$message->save();\n\n        app(InternalChatRealtimeService::class)->conversationChanged(\n            \$conversationId,\n            'message.deleted',\n            (int) \$user->id\n        );\n\n        return response()->json([",
    );
    $readHelpers = <<<'PHP'
    /** INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */
    private function markConversationRead(
        InternalChatService $chat,
        int $conversationId,
        int $userId
    ): void {
        $previousCursor = $this->readMessageCursor($conversationId, $userId);

        $chat->markRead($conversationId, $userId);
        $this->syncReadMessageCursor($conversationId, $userId);

        $currentCursor = $this->readMessageCursor($conversationId, $userId);

        if ($currentCursor > $previousCursor) {
            app(InternalChatRealtimeService::class)->conversationChanged(
                $conversationId,
                'conversation.read',
                $userId
            );
        }
    }

    private function readMessageCursor(int $conversationId, int $userId): int
    {
        if (! Schema::hasColumn('internal_conversation_members', 'last_read_message_id')) {
            return 0;
        }

        return (int) (DB::table('internal_conversation_members')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->value('last_read_message_id') ?? 0);
    }

PHP;
    $replace(
        $controller,
        "    /**\n     * Keep unread state deterministic per conversation.",
        $readHelpers."    /**\n     * Keep unread state deterministic per conversation.",
    );

    $experience = $root.'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatExperienceController.php';
    $replace(
        $experience,
        "use Webkul\\Admin\\Http\\Controllers\\Controller;",
        "use Webkul\\Admin\\Http\\Controllers\\Controller;\nuse Webkul\\Admin\\Services\\InternalChatRealtimeService;",
    );
    $replace(
        $experience,
        "        return response()->json([\n            'ok' =>\n                true,\n        ]);",
        "        /* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */\n        app(InternalChatRealtimeService::class)->typingChanged(\n            \$conversationId,\n            (int) \$user->id,\n            (string) (\$user->name ?: 'User'),\n            \$isTyping\n        );\n\n        return response()->json([\n            'ok' =>\n                true,\n        ]);",
    );

    $workflowService = $root.'/packages/Webkul/Admin/src/Services/WorkflowNotificationService.php';
    $replace(
        $workflowService,
        "        return WorkflowNotification::query()\n            ->firstOrCreate(",
        "        \$notification = WorkflowNotification::query()\n            ->firstOrCreate(",
    );
    $replace(
        $workflowService,
        "                ]\n            );\n    }\n\n    public function notifyUsers(",
        "                ]\n            );\n\n        /* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */\n        if (\$notification->wasRecentlyCreated) {\n            app(InternalChatRealtimeService::class)\n                ->workflowNotificationCreated(\$notification);\n        }\n\n        return \$notification;\n    }\n\n    public function notifyUsers(",
    );

    $workflowController = $root.'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/WorkflowNotificationController.php';
    $ackMethod = <<<'PHP'
    /** INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */
    public function popupAck(int $id): JsonResponse
    {
        $user = $this->user();

        WorkflowNotification::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->whereNull('popup_at')
            ->update(['popup_at' => now()]);

        return response()->json(['ok' => true]);
    }

PHP;
    $replace(
        $workflowController,
        "    public function open(\n        int \$id",
        $ackMethod."    public function open(\n        int \$id",
    );

    $chat = $root.'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';
    $replace(
        $chat,
        "                            <div class=\"rounded-full border bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-500\">\n                                🔒 Private\n                            </div>",
        "                            <span id=\"crm-chat-realtime-status\" class=\"rounded-full px-3 py-2 text-xs font-semibold\" title=\"Menghubungkan WebSocket\" style=\"background:#fef3c7;color:#92400e;\">Fallback</span>\n\n                            <div class=\"rounded-full border bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-500\">\n                                🔒 Private\n                            </div>",
    );
    $replace(
        $chat,
        "            window.setInterval(\n                pollTyping,\n                2000\n            );",
        "            /* Typing WebSocket; fallback ditangani listener V3.2.2 di bawah. */",
    );
    $replace(
        $chat,
        "                window.setInterval(\n                    pollMessages,\n                    5000\n                );",
        "                /* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */\n                window.crmChatPollMessages = pollMessages;\n\n                window.setInterval(\n                    () => {\n                        if (window.crmInternalChatRealtime?.shouldFallbackPoll?.() ?? true) {\n                            pollMessages();\n                        }\n                    },\n                    window.crmInternalChatRealtime?.fallbackPollMs || 30000\n                );",
    );
    $replace(
        $chat,
        "                        async () => {\n                            try {",
        "                        async () => {\n                            if (window.crmInternalChatRealtime?.isConnected?.()) {\n                                return;\n                            }\n\n                            try {",
    );
    $replace(
        $chat,
        "                        2000\n                    );",
        "                        window.crmInternalChatRealtime?.fallbackPollMs || 30000\n                    );",
    );
    $replace(
        $chat,
        "            window.setInterval(\n                refreshSidebar,\n                4000\n            );",
        "            window.setInterval(\n                () => {\n                    if (window.crmInternalChatRealtime?.shouldFallbackPoll?.() ?? true) {\n                        refreshSidebar();\n                    }\n                },\n                window.crmInternalChatRealtime?.fallbackPollMs || 30000\n            );",
    );
    $replace(
        $chat,
        "</x-admin::layouts>",
        "    @include('admin::internal-communication.realtime-chat-listeners')\n</x-admin::layouts>",
    );

    $widget = $root.'/packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php';
    $write(
        $widget,
        "{{-- INTERNAL_CHAT_WEBSOCKET_REVERB_V1 --}}\n@include('admin::internal-communication.realtime-config')\n".$read($widget),
    );
    $widgetListener = <<<'BLADE'

    /* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */
    const acknowledgeNotification = async (notification) => {
        if (! notification?.ack_url) {
            return;
        }

        const config = document.getElementById('crm-internal-chat-realtime-config');

        try {
            await fetch(notification.ack_url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': config?.dataset.csrf || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
        } catch (error) {
            // Fallback polling can safely claim the notification later.
        }
    };

    window.addEventListener('crm:chat-user-state', (event) => {
        const payload = event.detail?.payload || {};

        setBadge(notificationBadge, payload.notification_unread);
        setBadge(chatBadge, payload.chat_unread);

        if (payload.notification) {
            showToast(payload.notification);
            acknowledgeNotification(payload.notification);
        }
    });
BLADE;
    $replace(
        $widget,
        "\n    const poll = async () => {",
        $widgetListener."\n    const poll = async () => {",
    );
    $replace(
        $widget,
        "    window.setInterval(\n        poll,\n        12000\n    );",
        "    window.setInterval(\n        () => {\n            if (window.crmInternalChatRealtime?.shouldFallbackPoll?.() ?? true) {\n                poll();\n            }\n        },\n        window.crmInternalChatRealtime?.fallbackPollMs || 30000\n    );",
    );

    $unread = $root.'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat-unread-badge.blade.php';
    $replace(
        $unread,
        "        removeWrongRowBadges();\n\n        pollUnread();",
        "        /* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */\n        window.addEventListener('crm:chat-user-state', (event) => {\n            renderCount(\n                Math.max(0, Number(event.detail?.payload?.chat_unread || 0))\n            );\n        });\n\n        removeWrongRowBadges();\n\n        pollUnread();",
    );
    $replace(
        $unread,
        "        window.setInterval(\n            pollUnread,\n            5000\n        );",
        "        window.setInterval(\n            () => {\n                if (window.crmInternalChatRealtime?.shouldFallbackPoll?.() ?? true) {\n                    pollUnread();\n                }\n            },\n            window.crmInternalChatRealtime?.fallbackPollMs || 30000\n        );",
    );

    foreach ([
        'packages/Webkul/Admin/src/Events/InternalChatRealtimeEvent.php',
        'packages/Webkul/Admin/src/Services/InternalChatRealtimeService.php',
        'packages/Webkul/Admin/src/Providers/InternalCommunicationServiceProvider.php',
        'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php',
        'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatExperienceController.php',
        'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/WorkflowNotificationController.php',
        'packages/Webkul/Admin/src/Services/WorkflowNotificationService.php',
        'config/reverb.php',
        'config/broadcasting.php',
        'routes/channels.php',
    ] as $relative) {
        $output = [];
        $status = 1;
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$relative).' 2>&1', $output, $status);

        if ($status !== 0) {
            $fail('PHP lint gagal: '.$relative.PHP_EOL.implode(PHP_EOL, $output));
        }

        echo '[OK]    PHP lint '.$relative.PHP_EOL;
    }

    echo "\nPATCH BERHASIL.\n";
    echo 'Backup source: '.$backupRoot.PHP_EOL;
    echo "\nLanjutkan dari root project:\n";
    echo "composer require laravel/reverb:^1.0 --with-all-dependencies\n";
    echo "cd packages/Webkul/Admin && npm install && npm run build && cd ../../../\n";
    echo "php artisan optimize:clear\n";
    echo "php tools/check_internal_chat_websocket_reverb_v1.php\n";
} catch (Throwable $exception) {
    if ($manifest !== []) {
        foreach ($manifest as $relative => $entry) {
            $target = $root.'/'.$relative;
            $backup = $backupRoot.'/'.$relative;

            if ($entry['existed'] && is_file($backup)) {
                if (! is_dir(dirname($target))) {
                    @mkdir(dirname($target), 0775, true);
                }

                @copy($backup, $target);
            } elseif (! $entry['existed'] && is_file($target)) {
                @unlink($target);
            }
        }
    }

    fwrite(STDERR, "\nPATCH GAGAL: ".$exception->getMessage().PHP_EOL);
    fwrite(STDERR, "Semua file yang sempat diubah telah dipulihkan dari backup.\n");
    exit(1);
}
