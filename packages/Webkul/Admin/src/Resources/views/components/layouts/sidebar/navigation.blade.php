<v-crm-sidebar variant="{{ $variant }}" storage-key="captureit.sidebar.v1.{{ auth()->guard('user')->id() }}">
    <aside class="crm-sidebar crm-sidebar--{{ $variant }}" aria-label="Menu utama">
        <div class="crm-sidebar-loading">Memuat menu…</div>
    </aside>
</v-crm-sidebar>

@pushOnce('scripts', 'crm-categorized-sidebar')
    <script type="text/x-template" id="v-crm-sidebar-template">
        <aside class="crm-sidebar" :class="['crm-sidebar--' + variant, { 'is-collapsed': collapsed }]" aria-label="Menu utama">
            <div class="crm-sidebar-workspace">
                <div class="crm-workspace-symbol" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 3v18M3 12h18M5.6 5.6l12.8 12.8M5.6 18.4 18.4 5.6" /></svg>
                </div>
                <div class="crm-workspace-copy">
                    <strong>CRM WORKSPACE</strong>
                    <span>Penjualan &amp; operasional</span>
                </div>
                <button v-if="variant === 'desktop'" type="button" class="crm-sidebar-toggle" :aria-label="collapsed ? 'Perluas menu' : 'Ringkas menu'" :title="collapsed ? 'Perluas menu' : 'Ringkas menu'" :aria-expanded="!collapsed" aria-controls="crm-desktop-navigation" @click="setCollapsed(!collapsed)">
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m12 5-5 5 5 5" /></svg>
                </button>
            </div>
            <nav ref="scrollArea" class="crm-sidebar-scroll" :id="'crm-' + variant + '-navigation'" aria-label="Navigasi CRM" @scroll.passive="rememberScroll">
                @include('admin::components.layouts.sidebar.menu')
            </nav>
            @php($sidebarUser = auth()->guard('user')->user())
            <div class="crm-sidebar-account" title="{{ $sidebarUser?->name }}">
                <span class="crm-sidebar-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($sidebarUser?->name ?? '', 0, 1)) }}</span>
                <div class="crm-sidebar-account-copy">
                    <strong>{{ $sidebarUser?->name }}</strong>
                    <span>{{ $sidebarUser?->role?->name }}</span>
                </div>
            </div>
        </aside>
    </script>
    <script type="module">
        app.component('v-crm-sidebar', {
            template: '#v-crm-sidebar-template',
            props: ['variant', 'storageKey'],
            data() {
                return { collapsed: false };
            },
            mounted() {
                if (this.variant === 'desktop') {
                    try {
                        this.setCollapsed(localStorage.getItem(this.storageKey) === 'collapsed');
                    } catch (_) {
                        this.setCollapsed(false);
                    }
                }
                this.$nextTick(() => this.restoreScroll());
            },
            methods: {
                setCollapsed(value) {
                    this.collapsed = this.variant === 'desktop' && value;
                    const layout = this.$el.closest('[data-crm-layout]');
                    if (layout) layout.dataset.sidebarCollapsed = String(this.collapsed);
                    if (this.variant === 'desktop') {
                        try {
                            localStorage.setItem(this.storageKey, this.collapsed ? 'collapsed' : 'expanded');
                        } catch (_) {
                            // Keep navigation usable when browser storage is unavailable.
                        }
                    }
                },
                expandSubmenu(event) {
                    if (this.collapsed) {
                        event.preventDefault();
                        this.setCollapsed(false);
                        event.currentTarget.closest('details').open = true;
                    }
                },
                rememberScroll() {
                    if (!this.$refs.scrollArea?.clientHeight) return;
                    try {
                        sessionStorage.setItem(this.storageKey + '.' + this.variant + '.scroll', String(this.$refs.scrollArea.scrollTop));
                    } catch (_) {}
                },
                restoreScroll() {
                    const area = this.$refs.scrollArea;
                    if (!area?.clientHeight) return;
                    try {
                        area.scrollTop = Number(sessionStorage.getItem(this.storageKey + '.' + this.variant + '.scroll')) || 0;
                    } catch (_) {}
                    const active = area.querySelector('[aria-current="page"]');
                    if (!active || !active.getClientRects().length) return;
                    const bounds = area.getBoundingClientRect();
                    const item = active.getBoundingClientRect();
                    if (item.top < bounds.top || item.bottom > bounds.bottom) {
                        area.scrollTop += item.top - bounds.top - area.clientHeight / 2 + item.height / 2;
                    }
                },
            },
        });
    </script>
@endPushOnce
