{{-- INTERNAL_CHAT_WEBSOCKET_REVERB_V1 --}}
{{-- INTERNAL_CHAT_WEBSOCKET_ONLY_V2 --}}
@include('admin::internal-communication.realtime-config')
<!-- CRM_INTERNAL_COMMUNICATION_WIDGET -->
{{-- INTERNAL_COMMUNICATION_FLOATING_LAYER_FIX_V1 --}}
<style>
    #crm-comm-floating {
        position: fixed;
        right: 22px;
        bottom: 22px;
        z-index: 10000;
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .crm-comm-fab {
        position: relative;
        min-width: 48px;
        height: 48px;
        border: 0;
        border-radius: 9999px;
        background: #111827;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 15px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        box-shadow: 0 8px 24px rgba(0,0,0,.18);
        cursor: pointer;
    }

    .crm-comm-fab:hover {
        opacity: .92;
    }

    .crm-comm-badge {
        position: absolute;
        right: -3px;
        top: -5px;
        display: none;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        border-radius: 9999px;
        background: #dc2626;
        color: #ffffff;
        border: 2px solid #ffffff;
        font-size: 10px;
        line-height: 16px;
        text-align: center;
    }

    #crm-comm-toasts {
        position: fixed;
        right: 22px;
        top: 82px;
        z-index: 10000;
        width: min(380px, calc(100vw - 30px));
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .crm-comm-toast {
        background: #ffffff;
        color: #111827;
        border: 1px solid #e5e7eb;
        border-left: 4px solid #2563eb;
        border-radius: 12px;
        padding: 14px;
        box-shadow: 0 14px 35px rgba(0,0,0,.18);
    }

    .crm-comm-toast-title {
        font-size: 14px;
        font-weight: 800;
        margin-bottom: 5px;
    }

    .crm-comm-toast-message {
        font-size: 12px;
        color: #4b5563;
        line-height: 1.45;
        margin-bottom: 10px;
    }

    .crm-comm-toast-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .crm-comm-toast-open,
    .crm-comm-toast-close {
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #111827;
        border-radius: 7px;
        padding: 6px 10px;
        text-decoration: none;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
    }

    .crm-comm-toast-open {
        background: #111827;
        color: #ffffff;
        border-color: #111827;
    }

    @media (max-width: 720px) {
        #crm-comm-floating {
            right: 12px;
            bottom: 12px;
        }

        .crm-comm-fab {
            height: 44px;
            min-width: 44px;
            padding: 0 12px;
        }

        #crm-comm-toasts {
            right: 12px;
            top: 70px;
        }
    }
</style>

<div id="crm-comm-toasts"></div>

<div id="crm-comm-floating">
    <a
        href="{{ route('admin.internal-notifications.index') }}"
        class="crm-comm-fab"
        title="Notifications"
        aria-label="Notifications"
    >
        🔔
        <span
            id="crm-notification-count"
            class="crm-comm-badge"
        >0</span>
    </a>

    <a
        href="{{ route('admin.internal-chat.index') }}"
        class="crm-comm-fab"
        title="Internal Chat"
        aria-label="Internal Chat"
    >
        💬 Chat
        <span
            id="crm-chat-count"
            class="crm-comm-badge"
        >0</span>
    </a>
</div>

<script>
(() => {
    const pollUrl = @json(route('admin.internal-notifications.poll'));
    const toastRoot = document.getElementById('crm-comm-toasts');
    const notificationBadge = document.getElementById('crm-notification-count');
    const chatBadge = document.getElementById('crm-chat-count');

    const setBadge = (element, count) => {
        const value = Number(count || 0);

        element.textContent = value > 99 ? '99+' : String(value);
        element.style.display = value > 0 ? 'inline-block' : 'none';
    };

    const showToast = (notification) => {
        const toast = document.createElement('div');
        toast.className = 'crm-comm-toast';

        const title = document.createElement('div');
        title.className = 'crm-comm-toast-title';
        title.textContent = notification.title || 'Notification';

        const message = document.createElement('div');
        message.className = 'crm-comm-toast-message';
        message.textContent = notification.message || '';

        const actions = document.createElement('div');
        actions.className = 'crm-comm-toast-actions';

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'crm-comm-toast-close';
        close.textContent = 'Close';
        close.addEventListener('click', () => toast.remove());

        const open = document.createElement('a');
        open.className = 'crm-comm-toast-open';
        open.href = notification.open_url;
        open.textContent = 'Open';

        actions.append(close, open);
        toast.append(title, message, actions);
        toastRoot.appendChild(toast);

        /*
         * Optional browser desktop notification.
         * We never auto-request permission. User can enable it from the
         * Notification Center.
         */
        if (
            'Notification' in window
            && Notification.permission === 'granted'
        ) {
            try {
                const desktop = new Notification(
                    notification.title || 'CRM Notification',
                    {
                        body: notification.message || '',
                    }
                );

                desktop.onclick = () => {
                    window.focus();
                    window.location.href = notification.open_url;
                };
            } catch (error) {
                // In-app toast remains the source of truth.
            }
        }
    };

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
            // Reconnect resync will claim the notification later.
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
    const poll = async () => {
        try {
            const response = await fetch(
                pollUrl,
                {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                }
            );

            if (! response.ok) {
                return;
            }

            const data = await response.json();

            setBadge(
                notificationBadge,
                data.notification_unread
            );

            setBadge(
                chatBadge,
                data.chat_unread
            );

            (data.notifications || []).forEach(
                showToast
            );
        } catch (error) {
            /*
             * Silent by design. A temporary network interruption should not
             * create a second popup whose job is to complain about popups.
             */
        }
    };

    window.setTimeout(
        poll,
        1500
    );

    /* INTERNAL_CHAT_WEBSOCKET_ONLY_V2: satu kali resync saat connect/reconnect. */
    window.addEventListener('crm:realtime-status', (event) => {
        if (event.detail?.status === 'connected') {
            poll();
        }
    });
})();
</script>

@include('admin::internal-communication.chat-unread-badge')


{{-- CRM_CHAT_UI_PERFORMANCE_V1: only the activity-aware presence sender remains. --}}


{{-- INTERNAL CHAT V3.3.2 ACTIVITY PRESENCE --}}
<div
    id="crm-global-presence-v332"
    data-heartbeat-url="{{ route('admin.internal-chat.presence.heartbeat') }}"
    data-csrf="{{ csrf_token() }}"
    style="display:none;"
></div>

<script>
    (() => {
        if (window.__crmActivityPresenceV332) {
            return;
        }

        window.__crmActivityPresenceV332 =
            true;

        const config =
            document.getElementById(
                'crm-global-presence-v332'
            );

        if (! config) {
            return;
        }

        const url =
            String(
                config.dataset.heartbeatUrl
                || ''
            );

        const csrf =
            String(
                config.dataset.csrf
                || ''
            );

        if (! url) {
            return;
        }

        let lastActivityAt =
            Date.now();

        let lastSentAt =
            0;

        /* CRM_CHAT_UI_PERFORMANCE_V1: never overlap presence requests. */
        let presenceInFlight = false;

        const markActivity =
            () => {
                lastActivityAt =
                    Date.now();

                /*
                 * Don't POST on every mousemove. Human beings generate enough
                 * events already.
                 */
                if (
                    Date.now()
                    - lastSentAt
                    > 5000
                ) {
                    sendPresence();
                }
            };

        const isInChat =
            () =>
                window.location.pathname
                    .toLowerCase()
                    .includes(
                        '/admin/internal-chat'
                    );

        const sendPresence =
            async () => {
                if (
                    document.visibilityState
                    === 'hidden'
                    || presenceInFlight
                ) {
                    return;
                }

                const idleSeconds =
                    Math.max(
                        0,
                        Math.floor(
                            (
                                Date.now()
                                - lastActivityAt
                            )
                            / 1000
                        )
                    );

                lastSentAt =
                    Date.now();

                presenceInFlight = true;

                try {
                    await fetch(
                        url,
                        {
                            method:
                                'POST',

                            headers: {
                                'Accept':
                                    'application/json',

                                'Content-Type':
                                    'application/json',

                                'X-CSRF-TOKEN':
                                    csrf,

                                'X-Requested-With':
                                    'XMLHttpRequest',
                            },

                            credentials:
                                'same-origin',

                            body:
                                JSON.stringify({
                                    idle_seconds:
                                        idleSeconds,

                                    in_chat:
                                        isInChat(),
                                }),
                        }
                    );
                } catch (error) {
                    // Presence must never break ordinary CRM work.
                } finally {
                    presenceInFlight = false;
                }
            };

        [
            'pointerdown',
            'keydown',
            'touchstart',
            'scroll',
        ].forEach(
            (eventName) => {
                window.addEventListener(
                    eventName,
                    markActivity,
                    {
                        passive:
                            true,
                    }
                );
            }
        );

        window.addEventListener(
            'focus',
            markActivity
        );

        document.addEventListener(
            'visibilitychange',
            () => {
                if (
                    document.visibilityState
                    === 'visible'
                ) {
                    markActivity();
                }
            }
        );

        sendPresence();

        window.setInterval(
            sendPresence,
            15000
        );
    })();
</script>
