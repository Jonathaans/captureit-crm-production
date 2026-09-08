<x-admin::layouts>
    <x-slot:title>
        Internal Chat
    </x-slot>

    @php
        $currentUser = auth()->guard('user')->user();

        $lastMessageId =
            $messages->isNotEmpty()
                ? $messages->max('id')
                : 0;

        $makeInitials = function ($value) {
            $parts = preg_split('/\s+/', trim((string) $value)) ?: [];
            $initials = '';

            foreach (array_slice($parts, 0, 2) as $part) {
                $initials .= strtoupper(mb_substr($part, 0, 1));
            }

            return $initials ?: 'U';
        };
    @endphp

    <div class="flex flex-col gap-4">
        <div class="rounded-xl border bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="text-xs font-bold uppercase text-gray-500">
                        Internal Communication
                    </div>

                    <h1 class="mt-1 text-2xl font-bold text-gray-900">
                        Internal Chat
                    </h1>

                    <p class="mt-1 text-sm text-gray-500">
                        Direct message antar akun CRM untuk koordinasi sales, admin, warehouse, dan operasional.
                    </p>
                </div>

                <a
                    href="{{ route('admin.internal-notifications.index') }}"
                    class="secondary-button"
                >
                    🔔 Notifications
                </a>
            </div>
        </div>

        <div class="flex min-h-0 overflow-hidden rounded-xl border bg-white shadow-sm" style="height:clamp(520px,calc(100dvh - 260px),760px);min-height:0;" data-chat-shell-newest-v1="1">
            {{-- Compact recent chat list. Full user directory moved to modal. --}}
            <aside class="{{ $conversation ? 'hidden lg:flex' : 'flex' }} min-h-0 w-full flex-col overflow-hidden border-r bg-white lg:w-96 lg:flex-none">
                <div class="border-b p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-lg font-bold text-gray-900">
                                Chats
                            </div>

                            <div class="mt-1 text-xs text-gray-500">
                                {{ $conversationList->count() }} conversation aktif
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                id="crm-new-chat-open"
                                class="primary-button"
                                onclick="return window.crmNewChatOpen(event);"
                            >
                                + New
                            </button>

                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-bold text-gray-700">
                                {{ $makeInitials($currentUser?->name ?: 'Me') }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center gap-2 rounded-xl border bg-gray-50 px-3 py-2">
                        <span class="text-gray-400">🔎</span>

                        <input
                            type="text"
                            id="crm-wa-chat-search"
                            class="w-full border-0 bg-transparent text-sm text-gray-900 outline-none"
                            placeholder="Cari conversation..."
                        >
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <div id="crm-wa-conversation-list">
                        @forelse ($conversationList as $row)
                            @php
                                $otherName = $row->other?->name ?: 'User';

                                $preview =
                                    $row->last_message?->body
                                        ? \Illuminate\Support\Str::limit(
                                            preg_replace(
                                                '/\s+/',
                                                ' ',
                                                trim((string) $row->last_message->body)
                                            ),
                                            46
                                        )
                                        : 'Belum ada pesan.';

                                $lastTime = '';

                                if (! empty($row->last_message?->created_at)) {
                                    try {
                                        $lastTime = \Illuminate\Support\Carbon::parse(
                                            $row->last_message->created_at
                                        )->format('H:i');
                                    } catch (\Throwable) {
                                        $lastTime = '';
                                    }
                                }

                                $unreadCount = (int) ($row->unread_count ?? 0);
                            @endphp

                            <a
                                href="{{ route('admin.internal-chat.index', ['conversation' => $row->id]) }}"
                                class="flex items-center gap-3 border-b px-4 py-3 no-underline hover:bg-gray-50 {{ $conversation?->id === $row->id ? 'bg-gray-100' : 'bg-white' }}"
                                data-conversation-search="{{ strtolower($otherName.' '.($row->other?->role_name ?: '').' '.$preview) }}"
                            >
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-700">
                                    {{ $makeInitials($otherName) }}
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="truncate text-sm font-bold text-gray-900">
                                            {{ $otherName }}
                                        </div>

                                        @if ($lastTime)
                                            <div class="shrink-0 text-xs {{ $unreadCount > 0 ? 'font-bold text-gray-900' : 'text-gray-400' }}">
                                                {{ $lastTime }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="mt-1 flex items-center gap-2">
                                        <div class="min-w-0 flex-1 truncate text-sm {{ $unreadCount > 0 ? 'font-semibold text-gray-900' : 'text-gray-500' }}">
                                            {{ $preview }}
                                        </div>

                                        @if ($unreadCount > 0)
                                            <div class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-gray-900 px-1.5 text-xs font-bold text-white">
                                                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="mt-1 truncate text-xs text-gray-400">
                                        {{ $row->other?->role_name ?: 'Internal User' }}
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="px-4 py-10 text-center">
                                <div class="text-2xl">💬</div>

                                <div class="mt-2 text-sm font-semibold text-gray-700">
                                    Belum ada conversation.
                                </div>

                                <button
                                    type="button"
                                    class="mt-4 primary-button"
                                    data-open-new-chat
                                    onclick="return window.crmNewChatOpen(event);"
                                >
                                    Start New Chat
                                </button>
                            </div>
                        @endforelse
                    </div>
                </div>
            </aside>

            <section class="{{ $conversation ? 'flex' : 'hidden lg:flex' }} min-h-0 w-full min-w-0 flex-1 flex-col overflow-hidden bg-gray-50">
                @if ($conversation)
                    <div class="flex items-center justify-between gap-4 border-b bg-white px-4 py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <a
                                href="{{ route('admin.internal-chat.index') }}"
                                class="secondary-button lg:hidden"
                            >
                                ‹
                            </a>

                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-200 text-sm font-bold text-gray-700">
                                {{ $makeInitials($activeOtherUser?->name ?: 'User') }}
                            </div>

                            <div class="min-w-0">
                                <div class="truncate text-base font-bold text-gray-900">
                                    {{ $activeOtherUser?->name ?: 'Internal Chat' }}
                                </div>

                                <div class="mt-0.5 truncate text-xs text-gray-500">
                                    {{ $activeOtherUser?->role_name ?: '-' }}
                                    · {{ $activeOtherUser?->email ?: '-' }}
                                </div>

                                <div
                                    id="crm-chat-typing-indicator"
                                    class="mt-1 hidden text-xs font-semibold text-green-600"
                                >
                                    sedang mengetik...
                                </div>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                type="button"
                                class="secondary-button"
                                onclick="return window.crmChatSearchOpen(event);"
                            >
                                🔎 Search
                            </button>

                            <div class="rounded-full border bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-500">
                                🔒 Private
                            </div>
                        </div>
                    </div>

                    <div
                        id="crm-chat-messages" class="flex min-h-0 flex-1 flex-col overflow-y-auto bg-gray-100 p-5"
                        data-last-id="{{ $lastMessageId }}"
                        data-current-user-id="{{ $currentUser->id }}"
                        data-read-up-to-id="{{ (int) $activeReadUpToId }}"
                        data-sync-at="{{ now()->format('Y-m-d H:i:s.u') }}"
                        data-action-base="{{ url('admin/internal-chat/'.$conversation->id.'/messages') }}"
                        data-search-url="{{ route('admin.internal-chat.search', $conversation->id) }}"
                        data-typing-url="{{ route('admin.internal-chat.typing', $conversation->id) }}"
                        data-typing-status-url="{{ route('admin.internal-chat.typing-status', $conversation->id) }}" style="min-height:0;overscroll-behavior:contain;scroll-behavior:auto;" data-chat-newest-scroll-v1="1">
                        <div
                            id="crm-chat-message-stack" class="mt-auto flex w-full shrink-0 flex-col" style="flex-shrink:0;" data-chat-newest-stack-v1="1">
<div class="mb-5 text-center">
                            <span class="rounded-full bg-white px-3 py-2 text-xs font-semibold text-gray-500 shadow-sm">
                                Direct Conversation
                            </span>
                        </div>

                        @foreach ($messages as $message)
                            @php
                                $isMine =
                                    (int) $message->user_id
                                    === (int) $currentUser->id;

                                $isRead =
                                    $isMine
                                    && (int) $message->id
                                        <= (int) $activeReadUpToId;
                            @endphp

                            <div
                                class="mb-3 flex {{ $isMine ? 'justify-end' : 'justify-start' }}"
                                data-message-id="{{ $message->id }}"
                            >
                                <div class="max-w-2xl">
                                    @if (! $isMine)
                                        <div class="mb-1 px-1 text-xs font-bold text-gray-500">
                                            {{ $senderNames[$message->user_id] ?? 'User' }}
                                        </div>
                                    @endif

                                    <div class="rounded-xl px-4 py-3 shadow-sm {{ $isMine ? 'bg-gray-900 text-white' : 'border bg-white text-gray-900' }}">
                                        @if ($message->reply_to_message_id)
                                            @php
                                                $replyMessage =
                                                    $replyMessages[
                                                        $message->reply_to_message_id
                                                    ]
                                                    ?? null;

                                                $replySenderName =
                                                    $replyMessage
                                                        ? (
                                                            $replySenderNames[
                                                                $replyMessage->user_id
                                                            ]
                                                            ?? 'User'
                                                        )
                                                        : 'User';

                                                $replyBody =
                                                    $replyMessage
                                                        ? (
                                                            $replyMessage->deleted_at
                                                                ? 'Pesan telah dihapus'
                                                                : (
                                                                    trim(
                                                                        (string) $replyMessage->body
                                                                    )
                                                                    ?: 'Attachment'
                                                                )
                                                        )
                                                        : 'Pesan tidak tersedia';
                                            @endphp

                                            <div class="mb-2 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-gray-700">
                                                <div class="text-xs font-bold">
                                                    {{ $replySenderName }}
                                                </div>

                                                <div class="mt-1 max-w-sm truncate text-xs">
                                                    {{ \Illuminate\Support\Str::limit($replyBody, 90) }}
                                                </div>
                                            </div>
                                        @endif

                                        @if ($message->body)
                                            <div class="whitespace-pre-wrap break-words text-sm">
                                                {{ $message->body }}
                                            </div>
                                        @endif

                                        @if ($message->attachments->isNotEmpty())
                                            <div class="mt-3 flex flex-col gap-2">
                                                @foreach ($message->attachments as $attachment)
                                                    <a
                                                        href="{{ route('admin.internal-chat.attachments.download', $attachment->id) }}"
                                                        data-attachment-download-url="{{ route('admin.internal-chat.attachments.download', $attachment->id) }}"
                                                        data-attachment-name="{{ $attachment->original_name }}"
                                                        data-attachment-preview-url="{{ route('admin.internal-chat.attachments.preview', $attachment->id) }}"
                                                        onclick="return window.crmChatPreviewAttachment(this, event);"
                                                        class="rounded-lg border px-3 py-2 text-xs font-semibold no-underline {{ $isMine ? 'border-gray-600 text-white' : 'bg-gray-50 text-gray-700' }}"
                                                    >
                                                        📎 {{ $attachment->original_name }}
                                                        · {{ number_format($attachment->size / 1024, 1) }} KB
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="mt-2 flex items-center justify-end gap-1 text-xs">
                                            <span class="{{ $isMine ? 'text-gray-300' : 'text-gray-400' }}">
                                                {{ $message->created_at?->format('d M · H:i') }}
                                            </span>

                                            @if ($message->edited_at)
                                                <span
                                                    data-message-edited-label="1"
                                                    class="text-gray-400"
                                                >
                                                    · edited
                                                </span>
                                            @endif

                                            @if ($isMine)
                                                <span
                                                    data-read-receipt="{{ $message->id }}"
                                                    class="font-bold {{ $isRead ? 'text-blue-400' : 'text-gray-400' }}"
                                                    title="{{ $isRead ? 'Read' : 'Delivered, not read yet' }}"
                                                >
                                                    ✓✓
                                                </span>
                                            @endif
                                        </div>

                                        <div class="mt-2 flex items-center justify-end gap-2 border-t border-gray-300 pt-2 text-xs">
                                            <button
                                                type="button"
                                                class="{{ $isMine ? 'text-gray-300' : 'text-gray-500' }} hover:underline"
                                                data-reply-message="{{ $message->id }}"
                                                onclick="return window.crmChatReplyAction(this, event);"
                                                data-reply-sender="{{ $senderNames[$message->user_id] ?? 'User' }}"
                                                data-reply-body="{{ e(trim((string) $message->body) ?: 'Attachment') }}"
                                            >
                                                Reply
                                            </button>

                                            @if ($isMine)
                                                <button
                                                    type="button"
                                                    class="text-gray-300 hover:underline"
                                                    data-edit-message="{{ $message->id }}"
                                                    onclick="return window.crmChatEditAction(this, event);"
                                                    data-edit-body="{{ e((string) $message->body) }}"
                                                >
                                                    Edit
                                                </button>

                                                <button
                                                    type="button"
                                                    class="text-red-300 hover:underline"
                                                    data-delete-message="{{ $message->id }}"
                                                    onclick="return window.crmChatDeleteAction(this, event);"
                                                >
                                                    Delete
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                            <div
                                id="crm-chat-bottom"
                                aria-hidden="true"
                                style="height:1px; width:100%; flex:0 0 auto;"
                            ></div>
                        </div>
                    </div>

                    <div class="border-t bg-white p-4">
                        <form
                            id="crm-chat-send-form"
                            method="POST"
                            enctype="multipart/form-data"
                            action="{{ route('admin.internal-chat.send', $conversation->id) }}"
                            class="flex flex-col gap-3"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="reply_to_message_id"
                                id="crm-chat-reply-to-message-id"
                                value=""
                            >

                            <div class="rounded-xl border bg-gray-50 p-3">
                                <div
                                    id="crm-chat-reply-preview"
                                    class="mb-3 hidden items-start justify-between gap-3 rounded-lg border bg-white px-3 py-2"
                                >
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold text-gray-800">
                                            Replying to
                                            <span id="crm-chat-reply-sender"></span>
                                        </div>

                                        <div
                                            id="crm-chat-reply-body"
                                            class="mt-1 truncate text-xs text-gray-500"
                                        ></div>
                                    </div>

                                    <button
                                        type="button"
                                        id="crm-chat-reply-cancel"
                                        class="text-xs font-bold text-gray-500"
                                    >
                                        ×
                                    </button>
                                </div>

                                <div
                                    id="crm-chat-edit-preview"
                                    class="mb-3 hidden items-center justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2"
                                >
                                    <div class="text-xs font-bold text-blue-700">
                                        Editing message
                                    </div>

                                    <button
                                        type="button"
                                        id="crm-chat-edit-cancel"
                                        class="text-xs font-bold text-blue-700"
                                    >
                                        Cancel
                                    </button>
                                </div>

                                <textarea
                                    name="body"
                                    id="crm-chat-body"
                                    class="min-h-24 w-full resize-y border-0 bg-transparent text-sm text-gray-900 outline-none"
                                    placeholder="Ketik pesan..."
                                ></textarea>

                                <input
                                    type="file"
                                    id="crm-chat-attachments"
                                    name="attachments[]"
                                    multiple
                                    hidden
                                    onchange="window.crmChatRenderSelectedPreview(this);"
                                >

                                <div
                                    id="crm-chat-selected-preview"
                                    style="
                                        display:none;
                                        margin-top:10px;
                                        padding:10px;
                                        border:1px solid #e5e7eb;
                                        border-radius:12px;
                                        background:#ffffff;
                                    "
                                ></div>

                                <div id="crm-chat-file-list" class="mt-2 flex flex-wrap gap-2"></div>

                                <div
                                    id="crm-chat-feedback"
                                    class="mt-2 hidden rounded-lg px-3 py-2 text-xs font-semibold"
                                ></div>

                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t pt-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <label
                                            for="crm-chat-attachments"
                                            class="secondary-button cursor-pointer"
                                        >
                                            📎 Attachment
                                        </label>

                                        <span class="text-xs text-gray-400">
                                            Max 5 file, 10 MB/file
                                        </span>
                                    </div>

                                    <button type="submit" class="primary-button">
                                        Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="flex flex-1 items-center justify-center p-10">
                        <div class="max-w-md text-center">
                            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white text-2xl shadow-sm">
                                💬
                            </div>

                            <h2 class="mt-4 text-xl font-bold text-gray-900">
                                Pilih Conversation
                            </h2>

                            <p class="mt-2 text-sm leading-6 text-gray-500">
                                Pilih chat di sebelah kiri atau buat conversation baru.
                            </p>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>

    {{-- INTERNAL CHAT V3.2 EXPERIENCE FEATURES --}}
    <div
        id="crm-chat-search-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-40 p-4"
        aria-hidden="true"
    >
        <div class="flex flex-col overflow-hidden border bg-white shadow-lg"
            style="width:min(94vw,620px);max-height:min(76vh,680px);border-radius:18px;box-shadow:0 28px 80px rgba(15,23,42,.24);">
            <div class="flex items-center justify-between gap-3 border-b p-4">
                <div>
                    <div class="text-lg font-bold text-gray-900">
                        Search Messages
                    </div>

                    <div class="mt-1 text-xs text-gray-500">
                        Cari isi pesan pada conversation ini.
                    </div>
                </div>

                <button
                    type="button"
                    class="secondary-button"
                    onclick="return window.crmChatSearchClose(event);"
                >
                    Close
                </button>
            </div>

            <div class="border-b p-4">
                <div class="flex gap-2">
                    <input
                        type="text"
                        id="crm-chat-message-search-input"
                        class="w-full rounded-lg border px-3 py-2 text-sm"
                        placeholder="Contoh: SPK, invoice, Semarang..."
                        onkeydown="if (event.key === 'Enter') { event.preventDefault(); window.crmChatSearchRun(); }"
                    >

                    <button
                        type="button"
                        class="primary-button"
                        onclick="return window.crmChatSearchRun(event);"
                    >
                        Search
                    </button>
                </div>

                <div
                    id="crm-chat-message-search-feedback"
                    class="mt-2 text-xs text-gray-500"
                ></div>
            </div>

            <div
                id="crm-chat-message-search-results"
                class="flex-1 overflow-y-auto"
            ></div>
        </div>
    </div>

    <div
        id="crm-chat-attachment-preview-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-60 p-4"
        aria-hidden="true"
    >
        <div class="flex h-full max-h-screen w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-xl" style="width:min(92vw,920px);height:min(78vh,680px);max-height:78vh;display:flex;flex-direction:column;overflow:hidden;border-radius:18px;background:#ffffff;box-shadow:0 28px 90px rgba(15,23,42,.30);">
            <div
                style="
                    flex:0 0 auto;
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:12px;
                    padding:12px 14px;
                    border-bottom:1px solid #e5e7eb;
                    background:#ffffff;
                    position:relative;
                    z-index:3;
                "
            >
                <div style="min-width:0;flex:1;">
                    <div style="font-size:14px;font-weight:800;color:#0f172a;">
                        Attachment Preview
                    </div>

                    <div
                        id="crm-chat-attachment-preview-name"
                        style="
                            margin-top:2px;
                            max-width:520px;
                            overflow:hidden;
                            text-overflow:ellipsis;
                            white-space:nowrap;
                            font-size:11px;
                            font-weight:600;
                            color:#64748b;
                        "
                    ></div>
                </div>

                <div style="display:flex;gap:8px;align-items:center;flex:0 0 auto;">
                    <a
                        id="crm-chat-attachment-download"
                        href="#"
                        class="secondary-button"
                        download
                    >
                        ⬇ Download / Save
                    </a>

                    <button
                        type="button"
                        class="secondary-button"
                        onclick="return window.crmChatPreviewClose(event);"
                    >
                        ← Back
                    </button>
                </div>
            </div>

            <div
                style="
                    flex:1 1 auto;
                    min-height:0;
                    overflow:hidden;
                    padding:10px;
                    background:#e5e7eb;
                "
            >
                <div
                    id="crm-chat-image-preview-stage"
                    style="
                        display:none;
                        width:100%;
                        height:100%;
                        min-height:0;
                        align-items:center;
                        justify-content:center;
                        overflow:auto;
                        padding:14px;
                        background:#0f172a;
                        border-radius:12px;
                    "
                >
                    <img
                        id="crm-chat-attachment-preview-image"
                        alt="Image preview"
                        style="
                            display:block;
                            max-width:88%;
                            max-height:100%;
                            width:auto;
                            height:auto;
                            object-fit:contain;
                            border-radius:10px;
                            background:#ffffff;
                            box-shadow:0 12px 40px rgba(0,0,0,.28);
                        "
                    >
                </div>

                <iframe
                    id="crm-chat-attachment-preview-frame"
                    title="Attachment preview"
                    style="
                        display:block;
                        width:100%;
                        height:100%;
                        border:0;
                        border-radius:10px;
                        background:#ffffff;
                    "
                ></iframe>
            </div>

            <div
                style="
                    flex:0 0 auto;
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:12px;
                    padding:10px 14px;
                    border-top:1px solid #e5e7eb;
                    background:#ffffff;
                    position:relative;
                    z-index:3;
                "
            >
                <div style="font-size:11px;color:#64748b;">
                    Image dan PDF dapat dipreview. File lain tetap didownload.
                </div>

                <div style="display:flex;gap:8px;align-items:center;">
                    <a
                        id="crm-chat-attachment-download-bottom"
                        href="#"
                        class="secondary-button"
                        download
                    >
                        ⬇ Download
                    </a>

                    <button
                        type="button"
                        class="secondary-button"
                        onclick="return window.crmChatPreviewClose(event);"
                    >
                        ← Back
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const root =
                document.getElementById(
                    'crm-chat-messages'
                );

            if (! root) {
                return;
            }

            const csrf =
                document.querySelector(
                    '#crm-chat-send-form input[name="_token"]'
                );

            const csrfToken =
                csrf
                    ? String(
                        csrf.value
                        || ''
                    )
                    : '';

            const searchModal =
                document.getElementById(
                    'crm-chat-search-modal'
                );

            const searchInput =
                document.getElementById(
                    'crm-chat-message-search-input'
                );

            const searchResults =
                document.getElementById(
                    'crm-chat-message-search-results'
                );

            const searchFeedback =
                document.getElementById(
                    'crm-chat-message-search-feedback'
                );

            const previewModal =
                document.getElementById(
                    'crm-chat-attachment-preview-modal'
                );

            const previewFrame =
                document.getElementById(
                    'crm-chat-attachment-preview-frame'
                );

            const previewDownload =
                document.getElementById(
                    'crm-chat-attachment-download'
                );

            const typingIndicator =
                document.getElementById(
                    'crm-chat-typing-indicator'
                );

            const bodyInput =
                document.getElementById(
                    'crm-chat-body'
                );

            const searchUrl =
                String(
                    root.dataset.searchUrl
                    || ''
                );

            const typingUrl =
                String(
                    root.dataset.typingUrl
                    || ''
                );

            const typingStatusUrl =
                String(
                    root.dataset.typingStatusUrl
                    || ''
                );

            const stopEvent = (event) => {
                if (! event) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
            };

            window.crmChatSearchOpen =
                function (event) {
                    stopEvent(
                        event
                    );

                    if (! searchModal) {
                        return false;
                    }

                    searchModal.classList.remove(
                        'hidden'
                    );

                    searchModal.classList.add(
                        'flex'
                    );

                    searchModal.setAttribute(
                        'aria-hidden',
                        'false'
                    );

                    window.setTimeout(
                        () => {
                            if (searchInput) {
                                searchInput.focus();
                            }
                        },
                        40
                    );

                    return false;
                };

            window.crmChatSearchClose =
                function (event) {
                    stopEvent(
                        event
                    );

                    if (! searchModal) {
                        return false;
                    }

                    searchModal.classList.add(
                        'hidden'
                    );

                    searchModal.classList.remove(
                        'flex'
                    );

                    searchModal.setAttribute(
                        'aria-hidden',
                        'true'
                    );

                    return false;
                };

            const jumpToMessage = (
                id
            ) => {
                const message =
                    document.querySelector(
                        '[data-message-id="'
                        + Number(id)
                        + '"]'
                    );

                if (! message) {
                    if (searchFeedback) {
                        searchFeedback.textContent =
                            'Pesan ditemukan di histori tetapi belum dimuat pada panel chat saat ini.';
                    }

                    return;
                }

                window.crmChatSearchClose();

                message.scrollIntoView({
                    behavior:
                        'smooth',

                    block:
                        'center',
                });

                message.classList.add(
                    'ring-2',
                    'ring-blue-400'
                );

                window.setTimeout(
                    () => {
                        message.classList.remove(
                            'ring-2',
                            'ring-blue-400'
                        );
                    },
                    2200
                );
            };

            window.crmChatSearchRun =
                async function (event) {
                    stopEvent(
                        event
                    );

                    const q =
                        String(
                            searchInput
                                ? searchInput.value
                                : ''
                        ).trim();

                    if (! searchResults) {
                        return false;
                    }

                    searchResults.innerHTML =
                        '';

                    if (q.length < 2) {
                        if (searchFeedback) {
                            searchFeedback.textContent =
                                'Masukkan minimal 2 karakter.';
                        }

                        return false;
                    }

                    if (searchFeedback) {
                        searchFeedback.textContent =
                            'Searching...';
                    }

                    try {
                        const response =
                            await fetch(
                                searchUrl
                                + '?q='
                                + encodeURIComponent(
                                    q
                                ),
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
                            throw new Error(
                                await response.text()
                            );
                        }

                        const data =
                            await response.json();

                        const results =
                            data.results
                            || [];

                        if (searchFeedback) {
                            searchFeedback.textContent =
                                results.length
                                + ' hasil ditemukan';
                        }

                        if (results.length === 0) {
                            const empty =
                                document.createElement(
                                    'div'
                                );

                            empty.className =
                                'p-8 text-center text-sm text-gray-500';

                            empty.textContent =
                                'Tidak ada pesan yang cocok.';

                            searchResults.appendChild(
                                empty
                            );

                            return false;
                        }

                        results.forEach(
                            (result) => {
                                const button =
                                    document.createElement(
                                        'button'
                                    );

                                button.type =
                                    'button';

                                button.className =
                                    'block w-full border-b px-4 py-3 text-left hover:bg-gray-50';

                                const header =
                                    document.createElement(
                                        'div'
                                    );

                                header.className =
                                    'flex items-center justify-between gap-3';

                                const sender =
                                    document.createElement(
                                        'div'
                                    );

                                sender.className =
                                    'text-sm font-bold text-gray-900';

                                sender.textContent =
                                    result.sender_name
                                    || 'User';

                                const date =
                                    document.createElement(
                                        'div'
                                    );

                                date.className =
                                    'shrink-0 text-xs text-gray-400';

                                date.textContent =
                                    result.created_at
                                    || '';

                                const body =
                                    document.createElement(
                                        'div'
                                    );

                                body.className =
                                    'mt-1 text-sm text-gray-600';

                                body.textContent =
                                    String(
                                        result.body
                                        || ''
                                    ).slice(
                                        0,
                                        180
                                    );

                                header.appendChild(
                                    sender
                                );

                                header.appendChild(
                                    date
                                );

                                button.appendChild(
                                    header
                                );

                                button.appendChild(
                                    body
                                );

                                button.onclick =
                                    function () {
                                        jumpToMessage(
                                            result.id
                                        );
                                    };

                                searchResults.appendChild(
                                    button
                                );
                            }
                        );
                    } catch (error) {
                        console.error(
                            'Internal Chat search failed:',
                            error
                        );

                        if (searchFeedback) {
                            searchFeedback.textContent =
                                'Search gagal. Coba kembali.';
                        }
                    }

                    return false;
                };

            window.crmChatPreviewAttachment =
                function (link, event) {
                    stopEvent(
                        event
                    );

                    if (
                        ! previewModal
                        || ! previewFrame
                        || ! previewDownload
                    ) {
                        return true;
                    }

                    const previewUrl =
                        String(
                            link.dataset
                                .attachmentPreviewUrl
                            || ''
                        );

                    const downloadUrl =
                        String(
                            link.dataset
                                .attachmentDownloadUrl
                            || link.href
                            || ''
                        );

                    previewDownload.href =
                        downloadUrl;

                    previewFrame.src =
                        previewUrl;

                    previewModal.classList.remove(
                        'hidden'
                    );

                    previewModal.classList.add(
                        'flex'
                    );

                    previewModal.setAttribute(
                        'aria-hidden',
                        'false'
                    );

                    return false;
                };

            window.crmChatPreviewClose =
                function (event) {
                    stopEvent(
                        event
                    );

                    if (! previewModal) {
                        return false;
                    }

                    previewModal.classList.add(
                        'hidden'
                    );

                    previewModal.classList.remove(
                        'flex'
                    );

                    previewModal.setAttribute(
                        'aria-hidden',
                        'true'
                    );

                    if (previewFrame) {
                        previewFrame.src =
                            'about:blank';
                    }

                    return false;
                };

            const postTyping =
                async (typing) => {
                    if (! typingUrl) {
                        return;
                    }

                    try {
                        await fetch(
                            typingUrl,
                            {
                                method:
                                    'POST',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'Content-Type':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        csrfToken,

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                credentials:
                                    'same-origin',

                                body:
                                    JSON.stringify({
                                        typing:
                                            Boolean(
                                                typing
                                            ),
                                    }),
                            }
                        );
                    } catch (error) {
                        // Typing is a best-effort UX signal.
                    }
                };

            let typingTimer =
                null;

            let typingThrottleAt =
                0;

            if (bodyInput) {
                bodyInput.addEventListener(
                    'input',
                    () => {
                        const now =
                            Date.now();

                        if (
                            now
                            - typingThrottleAt
                            > 1400
                        ) {
                            typingThrottleAt =
                                now;

                            postTyping(
                                true
                            );
                        }

                        window.clearTimeout(
                            typingTimer
                        );

                        typingTimer =
                            window.setTimeout(
                                () => {
                                    postTyping(
                                        false
                                    );
                                },
                                2600
                            );
                    }
                );

                bodyInput.addEventListener(
                    'blur',
                    () => {
                        postTyping(
                            false
                        );
                    }
                );
            }

            const pollTyping =
                async () => {
                    if (
                        ! typingStatusUrl
                        || ! typingIndicator
                    ) {
                        return;
                    }

                    try {
                        const response =
                            await fetch(
                                typingStatusUrl,
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

                        if (
                            data.typing
                            && (
                                data.users
                                || []
                            ).length
                        ) {
                            const names =
                                data.users
                                    .map(
                                        (user) =>
                                            user.name
                                            || 'User'
                                    )
                                    .join(
                                        ', '
                                    );

                            typingIndicator.textContent =
                                names
                                + ' sedang mengetik...';

                            typingIndicator.classList.remove(
                                'hidden'
                            );
                        } else {
                            typingIndicator.classList.add(
                                'hidden'
                            );
                        }
                    } catch (error) {
                        // Typing status failure does not affect chat.
                    }
                };

            window.setInterval(
                pollTyping,
                2000
            );

            if (searchModal) {
                searchModal.addEventListener(
                    'click',
                    (event) => {
                        if (
                            event.target
                            === searchModal
                        ) {
                            window.crmChatSearchClose(
                                event
                            );
                        }
                    }
                );
            }

            if (previewModal) {
                previewModal.addEventListener(
                    'click',
                    (event) => {
                        if (
                            event.target
                            === previewModal
                        ) {
                            window.crmChatPreviewClose(
                                event
                            );
                        }
                    }
                );
            }
        })();
    </script>
    {{-- New Chat modal. The long user list no longer consumes the sidebar. --}}
    <div
        id="crm-new-chat-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-40 p-4"
        style="background:rgba(15,23,42,.38);backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px);padding:20px;align-items:center;justify-content:center;"
        aria-hidden="true"
    >
        <div class="flex flex-col overflow-hidden border bg-white shadow-lg"
            style="width:min(92vw,460px);max-height:min(72vh,620px);border-radius:18px;box-shadow:0 28px 80px rgba(15,23,42,.24);">
            <div class="flex items-center justify-between gap-3 border-b p-4">
                <div>
                    <div class="text-lg font-bold text-gray-900">
                        Start New Chat
                    </div>

                    <div class="mt-1 text-xs text-gray-500">
                        Pilih akun CRM untuk direct conversation.
                    </div>
                </div>

                <button
                    type="button"
                    id="crm-new-chat-close"
                    class="secondary-button"
                    onclick="return window.crmNewChatClose(event);"
                >
                    Close
                </button>
            </div>

            <div class="border-b p-4">
                <div class="flex items-center gap-2 rounded-xl border bg-gray-50 px-3 py-2">
                    <span class="text-gray-400">🔎</span>

                    <input
                        type="text"
                        id="crm-new-chat-search"
                        class="w-full border-0 bg-transparent text-sm text-gray-900 outline-none"
                        placeholder="Cari nama, role, atau email..."
                    >
                </div>
            </div>

            <div id="crm-chat-user-modal-list" class="flex-1 overflow-y-auto">
                @forelse ($users as $chatUser)
                    <form
                        method="POST"
                        action="{{ route('admin.internal-chat.direct', $chatUser->id) }}"
                        class="m-0 border-b"
                        data-user-search="{{ strtolower($chatUser->name.' '.($chatUser->role_name ?: '').' '.$chatUser->email) }}"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="flex w-full items-center gap-3 bg-white px-4 py-2.5 text-left hover:bg-gray-50"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-700">
                                {{ $makeInitials($chatUser->name) }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-bold text-gray-900">
                                    {{ $chatUser->name }}
                                </div>

                                <div class="mt-1 truncate text-xs text-gray-500">
                                    {{ $chatUser->role_name ?: 'Internal User' }}
                                    · {{ $chatUser->email }}
                                </div>
                            </div>

                            <span class="text-gray-400">›</span>
                        </button>
                    </form>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-500">
                        Tidak ada user lain.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        (() => {
            const conversationSearch =
                document.getElementById('crm-wa-chat-search');

            if (conversationSearch) {
                conversationSearch.addEventListener('input', () => {
                    const keyword = String(conversationSearch.value || '')
                        .toLowerCase()
                        .trim();

                    document
                        .querySelectorAll('[data-conversation-search]')
                        .forEach((node) => {
                            const text = String(
                                node.dataset.conversationSearch || ''
                            ).toLowerCase();

                            node.style.display =
                                keyword === '' || text.includes(keyword)
                                    ? ''
                                    : 'none';
                        });
                });
            }

            const modal = document.getElementById('crm-new-chat-modal');
            const openButton = document.getElementById('crm-new-chat-open');
            const emptyOpenButton = document.querySelector('[data-open-new-chat]');
            const closeButton = document.getElementById('crm-new-chat-close');
            const userSearch = document.getElementById('crm-new-chat-search');

            const openModal = () => {
                if (! modal) {
                    return;
                }

                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modal.setAttribute('aria-hidden', 'false');

                if (userSearch) {
                    userSearch.value = '';
                    userSearch.dispatchEvent(new Event('input'));
                    window.setTimeout(() => userSearch.focus(), 50);
                }
            };

            const closeModal = () => {
                if (! modal) {
                    return;
                }

                modal.classList.add('hidden');
                modal.classList.remove('flex');
                modal.setAttribute('aria-hidden', 'true');
            };

            if (openButton) {
                openButton.addEventListener('click', openModal);
            }

            if (emptyOpenButton) {
                emptyOpenButton.addEventListener('click', openModal);
            }

            if (closeButton) {
                closeButton.addEventListener('click', closeModal);
            }

            if (modal) {
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        closeModal();
                    }
                });
            }

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });

            if (userSearch) {
                userSearch.addEventListener('input', () => {
                    const keyword = String(userSearch.value || '')
                        .toLowerCase()
                        .trim();

                    document
                        .querySelectorAll('[data-user-search]')
                        .forEach((node) => {
                            const text = String(
                                node.dataset.userSearch || ''
                            ).toLowerCase();

                            node.style.display =
                                keyword === '' || text.includes(keyword)
                                    ? ''
                                    : 'none';
                        });
                });
            }
        })();
    </script>

    @if ($conversation)
        {{-- INTERNAL CHAT V3.1.4 HARD ACTION FALLBACK --}}
        <script>
            window.crmChatStopActionEvent = function (event) {
                if (! event) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (
                    typeof event.stopImmediatePropagation
                    === 'function'
                ) {
                    event.stopImmediatePropagation();
                }
            };

            window.crmChatCsrfToken = function () {
                const token =
                    document.querySelector(
                        '#crm-chat-send-form input[name="_token"]'
                    );

                return token
                    ? String(token.value || '')
                    : '';
            };

            window.crmChatMessageActionUrl = function (messageId) {
                const root =
                    document.getElementById(
                        'crm-chat-messages'
                    );

                const base =
                    root
                    ? String(
                        root.dataset.actionBase
                        || ''
                    )
                    : '';

                return base.replace(
                    /\/+$/,
                    ''
                )
                + '/'
                + encodeURIComponent(
                    String(messageId)
                );
            };

            window.crmChatReplyAction = function (button, event) {
                window.crmChatStopActionEvent(
                    event
                );

                const input =
                    document.getElementById(
                        'crm-chat-reply-to-message-id'
                    );

                const preview =
                    document.getElementById(
                        'crm-chat-reply-preview'
                    );

                const sender =
                    document.getElementById(
                        'crm-chat-reply-sender'
                    );

                const body =
                    document.getElementById(
                        'crm-chat-reply-body'
                    );

                const textarea =
                    document.getElementById(
                        'crm-chat-body'
                    );

                if (! input || ! preview) {
                    window.alert(
                        'Reply UI tidak ditemukan. Refresh halaman lalu coba kembali.'
                    );

                    return false;
                }

                input.value =
                    String(
                        button.dataset.replyMessage
                        || ''
                    );

                if (sender) {
                    sender.textContent =
                        String(
                            button.dataset.replySender
                            || 'User'
                        );
                }

                if (body) {
                    body.textContent =
                        String(
                            button.dataset.replyBody
                            || 'Attachment'
                        );
                }

                preview.classList.remove(
                    'hidden'
                );

                preview.classList.add(
                    'flex'
                );

                const editPreview =
                    document.getElementById(
                        'crm-chat-edit-preview'
                    );

                if (editPreview) {
                    editPreview.classList.add(
                        'hidden'
                    );

                    editPreview.classList.remove(
                        'flex'
                    );
                }

                if (textarea) {
                    textarea.focus();
                }

                return false;
            };

            window.crmChatEditAction = async function (button, event) {
                window.crmChatStopActionEvent(
                    event
                );

                const messageId =
                    Number(
                        button.dataset.editMessage
                        || 0
                    );

                const currentBody =
                    String(
                        button.dataset.editBody
                        || ''
                    );

                if (messageId < 1) {
                    window.alert(
                        'Message ID tidak valid.'
                    );

                    return false;
                }

                if (currentBody.trim() === '') {
                    window.alert(
                        'Pesan attachment-only tidak memiliki teks untuk diedit.'
                    );

                    return false;
                }

                const replacement =
                    window.prompt(
                        'Edit pesan:',
                        currentBody
                    );

                if (replacement === null) {
                    return false;
                }

                const body =
                    String(
                        replacement
                    ).trim();

                if (body === '') {
                    window.alert(
                        'Pesan tidak boleh kosong.'
                    );

                    return false;
                }

                button.disabled =
                    true;

                try {
                    const response =
                        await fetch(
                            window.crmChatMessageActionUrl(
                                messageId
                            ),
                            {
                                method:
                                    'PATCH',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'Content-Type':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        window.crmChatCsrfToken(),

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                credentials:
                                    'same-origin',

                                body:
                                    JSON.stringify({
                                        body:
                                            body,
                                    }),
                            }
                        );

                    if (! response.ok) {
                        throw new Error(
                            await response.text()
                        );
                    }

                    const wrapper =
                        document.querySelector(
                            '[data-message-id="'
                            + messageId
                            + '"]'
                        );

                    if (wrapper) {
                        const textNode =
                            wrapper.querySelector(
                                '.whitespace-pre-wrap.break-words.text-sm'
                            );

                        if (textNode) {
                            textNode.textContent =
                                body;
                        }

                        const editButton =
                            wrapper.querySelector(
                                '[data-edit-message]'
                            );

                        if (editButton) {
                            editButton.dataset.editBody =
                                body;
                        }

                        const replyButton =
                            wrapper.querySelector(
                                '[data-reply-message]'
                            );

                        if (replyButton) {
                            replyButton.dataset.replyBody =
                                body;
                        }

                        if (
                            ! wrapper.querySelector(
                                '[data-message-edited-label]'
                            )
                        ) {
                            const receipt =
                                wrapper.querySelector(
                                    '[data-read-receipt]'
                                );

                            const meta =
                                receipt
                                    ? receipt.parentElement
                                    : null;

                            if (meta) {
                                const edited =
                                    document.createElement(
                                        'span'
                                    );

                                edited.dataset.messageEditedLabel =
                                    '1';

                                edited.className =
                                    'text-gray-400';

                                edited.textContent =
                                    '· edited';

                                if (receipt) {
                                    meta.insertBefore(
                                        edited,
                                        receipt
                                    );
                                } else {
                                    meta.appendChild(
                                        edited
                                    );
                                }
                            }
                        }
                    }
                } catch (error) {
                    console.error(
                        'Internal Chat edit failed:',
                        error
                    );

                    window.alert(
                        'Pesan gagal diedit. Lihat Console jika error berulang.'
                    );
                } finally {
                    button.disabled =
                        false;
                }

                return false;
            };

            window.crmChatDeleteAction = async function (button, event) {
                window.crmChatStopActionEvent(
                    event
                );

                const messageId =
                    Number(
                        button.dataset.deleteMessage
                        || 0
                    );

                if (messageId < 1) {
                    window.alert(
                        'Message ID tidak valid.'
                    );

                    return false;
                }

                if (
                    ! window.confirm(
                        'Hapus pesan ini? Pesan akan disembunyikan dari conversation tetapi tetap tersimpan untuk audit.'
                    )
                ) {
                    return false;
                }

                button.disabled =
                    true;

                try {
                    const response =
                        await fetch(
                            window.crmChatMessageActionUrl(
                                messageId
                            ),
                            {
                                method:
                                    'DELETE',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        window.crmChatCsrfToken(),

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                credentials:
                                    'same-origin',
                            }
                        );

                    if (! response.ok) {
                        throw new Error(
                            await response.text()
                        );
                    }

                    const wrapper =
                        document.querySelector(
                            '[data-message-id="'
                            + messageId
                            + '"]'
                        );

                    if (wrapper) {
                        wrapper.remove();
                    }
                } catch (error) {
                    button.disabled =
                        false;

                    console.error(
                        'Internal Chat delete failed:',
                        error
                    );

                    window.alert(
                        'Pesan gagal dihapus. Lihat Console jika error berulang.'
                    );
                }

                return false;
            };
        </script>

        <script>
            (() => {
                const currentUserId =
                    {{ (int) $currentUser->id }};

                const messagesRoot =
                    document.getElementById(
                        'crm-chat-messages'
                    );

                /* INTERNAL CHAT WHATSAPP BOTTOM STACK V1.3 */
                const messageStack =
                    document.getElementById(
                        'crm-chat-message-stack'
                    )
                    || messagesRoot;

                const form =
                    document.getElementById(
                        'crm-chat-send-form'
                    );

                const pollUrl =
                    @json(
                        route(
                            'admin.internal-chat.messages',
                            $conversation->id
                        )
                    );

                {{-- INTERNAL CHAT V3.1.2 SAFE ROUTE TEMPLATES --}}
                @php
                    $crmEditMessageUrlTemplate =
                        route(
                            'admin.internal-chat.messages.update',
                            [
                                'conversationId' =>
                                    $conversation->id,

                                'messageId' =>
                                    '__MESSAGE_ID__',
                            ]
                        );

                    $crmDeleteMessageUrlTemplate =
                        route(
                            'admin.internal-chat.messages.delete',
                            [
                                'conversationId' =>
                                    $conversation->id,

                                'messageId' =>
                                    '__MESSAGE_ID__',
                            ]
                        );
                @endphp

                const editUrlTemplate =
                    @json(
                        $crmEditMessageUrlTemplate
                    );

                const deleteUrlTemplate =
                    @json(
                        $crmDeleteMessageUrlTemplate
                    );

                const fileInput =
                    document.getElementById(
                        'crm-chat-attachments'
                    );

                const fileList =
                    document.getElementById(
                        'crm-chat-file-list'
                    );

                const feedback =
                    document.getElementById(
                        'crm-chat-feedback'
                    );

                const bodyInput =
                    document.getElementById(
                        'crm-chat-body'
                    );

                const replyInput =
                    document.getElementById(
                        'crm-chat-reply-to-message-id'
                    );

                const replyPreview =
                    document.getElementById(
                        'crm-chat-reply-preview'
                    );

                const replySender =
                    document.getElementById(
                        'crm-chat-reply-sender'
                    );

                const replyBody =
                    document.getElementById(
                        'crm-chat-reply-body'
                    );

                const replyCancel =
                    document.getElementById(
                        'crm-chat-reply-cancel'
                    );

                const editPreview =
                    document.getElementById(
                        'crm-chat-edit-preview'
                    );

                const editCancel =
                    document.getElementById(
                        'crm-chat-edit-cancel'
                    );

                const csrfToken =
                    form.querySelector(
                        'input[name="_token"]'
                    )?.value
                    || '';

                let editingMessageId =
                    0;

                const messageUrl = (
                    template,
                    id
                ) => template.replace(
                    '__MESSAGE_ID__',
                    encodeURIComponent(
                        String(id)
                    )
                );

                const updateReadReceipts = (
                    readUpToId
                ) => {
                    const limit =
                        Number(
                            readUpToId
                            || 0
                        );

                    messagesRoot.dataset.readUpToId =
                        String(limit);

                    document
                        .querySelectorAll(
                            '[data-read-receipt]'
                        )
                        .forEach(
                            (receipt) => {
                                const messageId =
                                    Number(
                                        receipt.dataset
                                            .readReceipt
                                        || 0
                                    );

                                const isRead =
                                    messageId > 0
                                    && messageId <= limit;

                                receipt.classList.remove(
                                    'text-blue-400',
                                    'text-gray-400'
                                );

                                receipt.classList.add(
                                    isRead
                                        ? 'text-blue-400'
                                        : 'text-gray-400'
                                );

                                receipt.title =
                                    isRead
                                        ? 'Read'
                                        : 'Delivered, not read yet';
                            }
                        );
                };

                const showFeedback = (
                    message,
                    type = 'error'
                ) => {
                    if (! feedback) {
                        return;
                    }

                    feedback.textContent =
                        message;

                    feedback.classList.remove(
                        'hidden',
                        'bg-red-50',
                        'text-red-700',
                        'bg-green-50',
                        'text-green-700'
                    );

                    if (type === 'success') {
                        feedback.classList.add(
                            'bg-green-50',
                            'text-green-700'
                        );
                    } else {
                        feedback.classList.add(
                            'bg-red-50',
                            'text-red-700'
                        );
                    }

                    window.clearTimeout(
                        feedback.__crmTimer
                    );

                    feedback.__crmTimer =
                        window.setTimeout(
                            () => {
                                feedback.classList.add(
                                    'hidden'
                                );
                            },
                            3000
                        );
                };

                const clearReply = () => {
                    if (replyInput) {
                        replyInput.value =
                            '';
                    }

                    if (replyPreview) {
                        replyPreview.classList.add(
                            'hidden'
                        );

                        replyPreview.classList.remove(
                            'flex'
                        );
                    }
                };

                const setReply = (
                    id,
                    sender,
                    text
                ) => {
                    editingMessageId =
                        0;

                    if (editPreview) {
                        editPreview.classList.add(
                            'hidden'
                        );

                        editPreview.classList.remove(
                            'flex'
                        );
                    }

                    if (replyInput) {
                        replyInput.value =
                            String(id);
                    }

                    if (replySender) {
                        replySender.textContent =
                            sender
                            || 'User';
                    }

                    if (replyBody) {
                        replyBody.textContent =
                            text
                            || 'Attachment';
                    }

                    if (replyPreview) {
                        replyPreview.classList.remove(
                            'hidden'
                        );

                        replyPreview.classList.add(
                            'flex'
                        );
                    }

                    bodyInput?.focus();
                };

                const clearEdit = () => {
                    editingMessageId =
                        0;

                    if (editPreview) {
                        editPreview.classList.add(
                            'hidden'
                        );

                        editPreview.classList.remove(
                            'flex'
                        );
                    }

                    if (bodyInput) {
                        bodyInput.value =
                            '';
                    }
                };

                const setEdit = (
                    id,
                    text
                ) => {
                    clearReply();

                    editingMessageId =
                        Number(id)
                        || 0;

                    if (editPreview) {
                        editPreview.classList.remove(
                            'hidden'
                        );

                        editPreview.classList.add(
                            'flex'
                        );
                    }

                    if (bodyInput) {
                        bodyInput.value =
                            text
                            || '';

                        bodyInput.focus();

                        bodyInput.setSelectionRange(
                            bodyInput.value.length,
                            bodyInput.value.length
                        );
                    }
                };

                const renderFileList = () => {
                    if (
                        ! fileInput
                        || ! fileList
                    ) {
                        return;
                    }

                    fileList.innerHTML =
                        '';

                    Array.from(
                        fileInput.files
                        || []
                    ).forEach(
                        (file) => {
                            const chip =
                                document.createElement(
                                    'span'
                                );

                            chip.className =
                                'rounded-full border bg-white px-3 py-1 text-xs font-semibold text-gray-600';

                            chip.textContent =
                                '📎 '
                                + file.name;

                            fileList.appendChild(
                                chip
                            );
                        }
                    );
                };

                const createReplyPreview = (
                    message
                ) => {
                    if (! message.reply) {
                        return null;
                    }

                    const reply =
                        document.createElement(
                            'div'
                        );

                    reply.className =
                        'mb-2 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-gray-700';

                    const name =
                        document.createElement(
                            'div'
                        );

                    name.className =
                        'text-xs font-bold';

                    name.textContent =
                        message.reply.sender_name
                        || 'User';

                    const text =
                        document.createElement(
                            'div'
                        );

                    text.className =
                        'mt-1 max-w-sm truncate text-xs';

                    text.textContent =
                        message.reply.body
                        || 'Attachment';

                    reply.appendChild(
                        name
                    );

                    reply.appendChild(
                        text
                    );

                    return reply;
                };

                const appendMessage = (
                    message
                ) => {
                    if (
                        document.querySelector(
                            `[data-message-id="${message.id}"]`
                        )
                    ) {
                        return;
                    }

                    const mine =
                        Number(
                            message.user_id
                        )
                        === currentUserId;

                    const wrapper =
                        document.createElement(
                            'div'
                        );

                    wrapper.className =
                        'mb-3 flex '
                        + (
                            mine
                                ? 'justify-end'
                                : 'justify-start'
                        );

                    wrapper.dataset.messageId =
                        message.id;

                    const container =
                        document.createElement(
                            'div'
                        );

                    container.className =
                        'max-w-2xl';

                    if (! mine) {
                        const sender =
                            document.createElement(
                                'div'
                            );

                        sender.className =
                            'mb-1 px-1 text-xs font-bold text-gray-500';

                        sender.textContent =
                            message.sender_name
                            || 'User';

                        container.appendChild(
                            sender
                        );
                    }

                    const bubble =
                        document.createElement(
                            'div'
                        );

                    bubble.className =
                        'rounded-xl px-4 py-3 shadow-sm '
                        + (
                            mine
                                ? 'bg-gray-900 text-white'
                                : 'border bg-white text-gray-900'
                        );

                    const quoted =
                        createReplyPreview(
                            message
                        );

                    if (quoted) {
                        bubble.appendChild(
                            quoted
                        );
                    }

                    if (message.body) {
                        const body =
                            document.createElement(
                                'div'
                            );

                        body.className =
                            'whitespace-pre-wrap break-words text-sm';

                        body.textContent =
                            message.body;

                        bubble.appendChild(
                            body
                        );
                    }

                    if (
                        (
                            message.attachments
                            || []
                        ).length
                    ) {
                        const attachments =
                            document.createElement(
                                'div'
                            );

                        attachments.className =
                            'mt-3 flex flex-col gap-2';

                        (
                            message.attachments
                            || []
                        ).forEach(
                            (attachment) => {
                                const link =
                                    document.createElement(
                                        'a'
                                    );

                                link.href =
                                    attachment.download_url;

                                link.dataset.attachmentDownloadUrl =
                                    attachment.download_url;

                                link.dataset.attachmentName =
                                    String(
                                        attachment.name
                                        || 'Attachment'
                                    );

                                link.dataset.attachmentPreviewUrl =
                                    String(
                                        attachment.download_url
                                        || ''
                                    ).replace(
                                        /\/download(?:\?.*)?$/,
                                        '/preview'
                                    );

                                link.onclick =
                                    function (event) {
                                        return window.crmChatPreviewAttachment(
                                            this,
                                            event
                                        );
                                    };

                                link.className =
                                    'rounded-lg border px-3 py-2 text-xs font-semibold no-underline '
                                    + (
                                        mine
                                            ? 'border-gray-600 text-white'
                                            : 'bg-gray-50 text-gray-700'
                                    );

                                link.textContent =
                                    '📎 '
                                    + (
                                        attachment.name
                                        || 'Attachment'
                                    );

                                attachments.appendChild(
                                    link
                                );
                            }
                        );

                        bubble.appendChild(
                            attachments
                        );
                    }

                    const meta =
                        document.createElement(
                            'div'
                        );

                    meta.className =
                        'mt-2 flex items-center justify-end gap-1 text-xs';

                    const date =
                        document.createElement(
                            'span'
                        );

                    date.className =
                        mine
                            ? 'text-gray-300'
                            : 'text-gray-400';

                    date.textContent =
                        message.created_at
                        || '';

                    meta.appendChild(
                        date
                    );

                    if (message.edited_at) {
                        const edited =
                            document.createElement(
                                'span'
                            );

                        edited.dataset.messageEditedLabel =
                            '1';

                        edited.className =
                            'text-gray-400';

                        edited.textContent =
                            '· edited';

                        meta.appendChild(
                            edited
                        );
                    }

                    if (mine) {
                        const receipt =
                            document.createElement(
                                'span'
                            );

                        receipt.dataset.readReceipt =
                            String(
                                message.id
                            );

                        receipt.className =
                            'font-bold text-gray-400';

                        receipt.title =
                            'Delivered, not read yet';

                        receipt.textContent =
                            '✓✓';

                        meta.appendChild(
                            receipt
                        );
                    }

                    bubble.appendChild(
                        meta
                    );

                    const actions =
                        document.createElement(
                            'div'
                        );

                    actions.className =
                        'mt-2 flex items-center justify-end gap-2 border-t border-gray-300 pt-2 text-xs';

                    const replyButton =
                        document.createElement(
                            'button'
                        );

                    replyButton.type =
                        'button';

                    replyButton.className =
                        (
                            mine
                                ? 'text-gray-300'
                                : 'text-gray-500'
                        )
                        + ' hover:underline';

                    replyButton.dataset.replyMessage =
                        String(
                            message.id
                        );

                    replyButton.dataset.replySender =
                        message.sender_name
                        || 'User';

                    replyButton.dataset.replyBody =
                        message.body
                        || 'Attachment';

                    replyButton.textContent =
                        'Reply';

                    replyButton.onclick =
                        function (event) {
                            return window.crmChatReplyAction(
                                this,
                                event
                            );
                        };

                    actions.appendChild(
                        replyButton
                    );

                    if (mine) {
                        const editButton =
                            document.createElement(
                                'button'
                            );

                        editButton.type =
                            'button';

                        editButton.className =
                            'text-gray-300 hover:underline';

                        editButton.dataset.editMessage =
                            String(
                                message.id
                            );

                        editButton.dataset.editBody =
                            message.body
                            || '';

                        editButton.textContent =
                            'Edit';

                        editButton.onclick =
                            function (event) {
                                return window.crmChatEditAction(
                                    this,
                                    event
                                );
                            };

                        actions.appendChild(
                            editButton
                        );

                        const deleteButton =
                            document.createElement(
                                'button'
                            );

                        deleteButton.type =
                            'button';

                        deleteButton.className =
                            'text-red-300 hover:underline';

                        deleteButton.dataset.deleteMessage =
                            String(
                                message.id
                            );

                        deleteButton.textContent =
                            'Delete';

                        deleteButton.onclick =
                            function (event) {
                                return window.crmChatDeleteAction(
                                    this,
                                    event
                                );
                            };

                        actions.appendChild(
                            deleteButton
                        );
                    }

                    bubble.appendChild(
                        actions
                    );

                    container.appendChild(
                        bubble
                    );

                    wrapper.appendChild(
                        container
                    );

                    messageStack.appendChild(
                        wrapper
                    );

                    messagesRoot.dataset.lastId =
                        Math.max(
                            Number(
                                messagesRoot.dataset.lastId
                                || 0
                            ),
                            Number(
                                message.id
                                || 0
                            )
                        );

                    messagesRoot.scrollTop =
                        messagesRoot.scrollHeight;
                };

                const updateExistingMessage = (
                    message
                ) => {
                    const wrapper =
                        document.querySelector(
                            `[data-message-id="${message.id}"]`
                        );

                    if (! wrapper) {
                        return;
                    }

                    const body =
                        wrapper.querySelector(
                            '.whitespace-pre-wrap.break-words.text-sm'
                        );

                    if (body) {
                        body.textContent =
                            message.body
                            || '';
                    }

                    const editButton =
                        wrapper.querySelector(
                            '[data-edit-message]'
                        );

                    if (editButton) {
                        editButton.dataset.editBody =
                            message.body
                            || '';
                    }

                    const replyButton =
                        wrapper.querySelector(
                            '[data-reply-message]'
                        );

                    if (replyButton) {
                        replyButton.dataset.replyBody =
                            message.body
                            || 'Attachment';
                    }

                    if (
                        message.edited_at
                        && ! wrapper.querySelector(
                            '[data-message-edited-label]'
                        )
                    ) {
                        const receipt =
                            wrapper.querySelector(
                                '[data-read-receipt]'
                            );

                        const meta =
                            receipt
                                ? receipt.parentElement
                                : null;

                        if (meta) {
                            const edited =
                                document.createElement(
                                    'span'
                                );

                            edited.dataset.messageEditedLabel =
                                '1';

                            edited.className =
                                'text-gray-400';

                            edited.textContent =
                                '· edited';

                            if (receipt) {
                                meta.insertBefore(
                                    edited,
                                    receipt
                                );
                            } else {
                                meta.appendChild(
                                    edited
                                );
                            }
                        }
                    }
                };

                const pollMessages =
                    async () => {
                        try {
                            const after =
                                Number(
                                    messagesRoot.dataset.lastId
                                    || 0
                                );

                            const syncAfter =
                                String(
                                    messagesRoot.dataset.syncAt
                                    || ''
                                );

                            const response =
                                await fetch(
                                    pollUrl
                                    + '?after='
                                    + encodeURIComponent(
                                        after
                                    )
                                    + '&sync_after='
                                    + encodeURIComponent(
                                        syncAfter
                                    ),
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

                            (
                                data.messages
                                || []
                            ).forEach(
                                appendMessage
                            );

                            (
                                data.changed_messages
                                || []
                            ).forEach(
                                updateExistingMessage
                            );

                            (
                                data.deleted_message_ids
                                || []
                            ).forEach(
                                (id) => {
                                    const wrapper =
                                        document.querySelector(
                                            `[data-message-id="${id}"]`
                                        );

                                    if (wrapper) {
                                        wrapper.remove();
                                    }
                                }
                            );

                            if (data.sync_at) {
                                messagesRoot.dataset.syncAt =
                                    data.sync_at;
                            }

                            updateReadReceipts(
                                data.read_up_to_id
                                || 0
                            );
                        } catch (error) {
                            // Temporary network errors do not disable the chat.
                        }
                    };

                form.addEventListener(
                    'submit',
                    async (event) => {
                        event.preventDefault();

                        const button =
                            form.querySelector(
                                'button[type="submit"]'
                            );

                        const original =
                            button.textContent;

                        button.disabled =
                            true;

                        button.textContent =
                            editingMessageId > 0
                                ? 'Saving...'
                                : 'Sending...';

                        try {
                            if (editingMessageId > 0) {
                                const editBody =
                                    String(
                                        bodyInput?.value
                                        || ''
                                    ).trim();

                                if (editBody === '') {
                                    throw new Error(
                                        'Pesan edit tidak boleh kosong.'
                                    );
                                }

                                const editResponse =
                                    await fetch(
                                        messageUrl(
                                            editUrlTemplate,
                                            editingMessageId
                                        ),
                                        {
                                            method:
                                                'PATCH',

                                            headers: {
                                                'Accept':
                                                    'application/json',

                                                'Content-Type':
                                                    'application/json',

                                                'X-CSRF-TOKEN':
                                                    csrfToken,

                                                'X-Requested-With':
                                                    'XMLHttpRequest',
                                            },

                                            credentials:
                                                'same-origin',

                                            body:
                                                JSON.stringify({
                                                    body:
                                                        editBody,
                                                }),
                                        }
                                    );

                                if (! editResponse.ok) {
                                    throw new Error(
                                        await editResponse.text()
                                    );
                                }

                                const editedMessage =
                                    await editResponse.json();

                                updateExistingMessage(
                                    editedMessage
                                );

                                clearEdit();

                                showFeedback(
                                    'Pesan berhasil diedit.',
                                    'success'
                                );

                                return;
                            }

                            const response =
                                await fetch(
                                    form.action,
                                    {
                                        method:
                                            'POST',

                                        headers: {
                                            'Accept':
                                                'application/json',

                                            'X-Requested-With':
                                                'XMLHttpRequest',
                                        },

                                        credentials:
                                            'same-origin',

                                        body:
                                            new FormData(
                                                form
                                            ),
                                    }
                                );

                            if (! response.ok) {
                                throw new Error(
                                    await response.text()
                                );
                            }

                            const message =
                                await response.json();

                            appendMessage(
                                message
                            );

                            form.reset();

                            clearReply();

                            renderFileList();

                            showFeedback(
                                'Pesan berhasil dikirim.',
                                'success'
                            );
                        } catch (error) {
                            showFeedback(
                                editingMessageId > 0
                                    ? 'Pesan gagal diedit.'
                                    : 'Pesan gagal dikirim. Coba kembali.',
                                'error'
                            );
                        } finally {
                            button.disabled =
                                false;

                            button.textContent =
                                original;
                        }
                    }
                );

                /* INTERNAL CHAT V3.1.3 DIRECT ACTION BINDINGS */
                const deleteMessageFromButton =
                    async (deleteButton) => {
                        const messageId =
                            Number(
                                deleteButton.dataset.deleteMessage
                                || 0
                            );

                        if (
                            messageId < 1
                            || ! window.confirm(
                                'Hapus pesan ini? Pesan akan disembunyikan dari conversation tetapi tetap tersimpan untuk audit.'
                            )
                        ) {
                            return;
                        }

                        deleteButton.disabled =
                            true;

                        try {
                            const response =
                                await fetch(
                                    messageUrl(
                                        deleteUrlTemplate,
                                        messageId
                                    ),
                                    {
                                        method:
                                            'DELETE',

                                        headers: {
                                            'Accept':
                                                'application/json',

                                            'X-CSRF-TOKEN':
                                                csrfToken,

                                            'X-Requested-With':
                                                'XMLHttpRequest',
                                        },

                                        credentials:
                                            'same-origin',
                                    }
                                );

                            if (! response.ok) {
                                throw new Error(
                                    await response.text()
                                );
                            }

                            const wrapper =
                                document.querySelector(
                                    `[data-message-id="${messageId}"]`
                                );

                            if (wrapper) {
                                wrapper.remove();
                            }

                            if (
                                editingMessageId
                                === messageId
                            ) {
                                clearEdit();
                            }

                            showFeedback(
                                'Pesan dihapus.',
                                'success'
                            );
                        } catch (error) {
                            deleteButton.disabled =
                                false;

                            showFeedback(
                                'Pesan gagal dihapus.',
                                'error'
                            );
                        }
                    };

                const bindMessageActionButtons =
                    (rootNode) => {
                        if (! rootNode) {
                            return;
                        }

                        rootNode
                            .querySelectorAll(
                                '[data-reply-message]'
                            )
                            .forEach(
                                (button) => {
                                    if (
                                        button.dataset.crmReplyBound
                                        === '1'
                                    ) {
                                        return;
                                    }

                                    button.dataset.crmReplyBound =
                                        '1';

                                    button.addEventListener(
                                        'click',
                                        (event) => {
                                            event.preventDefault();
                                            event.stopPropagation();

                                            setReply(
                                                button.dataset.replyMessage,
                                                button.dataset.replySender,
                                                button.dataset.replyBody
                                            );
                                        }
                                    );
                                }
                            );

                        rootNode
                            .querySelectorAll(
                                '[data-edit-message]'
                            )
                            .forEach(
                                (button) => {
                                    if (
                                        button.dataset.crmEditBound
                                        === '1'
                                    ) {
                                        return;
                                    }

                                    button.dataset.crmEditBound =
                                        '1';

                                    button.addEventListener(
                                        'click',
                                        (event) => {
                                            event.preventDefault();
                                            event.stopPropagation();

                                            setEdit(
                                                button.dataset.editMessage,
                                                button.dataset.editBody
                                            );
                                        }
                                    );
                                }
                            );

                        rootNode
                            .querySelectorAll(
                                '[data-delete-message]'
                            )
                            .forEach(
                                (button) => {
                                    if (
                                        button.dataset.crmDeleteBound
                                        === '1'
                                    ) {
                                        return;
                                    }

                                    button.dataset.crmDeleteBound =
                                        '1';

                                    button.addEventListener(
                                        'click',
                                        async (event) => {
                                            event.preventDefault();
                                            event.stopPropagation();

                                            await deleteMessageFromButton(
                                                button
                                            );
                                        }
                                    );
                                }
                            );
                    };

                bindMessageActionButtons(
                    messagesRoot
                );

                const messageActionObserver =
                    new MutationObserver(
                        (mutations) => {
                            mutations.forEach(
                                (mutation) => {
                                    mutation.addedNodes.forEach(
                                        (node) => {
                                            if (
                                                node.nodeType
                                                !== Node.ELEMENT_NODE
                                            ) {
                                                return;
                                            }

                                            bindMessageActionButtons(
                                                node
                                            );
                                        }
                                    );
                                }
                            );
                        }
                    );

                messageActionObserver.observe(
                    messagesRoot,
                    {
                        childList: true,
                        subtree: true,
                    }
                );

                if (replyCancel) {
                    replyCancel.addEventListener(
                        'click',
                        clearReply
                    );
                }

                if (editCancel) {
                    editCancel.addEventListener(
                        'click',
                        clearEdit
                    );
                }

                if (fileInput) {
                    fileInput.addEventListener(
                        'change',
                        renderFileList
                    );
                }

                messagesRoot.scrollTop =
                    messagesRoot.scrollHeight;

                updateReadReceipts(
                    Number(
                        messagesRoot.dataset.readUpToId
                        || 0
                    )
                );

                window.setInterval(
                    pollMessages,
                    5000
                );
            })();
        </script>
    @endif


    {{-- INTERNAL CHAT V3.2.2 ROBUST UI INTERACTIONS --}}
    <script>
        (() => {
            const stopEvent = (
                event
            ) => {
                if (! event) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
            };

            const showOverlay = (
                element
            ) => {
                if (! element) {
                    return;
                }

                element.classList.remove(
                    'hidden'
                );

                element.classList.add(
                    'flex'
                );

                /*
                 * Inline display is deliberate here. It makes the hotfix
                 * independent from compiled utility-class ordering.
                 */
                element.style.display =
                    'flex';

                element.setAttribute(
                    'aria-hidden',
                    'false'
                );
            };

            const hideOverlay = (
                element
            ) => {
                if (! element) {
                    return;
                }

                element.classList.add(
                    'hidden'
                );

                element.classList.remove(
                    'flex'
                );

                element.style.display =
                    'none';

                element.setAttribute(
                    'aria-hidden',
                    'true'
                );
            };

            /*
            |--------------------------------------------------------------------------
            | New Chat
            |--------------------------------------------------------------------------
            */

            window.crmNewChatOpen =
                function (event) {
                    stopEvent(
                        event
                    );

                    const modal =
                        document.getElementById(
                            'crm-new-chat-modal'
                        );

                    const search =
                        document.getElementById(
                            'crm-new-chat-search'
                        );

                    showOverlay(
                        modal
                    );

                    if (search) {
                        search.value =
                            '';

                        search.dispatchEvent(
                            new Event(
                                'input'
                            )
                        );

                        window.setTimeout(
                            () => {
                                search.focus();
                            },
                            40
                        );
                    }

                    return false;
                };

            window.crmNewChatClose =
                function (event) {
                    stopEvent(
                        event
                    );

                    hideOverlay(
                        document.getElementById(
                            'crm-new-chat-modal'
                        )
                    );

                    return false;
                };

            const newButton =
                document.getElementById(
                    'crm-new-chat-open'
                );

            if (
                newButton
                && newButton.dataset.crmV322Bound !== '1'
            ) {
                newButton.dataset.crmV322Bound =
                    '1';

                newButton.addEventListener(
                    'click',
                    window.crmNewChatOpen
                );
            }

            const emptyNewButton =
                document.querySelector(
                    '[data-open-new-chat]'
                );

            if (
                emptyNewButton
                && emptyNewButton.dataset.crmV322Bound !== '1'
            ) {
                emptyNewButton.dataset.crmV322Bound =
                    '1';

                emptyNewButton.addEventListener(
                    'click',
                    window.crmNewChatOpen
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Message Search
            |--------------------------------------------------------------------------
            */

            window.crmChatSearchOpen =
                function (event) {
                    stopEvent(
                        event
                    );

                    const modal =
                        document.getElementById(
                            'crm-chat-search-modal'
                        );

                    const input =
                        document.getElementById(
                            'crm-chat-message-search-input'
                        );

                    showOverlay(
                        modal
                    );

                    window.setTimeout(
                        () => {
                            if (input) {
                                input.focus();
                            }
                        },
                        40
                    );

                    return false;
                };

            window.crmChatSearchClose =
                function (event) {
                    stopEvent(
                        event
                    );

                    hideOverlay(
                        document.getElementById(
                            'crm-chat-search-modal'
                        )
                    );

                    return false;
                };

            const jumpToMessage = (
                id
            ) => {
                const message =
                    document.querySelector(
                        '[data-message-id="'
                        + Number(id)
                        + '"]'
                    );

                if (! message) {
                    const feedback =
                        document.getElementById(
                            'crm-chat-message-search-feedback'
                        );

                    if (feedback) {
                        feedback.textContent =
                            'Pesan ditemukan, tetapi tidak termasuk 300 pesan yang sedang dimuat pada panel.';
                    }

                    return;
                }

                window.crmChatSearchClose();

                message.scrollIntoView({
                    behavior:
                        'smooth',

                    block:
                        'center',
                });

                /*
                 * Inline outline avoids depending on a dynamic Tailwind class.
                 */
                const previousOutline =
                    message.style.outline;

                const previousOffset =
                    message.style.outlineOffset;

                message.style.outline =
                    '3px solid #3b82f6';

                message.style.outlineOffset =
                    '4px';

                window.setTimeout(
                    () => {
                        message.style.outline =
                            previousOutline;

                        message.style.outlineOffset =
                            previousOffset;
                    },
                    2200
                );
            };

            window.crmChatSearchRun =
                async function (event) {
                    stopEvent(
                        event
                    );

                    const root =
                        document.getElementById(
                            'crm-chat-messages'
                        );

                    const input =
                        document.getElementById(
                            'crm-chat-message-search-input'
                        );

                    const results =
                        document.getElementById(
                            'crm-chat-message-search-results'
                        );

                    const feedback =
                        document.getElementById(
                            'crm-chat-message-search-feedback'
                        );

                    if (
                        ! root
                        || ! input
                        || ! results
                    ) {
                        return false;
                    }

                    const q =
                        String(
                            input.value
                            || ''
                        ).trim();

                    results.innerHTML =
                        '';

                    if (q.length < 2) {
                        if (feedback) {
                            feedback.textContent =
                                'Masukkan minimal 2 karakter.';
                        }

                        return false;
                    }

                    const url =
                        String(
                            root.dataset.searchUrl
                            || ''
                        );

                    if (! url) {
                        if (feedback) {
                            feedback.textContent =
                                'Search endpoint tidak tersedia.';
                        }

                        return false;
                    }

                    if (feedback) {
                        feedback.textContent =
                            'Searching...';
                    }

                    try {
                        const response =
                            await fetch(
                                url
                                + '?q='
                                + encodeURIComponent(
                                    q
                                ),
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
                            throw new Error(
                                await response.text()
                            );
                        }

                        const data =
                            await response.json();

                        const rows =
                            Array.isArray(
                                data.results
                            )
                                ? data.results
                                : [];

                        if (feedback) {
                            feedback.textContent =
                                rows.length
                                + ' hasil ditemukan';
                        }

                        if (rows.length === 0) {
                            const empty =
                                document.createElement(
                                    'div'
                                );

                            empty.style.padding =
                                '24px';

                            empty.style.textAlign =
                                'center';

                            empty.style.color =
                                '#6b7280';

                            empty.textContent =
                                'Tidak ada pesan yang cocok.';

                            results.appendChild(
                                empty
                            );

                            return false;
                        }

                        rows.forEach(
                            (row) => {
                                const button =
                                    document.createElement(
                                        'button'
                                    );

                                button.type =
                                    'button';

                                button.style.display =
                                    'block';

                                button.style.width =
                                    '100%';

                                button.style.padding =
                                    '12px 16px';

                                button.style.textAlign =
                                    'left';

                                button.style.borderBottom =
                                    '1px solid #e5e7eb';

                                button.style.background =
                                    '#ffffff';

                                button.style.cursor =
                                    'pointer';

                                const header =
                                    document.createElement(
                                        'div'
                                    );

                                header.style.display =
                                    'flex';

                                header.style.justifyContent =
                                    'space-between';

                                header.style.gap =
                                    '12px';

                                const sender =
                                    document.createElement(
                                        'strong'
                                    );

                                sender.textContent =
                                    row.sender_name
                                    || 'User';

                                const date =
                                    document.createElement(
                                        'span'
                                    );

                                date.style.fontSize =
                                    '12px';

                                date.style.color =
                                    '#9ca3af';

                                date.textContent =
                                    row.created_at
                                    || '';

                                const body =
                                    document.createElement(
                                        'div'
                                    );

                                body.style.marginTop =
                                    '4px';

                                body.style.fontSize =
                                    '13px';

                                body.style.color =
                                    '#4b5563';

                                body.textContent =
                                    String(
                                        row.body
                                        || ''
                                    ).slice(
                                        0,
                                        220
                                    );

                                header.appendChild(
                                    sender
                                );

                                header.appendChild(
                                    date
                                );

                                button.appendChild(
                                    header
                                );

                                button.appendChild(
                                    body
                                );

                                button.onclick =
                                    function () {
                                        jumpToMessage(
                                            row.id
                                        );
                                    };

                                results.appendChild(
                                    button
                                );
                            }
                        );
                    } catch (error) {
                        console.error(
                            'Internal Chat search failed:',
                            error
                        );

                        if (feedback) {
                            feedback.textContent =
                                'Search gagal. Coba kembali.';
                        }
                    }

                    return false;
                };

            /*
            |--------------------------------------------------------------------------
            | Attachment preview after Send
            |--------------------------------------------------------------------------
            */

            const isImageName = (
                value
            ) => /\.(png|jpe?g|webp|gif|bmp)$/i.test(
                String(
                    value
                    || ''
                )
            );

            const isPdfName = (
                value
            ) => /\.pdf$/i.test(
                String(
                    value
                    || ''
                )
            );

            window.crmChatPreviewAttachment =
                function (link, event) {
                    if (! link) {
                        return true;
                    }

                    const fileLabel =
                        String(
                            link.textContent
                            || ''
                        );

                    const previewable =
                        isImageName(
                            fileLabel
                        )
                        || isPdfName(
                            fileLabel
                        );

                    /*
                     * Non-image/PDF keeps the normal download behavior.
                     */
                    if (! previewable) {
                        return true;
                    }

                    stopEvent(
                        event
                    );

                    const modal =
                        document.getElementById(
                            'crm-chat-attachment-preview-modal'
                        );

                    const frame =
                        document.getElementById(
                            'crm-chat-attachment-preview-frame'
                        );

                    const download =
                        document.getElementById(
                            'crm-chat-attachment-download'
                        );

                    if (
                        ! modal
                        || ! frame
                        || ! download
                    ) {
                        return true;
                    }

                    const previewUrl =
                        String(
                            link.dataset.attachmentPreviewUrl
                            || ''
                        );

                    const downloadUrl =
                        String(
                            link.dataset.attachmentDownloadUrl
                            || link.href
                            || ''
                        );

                    if (! previewUrl) {
                        return true;
                    }

                    frame.src =
                        previewUrl;

                    download.href =
                        downloadUrl;

                    showOverlay(
                        modal
                    );

                    return false;
                };

            window.crmChatPreviewClose =
                function (event) {
                    stopEvent(
                        event
                    );

                    const modal =
                        document.getElementById(
                            'crm-chat-attachment-preview-modal'
                        );

                    const frame =
                        document.getElementById(
                            'crm-chat-attachment-preview-frame'
                        );

                    hideOverlay(
                        modal
                    );

                    if (frame) {
                        frame.src =
                            'about:blank';
                    }

                    return false;
                };

            const decorateSentAttachment =
                (link) => {
                    if (
                        ! link
                        || link.dataset.crmInlinePreviewReady === '1'
                    ) {
                        return;
                    }

                    link.dataset.crmInlinePreviewReady =
                        '1';

                    const label =
                        String(
                            link.textContent
                            || ''
                        );

                    if (! isImageName(label)) {
                        return;
                    }

                    const previewUrl =
                        String(
                            link.dataset.attachmentPreviewUrl
                            || ''
                        );

                    if (! previewUrl) {
                        return;
                    }

                    const image =
                        document.createElement(
                            'img'
                        );

                    image.src =
                        previewUrl;

                    image.alt =
                        label.trim();

                    image.loading =
                        'lazy';

                    image.style.display =
                        'block';

                    image.style.maxWidth =
                        '320px';

                    image.style.maxHeight =
                        '240px';

                    image.style.objectFit =
                        'contain';

                    image.style.borderRadius =
                        '8px';

                    image.style.marginBottom =
                        '8px';

                    image.style.background =
                        '#ffffff';

                    link.insertBefore(
                        image,
                        link.firstChild
                    );

                    link.style.display =
                        'block';
                };

            const decorateAllSentAttachments =
                (scope) => {
                    const parent =
                        scope
                        || document;

                    if (
                        parent.matches
                        && parent.matches(
                            '[data-attachment-preview-url]'
                        )
                    ) {
                        decorateSentAttachment(
                            parent
                        );
                    }

                    if (parent.querySelectorAll) {
                        parent
                            .querySelectorAll(
                                '[data-attachment-preview-url]'
                            )
                            .forEach(
                                decorateSentAttachment
                            );
                    }
                };

            decorateAllSentAttachments(
                document
            );

            const messages =
                document.getElementById(
                    'crm-chat-messages'
                );

            if (messages) {
                const observer =
                    new MutationObserver(
                        (mutations) => {
                            mutations.forEach(
                                (mutation) => {
                                    mutation.addedNodes.forEach(
                                        (node) => {
                                            if (
                                                node.nodeType
                                                !== Node.ELEMENT_NODE
                                            ) {
                                                return;
                                            }

                                            decorateAllSentAttachments(
                                                node
                                            );
                                        }
                                    );
                                }
                            );
                        }
                    );

                observer.observe(
                    messages,
                    {
                        childList:
                            true,

                        subtree:
                            true,
                    }
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Image preview BEFORE Send
            |--------------------------------------------------------------------------
            */

            const fileInput =
                document.getElementById(
                    'crm-chat-attachments'
                );

            const fileList =
                document.getElementById(
                    'crm-chat-file-list'
                );

            let objectUrls =
                [];

            const clearObjectUrls =
                () => {
                    objectUrls.forEach(
                        (url) => {
                            try {
                                URL.revokeObjectURL(
                                    url
                                );
                            } catch (error) {
                                // Ignore stale object URLs.
                            }
                        }
                    );

                    objectUrls =
                        [];
                };

            const renderSelectedFiles =
                () => {
                    if (
                        ! fileInput
                        || ! fileList
                    ) {
                        return;
                    }

                    clearObjectUrls();

                    fileList.innerHTML =
                        '';

                    Array.from(
                        fileInput.files
                        || []
                    ).forEach(
                        (file) => {
                            const card =
                                document.createElement(
                                    'div'
                                );

                            card.style.border =
                                '1px solid #e5e7eb';

                            card.style.borderRadius =
                                '10px';

                            card.style.padding =
                                '8px';

                            card.style.background =
                                '#ffffff';

                            card.style.maxWidth =
                                '220px';

                            if (
                                String(
                                    file.type
                                    || ''
                                ).startsWith(
                                    'image/'
                                )
                            ) {
                                const url =
                                    URL.createObjectURL(
                                        file
                                    );

                                objectUrls.push(
                                    url
                                );

                                const image =
                                    document.createElement(
                                        'img'
                                    );

                                image.src =
                                    url;

                                image.alt =
                                    file.name;

                                image.style.display =
                                    'block';

                                image.style.width =
                                    '180px';

                                image.style.height =
                                    '130px';

                                image.style.objectFit =
                                    'cover';

                                image.style.borderRadius =
                                    '8px';

                                image.style.marginBottom =
                                    '6px';

                                card.appendChild(
                                    image
                                );
                            } else if (
                                String(
                                    file.type
                                    || ''
                                ) === 'application/pdf'
                            ) {
                                const pdf =
                                    document.createElement(
                                        'div'
                                    );

                                pdf.style.padding =
                                    '18px';

                                pdf.style.textAlign =
                                    'center';

                                pdf.style.borderRadius =
                                    '8px';

                                pdf.style.background =
                                    '#f3f4f6';

                                pdf.style.marginBottom =
                                    '6px';

                                pdf.textContent =
                                    '📄 PDF';

                                card.appendChild(
                                    pdf
                                );
                            }

                            const name =
                                document.createElement(
                                    'div'
                                );

                            name.style.fontSize =
                                '12px';

                            name.style.fontWeight =
                                '600';

                            name.style.wordBreak =
                                'break-word';

                            name.textContent =
                                file.name;

                            card.appendChild(
                                name
                            );

                            fileList.appendChild(
                                card
                            );
                        }
                    );
                };

            if (
                fileInput
                && fileInput.dataset.crmV322PreviewBound !== '1'
            ) {
                fileInput.dataset.crmV322PreviewBound =
                    '1';

                fileInput.addEventListener(
                    'change',
                    renderSelectedFiles
                );

                /*
                 * Existing V3 listener is registered before this one.
                 * This handler runs afterwards and upgrades chips to previews.
                 */
            }

            /*
            |--------------------------------------------------------------------------
            | Typing indicator robust fallback
            |--------------------------------------------------------------------------
            */

            const chatRoot =
                document.getElementById(
                    'crm-chat-messages'
                );

            const bodyInput =
                document.getElementById(
                    'crm-chat-body'
                );

            const typingIndicator =
                document.getElementById(
                    'crm-chat-typing-indicator'
                );

            const csrf =
                document.querySelector(
                    '#crm-chat-send-form input[name="_token"]'
                );

            const typingUrl =
                chatRoot
                    ? String(
                        chatRoot.dataset.typingUrl
                        || ''
                    )
                    : '';

            const typingStatusUrl =
                chatRoot
                    ? String(
                        chatRoot.dataset.typingStatusUrl
                        || ''
                    )
                    : '';

            const csrfToken =
                csrf
                    ? String(
                        csrf.value
                        || ''
                    )
                    : '';

            const sendTyping =
                async (typing) => {
                    if (! typingUrl) {
                        return;
                    }

                    try {
                        await fetch(
                            typingUrl,
                            {
                                method:
                                    'POST',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'Content-Type':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        csrfToken,

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                credentials:
                                    'same-origin',

                                body:
                                    JSON.stringify({
                                        typing:
                                            Boolean(
                                                typing
                                            ),
                                    }),
                            }
                        );
                    } catch (error) {
                        // Best-effort UI signal.
                    }
                };

            if (
                bodyInput
                && bodyInput.dataset.crmV322TypingBound !== '1'
            ) {
                bodyInput.dataset.crmV322TypingBound =
                    '1';

                let stopTimer =
                    null;

                let lastPing =
                    0;

                bodyInput.addEventListener(
                    'input',
                    () => {
                        const now =
                            Date.now();

                        if (
                            now
                            - lastPing
                            > 1400
                        ) {
                            lastPing =
                                now;

                            sendTyping(
                                true
                            );
                        }

                        window.clearTimeout(
                            stopTimer
                        );

                        stopTimer =
                            window.setTimeout(
                                () => {
                                    sendTyping(
                                        false
                                    );
                                },
                                2600
                            );
                    }
                );

                bodyInput.addEventListener(
                    'blur',
                    () => {
                        sendTyping(
                            false
                        );
                    }
                );
            }

            if (
                typingStatusUrl
                && typingIndicator
                && ! window.__crmV322TypingPoll
            ) {
                window.__crmV322TypingPoll =
                    window.setInterval(
                        async () => {
                            try {
                                const response =
                                    await fetch(
                                        typingStatusUrl,
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

                                if (
                                    data.typing
                                    && Array.isArray(
                                        data.users
                                    )
                                    && data.users.length
                                ) {
                                    typingIndicator.textContent =
                                        data.users
                                            .map(
                                                (user) =>
                                                    user.name
                                                    || 'User'
                                            )
                                            .join(
                                                ', '
                                            )
                                        + ' sedang mengetik...';

                                    typingIndicator.classList.remove(
                                        'hidden'
                                    );

                                    typingIndicator.style.display =
                                        'block';
                                } else {
                                    typingIndicator.classList.add(
                                        'hidden'
                                    );

                                    typingIndicator.style.display =
                                        'none';
                                }
                            } catch (error) {
                                // Ignore temporary polling failure.
                            }
                        },
                        2000
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Modal backdrop close
            |--------------------------------------------------------------------------
            */

            [
                [
                    'crm-new-chat-modal',
                    window.crmNewChatClose,
                ],
                [
                    'crm-chat-search-modal',
                    window.crmChatSearchClose,
                ],
                [
                    'crm-chat-attachment-preview-modal',
                    window.crmChatPreviewClose,
                ],
            ].forEach(
                ([id, closer]) => {
                    const overlay =
                        document.getElementById(
                            id
                        );

                    if (! overlay) {
                        return;
                    }

                    overlay.addEventListener(
                        'click',
                        (event) => {
                            if (
                                event.target
                                === overlay
                            ) {
                                closer(
                                    event
                                );
                            }
                        }
                    );
                }
            );
        })();
    </script>

    {{-- INTERNAL CHAT V3.2.3 PREVIEW + MODERN MODAL --}}
    <script>
        (() => {
            const fileNameFromLink = (
                link
            ) => {
                if (! link) {
                    return '';
                }

                const explicit =
                    String(
                        link.dataset.attachmentName
                        || ''
                    ).trim();

                if (explicit) {
                    return explicit;
                }

                /*
                 * Fallback for older rows: strip the "· 123 KB" suffix.
                 */
                return String(
                    link.textContent
                    || ''
                )
                    .replace(
                        /^\s*📎\s*/,
                        ''
                    )
                    .replace(
                        /\s*·\s*[\d,.]+\s*KB\s*$/i,
                        ''
                    )
                    .trim();
            };

            const isImageFileName = (
                value
            ) => /\.(png|jpe?g|webp|gif|bmp)$/i.test(
                String(
                    value
                    || ''
                ).trim()
            );

            const isPdfFileName = (
                value
            ) => /\.pdf$/i.test(
                String(
                    value
                    || ''
                ).trim()
            );

            /*
            |--------------------------------------------------------------------------
            | PRE-SEND PREVIEW
            |--------------------------------------------------------------------------
            |
            | Uses a dedicated container, so older crm-chat-file-list listeners
            | cannot erase the visual preview.
            |
            */

            let selectedObjectUrls =
                [];

            const revokeSelectedUrls =
                () => {
                    selectedObjectUrls.forEach(
                        (url) => {
                            try {
                                URL.revokeObjectURL(
                                    url
                                );
                            } catch (error) {
                                // Ignore already revoked URLs.
                            }
                        }
                    );

                    selectedObjectUrls =
                        [];
                };

            const clearSelectedPreview =
                () => {
                    const preview =
                        document.getElementById(
                            'crm-chat-selected-preview'
                        );

                    revokeSelectedUrls();

                    if (! preview) {
                        return;
                    }

                    preview.innerHTML =
                        '';

                    preview.style.display =
                        'none';
                };

            window.crmChatRenderSelectedPreview =
                function (input) {
                    const preview =
                        document.getElementById(
                            'crm-chat-selected-preview'
                        );

                    if (
                        ! input
                        || ! preview
                    ) {
                        return;
                    }

                    revokeSelectedUrls();

                    preview.innerHTML =
                        '';

                    const files =
                        Array.from(
                            input.files
                            || []
                        );

                    if (files.length === 0) {
                        preview.style.display =
                            'none';

                        return;
                    }

                    preview.style.display =
                        'grid';

                    preview.style.gridTemplateColumns =
                        'repeat(auto-fill,minmax(150px,1fr))';

                    preview.style.gap =
                        '10px';

                    files.forEach(
                        (file) => {
                            const card =
                                document.createElement(
                                    'div'
                                );

                            card.style.minWidth =
                                '0';

                            card.style.padding =
                                '8px';

                            card.style.border =
                                '1px solid #e5e7eb';

                            card.style.borderRadius =
                                '10px';

                            card.style.background =
                                '#f8fafc';

                            if (
                                String(
                                    file.type
                                    || ''
                                ).startsWith(
                                    'image/'
                                )
                                || isImageFileName(
                                    file.name
                                )
                            ) {
                                const url =
                                    URL.createObjectURL(
                                        file
                                    );

                                selectedObjectUrls.push(
                                    url
                                );

                                const image =
                                    document.createElement(
                                        'img'
                                    );

                                image.src =
                                    url;

                                image.alt =
                                    file.name;

                                image.style.display =
                                    'block';

                                image.style.width =
                                    '100%';

                                image.style.height =
                                    '120px';

                                image.style.objectFit =
                                    'cover';

                                image.style.borderRadius =
                                    '8px';

                                image.style.background =
                                    '#ffffff';

                                card.appendChild(
                                    image
                                );
                            } else if (
                                String(
                                    file.type
                                    || ''
                                ) === 'application/pdf'
                                || isPdfFileName(
                                    file.name
                                )
                            ) {
                                const pdf =
                                    document.createElement(
                                        'div'
                                    );

                                pdf.style.height =
                                    '120px';

                                pdf.style.display =
                                    'flex';

                                pdf.style.alignItems =
                                    'center';

                                pdf.style.justifyContent =
                                    'center';

                                pdf.style.borderRadius =
                                    '8px';

                                pdf.style.background =
                                    '#eef2f7';

                                pdf.style.fontSize =
                                    '34px';

                                pdf.textContent =
                                    '📄';

                                card.appendChild(
                                    pdf
                                );
                            } else {
                                const generic =
                                    document.createElement(
                                        'div'
                                    );

                                generic.style.height =
                                    '80px';

                                generic.style.display =
                                    'flex';

                                generic.style.alignItems =
                                    'center';

                                generic.style.justifyContent =
                                    'center';

                                generic.style.borderRadius =
                                    '8px';

                                generic.style.background =
                                    '#eef2f7';

                                generic.style.fontSize =
                                    '28px';

                                generic.textContent =
                                    '📎';

                                card.appendChild(
                                    generic
                                );
                            }

                            const name =
                                document.createElement(
                                    'div'
                                );

                            name.style.marginTop =
                                '7px';

                            name.style.fontSize =
                                '11px';

                            name.style.fontWeight =
                                '700';

                            name.style.color =
                                '#334155';

                            name.style.whiteSpace =
                                'nowrap';

                            name.style.overflow =
                                'hidden';

                            name.style.textOverflow =
                                'ellipsis';

                            name.title =
                                file.name;

                            name.textContent =
                                file.name;

                            card.appendChild(
                                name
                            );

                            preview.appendChild(
                                card
                            );
                        }
                    );
                };

            const fileInput =
                document.getElementById(
                    'crm-chat-attachments'
                );

            if (
                fileInput
                && fileInput.dataset.crmV323Bound !== '1'
            ) {
                fileInput.dataset.crmV323Bound =
                    '1';

                fileInput.addEventListener(
                    'change',
                    () => {
                        window.crmChatRenderSelectedPreview(
                            fileInput
                        );
                    }
                );
            }

            const form =
                document.getElementById(
                    'crm-chat-send-form'
                );

            if (
                form
                && form.dataset.crmV323ResetBound !== '1'
            ) {
                form.dataset.crmV323ResetBound =
                    '1';

                form.addEventListener(
                    'reset',
                    () => {
                        window.setTimeout(
                            clearSelectedPreview,
                            0
                        );
                    }
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SENT ATTACHMENT INLINE PREVIEW
            |--------------------------------------------------------------------------
            */

            const makeSentImagePreview = (
                link
            ) => {
                if (
                    ! link
                    || link.dataset.crmV323Decorated === '1'
                ) {
                    return;
                }

                link.dataset.crmV323Decorated =
                    '1';

                const fileName =
                    fileNameFromLink(
                        link
                    );

                link.dataset.attachmentName =
                    fileName;

                if (! isImageFileName(fileName)) {
                    return;
                }

                const previewUrl =
                    String(
                        link.dataset.attachmentPreviewUrl
                        || ''
                    );

                if (! previewUrl) {
                    return;
                }

                const image =
                    document.createElement(
                        'img'
                    );

                image.src =
                    previewUrl;

                image.alt =
                    fileName;

                image.loading =
                    'lazy';

                image.style.display =
                    'block';

                image.style.width =
                    'min(320px,100%)';

                image.style.maxHeight =
                    '260px';

                image.style.objectFit =
                    'cover';

                image.style.borderRadius =
                    '10px';

                image.style.marginBottom =
                    '8px';

                image.style.background =
                    '#ffffff';

                image.onerror =
                    function () {
                        this.remove();
                    };

                link.insertBefore(
                    image,
                    link.firstChild
                );

                link.style.display =
                    'block';

                link.style.maxWidth =
                    '340px';
            };

            const decorateSentAttachments = (
                scope
            ) => {
                const root =
                    scope
                    || document;

                if (
                    root.matches
                    && root.matches(
                        '[data-attachment-preview-url]'
                    )
                ) {
                    makeSentImagePreview(
                        root
                    );
                }

                if (root.querySelectorAll) {
                    root
                        .querySelectorAll(
                            '[data-attachment-preview-url]'
                        )
                        .forEach(
                            makeSentImagePreview
                        );
                }
            };

            decorateSentAttachments(
                document
            );

            const messagesRoot =
                document.getElementById(
                    'crm-chat-messages'
                );

            if (messagesRoot) {
                const observer =
                    new MutationObserver(
                        (mutations) => {
                            mutations.forEach(
                                (mutation) => {
                                    mutation.addedNodes.forEach(
                                        (node) => {
                                            if (
                                                node.nodeType
                                                !== Node.ELEMENT_NODE
                                            ) {
                                                return;
                                            }

                                            decorateSentAttachments(
                                                node
                                            );
                                        }
                                    );
                                }
                            );
                        }
                    );

                observer.observe(
                    messagesRoot,
                    {
                        childList:
                            true,

                        subtree:
                            true,
                    }
                );
            }

            /*
            |--------------------------------------------------------------------------
            | PREVIEW CLICK
            |--------------------------------------------------------------------------
            |
            | Uses data-attachment-name rather than visible text ending with KB.
            |
            */

            const previousPreviewHandler =
                window.crmChatPreviewAttachment;

            window.crmChatPreviewAttachment =
                function (link, event) {
                    const fileName =
                        fileNameFromLink(
                            link
                        );

                    const previewable =
                        isImageFileName(
                            fileName
                        )
                        || isPdfFileName(
                            fileName
                        );

                    if (! previewable) {
                        return true;
                    }

                    if (event) {
                        event.preventDefault();
                        event.stopPropagation();
                    }

                    const modal =
                        document.getElementById(
                            'crm-chat-attachment-preview-modal'
                        );

                    const frame =
                        document.getElementById(
                            'crm-chat-attachment-preview-frame'
                        );

                    const download =
                        document.getElementById(
                            'crm-chat-attachment-download'
                        );

                    const previewUrl =
                        String(
                            link.dataset.attachmentPreviewUrl
                            || ''
                        );

                    const downloadUrl =
                        String(
                            link.dataset.attachmentDownloadUrl
                            || link.href
                            || ''
                        );

                    if (
                        ! modal
                        || ! frame
                        || ! download
                        || ! previewUrl
                    ) {
                        if (
                            typeof previousPreviewHandler
                            === 'function'
                        ) {
                            return previousPreviewHandler(
                                link,
                                event
                            );
                        }

                        return true;
                    }

                    frame.src =
                        previewUrl;

                    download.href =
                        downloadUrl;

                    modal.classList.remove(
                        'hidden'
                    );

                    modal.classList.add(
                        'flex'
                    );

                    modal.style.display =
                        'flex';

                    modal.style.alignItems =
                        'center';

                    modal.style.justifyContent =
                        'center';

                    modal.setAttribute(
                        'aria-hidden',
                        'false'
                    );

                    return false;
                };
        })();
    </script>

    {{-- INTERNAL CHAT V3.2.6 UNIVERSAL PREVIEW TOOLBAR --}}
    <script>
        (() => {
            const fileNameFromLink = (
                link
            ) => {
                if (! link) {
                    return '';
                }

                const explicit =
                    String(
                        link.dataset.attachmentName
                        || ''
                    ).trim();

                if (explicit) {
                    return explicit;
                }

                return String(
                    link.textContent
                    || ''
                )
                    .replace(
                        /^\s*📎\s*/,
                        ''
                    )
                    .replace(
                        /\s*·\s*[\d,.]+\s*KB\s*$/i,
                        ''
                    )
                    .trim();
            };

            const isImage = (
                name
            ) =>
                /\.(png|jpe?g|webp|gif|bmp)$/i.test(
                    String(
                        name
                        || ''
                    )
                );

            const isPdf = (
                name
            ) =>
                /\.pdf$/i.test(
                    String(
                        name
                        || ''
                    )
                );

            const showModal = (
                modal
            ) => {
                if (! modal) {
                    return;
                }

                modal.classList.remove(
                    'hidden'
                );

                modal.classList.add(
                    'flex'
                );

                modal.style.display =
                    'flex';

                modal.style.alignItems =
                    'center';

                modal.style.justifyContent =
                    'center';

                modal.style.padding =
                    '18px';

                modal.style.background =
                    'rgba(15,23,42,.56)';

                modal.style.backdropFilter =
                    'blur(3px)';

                modal.setAttribute(
                    'aria-hidden',
                    'false'
                );
            };

            const hideModal = (
                modal
            ) => {
                if (! modal) {
                    return;
                }

                modal.classList.add(
                    'hidden'
                );

                modal.classList.remove(
                    'flex'
                );

                modal.style.display =
                    'none';

                modal.setAttribute(
                    'aria-hidden',
                    'true'
                );
            };

            const syncDownloadLinks = (
                url,
                filename
            ) => {
                [
                    'crm-chat-attachment-download',
                    'crm-chat-attachment-download-bottom',
                ].forEach(
                    (id) => {
                        const link =
                            document.getElementById(
                                id
                            );

                        if (! link) {
                            return;
                        }

                        link.href =
                            url
                            || '#';

                        if (filename) {
                            link.setAttribute(
                                'download',
                                filename
                            );
                        } else {
                            link.removeAttribute(
                                'download'
                            );
                        }
                    }
                );
            };

            window.crmChatPreviewAttachment =
                function (
                    link,
                    event
                ) {
                    const fileName =
                        fileNameFromLink(
                            link
                        );

                    const imageFile =
                        isImage(
                            fileName
                        );

                    const pdfFile =
                        isPdf(
                            fileName
                        );

                    if (
                        ! imageFile
                        && ! pdfFile
                    ) {
                        return true;
                    }

                    if (event) {
                        event.preventDefault();
                        event.stopPropagation();
                    }

                    const modal =
                        document.getElementById(
                            'crm-chat-attachment-preview-modal'
                        );

                    const frame =
                        document.getElementById(
                            'crm-chat-attachment-preview-frame'
                        );

                    const imageStage =
                        document.getElementById(
                            'crm-chat-image-preview-stage'
                        );

                    const image =
                        document.getElementById(
                            'crm-chat-attachment-preview-image'
                        );

                    const nameLabel =
                        document.getElementById(
                            'crm-chat-attachment-preview-name'
                        );

                    const previewUrl =
                        String(
                            link.dataset.attachmentPreviewUrl
                            || ''
                        );

                    const downloadUrl =
                        String(
                            link.dataset.attachmentDownloadUrl
                            || link.href
                            || ''
                        );

                    if (
                        ! modal
                        || ! frame
                        || ! imageStage
                        || ! image
                        || ! previewUrl
                    ) {
                        return true;
                    }

                    if (nameLabel) {
                        nameLabel.textContent =
                            fileName;
                    }

                    syncDownloadLinks(
                        downloadUrl,
                        fileName
                    );

                    if (imageFile) {
                        frame.src =
                            'about:blank';

                        frame.style.display =
                            'none';

                        image.src =
                            previewUrl;

                        image.alt =
                            fileName;

                        imageStage.style.display =
                            'flex';
                    } else {
                        image.removeAttribute(
                            'src'
                        );

                        imageStage.style.display =
                            'none';

                        frame.style.display =
                            'block';

                        frame.src =
                            previewUrl;
                    }

                    showModal(
                        modal
                    );

                    return false;
                };

            window.crmChatPreviewClose =
                function (
                    event
                ) {
                    if (event) {
                        event.preventDefault();
                        event.stopPropagation();
                    }

                    const modal =
                        document.getElementById(
                            'crm-chat-attachment-preview-modal'
                        );

                    const frame =
                        document.getElementById(
                            'crm-chat-attachment-preview-frame'
                        );

                    const imageStage =
                        document.getElementById(
                            'crm-chat-image-preview-stage'
                        );

                    const image =
                        document.getElementById(
                            'crm-chat-attachment-preview-image'
                        );

                    if (frame) {
                        frame.src =
                            'about:blank';

                        frame.style.display =
                            'block';
                    }

                    if (image) {
                        image.removeAttribute(
                            'src'
                        );
                    }

                    if (imageStage) {
                        imageStage.style.display =
                            'none';
                    }

                    hideModal(
                        modal
                    );

                    return false;
                };
        })();
    </script>

    {{-- INTERNAL CHAT V3.3 CONVERSATION MANAGEMENT --}}
    <div
        id="crm-chat-v33-config"
        data-summary-url="{{ route('admin.internal-chat.sidebar-summary') }}"
        data-preference-base="{{ url('admin/internal-chat') }}"
        data-heartbeat-url="{{ route('admin.internal-chat.presence.heartbeat') }}"
        data-csrf="{{ csrf_token() }}"
        data-active-conversation="{{ (int) ($conversation?->id ?? 0) }}"
        style="display:none;"
    ></div>

    <script>
        (() => {
            const config =
                document.getElementById(
                    'crm-chat-v33-config'
                );

            if (! config) {
                return;
            }

            const summaryUrl =
                String(
                    config.dataset.summaryUrl
                    || ''
                );

            const preferenceBase =
                String(
                    config.dataset.preferenceBase
                    || ''
                ).replace(
                    /\/+$/,
                    ''
                );

            const heartbeatUrl =
                String(
                    config.dataset.heartbeatUrl
                    || ''
                );

            const activeConversation =
                Number(
                    config.dataset.activeConversation
                    || 0
                );

            /*
             * V3.3.1: use Blade-provided token.
             * /admin/internal-chat may not have crm-chat-send-form yet.
             */
            const csrf =
                String(
                    config.dataset.csrf
                    || ''
                );
            const list =
                document.getElementById(
                    'crm-wa-conversation-list'
                );

            const search =
                document.getElementById(
                    'crm-wa-chat-search'
                );

            const typingIndicator =
                document.getElementById(
                    'crm-chat-typing-indicator'
                );

            /*
            |--------------------------------------------------------------------------
            | Presence label in active conversation
            |--------------------------------------------------------------------------
            */

            let activePresence =
                document.getElementById(
                    'crm-chat-v33-presence'
                );

            if (
                ! activePresence
                && typingIndicator
            ) {
                activePresence =
                    document.createElement(
                        'div'
                    );

                activePresence.id =
                    'crm-chat-v33-presence';

                activePresence.style.marginTop =
                    '3px';

                activePresence.style.fontSize =
                    '11px';

                activePresence.style.fontWeight =
                    '700';

                activePresence.style.color =
                    '#64748b';

                typingIndicator.parentNode
                    .insertBefore(
                        activePresence,
                        typingIndicator
                    );
            }

            const escapeHtml = (
                value
            ) => {
                const div =
                    document.createElement(
                        'div'
                    );

                div.textContent =
                    String(
                        value
                        || ''
                    );

                return div.innerHTML;
            };

            const conversationUrl = (
                id
            ) => {
                const url =
                    new URL(
                        window.location.href
                    );

                url.searchParams.set(
                    'conversation',
                    String(
                        id
                    )
                );

                return url.pathname
                    + url.search;
            };

            /*
            |--------------------------------------------------------------------------
            | Mute modal
            |--------------------------------------------------------------------------
            */

            const muteOverlay =
                document.createElement(
                    'div'
                );

            muteOverlay.id =
                'crm-chat-v33-mute-modal';

            muteOverlay.style.cssText =
                'display:none;'
                +'position:fixed;'
                +'inset:0;'
                +'z-index:90;'
                +'align-items:center;'
                +'justify-content:center;'
                +'padding:18px;'
                +'background:rgba(15,23,42,.42);'
                +'backdrop-filter:blur(3px);';

            muteOverlay.innerHTML =
                '<div style="'
                +'width:min(92vw,360px);'
                +'overflow:hidden;'
                +'border:1px solid #e5e7eb;'
                +'border-radius:18px;'
                +'background:#fff;'
                +'box-shadow:0 28px 80px rgba(15,23,42,.25);'
                +'">'
                +'<div style="padding:15px 16px;border-bottom:1px solid #e5e7eb;">'
                +'<div style="font-size:15px;font-weight:800;color:#0f172a;">Mute Conversation</div>'
                +'<div id="crm-chat-v33-mute-name" style="margin-top:3px;font-size:12px;color:#64748b;"></div>'
                +'</div>'
                +'<div style="padding:8px;">'
                +'<button type="button" data-v33-mute-action="mute_1_hour" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔕 1 jam</button>'
                +'<button type="button" data-v33-mute-action="mute_today" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔕 Sampai akhir hari</button>'
                +'<button type="button" data-v33-mute-action="mute_forever" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔕 Sampai diaktifkan kembali</button>'
                +'<button type="button" data-v33-mute-action="unmute" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔔 Unmute</button>'
                +'</div>'
                +'<div style="padding:10px 16px;border-top:1px solid #e5e7eb;text-align:right;">'
                +'<button type="button" id="crm-chat-v33-mute-close" class="secondary-button">Close</button>'
                +'</div>'
                +'</div>';

            document.body.appendChild(
                muteOverlay
            );

            let muteConversationId =
                0;

            const closeMute = () => {
                muteOverlay.style.display =
                    'none';

                muteConversationId =
                    0;
            };

            document
                .getElementById(
                    'crm-chat-v33-mute-close'
                )
                .addEventListener(
                    'click',
                    closeMute
                );

            muteOverlay.addEventListener(
                'click',
                (event) => {
                    if (
                        event.target
                        === muteOverlay
                    ) {
                        closeMute();
                    }
                }
            );

            const preference = async (
                conversationId,
                action
            ) => {
                const response =
                    await fetch(
                        preferenceBase
                        + '/'
                        + encodeURIComponent(
                            String(
                                conversationId
                            )
                        )
                        + '/preference',
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
                                    action:
                                        action,
                                }),
                        }
                    );

                if (! response.ok) {
                    throw new Error(
                        await response.text()
                    );
                }

                return response.json();
            };

            muteOverlay
                .querySelectorAll(
                    '[data-v33-mute-action]'
                )
                .forEach(
                    (button) => {
                        button.addEventListener(
                            'click',
                            async () => {
                                if (
                                    muteConversationId
                                    < 1
                                ) {
                                    return;
                                }

                                button.disabled =
                                    true;

                                try {
                                    await preference(
                                        muteConversationId,
                                        button.dataset
                                            .v33MuteAction
                                    );

                                    closeMute();

                                    await refreshSidebar();
                                } catch (error) {
                                    console.error(
                                        'Mute preference failed:',
                                        error
                                    );

                                    window.alert(
                                        'Mute conversation gagal.'
                                    );
                                } finally {
                                    button.disabled =
                                        false;
                                }
                            }
                        );
                    }
                );

            /*
            |--------------------------------------------------------------------------
            | Modern realtime sidebar
            |--------------------------------------------------------------------------
            */

            /* INTERNAL CHAT V3.3.1 COMPACT SIDEBAR */
            /* INTERNAL CHAT V3.3.10 AUTHORITATIVE UNREAD */
            const renderSidebar = (
                rows
            ) => {
                if (! list) {
                    return;
                }

                list.style.width =
                    '100%';

                list.style.maxWidth =
                    '100%';

                list.style.minWidth =
                    '0';

                list.style.overflowX =
                    'hidden';

                list.style.boxSizing =
                    'border-box';

                if (list.parentElement) {
                    list.parentElement.style.width =
                        '100%';

                    list.parentElement.style.maxWidth =
                        '100%';

                    list.parentElement.style.minWidth =
                        '0';

                    list.parentElement.style.overflowX =
                        'hidden';
                }

                const configV333 =
                    document.getElementById(
                        'crm-chat-v333-config'
                    );

                const preferenceTemplate =
                    String(
                        configV333?.dataset
                            .preferenceTemplate
                        || ''
                    );

                const hardCsrf =
                    String(
                        configV333?.dataset
                            .csrf
                        || ''
                    );

                const preferenceUrl = (
                    conversationId
                ) =>
                    preferenceTemplate.replace(
                        '__CID__',
                        encodeURIComponent(
                            String(
                                conversationId
                            )
                        )
                    );

                const csrfInput = () => {
                    const token =
                        document.createElement(
                            'input'
                        );

                    token.type =
                        'hidden';

                    token.name =
                        '_token';

                    token.value =
                        hardCsrf;

                    return token;
                };

                const pinForm = (
                    row
                ) => {
                    const form =
                        document.createElement(
                            'form'
                        );

                    form.method =
                        'POST';

                    form.action =
                        preferenceUrl(
                            row.id
                        );

                    form.style.cssText =
                        'margin:0;'
                        +'padding:0;'
                        +'flex:0 0 auto;';

                    const button =
                        document.createElement(
                            'button'
                        );

                    button.type =
                        'submit';

                    button.name =
                        'action';

                    button.value =
                        row.pinned
                            ? 'unpin'
                            : 'pin';

                    button.title =
                        row.pinned
                            ? 'Unpin'
                            : 'Pin';

                    button.textContent =
                        row.pinned
                            ? '📍'
                            : '📌';

                    button.style.cssText =
                        'width:23px;'
                        +'height:24px;'
                        +'display:flex;'
                        +'align-items:center;'
                        +'justify-content:center;'
                        +'padding:0;'
                        +'border:1px solid #e5e7eb;'
                        +'border-radius:7px;'
                        +'background:#ffffff;'
                        +'font-size:9px;'
                        +'cursor:pointer;';

                    form.appendChild(
                        csrfInput()
                    );

                    form.appendChild(
                        button
                    );

                    return form;
                };

                const muteForm = (
                    row
                ) => {
                    const form =
                        document.createElement(
                            'form'
                        );

                    form.method =
                        'POST';

                    form.action =
                        preferenceUrl(
                            row.id
                        );

                    form.style.cssText =
                        'margin:0;'
                        +'padding:0;'
                        +'display:flex;'
                        +'align-items:center;'
                        +'gap:2px;'
                        +'flex:0 0 auto;';

                    const select =
                        document.createElement(
                            'select'
                        );

                    select.name =
                        'action';

                    select.required =
                        true;

                    select.title =
                        row.muted
                            ? (
                                row.mute_label
                                || 'Muted'
                            )
                            : 'Mute notification';

                    select.style.cssText =
                        'width:31px;'
                        +'height:24px;'
                        +'padding:0 1px;'
                        +'border:1px solid '
                        +(
                            row.muted
                                ? '#f59e0b'
                                : '#e5e7eb'
                        )
                        +';'
                        +'border-radius:7px;'
                        +'background:'
                        +(
                            row.muted
                                ? '#fffbeb'
                                : '#ffffff'
                        )
                        +';'
                        +'font-size:9px;'
                        +'font-weight:700;'
                        +'color:#334155;'
                        +'cursor:pointer;';

                    const placeholder =
                        document.createElement(
                            'option'
                        );

                    placeholder.value =
                        '';

                    placeholder.textContent =
                        row.muted
                            ? '🔕'
                            : '🔔';

                    placeholder.selected =
                        true;

                    placeholder.disabled =
                        true;

                    select.appendChild(
                        placeholder
                    );

                    [
                        [
                            'mute_1_hour',
                            'Mute 1 jam',
                        ],
                        [
                            'mute_today',
                            'Sampai akhir hari',
                        ],
                        [
                            'mute_forever',
                            'Sampai diaktifkan kembali',
                        ],
                        [
                            'unmute',
                            'Unmute',
                        ],
                    ].forEach(
                        ([value, label]) => {
                            const option =
                                document.createElement(
                                    'option'
                                );

                            option.value =
                                value;

                            option.textContent =
                                label;

                            select.appendChild(
                                option
                            );
                        }
                    );

                    const apply =
                        document.createElement(
                            'button'
                        );

                    apply.type =
                        'submit';

                    apply.textContent =
                        '✓';

                    apply.title =
                        'Apply notification setting';

                    apply.style.cssText =
                        'width:23px;'
                        +'height:24px;'
                        +'padding:0;'
                        +'border:1px solid #0f172a;'
                        +'border-radius:7px;'
                        +'background:#0f172a;'
                        +'color:#ffffff;'
                        +'font-size:9px;'
                        +'font-weight:900;'
                        +'cursor:pointer;';

                    select.addEventListener(
                        'focus',
                        () => {
                            window.__crmMuteSelectOpen =
                                true;
                        }
                    );

                    select.addEventListener(
                        'blur',
                        () => {
                            window.setTimeout(
                                () => {
                                    window.__crmMuteSelectOpen =
                                        false;
                                },
                                150
                            );
                        }
                    );

                    form.appendChild(
                        csrfInput()
                    );

                    form.appendChild(
                        select
                    );

                    form.appendChild(
                        apply
                    );

                    return form;
                };

                const fragment =
                    document.createDocumentFragment();

                rows.forEach(
                    (row) => {
                        const wrapper =
                            document.createElement(
                                'div'
                            );

                        wrapper.dataset.crmV3310Row =
                            String(
                                row.id
                            );

                        wrapper.dataset.conversationSearch =
                            String(
                                row.name
                                +' '
                                +row.role
                                +' '
                                +row.preview
                            ).toLowerCase();

                        wrapper.style.cssText =
                            'display:grid;'
                            +'grid-template-columns:minmax(0,1fr) 82px;'
                            +'align-items:center;'
                            +'width:100%;'
                            +'max-width:100%;'
                            +'min-width:0;'
                            +'box-sizing:border-box;'
                            +'overflow:hidden;'
                            +'border-bottom:1px solid #e5e7eb;'
                            +'background:'
                            +(
                                Number(row.id)
                                === activeConversation
                                    ? '#f8fafc'
                                    : '#ffffff'
                            )
                            +';';

                        const link =
                            document.createElement(
                                'a'
                            );

                        link.href =
                            conversationUrl(
                                row.id
                            );

                        link.style.cssText =
                            'min-width:0;'
                            +'max-width:100%;'
                            +'overflow:hidden;'
                            +'display:flex;'
                            +'align-items:center;'
                            +'gap:7px;'
                            +'padding:7px 4px 7px 9px;'
                            +'text-decoration:none;'
                            +'color:inherit;'
                            +'box-sizing:border-box;';

                        const unread =
                            Math.max(
                                0,
                                Number(
                                    row.unread
                                    || 0
                                )
                            );

                        const presenceColor =
                            row.online
                                ? '#22c55e'
                                : (
                                    row.idle
                                        ? '#f59e0b'
                                        : '#cbd5e1'
                                );

                        link.innerHTML =
                            '<div style="position:relative;flex:0 0 auto;">'
                            +'<div style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#f1f5f9;color:#334155;font-size:9px;font-weight:800;">'
                            +escapeHtml(
                                row.initials
                            )
                            +'</div>'
                            +'<span style="position:absolute;right:-1px;bottom:0;width:8px;height:8px;border:2px solid #fff;border-radius:50%;background:'
                            +presenceColor
                            +';"></span>'
                            +'</div>'
                            +'<div style="min-width:0;max-width:100%;overflow:hidden;flex:1;">'
                            +'<div style="display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:5px;min-width:0;">'
                            +'<div style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;font-weight:800;color:#0f172a;">'
                            +(
                                row.pinned
                                    ? '📌 '
                                    : ''
                            )
                            +escapeHtml(
                                row.name
                            )
                            +'</div>'
                            +(
                                row.muted
                                    ? '<span title="'
                                        +escapeHtml(
                                            row.mute_label
                                            || 'Muted'
                                        )
                                        +'" style="flex:0 0 auto;padding:1px 4px;border:1px solid #fde68a;border-radius:999px;background:#fffbeb;color:#92400e;font-size:7.5px;font-weight:800;">🔕 Muted</span>'
                                    : ''
                            )
                            +'<div style="flex:0 0 auto;font-size:8px;color:#94a3b8;">'
                            +escapeHtml(
                                row.time
                            )
                            +'</div>'
                            +'</div>'
                            +'<div style="margin-top:1px;display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:5px;min-width:0;">'
                            +'<div style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:9.5px;line-height:12px;color:'
                            +(
                                unread > 0
                                    ? '#0f172a'
                                    : '#64748b'
                            )
                            +';font-weight:'
                            +(
                                unread > 0
                                    ? '700'
                                    : '500'
                            )
                            +';">'
                            +escapeHtml(
                                row.preview
                            )
                            +'</div>'
                            +(
                                unread > 0
                                    ? '<span data-crm-v3310-unread="1" title="'
                                        +unread
                                        +' unread message(s)" style="position:static;flex:0 0 auto;min-width:18px;height:18px;padding:0 5px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;background:#dc2626;color:#fff;font-size:8.5px;font-weight:900;line-height:18px;">'
                                        +(
                                            unread > 99
                                                ? '99+'
                                                : unread
                                        )
                                        +'</span>'
                                    : ''
                            )
                            +'</div>'
                            +'<div style="margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:8px;line-height:10px;color:#94a3b8;">'
                            +escapeHtml(
                                row.role
                            )
                            +' · '
                            +'<span style="color:'
                            +(
                                row.online
                                    ? '#16a34a'
                                    : (
                                        row.idle
                                            ? '#d97706'
                                            : '#94a3b8'
                                    )
                            )
                            +';">'
                            +escapeHtml(
                                row.presence
                            )
                            +'</span>'
                            +'</div>'
                            +'</div>';

                        const controls =
                            document.createElement(
                                'div'
                            );

                        controls.style.cssText =
                            'display:flex;'
                            +'align-items:center;'
                            +'justify-content:flex-end;'
                            +'gap:2px;'
                            +'width:82px;'
                            +'max-width:82px;'
                            +'min-width:82px;'
                            +'overflow:visible;'
                            +'padding:0 4px 0 2px;'
                            +'box-sizing:border-box;';

                        controls.appendChild(
                            pinForm(
                                row
                            )
                        );

                        controls.appendChild(
                            muteForm(
                                row
                            )
                        );

                        wrapper.appendChild(
                            link
                        );

                        wrapper.appendChild(
                            controls
                        );

                        fragment.appendChild(
                            wrapper
                        );
                    }
                );

                list.innerHTML =
                    '';

                list.appendChild(
                    fragment
                );

                window.crmChatV3310CleanupLegacyUnread?.();

                if (search) {
                    search.dispatchEvent(
                        new Event(
                            'input'
                        )
                    );
                }
            };
            let refreshing =
                false;

            const refreshSidebar =
                async () => {
                    if (
                        window.__crmMuteSelectOpen
                        || refreshing
                        || ! summaryUrl
                    ) {
                        return;
                    }

                    refreshing =
                        true;

                    try {
                        const response =
                            await fetch(
                                summaryUrl,
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

                        const rows =
                            Array.isArray(
                                data.conversations
                            )
                                ? data.conversations
                                : [];

                        renderSidebar(
                            rows
                        );

                        if (
                            activeConversation > 0
                            && activePresence
                        ) {
                            const active =
                                rows.find(
                                    (row) =>
                                        Number(
                                            row.id
                                        )
                                        === activeConversation
                                );

                            if (active) {
                                activePresence.textContent =
                                    (
                                        active.online
                                            ? '● '
                                            : ''
                                    )
                                    +active.presence;

                                activePresence.style.color =
                                    active.online
                                        ? '#16a34a'
                                        : '#64748b';
                            }
                        }
                    } catch (error) {
                        console.error(
                            'Sidebar refresh failed:',
                            error
                        );
                    } finally {
                        refreshing =
                            false;
                    }
                };

            /*
            |--------------------------------------------------------------------------
            | Presence heartbeat
            |--------------------------------------------------------------------------
            */

            const heartbeat =
                async () => {
                    if (! heartbeatUrl) {
                        return;
                    }

                    try {
                        await fetch(
                            heartbeatUrl,
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
                                    JSON.stringify({}),
                            }
                        );
                    } catch (error) {
                        // Presence is a best-effort UX signal.
                    }
                };

            heartbeat();

            refreshSidebar();

            window.setInterval(
                heartbeat,
                15000
            );

            window.setInterval(
                refreshSidebar,
                4000
            );

            window.crmChatV33RefreshSidebar =
                refreshSidebar;
        })();
    </script>

    {{-- INTERNAL CHAT V3.3.2 HARD PIN MUTE --}}
    <div
        id="crm-chat-v332-hard-config"
        data-preference-base="{{ url('admin/internal-chat') }}"
        data-csrf="{{ csrf_token() }}"
        style="display:none;"
    ></div>

    <div
        id="crm-chat-v332-mute-modal"
        style="
            display:none;
            position:fixed;
            inset:0;
            z-index:120;
            align-items:center;
            justify-content:center;
            padding:18px;
            background:rgba(15,23,42,.44);
            backdrop-filter:blur(3px);
        "
    >
        <div
            style="
                width:min(92vw,350px);
                overflow:hidden;
                border:1px solid #e5e7eb;
                border-radius:18px;
                background:#ffffff;
                box-shadow:0 28px 80px rgba(15,23,42,.25);
            "
        >
            <div style="padding:15px 16px;border-bottom:1px solid #e5e7eb;">
                <div style="font-size:15px;font-weight:800;color:#0f172a;">
                    Mute Conversation
                </div>

                <div
                    id="crm-chat-v332-mute-name"
                    style="margin-top:3px;font-size:12px;color:#64748b;"
                ></div>
            </div>

            <div style="padding:8px;">
                <button type="button" data-v332-pref="mute_1_hour" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔕 1 jam</button>
                <button type="button" data-v332-pref="mute_today" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔕 Sampai akhir hari</button>
                <button type="button" data-v332-pref="mute_forever" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔕 Sampai diaktifkan kembali</button>
                <button type="button" data-v332-pref="unmute" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔔 Unmute</button>
            </div>

            <div style="padding:10px 16px;border-top:1px solid #e5e7eb;text-align:right;">
                <button
                    type="button"
                    id="crm-chat-v332-mute-close"
                    class="secondary-button"
                >
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const config =
                document.getElementById(
                    'crm-chat-v332-hard-config'
                );

            const list =
                document.getElementById(
                    'crm-wa-conversation-list'
                );

            const muteModal =
                document.getElementById(
                    'crm-chat-v332-mute-modal'
                );

            if (
                ! config
                || ! list
                || ! muteModal
            ) {
                return;
            }

            const preferenceBase =
                String(
                    config.dataset.preferenceBase
                    || ''
                ).replace(
                    /\/+$/,
                    ''
                );

            const csrf =
                String(
                    config.dataset.csrf
                    || ''
                );

            let muteConversationId =
                0;

            const conversationIdFromButton = (
                button
            ) => {
                if (! button) {
                    return 0;
                }

                let row =
                    button.parentElement;

                while (
                    row
                    && row !== list
                ) {
                    const link =
                        row.querySelector(
                            'a[href*="conversation="]'
                        );

                    if (link) {
                        try {
                            const url =
                                new URL(
                                    link.href,
                                    window.location.origin
                                );

                            return Number(
                                url.searchParams.get(
                                    'conversation'
                                )
                                || 0
                            );
                        } catch (error) {
                            return 0;
                        }
                    }

                    row =
                        row.parentElement;
                }

                return 0;
            };

            const submitPreference = (
                conversationId,
                action
            ) => {
                if (
                    conversationId < 1
                    || ! action
                ) {
                    return;
                }

                const form =
                    document.createElement(
                        'form'
                    );

                form.method =
                    'POST';

                form.action =
                    preferenceBase
                    + '/'
                    + encodeURIComponent(
                        String(
                            conversationId
                        )
                    )
                    + '/preference';

                form.style.display =
                    'none';

                const token =
                    document.createElement(
                        'input'
                    );

                token.type =
                    'hidden';

                token.name =
                    '_token';

                token.value =
                    csrf;

                const actionInput =
                    document.createElement(
                        'input'
                    );

                actionInput.type =
                    'hidden';

                actionInput.name =
                    'action';

                actionInput.value =
                    action;

                form.appendChild(
                    token
                );

                form.appendChild(
                    actionInput
                );

                document.body.appendChild(
                    form
                );

                form.submit();
            };

            const openMute = (
                conversationId,
                name
            ) => {
                muteConversationId =
                    Number(
                        conversationId
                    );

                const label =
                    document.getElementById(
                        'crm-chat-v332-mute-name'
                    );

                if (label) {
                    label.textContent =
                        name
                        || 'Conversation';
                }

                muteModal.style.display =
                    'flex';
            };

            const closeMute = () => {
                muteModal.style.display =
                    'none';

                muteConversationId =
                    0;
            };

            /*
             * Capture phase intentionally wins over old V3.3 listeners.
             * We use ordinary POST form submission instead of fetch.
             */
            list.addEventListener(
                'click',
                (event) => {
                    const button =
                        event.target.closest(
                            'button'
                        );

                    if (
                        ! button
                        || ! list.contains(
                            button
                        )
                    ) {
                        return;
                    }

                    const title =
                        String(
                            button.title
                            || ''
                        ).toLowerCase();

                    const conversationId =
                        conversationIdFromButton(
                            button
                        );

                    if (
                        title.includes(
                            'pin conversation'
                        )
                        || title.includes(
                            'unpin conversation'
                        )
                    ) {
                        event.preventDefault();
                        event.stopImmediatePropagation();

                        submitPreference(
                            conversationId,
                            title.includes(
                                'unpin'
                            )
                                ? 'unpin'
                                : 'pin'
                        );

                        return;
                    }

                    if (
                        title.includes(
                            'mute conversation'
                        )
                        || title.includes(
                            'muted'
                        )
                    ) {
                        event.preventDefault();
                        event.stopImmediatePropagation();

                        let name =
                            'Conversation';

                        const row =
                            button.parentElement
                                ? button.parentElement.parentElement
                                : null;

                        const link =
                            row
                                ? row.querySelector(
                                    'a[href*="conversation="]'
                                )
                                : null;

                        if (link) {
                            const nameNode =
                                link.querySelector(
                                    'div[style*="font-weight:800"]'
                                );

                            if (nameNode) {
                                name =
                                    String(
                                        nameNode.textContent
                                        || ''
                                    )
                                        .replace(
                                            '📌',
                                            ''
                                        )
                                        .trim();
                            }
                        }

                        openMute(
                            conversationId,
                            name
                        );
                    }
                },
                true
            );

            muteModal
                .querySelectorAll(
                    '[data-v332-pref]'
                )
                .forEach(
                    (button) => {
                        button.addEventListener(
                            'click',
                            () => {
                                submitPreference(
                                    muteConversationId,
                                    button.dataset
                                        .v332Pref
                                );
                            }
                        );
                    }
                );

            document
                .getElementById(
                    'crm-chat-v332-mute-close'
                )
                .addEventListener(
                    'click',
                    closeMute
                );

            muteModal.addEventListener(
                'click',
                (event) => {
                    if (
                        event.target
                        === muteModal
                    ) {
                        closeMute();
                    }
                }
            );

            /*
             * Keep action controls tight even if V3.3 renderer runs.
             */
            const compactActions = () => {
                list
                    .querySelectorAll(
                        'button'
                    )
                    .forEach(
                        (button) => {
                            const title =
                                String(
                                    button.title
                                    || ''
                                ).toLowerCase();

                            if (
                                ! title.includes(
                                    'conversation'
                                )
                                && ! title.includes(
                                    'muted'
                                )
                            ) {
                                return;
                            }

                            button.style.width =
                                '24px';

                            button.style.height =
                                '24px';

                            button.style.padding =
                                '0';

                            button.style.fontSize =
                                '11px';

                            button.style.border =
                                '1px solid #e5e7eb';

                            button.style.background =
                                '#ffffff';
                        }
                    );
            };

            compactActions();

            const observer =
                new MutationObserver(
                    compactActions
                );

            observer.observe(
                list,
                {
                    childList:
                        true,

                    subtree:
                        true,
                }
            );
        })();
    </script>

    {{-- INTERNAL CHAT V3.3.3 NATIVE PIN MUTE --}}
    <div
        id="crm-chat-v333-config"
        data-preference-template="{{ route('admin.internal-chat.preference', ['conversationId' => '__CID__']) }}"
        data-csrf="{{ csrf_token() }}"
        style="display:none;"
    ></div>

    <div
        id="crm-chat-v333-mute-modal"
        style="
            display:none;
            position:fixed;
            inset:0;
            z-index:150;
            align-items:center;
            justify-content:center;
            padding:18px;
            background:rgba(15,23,42,.46);
            backdrop-filter:blur(3px);
        "
    >
        <form
            id="crm-chat-v333-mute-form"
            method="POST"
            action=""
            style="
                width:min(92vw,350px);
                overflow:hidden;
                border:1px solid #e5e7eb;
                border-radius:18px;
                background:#ffffff;
                box-shadow:0 28px 80px rgba(15,23,42,.26);
            "
        >
            @csrf

            <div style="padding:15px 16px;border-bottom:1px solid #e5e7eb;">
                <div style="font-size:15px;font-weight:800;color:#0f172a;">
                    Mute Conversation
                </div>

                <div
                    id="crm-chat-v333-mute-name"
                    style="margin-top:3px;font-size:12px;color:#64748b;"
                ></div>
            </div>

            <div style="padding:8px;">
                <button
                    type="submit"
                    name="action"
                    value="mute_1_hour"
                    style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;"
                >
                    🔕 1 jam
                </button>

                <button
                    type="submit"
                    name="action"
                    value="mute_today"
                    style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;"
                >
                    🔕 Sampai akhir hari
                </button>

                <button
                    type="submit"
                    name="action"
                    value="mute_forever"
                    style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;"
                >
                    🔕 Sampai diaktifkan kembali
                </button>

                <button
                    type="submit"
                    name="action"
                    value="unmute"
                    style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;"
                >
                    🔔 Unmute
                </button>
            </div>

            <div
                style="
                    display:flex;
                    align-items:center;
                    justify-content:flex-end;
                    padding:10px 16px;
                    border-top:1px solid #e5e7eb;
                "
            >
                <button
                    type="button"
                    id="crm-chat-v333-mute-close"
                    class="secondary-button"
                >
                    Close
                </button>
            </div>
        </form>
    </div>

    <script>
        (() => {
            const config =
                document.getElementById(
                    'crm-chat-v333-config'
                );

            const modal =
                document.getElementById(
                    'crm-chat-v333-mute-modal'
                );

            const form =
                document.getElementById(
                    'crm-chat-v333-mute-form'
                );

            const name =
                document.getElementById(
                    'crm-chat-v333-mute-name'
                );

            if (
                ! config
                || ! modal
                || ! form
            ) {
                return;
            }

            const template =
                String(
                    config.dataset.preferenceTemplate
                    || ''
                );

            const preferenceUrl = (
                conversationId
            ) =>
                template.replace(
                    '__CID__',
                    encodeURIComponent(
                        String(
                            conversationId
                        )
                    )
                );

            window.crmChatV333OpenMute =
                function (
                    conversationId,
                    conversationName,
                    muteLabel
                ) {
                    form.action =
                        preferenceUrl(
                            conversationId
                        );

                    if (name) {
                        name.textContent =
                            String(
                                conversationName
                                || 'Conversation'
                            )
                            +(
                                muteLabel
                                    ? ' · '
                                        +muteLabel
                                    : ''
                            );
                    }

                    modal.style.display =
                        'flex';

                    return false;
                };

            const close =
                () => {
                    modal.style.display =
                        'none';

                    form.action =
                        '';
                };

            document
                .getElementById(
                    'crm-chat-v333-mute-close'
                )
                .addEventListener(
                    'click',
                    close
                );

            modal.addEventListener(
                'click',
                (event) => {
                    if (
                        event.target
                        === modal
                    ) {
                        close();
                    }
                }
            );
        })();
    </script>

    {{-- INTERNAL CHAT V3.3.5 MODERN MUTE DIALOG --}}
    <dialog
        id="crm-chat-v335-mute-dialog"
        style="
            width:min(92vw,390px);
            max-width:390px;
            padding:0;
            border:1px solid #e5e7eb;
            border-radius:18px;
            background:#ffffff;
            box-shadow:0 28px 80px rgba(15,23,42,.28);
            overflow:hidden;
        "
    >
        <form
            id="crm-chat-v335-mute-form"
            method="POST"
            action=""
            style="margin:0;"
        >
            @csrf

            <div
                style="
                    display:flex;
                    align-items:flex-start;
                    justify-content:space-between;
                    gap:12px;
                    padding:15px 16px;
                    border-bottom:1px solid #e5e7eb;
                    background:#ffffff;
                "
            >
                <div style="min-width:0;">
                    <div style="font-size:15px;font-weight:800;color:#0f172a;">
                        Notification Settings
                    </div>

                    <div
                        id="crm-chat-v335-mute-name"
                        style="
                            margin-top:3px;
                            overflow:hidden;
                            text-overflow:ellipsis;
                            white-space:nowrap;
                            font-size:11px;
                            color:#64748b;
                        "
                    ></div>
                </div>

                <button
                    type="button"
                    id="crm-chat-v335-mute-close"
                    class="secondary-button"
                    style="flex:0 0 auto;"
                >
                    Close
                </button>
            </div>

            <div style="padding:8px;background:#ffffff;">
                <button
                    type="submit"
                    name="action"
                    value="mute_1_hour"
                    style="
                        display:block;
                        width:100%;
                        padding:10px 12px;
                        border-radius:10px;
                        text-align:left;
                        font-size:12px;
                    "
                >
                    🔕 &nbsp;Mute 1 jam
                </button>

                <button
                    type="submit"
                    name="action"
                    value="mute_today"
                    style="
                        display:block;
                        width:100%;
                        padding:10px 12px;
                        border-radius:10px;
                        text-align:left;
                        font-size:12px;
                    "
                >
                    🔕 &nbsp;Sampai akhir hari
                </button>

                <button
                    type="submit"
                    name="action"
                    value="mute_forever"
                    style="
                        display:block;
                        width:100%;
                        padding:10px 12px;
                        border-radius:10px;
                        text-align:left;
                        font-size:12px;
                    "
                >
                    🔕 &nbsp;Sampai diaktifkan kembali
                </button>

                <div
                    style="
                        height:1px;
                        margin:6px 3px;
                        background:#e5e7eb;
                    "
                ></div>

                <button
                    type="submit"
                    name="action"
                    value="unmute"
                    style="
                        display:block;
                        width:100%;
                        padding:10px 12px;
                        border-radius:10px;
                        text-align:left;
                        font-size:12px;
                    "
                >
                    🔔 &nbsp;Unmute
                </button>
            </div>
        </form>
    </dialog>

    <script>
        (() => {
            const dialog =
                document.getElementById(
                    'crm-chat-v335-mute-dialog'
                );

            const form =
                document.getElementById(
                    'crm-chat-v335-mute-form'
                );

            const name =
                document.getElementById(
                    'crm-chat-v335-mute-name'
                );

            const config =
                document.getElementById(
                    'crm-chat-v333-config'
                );

            if (
                ! dialog
                || ! form
                || ! config
            ) {
                return;
            }

            const template =
                String(
                    config.dataset.preferenceTemplate
                    || ''
                );

            const preferenceUrl = (
                conversationId
            ) =>
                template.replace(
                    '__CID__',
                    encodeURIComponent(
                        String(
                            conversationId
                        )
                    )
                );

            window.crmChatV335OpenMute =
                function (
                    conversationId,
                    conversationName,
                    muteLabel
                ) {
                    form.action =
                        preferenceUrl(
                            conversationId
                        );

                    if (name) {
                        name.textContent =
                            String(
                                conversationName
                                || 'Conversation'
                            )
                            +(
                                muteLabel
                                    ? ' · '
                                        +muteLabel
                                    : ''
                            );
                    }

                    if (
                        typeof dialog.showModal
                        === 'function'
                    ) {
                        if (! dialog.open) {
                            dialog.showModal();
                        }
                    } else {
                        dialog.setAttribute(
                            'open',
                            'open'
                        );

                        dialog.style.position =
                            'fixed';

                        dialog.style.left =
                            '50%';

                        dialog.style.top =
                            '50%';

                        dialog.style.transform =
                            'translate(-50%,-50%)';

                        dialog.style.zIndex =
                            '160';
                    }

                    return false;
                };

            const close =
                () => {
                    if (
                        typeof dialog.close
                        === 'function'
                        && dialog.open
                    ) {
                        dialog.close();
                    } else {
                        dialog.removeAttribute(
                            'open'
                        );
                    }

                    form.action =
                        '';
                };

            document
                .getElementById(
                    'crm-chat-v335-mute-close'
                )
                .addEventListener(
                    'click',
                    close
                );

            dialog.addEventListener(
                'click',
                (event) => {
                    const rect =
                        dialog.getBoundingClientRect();

                    const inside =
                        event.clientX >= rect.left
                        && event.clientX <= rect.right
                        && event.clientY >= rect.top
                        && event.clientY <= rect.bottom;

                    if (! inside) {
                        close();
                    }
                }
            );

            dialog.addEventListener(
                'cancel',
                (event) => {
                    event.preventDefault();
                    close();
                }
            );
        })();
    </script>

    {{-- INTERNAL CHAT V3.3.6 NATIVE MUTE SELECT FINAL --}}
    {{-- INTERNAL CHAT V3.3.7 PREFERENCE TOAST --}}
    @if (session('internal_chat_preference_notice'))
        <div
            id="crm-chat-v337-toast"
            style="
                position:fixed;
                right:22px;
                top:92px;
                z-index:180;
                max-width:360px;
                padding:11px 14px;
                border:1px solid #bbf7d0;
                border-radius:12px;
                background:#f0fdf4;
                color:#166534;
                box-shadow:0 14px 36px rgba(15,23,42,.15);
                font-size:12px;
                font-weight:700;
            "
        >
            ✓ {{ session('internal_chat_preference_notice') }}
        </div>

        <script>
            window.setTimeout(
                () => {
                    const toast =
                        document.getElementById(
                            'crm-chat-v337-toast'
                        );

                    if (toast) {
                        toast.remove();
                    }
                },
                3500
            );
        </script>
    @endif
    {{-- INTERNAL CHAT V3.3.8 EXPLICIT APPLY FINAL --}}
    {{-- INTERNAL CHAT V3.3.10 LEGACY UNREAD CLEANUP --}}

    {{-- INTERNAL CHAT NEWEST PANEL V1.3 HOTFIX --}}
    @if ($conversation)
        <script>
            (() => {
                const bootChatNewestV13 = () => {
                    const root = document.getElementById('crm-chat-messages');
                    const stack = document.getElementById('crm-chat-message-stack');
                    const bottom = document.getElementById('crm-chat-bottom');
                    const form = document.getElementById('crm-chat-send-form');

                    if (! root || ! stack || root.dataset.newestPanelV13 === '1') {
                        return;
                    }

                    root.dataset.newestPanelV13 = '1';
                    root.dataset.newestRuntimeV13 = 'active';
                    root.style.overflowAnchor = 'none';

                    if ('scrollRestoration' in history) {
                        history.scrollRestoration = 'manual';
                    }

                    let followNewest = true;
                    let pointerActive = false;
                    let guardTimer = null;
                    let guardUntil = 0;

                    const scrollCandidates = () => {
                        const candidates = [root];
                        let element = root.parentElement;

                        while (element && element !== document.body && candidates.length < 7) {
                            candidates.push(element);
                            element = element.parentElement;
                        }

                        const scrollable = candidates.filter((candidate) => {
                            const style = window.getComputedStyle(candidate);
                            const overflowY = style.overflowY;

                            return (
                                overflowY === 'auto'
                                || overflowY === 'scroll'
                                || overflowY === 'overlay'
                            ) && candidate.scrollHeight > candidate.clientHeight + 2;
                        });

                        return scrollable.length > 0 ? scrollable : [root];
                    };

                    const distanceFromNewest = (element) => Math.max(
                        0,
                        element.scrollHeight - element.clientHeight - element.scrollTop
                    );

                    const newestMessage = () => {
                        const messages = stack.querySelectorAll('[data-message-id]');

                        return messages.length > 0
                            ? messages[messages.length - 1]
                            : bottom;
                    };

                    const goNewest = () => {
                        if (! followNewest) {
                            return;
                        }

                        const candidates = scrollCandidates();

                        candidates.forEach((candidate) => {
                            candidate.scrollTop = candidate.scrollHeight + 100000;
                        });

                        const target = newestMessage();

                        if (target) {
                            target.scrollIntoView({
                                behavior: 'auto',
                                block: 'end',
                                inline: 'nearest',
                            });
                        }

                        candidates.forEach((candidate) => {
                            candidate.scrollTop = candidate.scrollHeight + 100000;
                        });

                        root.dataset.newestDistanceV13 = String(
                            Math.round(distanceFromNewest(root))
                        );
                    };

                    const scheduleNewest = () => {
                        window.requestAnimationFrame(() => {
                            goNewest();
                            window.requestAnimationFrame(goNewest);
                        });

                        [0, 40, 100, 220, 450, 900, 1600, 3000, 5000].forEach((delay) => {
                            window.setTimeout(goNewest, delay);
                        });
                    };

                    const guardNewest = (duration = 10000) => {
                        followNewest = true;
                        guardUntil = Date.now() + duration;

                        if (guardTimer !== null) {
                            window.clearInterval(guardTimer);
                        }

                        scheduleNewest();
                        guardTimer = window.setInterval(() => {
                            if (! followNewest || Date.now() >= guardUntil) {
                                window.clearInterval(guardTimer);
                                guardTimer = null;
                                return;
                            }

                            goNewest();
                        }, 120);
                    };

                    root.addEventListener('wheel', (event) => {
                        if (event.deltaY < 0) {
                            followNewest = false;
                        } else if (distanceFromNewest(root) <= 96) {
                            followNewest = true;
                        }
                    }, { passive: true });

                    let touchY = null;

                    root.addEventListener('touchstart', (event) => {
                        touchY = event.touches?.[0]?.clientY ?? null;
                    }, { passive: true });

                    root.addEventListener('touchmove', (event) => {
                        const currentY = event.touches?.[0]?.clientY ?? null;

                        if (touchY !== null && currentY !== null && currentY > touchY + 4) {
                            followNewest = false;
                        }
                    }, { passive: true });

                    root.addEventListener('pointerdown', () => {
                        pointerActive = true;
                    }, { passive: true });

                    window.addEventListener('pointerup', () => {
                        pointerActive = false;
                    }, { passive: true });

                    root.addEventListener('scroll', () => {
                        const nearNewest = distanceFromNewest(root) <= 64;

                        if (nearNewest) {
                            followNewest = true;
                        } else if (pointerActive) {
                            followNewest = false;
                        }
                    }, { passive: true });

                    document.addEventListener('submit', (event) => {
                        if (event.target === form || event.target?.id === 'crm-chat-send-form') {
                            guardNewest(10000);
                        }
                    }, true);

                    new MutationObserver(() => {
                        if (followNewest) {
                            scheduleNewest();
                        }
                    }).observe(stack, { childList: true, subtree: true });

                    if ('ResizeObserver' in window) {
                        new ResizeObserver(() => {
                            if (followNewest) {
                                goNewest();
                            }
                        }).observe(stack);
                    }

                    window.crmChatGoNewest = () => {
                        guardNewest(10000);
                    };

                    window.crmChatNewestDiagnostics = () => ({
                        version: '1.3',
                        followNewest,
                        rootScrollTop: root.scrollTop,
                        rootScrollHeight: root.scrollHeight,
                        rootClientHeight: root.clientHeight,
                        rootDistance: distanceFromNewest(root),
                        scrollers: scrollCandidates().map((element) => ({
                            id: element.id || null,
                            scrollTop: element.scrollTop,
                            scrollHeight: element.scrollHeight,
                            clientHeight: element.clientHeight,
                        })),
                    });

                    guardNewest(12000);
                    window.addEventListener('load', () => guardNewest(10000), { once: true });
                    window.addEventListener('pageshow', () => guardNewest(10000));
                    document.addEventListener('visibilitychange', () => {
                        if (! document.hidden && followNewest) {
                            guardNewest(5000);
                        }
                    });
                    document.fonts?.ready?.then(() => guardNewest(5000));
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bootChatNewestV13, { once: true });
                } else {
                    bootChatNewestV13();
                }
            })();
        </script>
    @endif
</x-admin::layouts>
