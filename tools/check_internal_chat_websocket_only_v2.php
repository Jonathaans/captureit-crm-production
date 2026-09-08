<?php

declare(strict_types=1);

const CHECK_TITLE = 'CHECK INTERNAL CHAT WEBSOCKET-ONLY V2.1';
const CHECK_MARKER = 'INTERNAL_CHAT_WEBSOCKET_ONLY_V2';

$root = dirname(__DIR__);
$failures = 0;

function wsCheckLine(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function wsCheckResult(bool $ok, string $label, string $detail = ''): void
{
    global $failures;

    if (! $ok) {
        $failures++;
    }

    wsCheckLine(($ok ? '[OK]   ' : '[FAIL] ').$label.($detail !== '' ? ' — '.$detail : ''));
}

function wsCheckRead(string $root, string $relative): string
{
    $path = $root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

    if (! is_file($path)) {
        return '';
    }

    return str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($path));
}

function wsCheckRun(string $root, array $arguments): array
{
    $command = escapeshellarg(PHP_BINARY);

    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg((string) $argument);
    }

    $previous = getcwd();
    chdir($root);
    exec($command.' 2>&1', $output, $exitCode);

    if ($previous !== false) {
        chdir($previous);
    }

    return [(int) $exitCode, implode(PHP_EOL, $output)];
}

wsCheckLine(CHECK_TITLE);
wsCheckLine(str_repeat('=', strlen(CHECK_TITLE)));
wsCheckLine();

$files = [
    'packages/Webkul/Admin/src/Config/internal_chat_realtime.php',
    'packages/Webkul/Admin/src/Events/InternalChatRealtimeEvent.php',
    'packages/Webkul/Admin/src/Services/InternalChatRealtimeService.php',
    'packages/Webkul/Admin/src/Resources/assets/js/internal-chat-realtime.js',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/realtime-config.blade.php',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/realtime-chat-listeners.blade.php',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/chat-unread-badge.blade.php',
    'docs/INTERNAL_CHAT_WEBSOCKET_ONLY_V2.md',
];

foreach ($files as $relative) {
    wsCheckResult(wsCheckRead($root, $relative) !== '', 'File tersedia: '.$relative);
}

$js = wsCheckRead($root, 'packages/Webkul/Admin/src/Resources/assets/js/internal-chat-realtime.js');
$config = wsCheckRead($root, 'packages/Webkul/Admin/src/Config/internal_chat_realtime.php');
$realtimeConfig = wsCheckRead($root, 'packages/Webkul/Admin/src/Resources/views/internal-communication/realtime-config.blade.php');
$listeners = wsCheckRead($root, 'packages/Webkul/Admin/src/Resources/views/internal-communication/realtime-chat-listeners.blade.php');
$chat = wsCheckRead($root, 'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php');
$widget = wsCheckRead($root, 'packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php');
$unread = wsCheckRead($root, 'packages/Webkul/Admin/src/Resources/views/internal-communication/chat-unread-badge.blade.php');
$event = wsCheckRead($root, 'packages/Webkul/Admin/src/Events/InternalChatRealtimeEvent.php');
$service = wsCheckRead($root, 'packages/Webkul/Admin/src/Services/InternalChatRealtimeService.php');

foreach ([
    'Realtime JS' => $js,
    'Realtime config PHP' => $config,
    'Realtime config Blade' => $realtimeConfig,
    'Realtime listeners' => $listeners,
    'Chat view' => $chat,
    'Widget view' => $widget,
    'Unread badge view' => $unread,
] as $label => $content) {
    wsCheckResult(str_contains($content, CHECK_MARKER), $label.' memakai marker V2');
}

wsCheckResult(
    str_contains($js, 'statusBadge.textContent = connected')
        && str_contains($js, ': "Offline"')
        && ! str_contains($js, 'Fallback'),
    'Status UI memakai Live/Connecting/Offline tanpa Fallback',
);
wsCheckResult(
    str_contains($event, 'implements ShouldBroadcastNow'),
    'Event chat dikirim langsung melalui Reverb tanpa menunggu queue',
);
wsCheckResult(
    str_contains($listeners, "window.addEventListener('crm:chat-conversation'")
        && str_contains($listeners, 'window.crmChatSyncMessages?.()')
        && str_contains($listeners, "event.detail?.status === 'connected'"),
    'Pesan resync hanya dari event dan reconnect',
);
wsCheckResult(
    str_contains($widget, "window.addEventListener('crm:realtime-status'")
        && str_contains($unread, "window.addEventListener('crm:realtime-status'"),
    'Widget dan unread melakukan satu kali resync saat reconnect',
);
wsCheckResult(
    str_contains($service, 'clients will resync after WebSocket reconnect')
        && ! str_contains($service, 'HTTP fallback remains active'),
    'Backend tidak lagi mengklaim HTTP fallback aktif',
);

$combined = implode("\n", [$js, $config, $realtimeConfig, $listeners, $chat, $widget, $unread]);

foreach ([
    'shouldFallbackPoll' => 'API fallback polling lama',
    'fallbackPollMs' => 'timer fallback lama',
    'data-fallback-poll-ms' => 'konfigurasi fallback DOM',
    'window.crmChatPollMessages' => 'global message polling lama',
    'window.__crmV322TypingPoll' => 'typing polling lama',
    "window.setInterval(\n                    pollMessages" => 'interval pesan 5 detik',
    "window.setInterval(\n                refreshSidebar" => 'interval sidebar 4 detik',
    "window.setInterval(\n        poll,\n        12000" => 'interval widget 12 detik',
    "window.setInterval(\n            pollUnread" => 'interval unread 5 detik',
] as $needle => $label) {
    wsCheckResult(! str_contains($combined, $needle), 'Tidak ada '.$label);
}

foreach ([
    'packages/Webkul/Admin/src/Config/internal_chat_realtime.php',
    'packages/Webkul/Admin/src/Events/InternalChatRealtimeEvent.php',
    'packages/Webkul/Admin/src/Services/InternalChatRealtimeService.php',
] as $relative) {
    [$exitCode, $output] = wsCheckRun($root, ['-l', $relative]);
    wsCheckResult($exitCode === 0, 'PHP lint: '.$relative, $exitCode === 0 ? '' : $output);
}

[$routeCode, $routeOutput] = wsCheckRun($root, ['artisan', 'route:list', '--path=broadcasting/auth']);
wsCheckResult($routeCode === 0 && str_contains($routeOutput, 'broadcasting/auth'), 'Route private channel authentication tersedia');

[$viewCode, $viewOutput] = wsCheckRun($root, ['artisan', 'view:cache']);
wsCheckResult($viewCode === 0, 'Semua Blade berhasil dikompilasi', $viewCode === 0 ? '' : $viewOutput);

$manifest = $root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json';
$jsSource = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, 'packages/Webkul/Admin/src/Resources/assets/js/internal-chat-realtime.js');
$assetFresh = is_file($manifest)
    && is_file($jsSource)
    && (int) filemtime($manifest) >= (int) filemtime($jsSource);
wsCheckResult($assetFresh, 'Admin frontend sudah dibuild setelah hotfix', $assetFresh ? '' : 'jalankan npm run build di packages/Webkul/Admin');

wsCheckLine();

if ($failures > 0) {
    wsCheckLine('[FAIL] Checker menemukan '.$failures.' masalah.');
    exit(1);
}

wsCheckLine('[PASS] Internal Chat sekarang WebSocket-only tanpa interval polling fallback.');
