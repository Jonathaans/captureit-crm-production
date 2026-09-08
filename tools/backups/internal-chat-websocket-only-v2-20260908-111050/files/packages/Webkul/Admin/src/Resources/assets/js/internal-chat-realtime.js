import Echo from "laravel-echo";
import Pusher from "pusher-js";

const dispatch = (name, detail = {}) => {
    window.dispatchEvent(new CustomEvent(name, { detail }));
};

const boot = () => {
    const config = document.getElementById("crm-internal-chat-realtime-config");

    if (!config || window.crmInternalChatRealtime) {
        return;
    }

    const enabled = config.dataset.enabled === "1";
    const userId = Number(config.dataset.userId || 0);
    const conversationId = Number(config.dataset.conversationId || 0);
    const appKey = String(config.dataset.appKey || "");
    const host = String(config.dataset.host || window.location.hostname);
    const scheme = String(
        config.dataset.scheme || (window.location.protocol === "https:" ? "https" : "http"),
    ).toLowerCase();
    const secure = scheme === "https" || window.location.protocol === "https:";
    const port = Number(config.dataset.port || (secure ? 443 : 80));
    const fallbackPollMs = Math.max(15000, Number(config.dataset.fallbackPollMs || 30000));

    const state = {
        echo: null,
        status: "starting",
        fallbackPollMs,
        isConnected() {
            return this.status === "connected";
        },
        shouldFallbackPoll() {
            return !this.isConnected();
        },
    };

    window.crmInternalChatRealtime = state;

    const setStatus = (status, reason = "") => {
        state.status = status;
        config.dataset.status = status;

        const statusBadge = document.getElementById("crm-chat-realtime-status");

        if (statusBadge) {
            const connected = status === "connected";
            statusBadge.textContent = connected ? "Live" : "Fallback";
            statusBadge.title = connected
                ? "WebSocket tersambung"
                : `WebSocket ${status}${reason ? `: ${reason}` : ""}; HTTP fallback aktif`;
            statusBadge.style.background = connected ? "#dcfce7" : "#fef3c7";
            statusBadge.style.color = connected ? "#166534" : "#92400e";
        }

        dispatch("crm:realtime-status", { status, reason });
    };

    if (!enabled) {
        setStatus("disabled", "dinonaktifkan melalui konfigurasi");
        return;
    }

    if (userId < 1 || appKey === "" || host === "") {
        setStatus("unavailable", "konfigurasi belum lengkap");
        return;
    }

    const seenEventIds = new Set();

    const remember = (eventId) => {
        if (!eventId || seenEventIds.has(eventId)) {
            return false;
        }

        seenEventIds.add(eventId);

        if (seenEventIds.size > 250) {
            const oldest = seenEventIds.values().next().value;
            seenEventIds.delete(oldest);
        }

        return true;
    };

    const handleRealtimeEvent = (event) => {
        const eventId = String(event?.event_id || "");

        if (eventId !== "" && !remember(eventId)) {
            return;
        }

        const detail = {
            event_id: eventId,
            kind: String(event?.kind || ""),
            payload: event?.payload || {},
            emitted_at: event?.emitted_at || null,
        };

        dispatch("crm:chat-realtime", detail);

        if (detail.kind === "conversation.typing") {
            dispatch("crm:chat-typing", detail);
        } else if (detail.kind === "conversation.changed") {
            dispatch("crm:chat-conversation", detail);
        } else if (detail.kind === "user.state") {
            dispatch("crm:chat-user-state", detail);
        }
    };

    try {
        window.Pusher = Pusher;

        const echo = new Echo({
            broadcaster: "reverb",
            key: appKey,
            wsHost: host,
            wsPort: port,
            wssPort: port,
            forceTLS: secure,
            enabledTransports: ["ws", "wss"],
            disableStats: true,
            authEndpoint: String(config.dataset.authEndpoint || "/broadcasting/auth"),
            auth: {
                headers: {
                    "X-CSRF-TOKEN": String(config.dataset.csrf || ""),
                    "X-Requested-With": "XMLHttpRequest",
                },
            },
        });

        state.echo = echo;
        window.Echo = echo;

        const connection = echo.connector?.pusher?.connection;

        connection?.bind("connected", () => setStatus("connected"));
        connection?.bind("connecting", () => setStatus("connecting"));
        connection?.bind("disconnected", () => setStatus("disconnected"));
        connection?.bind("unavailable", () => setStatus("unavailable"));
        connection?.bind("failed", () => setStatus("failed"));
        connection?.bind("error", (error) => {
            const reason = String(error?.error?.data?.message || error?.message || "connection error");
            setStatus("error", reason.slice(0, 160));
        });

        echo.private(`internal-chat.user.${userId}`)
            .listen(".crm.internal-chat", handleRealtimeEvent);

        if (conversationId > 0) {
            echo.private(`internal-chat.conversation.${conversationId}`)
                .listen(".crm.internal-chat", handleRealtimeEvent);
        }
    } catch (error) {
        setStatus("error", String(error?.message || error).slice(0, 160));
    }
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
} else {
    boot();
}
