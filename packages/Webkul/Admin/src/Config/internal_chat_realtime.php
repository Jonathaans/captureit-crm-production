<?php

return [
    'enabled' => env('CRM_INTERNAL_CHAT_REALTIME_ENABLED', true),

    /*
     * When Reverb is unavailable the existing HTTP sync endpoints remain a
     * recovery path. The browser only calls them while WebSocket is offline.
     */
    'fallback_poll_ms' => max(
        15_000,
        (int) env('CRM_INTERNAL_CHAT_FALLBACK_POLL_MS', 30_000),
    ),
];
