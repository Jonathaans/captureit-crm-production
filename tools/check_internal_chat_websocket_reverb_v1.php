<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = 0;

echo "CHECK INTERNAL CHAT WEBSOCKET REVERB V1\n";
echo "========================================\n\n";

$check = static function (bool $ok, string $label) use (&$failures): void {
    echo ($ok ? '[OK]   ' : '[FAIL] ').$label.PHP_EOL;

    if (! $ok) {
        $failures++;
    }
};

$files = [
    'config/reverb.php',
    'routes/channels.php',
    'packages/Webkul/Admin/src/Config/internal_chat_realtime.php',
    'packages/Webkul/Admin/src/Events/InternalChatRealtimeEvent.php',
    'packages/Webkul/Admin/src/Services/InternalChatRealtimeService.php',
    'packages/Webkul/Admin/src/Resources/assets/js/internal-chat-realtime.js',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/realtime-config.blade.php',
    'packages/Webkul/Admin/src/Resources/views/internal-communication/realtime-chat-listeners.blade.php',
    'tests/Unit/InternalChatRealtimeEventTest.php',
];

foreach ($files as $file) {
    $check(is_file($root.'/'.$file), 'File tersedia: '.$file);
}

$read = static fn (string $file): string => is_file($root.'/'.$file)
    ? (string) file_get_contents($root.'/'.$file)
    : '';

$controller = $read('packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php');
$experience = $read('packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatExperienceController.php');
$workflow = $read('packages/Webkul/Admin/src/Services/WorkflowNotificationService.php');
$provider = $read('packages/Webkul/Admin/src/Providers/InternalCommunicationServiceProvider.php');
$broadcasting = $read('config/broadcasting.php');
$channels = $read('routes/channels.php');
$chat = $read('packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php');
$widget = $read('packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php');
$unread = $read('packages/Webkul/Admin/src/Resources/views/internal-communication/chat-unread-badge.blade.php');
$appJs = $read('packages/Webkul/Admin/src/Resources/assets/js/app.js');
$package = json_decode($read('packages/Webkul/Admin/package.json'), true);

$check(str_contains($controller, 'InternalChatRealtimeService::class'), 'Create/edit/delete/read memicu realtime invalidation');
$check(substr_count($controller, '->conversationChanged(') >= 3, 'Create, edit, delete, dan read memicu conversation event');
$check(str_contains($experience, '->typingChanged('), 'Typing memicu WebSocket event');
$check(str_contains($workflow, '->workflowNotificationCreated('), 'Workflow notification memicu user event');
$check(str_contains($provider, "mergeConfigFrom") && str_contains($provider, 'popup-ack'), 'Provider memuat config dan route popup acknowledgement');
$check(str_contains($chat, 'crmChatPollMessages') && str_contains($chat, 'shouldFallbackPoll'), 'Message polling hanya fallback');
$check(str_contains($chat, 'realtime-chat-listeners'), 'Listener conversation realtime terpasang');
$check(str_contains($widget, 'realtime-config') && str_contains($widget, 'crm:chat-user-state'), 'Widget menerima state realtime');
$check(str_contains($unread, 'crm:chat-user-state') && str_contains($unread, 'shouldFallbackPoll'), 'Unread badge menerima event dan fallback');
$check(str_contains($appJs, 'internal-chat-realtime'), 'Realtime client di-import oleh Admin bundle');
$check(isset($package['dependencies']['laravel-echo'], $package['dependencies']['pusher-js']), 'Dependency Echo dan Pusher tersedia');
$check(str_contains($broadcasting, "'reverb' => [") && str_contains($broadcasting, 'BROADCAST_CONNECTION'), 'Broadcast connection Reverb tersedia');
$check(str_contains($channels, "internal-chat.user.{userId}") && str_contains($channels, "internal-chat.conversation.{conversationId}"), 'Private user dan conversation channels tersedia');

$forbiddenIntervals = [
    'window.setInterval(\n                    pollMessages,\n                    5000',
    'window.setInterval(\n                pollTyping,\n                2000',
    'window.setInterval(\n                refreshSidebar,\n                4000',
    'window.setInterval(\n            pollUnread,\n            5000',
    'window.setInterval(\n        poll,\n        12000',
];

foreach ($forbiddenIntervals as $marker) {
    $check(
        ! str_contains($chat.$widget.$unread, $marker),
        'Polling konstan dilepas: '.str_replace("\n", ' ', $marker),
    );
}

$phpFiles = array_filter($files, static fn (string $file): bool => str_ends_with($file, '.php'));

foreach ($phpFiles as $file) {
    $output = [];
    $status = 1;
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$file).' 2>&1', $output, $status);
    $check($status === 0, 'PHP lint: '.$file);
}

if (is_file($root.'/artisan') && is_file($root.'/vendor/autoload.php')) {
    require_once $root.'/vendor/autoload.php';

    $commands = [
        'route:list --path=broadcasting/auth',
        'view:cache',
    ];

    foreach ($commands as $command) {
        $output = [];
        $status = 1;
        exec(
            escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' '.$command.' 2>&1',
            $output,
            $status,
        );
        $check($status === 0, 'Artisan: '.$command);
    }

    $reverbInstalled = class_exists('Laravel\\Reverb\\ReverbServiceProvider');
    $check($reverbInstalled, 'Package laravel/reverb terpasang');
}

echo PHP_EOL;

if ($failures > 0) {
    echo '[FAIL] Checker menemukan '.$failures.' masalah.'.PHP_EOL;
    exit(1);
}

echo '[PASS] Internal Chat WebSocket Reverb V1 lengkap.'.PHP_EOL;
