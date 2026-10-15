<x-admin::layouts>
    <x-slot:title>@lang('admin::app.settings.title')</x-slot>
    @include('admin::invoices.partials.workspace-styles')
    @include('admin::settings.partials.finance-form-styles')
    @include('admin::settings.partials.directory-search')
    @push('styles')
        <style>
            .settings-directory .settings-groups { display:grid; gap:30px; margin-top:28px; }
            .settings-directory .settings-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin-top:14px; }
            .settings-directory .settings-card { display:flex; gap:16px; align-items:flex-start; padding:22px; text-decoration:none; transition:border-color .15s,box-shadow .15s; }
            .settings-directory .settings-card:hover { border-color:#d5a274; box-shadow:0 4px 16px #17203308; }
            .settings-directory .settings-icon { display:flex; align-items:center; justify-content:center; flex-shrink:0; width:44px; height:44px; border-radius:12px; background:var(--fw-soft); color:var(--fw-accent); font-size:25px; }
            .settings-directory .settings-card p { margin-top:6px; font-size:12px; }
            .settings-directory .settings-arrow { margin-left:auto; color:var(--fw-muted); }
            .settings-directory .directory-search { width:300px; max-width:100%; }
            @media (max-width:1200px) { .settings-directory .settings-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
            @media (max-width:600px) { .settings-directory .settings-grid { grid-template-columns:1fr; } .settings-directory .directory-search { width:100%; } }
        </style>
    @endpush
    <section class="crm-finance-workspace settings-directory" v-directory-search>
        <div v-pre>
            <header class="fw-header">
                <div>
                    <p class="fw-eyebrow">Workspace</p>
                    <h1>@lang('admin::app.settings.title')</h1>
                    <p class="fw-subtitle">Kelola tim, alur kerja, dan kebutuhan pencatatan bisnis Anda.</p>
                </div>
                <label class="directory-search"><span class="sr-only">Cari pengaturan</span><input type="search" placeholder="Cari pengaturan…" data-directory-search></label>
            </header>
            <div class="settings-groups">
                @foreach (menu()->getAdminMenuByKey('settings')->getChildren() as $setting)
                    <section data-directory-group>
                        <div class="fw-between"><h2 class="fw-title">{{ $setting->getName() }}</h2><span class="fw-badge">{{ count($setting->getChildren()) }} menu</span></div>
                        <p class="fw-muted">{{ $setting->getInfo() }}</p>
                        <div class="settings-grid">
                            @foreach ($setting->getChildren() as $child)
                                <a class="fw-card settings-card" href="{{ $child->getUrl() }}" data-directory-item data-search="{{ $setting->getName() }} {{ $child->getName() }} {{ $child->getInfo() }}">
                                    <span class="settings-icon" aria-hidden="true"><i class="{{ $child->getIcon() }}"></i></span>
                                    <div><h3 class="fw-title">{{ $child->getName() }}</h3><p class="fw-muted">{{ $child->getInfo() }}</p></div>
                                    <span class="settings-arrow" aria-hidden="true">↗</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach
                <p class="fw-card fw-empty" data-directory-empty hidden>Tidak ada pengaturan yang cocok. Coba kata lain.</p>
            </div>
        </div>
    </section>
</x-admin::layouts>
