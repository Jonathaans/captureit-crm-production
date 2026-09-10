@if ($conversation)
    <script>
/* CRM_CHAT_LIVE_SCROLL_V2: use live DOM after the layout mounts Vue on load. */
(() => {
    const boot = () => window.requestAnimationFrame(() => {

        /* INTERNAL_CHAT_WEBSOCKET_ONLY_V2 */
        (() => {
            const conversationId = {{ (int) $conversation->id }};
            const currentUserId = {{ (int) auth()->guard('user')->id() }};
            const typingIndicator = document.getElementById('crm-chat-typing-indicator');
            let typingTimer = null;

            const syncConversation = (event) => {
                const payload = event.detail?.payload || {};

                if (Number(payload.conversation_id || 0) !== conversationId) {
                    return;
                }

                window.crmChatSyncMessages?.();
                window.crmChatV33RefreshSidebar?.();
            };

            const renderTyping = (event) => {
                const payload = event.detail?.payload || {};

                if (
                    Number(payload.conversation_id || 0) !== conversationId
                    || Number(payload.user_id || 0) === currentUserId
                    || !typingIndicator
                ) {
                    return;
                }

                window.clearTimeout(typingTimer);

                if (payload.typing) {
                    typingIndicator.textContent = `${payload.name || 'User'} sedang mengetik...`;
                    typingIndicator.classList.remove('hidden');
                    typingIndicator.style.display = 'block';

                    typingTimer = window.setTimeout(() => {
                        typingIndicator.classList.add('hidden');
                        typingIndicator.style.display = 'none';
                    }, 6500);
                } else {
                    typingIndicator.classList.add('hidden');
                    typingIndicator.style.display = 'none';
                }
            };

            window.addEventListener('crm:chat-conversation', syncConversation);
            window.addEventListener('crm:chat-typing', renderTyping);
            window.addEventListener('crm:chat-user-state', () => {
                window.crmChatV33RefreshSidebar?.();
            });

            /* Satu kali resync setelah koneksi/reconnect; tidak ada timer polling. */
            // A fast WebSocket may connect before post-mount listeners are ready.
            if (window.crmInternalChatRealtime?.isConnected()) {
                window.crmChatSyncMessages?.();
                window.crmChatV33RefreshSidebar?.();
            }
            window.addEventListener('crm:realtime-status', (event) => {
                if (event.detail?.status === 'connected') {
                    window.crmChatSyncMessages?.();
                    window.crmChatV33RefreshSidebar?.();
                }
            });
        })();
    
    });
    if (document.readyState === "complete") {
        boot();
    } else {
        window.addEventListener("load", boot, { once: true });
    }
})();
</script>
@endif
