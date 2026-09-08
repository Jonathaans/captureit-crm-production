@php
    /* INTERNAL_CHAT_WEBSOCKET_ONLY_V2 */
    $crmRealtimeConnection = (string) config('broadcasting.default', 'null');
    $crmRealtimeOptions = (array) config('broadcasting.connections.'.$crmRealtimeConnection.'.options', []);
    $crmRealtimeEnabled = (bool) config('internal_chat_realtime.enabled', true)
        && in_array($crmRealtimeConnection, ['reverb', 'pusher'], true);
    $crmRealtimeConversationId = request()->routeIs('admin.internal-chat.index')
        ? request()->integer('conversation')
        : 0;
@endphp

<div
    id="crm-internal-chat-realtime-config"
    data-enabled="{{ $crmRealtimeEnabled ? '1' : '0' }}"
    data-user-id="{{ (int) auth()->guard('user')->id() }}"
    data-conversation-id="{{ (int) $crmRealtimeConversationId }}"
    data-app-key="{{ (string) config('broadcasting.connections.'.$crmRealtimeConnection.'.key', '') }}"
    data-host="{{ (string) ($crmRealtimeOptions['host'] ?? request()->getHost()) }}"
    data-port="{{ (int) ($crmRealtimeOptions['port'] ?? (request()->isSecure() ? 443 : 80)) }}"
    data-scheme="{{ (string) ($crmRealtimeOptions['scheme'] ?? (request()->isSecure() ? 'https' : 'http')) }}"
    data-auth-endpoint="{{ url('/broadcasting/auth') }}"
    data-csrf="{{ csrf_token() }}"
    data-status="starting"
    hidden
></div>
