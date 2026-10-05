<v-crm-mobile-menu class="crm-mobile-menu">
    <button type="button" class="crm-mobile-menu-button" aria-label="Buka menu"><span class="icon-menu" aria-hidden="true"></span></button>
</v-crm-mobile-menu>

@pushOnce('scripts', 'crm-mobile-menu')
    <script type="text/x-template" id="v-crm-mobile-menu-template">
        <div class="crm-mobile-menu">
            <button ref="trigger" type="button" class="crm-mobile-menu-button" aria-label="Buka menu" aria-controls="crm-mobile-dialog" :aria-expanded="isOpen" @click="open">
                <span class="icon-menu" aria-hidden="true"></span>
            </button>
            <dialog ref="dialog" id="crm-mobile-dialog" class="crm-mobile-dialog" aria-label="Menu utama CRM" @close="onClose" @click="closeBackdrop($event)">
                <div class="crm-mobile-dialog-header">
                    <span>Menu utama</span>
                    <button type="button" class="crm-mobile-close" aria-label="Tutup menu" autofocus @click="close"><span class="icon-cross-large" aria-hidden="true"></span></button>
                </div>
                @include('admin::components.layouts.sidebar.navigation', [
                    'variant' => 'mobile',
                    'groups' => app(\Webkul\Admin\Services\SidebarNavigationService::class)->getGroups(),
                ])
            </dialog>
        </div>
    </script>
    <script type="module">
        app.component('v-crm-mobile-menu', {
            template: '#v-crm-mobile-menu-template',
            data() {
                return { isOpen: false, previousOverflow: '' };
            },
            mounted() {
                this.desktopQuery = window.matchMedia('(min-width: 1024px)');
                this.onViewportChange = () => { if (this.desktopQuery.matches) this.close(); };
                this.desktopQuery.addEventListener('change', this.onViewportChange);
            },
            beforeUnmount() {
                this.desktopQuery.removeEventListener('change', this.onViewportChange);
                if (this.isOpen) document.body.style.overflow = this.previousOverflow;
            },
            methods: {
                open() {
                    if (this.isOpen) return;
                    this.previousOverflow = document.body.style.overflow;
                    this.$refs.dialog.showModal();
                    document.body.style.overflow = 'hidden';
                    this.isOpen = true;
                    this.$nextTick(() => {
                        const area = this.$refs.dialog.querySelector('.crm-sidebar-scroll');
                        const active = area?.querySelector('[aria-current="page"]');
                        if (active) area.scrollTop += active.getBoundingClientRect().top - area.getBoundingClientRect().top - area.clientHeight / 2;
                    });
                },
                close() {
                    if (this.$refs.dialog.open) this.$refs.dialog.close();
                },
                onClose() {
                    document.body.style.overflow = this.previousOverflow;
                    this.isOpen = false;
                },
                closeBackdrop(event) {
                    if (event.target !== this.$refs.dialog) return;
                    const box = this.$refs.dialog.getBoundingClientRect();
                    if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) this.close();
                },
            },
        });
    </script>
@endPushOnce
