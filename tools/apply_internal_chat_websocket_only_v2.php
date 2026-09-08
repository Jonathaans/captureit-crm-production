<?php

declare(strict_types=1);

const PATCH_TITLE = 'INTERNAL CHAT WEBSOCKET-ONLY V2.1';
const PATCH_MARKER = 'INTERNAL_CHAT_WEBSOCKET_ONLY_V2';
const V1_MARKER = 'INTERNAL_CHAT_WEBSOCKET_REVERB_V1';

$root = dirname(__DIR__);
$payloadRoot = __DIR__.DIRECTORY_SEPARATOR.'internal-chat-websocket-only-v2'.DIRECTORY_SEPARATOR.'payload';
$backupRoot = __DIR__.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.'internal-chat-websocket-only-v2-'.date('Ymd-His');

function wsOnlyLine(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function wsOnlyFail(string $message): never
{
    throw new RuntimeException($message);
}

function wsOnlyPath(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function wsOnlyRead(string $path): string
{
    if (! is_file($path)) {
        wsOnlyFail('File tidak ditemukan: '.$path);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        wsOnlyFail('File tidak dapat dibaca: '.$path);
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function wsOnlyWrite(string $path, string $content): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        wsOnlyFail('Folder tidak dapat dibuat: '.$directory);
    }

    $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        wsOnlyFail('File sementara tidak dapat ditulis: '.$temporary);
    }

    if (is_file($path) && ! unlink($path)) {
        @unlink($temporary);
        wsOnlyFail('File target tidak dapat diganti: '.$path);
    }

    if (! rename($temporary, $path)) {
        @unlink($temporary);
        wsOnlyFail('File target tidak dapat disimpan: '.$path);
    }
}

function wsOnlyReplaceOnce(string $content, string $search, string $replacement, string $label): string
{
    $count = substr_count($content, $search);

    if ($count !== 1) {
        wsOnlyFail('Preflight '.$label.' harus ditemukan tepat satu kali; count='.$count.'.');
    }

    return str_replace($search, $replacement, $content);
}

function wsOnlyReplaceBetween(
    string $content,
    string $start,
    string $end,
    string $replacement,
    string $label,
): string {
    if (substr_count($content, $start) !== 1 || substr_count($content, $end) < 1) {
        wsOnlyFail('Preflight blok '.$label.' tidak unik atau anchor akhir tidak ditemukan.');
    }

    $startAt = strpos($content, $start);
    $endAt = $startAt === false ? false : strpos($content, $end, $startAt + strlen($start));

    if ($startAt === false || $endAt === false) {
        wsOnlyFail('Preflight blok '.$label.' tidak dapat dipetakan.');
    }

    return substr($content, 0, $startAt).$replacement.substr($content, $endAt);
}

function wsOnlyRun(string $root, array $arguments): int
{
    $command = escapeshellarg(PHP_BINARY);

    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg((string) $argument);
    }

    wsOnlyLine('[RUN]   '.implode(' ', $arguments));
    $previous = getcwd();
    chdir($root);
    passthru($command, $exitCode);

    if ($previous !== false) {
        chdir($previous);
    }

    return (int) $exitCode;
}

function wsOnlyRestore(string $root, string $backupRoot, array $manifest): void
{
    foreach ($manifest['files'] as $relative => $metadata) {
        $target = $root.DIRECTORY_SEPARATOR.wsOnlyPath($relative);
        $backup = $backupRoot.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.wsOnlyPath($relative);

        if (($metadata['existed'] ?? false) === true && is_file($backup)) {
            wsOnlyWrite($target, wsOnlyRead($backup));
        } elseif (is_file($target)) {
            unlink($target);
        }
    }
}

wsOnlyLine(PATCH_TITLE);
wsOnlyLine(str_repeat('=', strlen(PATCH_TITLE)));
wsOnlyLine();

$required = [
    '.env.example',
    'packages/Webkul/Admin/src/Config/internal_chat_realtime.php',
    'packages/Webkul/Admin/src/Events/InternalChatRealtimeEvent.php',
    'packages/Webkul/Admin/src/Services/InternalChatRealtimeService.php',
    'packages/Webkul/Admin/src/Resources/assets/js/internal-chat-realtime.js',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/realtime-config.blade.php',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/realtime-chat-listeners.blade.php',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/chat-unread-badge.blade.php',
];

$payloadFiles = [
    'packages/Webkul/Admin/src/Config/internal_chat_realtime.php',
    'packages/Webkul/Admin/src/Resources/assets/js/internal-chat-realtime.js',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/realtime-config.blade.php',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/realtime-chat-listeners.blade.php',
    'docs/INTERNAL_CHAT_WEBSOCKET_ONLY_V2.md',
];

$manifest = ['patch' => PATCH_TITLE, 'created_at' => date(DATE_ATOM), 'files' => []];
$writesStarted = false;

try {
    if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
        wsOnlyFail('Jalankan tool dari root project Laravel.');
    }

    foreach ($required as $relative) {
        if (! is_file($root.DIRECTORY_SEPARATOR.wsOnlyPath($relative))) {
            wsOnlyFail('Preflight file wajib tidak ditemukan: '.$relative);
        }
    }

    foreach ($payloadFiles as $relative) {
        if (! is_file($payloadRoot.DIRECTORY_SEPARATOR.wsOnlyPath($relative))) {
            wsOnlyFail('Payload tidak lengkap: '.$relative);
        }
    }

    $jsPath = $root.DIRECTORY_SEPARATOR.wsOnlyPath('packages/Webkul/Admin/src/Resources/assets/js/internal-chat-realtime.js');
    $chatPath = $root.DIRECTORY_SEPARATOR.wsOnlyPath('packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php');

    if (str_contains(wsOnlyRead($jsPath), PATCH_MARKER) && str_contains(wsOnlyRead($chatPath), PATCH_MARKER)) {
        wsOnlyLine('[OK] WebSocket-Only V2 sudah terpasang.');
        wsOnlyLine('Jalankan npm run build lalu checker V2.');
        exit(0);
    }

    /*
     * V1's payload JS and listener Blade intentionally did not carry the V1
     * comment marker.  Detect the installed capabilities instead of requiring
     * a comment that can also disappear after another safe hotfix/formatter.
     */
    $v1Capabilities = [
        $jsPath => [
            'laravel-echo',
            'pusher-js',
            'window.crmInternalChatRealtime',
            'shouldFallbackPoll',
            'fallbackPollMs',
        ],
        $chatPath => [
            'window.crmChatPollMessages = pollMessages;',
            'shouldFallbackPoll',
            'refreshSidebar',
        ],
        $root.DIRECTORY_SEPARATOR.wsOnlyPath('packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php') => [
            "@include('admin::internal-communication.realtime-config')",
            'shouldFallbackPoll',
            "window.addEventListener('crm:chat-user-state'",
        ],
        $root.DIRECTORY_SEPARATOR.wsOnlyPath('packages/Webkul/Admin/src/Resources/views/internal-communication/chat-unread-badge.blade.php') => [
            'shouldFallbackPoll',
            "window.addEventListener('crm:chat-user-state'",
        ],
    ];

    foreach ($v1Capabilities as $v1File => $needles) {
        $v1Content = wsOnlyRead($v1File);
        $missing = array_values(array_filter(
            $needles,
            static fn (string $needle): bool => ! str_contains($v1Content, $needle),
        ));

        if ($missing !== []) {
            wsOnlyFail(
                'Dependency Internal Chat WebSocket Reverb V1 belum lengkap: '
                .$v1File.PHP_EOL
                .'Kapabilitas tidak ditemukan: '.implode(', ', $missing),
            );
        }
    }

    $targets = array_values(array_unique(array_merge($required, $payloadFiles)));

    if (! mkdir($backupRoot.DIRECTORY_SEPARATOR.'files', 0775, true)) {
        wsOnlyFail('Folder backup source tidak dapat dibuat.');
    }

    foreach ($targets as $relative) {
        $source = $root.DIRECTORY_SEPARATOR.wsOnlyPath($relative);
        $existed = is_file($source);
        $manifest['files'][$relative] = ['existed' => $existed];

        if (! $existed) {
            continue;
        }

        $backup = $backupRoot.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.wsOnlyPath($relative);
        wsOnlyWrite($backup, wsOnlyRead($source));
    }

    wsOnlyWrite(
        $backupRoot.DIRECTORY_SEPARATOR.'manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
    );
    wsOnlyWrite(
        $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.'internal-chat-websocket-only-v2-latest.txt',
        $backupRoot.PHP_EOL,
    );

    $writesStarted = true;

    foreach ($payloadFiles as $relative) {
        wsOnlyWrite(
            $root.DIRECTORY_SEPARATOR.wsOnlyPath($relative),
            wsOnlyRead($payloadRoot.DIRECTORY_SEPARATOR.wsOnlyPath($relative)),
        );
        wsOnlyLine('[WRITE] '.$relative);
    }

    $chat = wsOnlyRead($chatPath);
    $chat = wsOnlyReplaceOnce(
        $chat,
        '<span id="crm-chat-realtime-status" class="rounded-full px-3 py-2 text-xs font-semibold" title="Menghubungkan WebSocket" style="background:#fef3c7;color:#92400e;">Fallback</span>',
        '<span id="crm-chat-realtime-status" class="rounded-full px-3 py-2 text-xs font-semibold" title="Menghubungkan WebSocket" style="background:#fef3c7;color:#92400e;">Connecting</span>',
        'badge realtime',
    );
    $chat = wsOnlyReplaceOnce(
        $chat,
        '            /* Typing WebSocket; fallback ditangani listener V3.2.2 di bawah. */',
        '            /* '.PATCH_MARKER.': typing hanya melalui event Reverb. */',
        'marker typing V1',
    );

    $messageTimer = <<<'JS'
                /* INTERNAL_CHAT_WEBSOCKET_REVERB_V1 */
                window.crmChatPollMessages = pollMessages;

                window.setInterval(
                    () => {
                        if (window.crmInternalChatRealtime?.shouldFallbackPoll?.() ?? true) {
                            pollMessages();
                        }
                    },
                    window.crmInternalChatRealtime?.fallbackPollMs || 30000
                );
JS;
    $chat = wsOnlyReplaceOnce(
        $chat,
        $messageTimer,
        "                /* ".PATCH_MARKER." */\n                window.crmChatSyncMessages = pollMessages;",
        'timer pesan fallback',
    );

    $typingUrlDefinition = <<<'JS'
            const typingStatusUrl =
                chatRoot
                    ? String(
                        chatRoot.dataset.typingStatusUrl
                        || ''
                    )
                    : '';

JS;
    $chat = wsOnlyReplaceOnce(
        $chat,
        $typingUrlDefinition,
        "            /* Status typing diterima melalui WebSocket. */\n\n",
        'URL polling typing',
    );

    $typingStart = <<<'JS'
            if (
                typingStatusUrl
                && typingIndicator
                && ! window.__crmV322TypingPoll
            ) {
JS;
    $modalAnchor = <<<'JS'
            /*
            |--------------------------------------------------------------------------
            | Modal backdrop close
JS;
    $chat = wsOnlyReplaceBetween(
        $chat,
        $typingStart,
        $modalAnchor,
        "            /* ".PATCH_MARKER.": tidak ada polling typing. */\n\n",
        'fallback typing V3.2.2',
    );

    $sidebarTimer = <<<'JS'
            window.setInterval(
                () => {
                    if (window.crmInternalChatRealtime?.shouldFallbackPoll?.() ?? true) {
                        refreshSidebar();
                    }
                },
                window.crmInternalChatRealtime?.fallbackPollMs || 30000
            );
JS;
    $chat = wsOnlyReplaceOnce(
        $chat,
        $sidebarTimer,
        '            /* '.PATCH_MARKER.': sidebar hanya resync dari event/reconnect. */',
        'timer sidebar fallback',
    );
    wsOnlyWrite($chatPath, $chat);
    wsOnlyLine('[PATCH] packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php');

    $widgetPath = $root.DIRECTORY_SEPARATOR.wsOnlyPath('packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php');
    $widget = wsOnlyRead($widgetPath);
    $widget = wsOnlyReplaceOnce(
        $widget,
        "{{-- INTERNAL_CHAT_WEBSOCKET_REVERB_V1 --}}\n",
        "{{-- INTERNAL_CHAT_WEBSOCKET_REVERB_V1 --}}\n{{-- ".PATCH_MARKER." --}}\n",
        'marker widget V1',
    );
    $widget = str_replace(
        '// Fallback polling can safely claim the notification later.',
        '// Reconnect resync will claim the notification later.',
        $widget,
    );
    $widgetTimer = <<<'JS'
    window.setInterval(
        () => {
            if (window.crmInternalChatRealtime?.shouldFallbackPoll?.() ?? true) {
                poll();
            }
        },
        window.crmInternalChatRealtime?.fallbackPollMs || 30000
    );
JS;
    $widgetReconnect = <<<'JS'
    /* INTERNAL_CHAT_WEBSOCKET_ONLY_V2: satu kali resync saat connect/reconnect. */
    window.addEventListener('crm:realtime-status', (event) => {
        if (event.detail?.status === 'connected') {
            poll();
        }
    });
JS;
    $widget = wsOnlyReplaceOnce($widget, $widgetTimer, $widgetReconnect, 'timer widget fallback');
    wsOnlyWrite($widgetPath, $widget);
    wsOnlyLine('[PATCH] packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php');

    $unreadPath = $root.DIRECTORY_SEPARATOR.wsOnlyPath('packages/Webkul/Admin/src/Resources/views/internal-communication/chat-unread-badge.blade.php');
    $unread = wsOnlyRead($unreadPath);
    $unreadTimer = <<<'JS'
        window.setInterval(
            () => {
                if (window.crmInternalChatRealtime?.shouldFallbackPoll?.() ?? true) {
                    pollUnread();
                }
            },
            window.crmInternalChatRealtime?.fallbackPollMs || 30000
        );
JS;
    $unreadReconnect = <<<'JS'
        /* INTERNAL_CHAT_WEBSOCKET_ONLY_V2: satu kali resync saat connect/reconnect. */
        window.addEventListener('crm:realtime-status', (event) => {
            if (event.detail?.status === 'connected') {
                pollUnread();
            }
        });
JS;
    $unread = wsOnlyReplaceOnce($unread, $unreadTimer, $unreadReconnect, 'timer unread fallback');
    wsOnlyWrite($unreadPath, $unread);
    wsOnlyLine('[PATCH] packages/Webkul/Admin/src/Resources/views/internal-communication/chat-unread-badge.blade.php');

    $servicePath = $root.DIRECTORY_SEPARATOR.wsOnlyPath('packages/Webkul/Admin/src/Services/InternalChatRealtimeService.php');
    $service = wsOnlyRead($servicePath);
    $service = str_replace(
        'Internal Chat realtime broadcast failed; HTTP fallback remains active.',
        'Internal Chat realtime broadcast failed; clients will resync after WebSocket reconnect.',
        $service,
    );
    wsOnlyWrite($servicePath, $service);
    wsOnlyLine('[PATCH] packages/Webkul/Admin/src/Services/InternalChatRealtimeService.php');

    $envPath = $root.DIRECTORY_SEPARATOR.'.env.example';
    $env = wsOnlyRead($envPath);
    $env = str_replace(
        "CRM_INTERNAL_CHAT_FALLBACK_POLL_MS=30000\n",
        "# Internal Chat V2 memakai WebSocket tanpa polling fallback.\n",
        $env,
    );
    wsOnlyWrite($envPath, $env);
    wsOnlyLine('[PATCH] .env.example');

    foreach ([
        'packages/Webkul/Admin/src/Config/internal_chat_realtime.php',
        'packages/Webkul/Admin/src/Events/InternalChatRealtimeEvent.php',
        'packages/Webkul/Admin/src/Services/InternalChatRealtimeService.php',
    ] as $relative) {
        $output = [];
        $status = 1;
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.DIRECTORY_SEPARATOR.wsOnlyPath($relative)).' 2>&1', $output, $status);

        if ($status !== 0) {
            wsOnlyFail('PHP lint gagal: '.$relative.PHP_EOL.implode(PHP_EOL, $output));
        }

        wsOnlyLine('[OK]    PHP lint '.$relative);
    }

    if (wsOnlyRun($root, ['artisan', 'view:clear']) !== 0 || wsOnlyRun($root, ['artisan', 'view:cache']) !== 0) {
        wsOnlyFail('Blade gagal dikompilasi.');
    }

    wsOnlyLine();
    wsOnlyLine('PATCH BERHASIL. Tidak ada migration atau perubahan data.');
    wsOnlyLine('Backup source: '.$backupRoot);
    wsOnlyLine('Lanjutkan satu per satu:');
    wsOnlyLine('cd packages\\Webkul\\Admin');
    wsOnlyLine('npm run build');
    wsOnlyLine('cd ..\\..\\..');
    wsOnlyLine('php artisan optimize:clear');
    wsOnlyLine('php tools/check_internal_chat_websocket_only_v2.php');
} catch (Throwable $exception) {
    if ($writesStarted && is_dir($backupRoot)) {
        try {
            wsOnlyRestore($root, $backupRoot, $manifest);
            wsOnlyLine('Semua file dipulihkan dari backup.');
        } catch (Throwable $restoreError) {
            wsOnlyLine('PERINGATAN: rollback otomatis gagal: '.$restoreError->getMessage());
        }
    }

    wsOnlyLine();
    wsOnlyLine('PATCH GAGAL: '.$exception->getMessage());
    exit(1);
}
