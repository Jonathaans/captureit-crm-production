@php($sidebarColors = app(\Webkul\Admin\Services\SidebarThemeService::class)->cssVariables())

<style>
    .crm-layout { --crm-sidebar-width: 268px; }
    .crm-layout[data-sidebar-collapsed="true"] { --crm-sidebar-width: 76px; }
    .crm-sidebar, .crm-mobile-dialog {
        @foreach ($sidebarColors as $property => $value)
            {{ $property }}: {{ $value }};
        @endforeach
    }
    .crm-sidebar {
        display: flex;
        flex-direction: column;
        min-height: 0;
        color: var(--crm-nav-text);
        background: var(--crm-nav-bg);
        font-size: 13px;
        line-height: 1.5;
    }
    .crm-sidebar--desktop {
        position: fixed;
        inset-block: 61px 0;
        inset-inline-start: 0;
        z-index: 9999;
        width: var(--crm-sidebar-width, 268px);
        border-inline-end: 1px solid rgba(var(--crm-nav-ink), .12);
        transition: width .18s ease;
    }
    .crm-sidebar-workspace {
        display: flex;
        align-items: center;
        flex: 0 0 auto;
        gap: 10px;
        min-height: 64px;
        margin: 16px 12px 12px;
        padding: 11px 10px;
        border: 1px solid rgba(var(--crm-nav-ink), .13);
        border-radius: 12px;
        background: rgba(var(--crm-nav-ink), .07);
    }
    .crm-workspace-symbol, .crm-sidebar-avatar {
        display: grid;
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
        color: var(--crm-nav-accent-icon);
        background: rgba(var(--crm-nav-accent-rgb), .13);
    }
    .crm-workspace-symbol svg { width: 22px; height: 22px; stroke: currentColor; stroke-width: 2; stroke-linecap: round; }
    .crm-workspace-copy, .crm-sidebar-account-copy { display: flex; flex: 1; flex-direction: column; min-width: 0; gap: 3px; }
    .crm-workspace-copy strong { font-size: 10px; letter-spacing: 1.2px; font-weight: 700; white-space: nowrap; }
    .crm-workspace-copy span { color: var(--crm-nav-muted); font-size: 10px; white-space: nowrap; }
    .crm-sidebar-toggle {
        display: grid;
        flex: 0 0 28px;
        width: 28px;
        height: 32px;
        place-items: center;
        border: 0;
        border-radius: 7px;
        color: var(--crm-nav-muted);
        background: transparent;
        cursor: pointer;
    }
    .crm-sidebar-toggle:hover { background: rgba(var(--crm-nav-ink), .12); color: var(--crm-nav-text); }
    .crm-sidebar-toggle svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    [dir="rtl"] .crm-sidebar-toggle svg { transform: rotate(180deg); }
    .crm-sidebar-scroll {
        flex: 1 1 0;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0 8px 22px;
        overscroll-behavior-y: contain;
        scrollbar-width: thin;
        scrollbar-color: var(--crm-nav-muted) transparent;
        scrollbar-gutter: stable;
    }
    .crm-sidebar-scroll::-webkit-scrollbar { display: block; width: 6px; }
    .crm-sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
    .crm-sidebar-scroll::-webkit-scrollbar-thumb { border-radius: 12px; background: var(--crm-nav-muted); }
    .crm-nav-group { margin-block: 9px 20px; }
    .crm-nav-heading {
        margin: 0;
        padding: 9px 13px 8px;
        color: var(--crm-nav-muted);
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 1.8px;
        line-height: 1.5;
        text-transform: uppercase;
    }
    .crm-nav-list, .crm-nav-children { list-style: none; margin: 0; padding: 0; }
    .crm-nav-list { display: grid; gap: 4px; }
    .crm-nav-link {
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 44px;
        padding: 10px 13px;
        border-radius: 9px;
        color: var(--crm-nav-text);
        text-decoration: none;
        cursor: pointer;
        font-weight: 500;
        transition: background-color .15s ease, color .15s ease;
    }
    .crm-nav-link:hover, .crm-nav-child:hover { color: var(--crm-nav-text); background: rgba(var(--crm-nav-ink), .09); }
    .crm-nav-link.is-active, .crm-nav-child.is-active {
        color: var(--crm-nav-active-text);
        background: var(--crm-nav-active);
        font-weight: 650;
    }
    .crm-nav-link.is-active::before, .crm-nav-child.is-active::before {
        position: absolute;
        content: '';
        inset-block: 8px;
        inset-inline-start: 0;
        width: 3px;
        border-radius: 5px;
        background: var(--crm-nav-accent);
    }
    .crm-nav-link.is-active-parent { color: var(--crm-nav-text); background: rgba(var(--crm-nav-ink), .09); }
    .crm-sidebar .crm-nav-icon, .dark .crm-sidebar .crm-nav-icon {
        flex: 0 0 20px;
        width: 20px;
        color: var(--crm-nav-muted);
        font-size: 20px;
        line-height: 1;
        text-align: center;
    }
    .crm-sidebar .is-active .crm-nav-icon { color: var(--crm-nav-active-icon); }
    .crm-sidebar .is-active-parent .crm-nav-icon { color: var(--crm-nav-accent-icon); }
    .crm-nav-label { flex: 1; min-width: 0; overflow-wrap: anywhere; line-height: 1.4; }
    .crm-nav-chevron { width: 16px; height: 16px; flex: 0 0 16px; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
    .crm-nav-disclosure > summary { list-style: none; }
    .crm-nav-disclosure > summary::-webkit-details-marker { display: none; }
    .crm-nav-disclosure[open] > summary .crm-nav-chevron { transform: rotate(180deg); }
    .crm-nav-children { margin: 6px 8px 7px 24px; padding-inline-start: 10px; border-inline-start: 1px solid rgba(var(--crm-nav-ink), .2); }
    [dir="rtl"] .crm-nav-children { margin-inline: 24px 8px; }
    .crm-nav-child { position: relative; display: block; margin-block: 2px; padding: 10px 11px; min-height: 40px; border-radius: 7px; color: var(--crm-nav-muted); font-size: 12px; text-decoration: none; overflow-wrap: anywhere; }
    .crm-sidebar :is(a, summary, button):focus-visible, .crm-mobile-menu-button:focus-visible, .crm-mobile-close:focus-visible { outline: 2px solid currentColor; outline-offset: -2px; }
    .crm-sidebar-account {
        display: flex;
        align-items: center;
        flex: 0 0 auto;
        gap: 11px;
        padding: 14px 20px max(14px, env(safe-area-inset-bottom));
        border-top: 1px solid rgba(var(--crm-nav-ink), .12);
        background: rgba(var(--crm-nav-ink), .04);
    }
    .crm-sidebar-avatar { background: rgba(var(--crm-nav-ink), .12); color: var(--crm-nav-text); border: 1px solid rgba(var(--crm-nav-ink), .14); border-radius: 50%; font-size: 13px; font-weight: 700; }
    .crm-sidebar-account-copy strong { font-size: 12px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .crm-sidebar-account-copy span { font-size: 10px; color: var(--crm-nav-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .crm-sidebar-loading { padding: 24px; }
    .crm-sidebar.is-collapsed .crm-sidebar-workspace { justify-content: center; padding: 10px 0; margin-inline: 10px; }
    .crm-sidebar.is-collapsed :is(.crm-workspace-symbol, .crm-workspace-copy, .crm-sidebar-account-copy, .crm-nav-chevron, .crm-nav-children) { display: none; }
    .crm-sidebar.is-collapsed .crm-nav-label { position: absolute; width: 1px; height: 1px; padding: 0; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
    .crm-sidebar.is-collapsed .crm-nav-heading { font-size: 0; padding: 0; margin: 12px 10px; height: 1px; background: rgba(var(--crm-nav-ink), .18); }
    .crm-sidebar.is-collapsed .crm-nav-link { justify-content: center; padding-inline: 10px; }
    .crm-sidebar.is-collapsed .crm-nav-group { margin-block: 7px 13px; }
    .crm-sidebar.is-collapsed .crm-sidebar-account { justify-content: center; padding-inline: 10px; }
    .crm-sidebar.is-collapsed .crm-sidebar-toggle svg { transform: rotate(180deg); }
    [dir="rtl"] .crm-sidebar.is-collapsed .crm-sidebar-toggle svg { transform: none; }
    .crm-mobile-menu-button, .crm-mobile-close { display: grid; place-items: center; width: 40px; height: 40px; border: 0; border-radius: 8px; background: transparent; cursor: pointer; font-size: 23px; }
    .crm-mobile-menu-button:hover { background: rgba(128, 128, 128, .1); }
    .crm-mobile-dialog {
        display: none;
        flex-direction: column;
        position: fixed;
        inset-block: 0;
        inset-inline: 0 auto;
        width: min(300px, calc(100vw - 36px));
        max-width: none;
        height: 100vh;
        height: 100dvh;
        max-height: none;
        margin: 0;
        padding: 0;
        border: 0;
        color: var(--crm-nav-text);
        background: var(--crm-nav-bg);
        overflow: hidden;
        box-shadow: 8px 0 40px rgba(15, 23, 42, .2);
    }
    .crm-mobile-dialog[open] { display: flex; }
    .crm-mobile-dialog::backdrop { background: rgba(15, 23, 42, .55); }
    .crm-mobile-dialog-header { display: flex; align-items: center; justify-content: space-between; flex: 0 0 auto; gap: 12px; padding: 10px 15px; border-bottom: 1px solid rgba(var(--crm-nav-ink), .13); font-weight: 600; }
    .crm-mobile-close { color: var(--crm-nav-text); }
    .crm-mobile-close:hover { background: rgba(var(--crm-nav-ink), .1); }
    .crm-sidebar--mobile { width: 100%; flex: 1 1 0; }
    @media (min-width: 1024px) {
        .crm-mobile-menu { display: none; }
        .crm-page-content { padding-inline-start: calc(var(--crm-sidebar-width) + 16px); transition: padding-inline-start .18s ease; }
        .crm-page-footer { inset-inline-start: var(--crm-sidebar-width); transition: inset-inline-start .18s ease; }
    }
    @media (max-width: 1023px) { .crm-sidebar--desktop { display: none; } }
    @media (prefers-reduced-motion: reduce) {
        .crm-sidebar--desktop, .crm-page-content, .crm-page-footer, .crm-nav-link { transition: none; }
    }
</style>
