<x-admin::layouts>
    <x-slot:title>Kategori Expense</x-slot>
    @include('admin::invoices.partials.workspace-styles')
    @include('admin::settings.partials.finance-form-styles')
    @include('admin::settings.partials.directory-search')

    <section class="crm-finance-workspace" v-directory-search>
        <div v-pre>
            <header class="fw-header" style="margin-bottom:24px">
                <div>
                    <a class="fw-link" href="{{ route('admin.settings.index') }}">← Settings</a>
                    <p class="fw-eyebrow" style="margin-top:20px">Keuangan</p>
                    <h1>Kategori Expense</h1>
                    <p class="fw-subtitle">Kelompokkan pengeluaran agar pencatatan dan laporan lebih mudah dibaca.</p>
                </div>
                <span class="fw-badge fw-badge-converted">{{ $categories->where('is_active', true)->count() }} kategori aktif</span>
            </header>

            <div class="category-workspace">
                @if (bouncer()->hasPermission('settings.finance.expense-categories.create'))
                    <aside class="fw-card fw-pad">
                        <h2 class="fw-title">Tambah kategori</h2>
                        <p class="fw-subtitle">Kategori baru langsung tersedia pada form Expense.</p>
                        <form action="{{ route('admin.settings.expense-categories.store') }}" method="POST" class="finance-form">
                            @csrf
                            <input type="hidden" name="_category_form" value="create">
                            <input type="hidden" name="is_active" value="1">
                            @include('admin::settings.expense-categories.fields', ['formKey' => 'create', 'category' => null])
                            <button type="submit" class="primary-button finance-submit">Tambah Kategori</button>
                        </form>
                    </aside>
                @endif

                <div class="fw-card category-list">
                    <div class="fw-card-heading">
                        <div class="fw-between">
                            <h2 class="fw-title">Daftar kategori <span class="fw-badge">{{ $categories->count() }}</span></h2>
                            <label class="directory-search"><span class="sr-only">Cari kategori</span><input type="search" placeholder="Cari kategori…" data-directory-search></label>
                        </div>
                        <p class="fw-subtitle">Nonaktifkan kategori yang tidak digunakan. Riwayat expense tetap tersimpan.</p>
                    </div>
                    <div data-directory-group>
                        @foreach ($categories as $category)
                            <div class="category-row" data-directory-item data-search="{{ $category->name }} {{ $category->description }} {{ $category->is_active ? 'aktif' : 'nonaktif' }}">
                                <div class="fw-between">
                                    <div class="category-copy">
                                        <h3 class="fw-title">{{ $category->name }}</h3>
                                        @if ($category->description)<p class="fw-muted">{{ $category->description }}</p>@endif
                                    </div>
                                    <span class="fw-badge {{ $category->is_active ? 'fw-badge-converted' : '' }}">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </div>
                                @if (bouncer()->hasPermission('settings.finance.expense-categories.edit'))
                                    <details class="category-edit" @if (old('_category_form') === (string) $category->id) open @endif>
                                        <summary class="fw-link">Ubah kategori</summary>
                                        <form method="POST" action="{{ route('admin.settings.expense-categories.update', $category->id) }}" class="finance-form">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="_category_form" value="{{ $category->id }}">
                                            @include('admin::settings.expense-categories.fields', ['formKey' => (string) $category->id])
                                            <label class="fw-field">Status
                                                <select name="is_active">
                                                    @php($active = old('_category_form') === (string) $category->id ? (bool) old('is_active') : $category->is_active)
                                                    <option value="1" @selected($active)>Aktif — tersedia untuk expense baru</option>
                                                    <option value="0" @selected(! $active)>Nonaktif — hanya riwayat lama</option>
                                                </select>
                                            </label>
                                            <button type="submit" class="primary-button">Simpan Perubahan</button>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <p class="fw-empty" data-directory-empty hidden>Tidak ada kategori yang cocok.</p>
                </div>
            </div>
        </div>
    </section>
</x-admin::layouts>
