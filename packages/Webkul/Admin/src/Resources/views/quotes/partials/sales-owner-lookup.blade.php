<script
    type="text/x-template"
    id="v-quote-sales-owner-lookup-template"
>
    <div
        ref="lookup"
        class="relative w-full"
    >
        <input
            type="hidden"
            name="user_id"
            :value="selectedOwner?.id || ''"
        >

        <div class="relative">
            <input
                ref="searchInput"
                v-model="searchTerm"
                type="text"
                autocomplete="off"
                role="combobox"
                aria-autocomplete="list"
                aria-label="Sales Owner"
                :aria-expanded="isOpen ? 'true' : 'false'"
                :aria-controls="listboxId"
                :aria-activedescendant="activeDescendant"
                :aria-required="disabled ? 'false' : 'true'"
                :disabled="disabled"
                :placeholder="disabled ? 'Sales Owner terkunci' : 'Ketik minimal 2 huruf nama atau email'"
                :class="[
                    'w-full rounded-md border px-3 py-2 pr-10 text-sm outline-none transition',
                    disabled
                        ? 'cursor-not-allowed border-gray-200 bg-gray-100 text-gray-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-500'
                        : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400 focus:border-brandColor dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300'
                ]"
                @input="handleInput"
                @focus="handleFocus"
                @keydown.down.prevent="moveHighlight(1)"
                @keydown.up.prevent="moveHighlight(-1)"
                @keydown.enter.prevent="selectHighlighted"
                @keydown.esc="closeResults"
            >

            <button
                v-if="selectedOwner && ! disabled"
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                aria-label="Hapus Sales Owner"
                @click="clearSelection"
            >
                &times;
            </button>
        </div>

        <div
            v-if="isOpen && ! disabled"
            :id="listboxId"
            role="listbox"
            class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                v-if="isLoading"
                class="px-3 py-3 text-sm text-gray-500"
            >
                Mencari Sales Owner...
            </div>

            <div
                v-else-if="loadError"
                class="px-3 py-3 text-sm text-red-600"
            >
                @{{ loadError }}
            </div>

            <div
                v-else-if="selectedOwner && searchTerm === selectedOwner.name"
                class="px-3 py-3"
            >
                <p class="text-sm font-medium text-gray-800 dark:text-white">
                    @{{ selectedOwner.name }}
                </p>

                <p class="mt-0.5 text-xs text-gray-500">
                    @{{ selectedOwner.role_name || 'Current Owner' }}
                    <span v-if="selectedOwner.email"> · @{{ selectedOwner.email }}</span>
                    <span v-if="selectedOwner.is_legacy_current"> · Owner lama</span>
                </p>

                <p class="mt-2 text-xs text-gray-400">
                    Ketik nama atau email untuk mengganti.
                </p>
            </div>

            <div
                v-else-if="normalizedSearchTerm.length < minimumCharacters"
                class="px-3 py-3 text-sm text-gray-500"
            >
                Ketik minimal @{{ minimumCharacters }} huruf.
            </div>

            <div
                v-else-if="! results.length"
                class="px-3 py-3 text-sm text-gray-500"
            >
                Sales Owner tidak ditemukan.
            </div>

            <template v-else>
                <button
                    v-for="(owner, index) in results"
                    :id="`${listboxId}-option-${index}`"
                    :key="owner.id"
                    type="button"
                    role="option"
                    :aria-selected="highlightedIndex === index ? 'true' : 'false'"
                    :class="[
                        'block w-full border-b border-gray-100 px-3 py-2.5 text-left last:border-b-0 dark:border-gray-800',
                        highlightedIndex === index
                            ? 'bg-gray-100 dark:bg-gray-800'
                            : 'hover:bg-gray-50 dark:hover:bg-gray-800'
                    ]"
                    @mouseenter="highlightedIndex = index"
                    @mousedown.prevent="selectOwner(owner)"
                >
                    <span class="block text-sm font-medium text-gray-800 dark:text-white">
                        @{{ owner.name }}
                    </span>

                    <span class="mt-0.5 block text-xs text-gray-500">
                        @{{ owner.role_name }}<span v-if="owner.email"> · @{{ owner.email }}</span>
                    </span>
                </button>
            </template>
        </div>
    </div>
</script>

<script type="module">
    app.component('v-quote-sales-owner-lookup', {
        template: '#v-quote-sales-owner-lookup-template',

        props: {
            initialOwner: {
                type: [Object, Array],
                default: () => ({}),
            },

            searchUrl: {
                type: String,
                required: true,
            },

            disabled: {
                type: Boolean,
                default: false,
            },
        },

        data() {
            const initialOwner = this.initialOwner?.id
                ? { ...this.initialOwner }
                : null;

            return {
                minimumCharacters: 2,
                debounceMilliseconds: 300,
                selectedOwner: initialOwner,
                searchTerm: initialOwner?.name || '',
                results: [],
                isOpen: false,
                isLoading: false,
                loadError: '',
                highlightedIndex: -1,
                debounceTimer: null,
                requestSequence: 0,
                listboxId: `quote-sales-owner-${Math.random().toString(36).slice(2)}`,
            };
        },

        computed: {
            normalizedSearchTerm() {
                return this.searchTerm.trim();
            },

            activeDescendant() {
                if (this.highlightedIndex < 0) {
                    return undefined;
                }

                return `${this.listboxId}-option-${this.highlightedIndex}`;
            },
        },

        mounted() {
            document.addEventListener('mousedown', this.handleOutsideClick);
        },

        beforeUnmount() {
            document.removeEventListener('mousedown', this.handleOutsideClick);
            window.clearTimeout(this.debounceTimer);
            this.requestSequence++;
        },

        methods: {
            handleFocus(event) {
                if (this.disabled) {
                    return;
                }

                this.isOpen = true;

                if (this.selectedOwner) {
                    event.target.select();
                }
            },

            handleInput() {
                if (this.selectedOwner && this.searchTerm !== this.selectedOwner.name) {
                    this.selectedOwner = null;
                }

                this.isOpen = true;
                this.results = [];
                this.loadError = '';
                this.highlightedIndex = -1;
                this.isLoading = false;
                window.clearTimeout(this.debounceTimer);

                const query = this.normalizedSearchTerm;
                const requestSequence = ++this.requestSequence;

                if (query.length < this.minimumCharacters) {
                    return;
                }

                this.isLoading = true;
                this.debounceTimer = window.setTimeout(
                    () => this.fetchOwners(query, requestSequence),
                    this.debounceMilliseconds
                );
            },

            fetchOwners(query, requestSequence) {
                this.$axios.get(this.searchUrl, {
                    params: {
                        query,
                        limit: 20,
                    },
                })
                    .then((response) => {
                        if (requestSequence !== this.requestSequence) {
                            return;
                        }

                        this.results = Array.isArray(response.data)
                            ? response.data
                            : [];
                        this.highlightedIndex = this.results.length ? 0 : -1;
                    })
                    .catch(() => {
                        if (requestSequence !== this.requestSequence) {
                            return;
                        }

                        this.results = [];
                        this.loadError = 'Sales Owner gagal dimuat. Silakan coba lagi.';
                    })
                    .finally(() => {
                        if (requestSequence === this.requestSequence) {
                            this.isLoading = false;
                        }
                    });
            },

            selectOwner(owner) {
                this.selectedOwner = { ...owner };
                this.searchTerm = owner.name;
                this.results = [];
                this.isLoading = false;
                this.loadError = '';
                this.isOpen = false;
                this.highlightedIndex = -1;
                this.requestSequence++;
            },

            clearSelection() {
                this.selectedOwner = null;
                this.searchTerm = '';
                this.results = [];
                this.isLoading = false;
                this.loadError = '';
                this.isOpen = true;
                this.highlightedIndex = -1;
                this.requestSequence++;
                window.clearTimeout(this.debounceTimer);
                this.$nextTick(() => this.$refs.searchInput?.focus());
            },

            moveHighlight(direction) {
                if (! this.results.length) {
                    return;
                }

                this.isOpen = true;
                this.highlightedIndex = (
                    this.highlightedIndex + direction + this.results.length
                ) % this.results.length;
            },

            selectHighlighted() {
                if (
                    this.isOpen
                    && this.highlightedIndex >= 0
                    && this.results[this.highlightedIndex]
                ) {
                    this.selectOwner(this.results[this.highlightedIndex]);
                }
            },

            closeResults() {
                this.isOpen = false;
            },

            handleOutsideClick(event) {
                if (! this.$refs.lookup?.contains(event.target)) {
                    this.closeResults();
                }
            },
        },
    });
</script>
