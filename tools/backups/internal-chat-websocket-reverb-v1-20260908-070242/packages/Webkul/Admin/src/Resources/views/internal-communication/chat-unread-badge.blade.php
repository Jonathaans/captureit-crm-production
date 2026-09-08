@php
    $internalChatUrl =
        route(
            'admin.internal-chat.index'
        );

    $internalChatUnreadUrl =
        route(
            'admin.internal-chat.unread-summary'
        );
@endphp

{{-- INTERNAL CHAT V3.3.11 GLOBAL UNREAD TARGET ISOLATION --}}
<script>
    (() => {
        const chatUrl =
            @json(
                $internalChatUrl
            );

        const unreadUrl =
            @json(
                $internalChatUnreadUrl
            );

        const normalizedPath = (
            value
        ) => {
            try {
                const parsed =
                    new URL(
                        value,
                        window.location.origin
                    );

                return parsed.pathname
                    .replace(
                        /\/+$/,
                        ''
                    ) || '/';
            } catch (error) {
                return '';
            }
        };

        const chatPath =
            normalizedPath(
                chatUrl
            );

        /*
         * IMPORTANT:
         * Global unread belongs only to the global floating Chat launcher.
         * Conversation links such as:
         *   /admin/internal-chat?conversation=4
         * are NOT global targets.
         */
        const isExactChatLauncher = (
            node
        ) => {
            if (
                ! node
                || node.nodeType !== 1
                || node.closest(
                    '#crm-wa-conversation-list'
                )
                || node.closest(
                    '[data-crm-v3310-row]'
                )
            ) {
                return false;
            }

            const href =
                String(
                    node.getAttribute(
                        'href'
                    )
                    || ''
                ).trim();

            if (href === '') {
                return false;
            }

            try {
                const parsed =
                    new URL(
                        href,
                        window.location.origin
                    );

                return normalizedPath(
                    parsed.href
                ) === chatPath
                    && parsed.search === ''
                    && parsed.hash === '';
            } catch (error) {
                return false;
            }
        };

        const locateChatTargets = () => {
            const direct =
                Array.from(
                    document.querySelectorAll(
                        'a[href]'
                    )
                )
                    .filter(
                        isExactChatLauncher
                    );

            if (direct.length) {
                return direct;
            }

            /*
             * Fallback for a customized launcher rendered as a button.
             * Never inspect the conversation list itself.
             */
            return Array.from(
                document.querySelectorAll(
                    'button, [role="button"]'
                )
            )
                .filter(
                    (node) => {
                        if (
                            node.closest(
                                '#crm-wa-conversation-list'
                            )
                            || node.closest(
                                '[data-crm-v3310-row]'
                            )
                        ) {
                            return false;
                        }

                        return String(
                            node.textContent
                            || ''
                        )
                            .trim()
                            .toLowerCase()
                            === 'chat';
                    }
                );
        };

        const removeWrongRowBadges = () => {
            document
                .querySelectorAll(
                    '#crm-wa-conversation-list [data-global-chat-unread-badge], '
                    +'[data-crm-v3310-row] [data-global-chat-unread-badge]'
                )
                .forEach(
                    (badge) =>
                        badge.remove()
                );
        };

        const ensureBadge = (
            target
        ) => {
            let badge =
                target.querySelector(
                    ':scope > [data-global-chat-unread-badge]'
                );

            if (badge) {
                return badge;
            }

            target.style.position =
                target.style.position
                || 'relative';

            badge =
                document.createElement(
                    'span'
                );

            badge.dataset.globalChatUnreadBadge =
                '1';

            badge.dataset.globalChatUnreadOwner =
                'launcher';

            badge.style.position =
                'absolute';

            badge.style.top =
                '-7px';

            badge.style.right =
                '-7px';

            badge.style.minWidth =
                '20px';

            badge.style.height =
                '20px';

            badge.style.padding =
                '0 5px';

            badge.style.borderRadius =
                '9999px';

            badge.style.background =
                '#dc2626';

            badge.style.color =
                '#ffffff';

            badge.style.fontSize =
                '11px';

            badge.style.fontWeight =
                '700';

            badge.style.lineHeight =
                '20px';

            badge.style.textAlign =
                'center';

            badge.style.boxShadow =
                '0 0 0 2px #ffffff';

            badge.style.display =
                'none';

            badge.style.zIndex =
                '3';

            target.appendChild(
                badge
            );

            return badge;
        };

        const renderCount = (
            count
        ) => {
            /*
             * Defensive cleanup for DOM left by the old V3.1 selector.
             */
            removeWrongRowBadges();

            locateChatTargets()
                .forEach(
                    (target) => {
                        const badge =
                            ensureBadge(
                                target
                            );

                        if (count > 0) {
                            badge.textContent =
                                count > 99
                                    ? '99+'
                                    : String(
                                        count
                                    );

                            badge.style.display =
                                'inline-block';
                        } else {
                            badge.style.display =
                                'none';
                        }
                    }
                );
        };

        const pollUnread =
            async () => {
                try {
                    const response =
                        await fetch(
                            unreadUrl,
                            {
                                headers: {
                                    'Accept':
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                credentials:
                                    'same-origin',

                                cache:
                                    'no-store',
                            }
                        );

                    if (! response.ok) {
                        return;
                    }

                    const data =
                        await response.json();

                    renderCount(
                        Math.max(
                            0,
                            Number(
                                data.total
                                || 0
                            )
                        )
                    );
                } catch (error) {
                    // Badge polling must never break the CRM page.
                }
            };

        removeWrongRowBadges();

        pollUnread();

        window.setInterval(
            pollUnread,
            5000
        );
    })();
</script>
