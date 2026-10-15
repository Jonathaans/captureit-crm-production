@once
    @push('styles')
        <style>
            .crm-finance-workspace .finance-form { display:grid; gap:18px; margin-top:22px; }
            .crm-finance-workspace .finance-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
            .crm-finance-workspace .finance-optional { color:var(--fw-muted); font-size:11px; font-weight:400; margin-left:4px; }
            .crm-finance-workspace .finance-help { display:block; margin-top:7px; color:var(--fw-muted); font-size:11px; font-weight:400; line-height:1.6; }
            .crm-finance-workspace .finance-receipt { padding:16px; border:1px dashed #b3c3d5; border-radius:12px; background:var(--fw-soft); }
            .crm-finance-workspace .finance-receipt input { padding:10px; font-size:12px; }
            .crm-finance-workspace input::file-selector-button { border:0; border-radius:6px; padding:7px 10px; margin-right:10px; background:var(--fw-soft); color:var(--fw-text); cursor:pointer; }
            .crm-finance-workspace .finance-errors { border:1px solid #fca5a5; border-radius:10px; padding:12px 14px; background:#fff1f2; color:#991b1b; font-size:12px; line-height:1.8; }
            .dark .crm-finance-workspace .finance-errors { background:#40272f; color:#fecaca; }
            .crm-finance-workspace .finance-submit { justify-content:center; width:100%; padding:12px 18px; }
            .crm-finance-workspace .finance-stats { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:18px; padding:14px; background:var(--fw-soft); border-radius:10px; }
            .crm-finance-workspace .finance-stats small { color:var(--fw-muted); font-size:11px; display:block; margin-bottom:5px; }
            .crm-finance-workspace .finance-stats strong { font-size:14px; font-variant-numeric:tabular-nums; }
            .crm-finance-workspace .category-workspace { display:grid; grid-template-columns:minmax(260px,.8fr) minmax(0,1.8fr); gap:24px; align-items:start; }
            .crm-finance-workspace .category-list:only-child { grid-column:1/-1; }
            .crm-finance-workspace .category-row { padding:20px 24px; border-bottom:1px solid var(--fw-line); }
            .crm-finance-workspace .category-row:last-child { border-bottom:0; }
            .crm-finance-workspace .category-copy { overflow-wrap:anywhere; flex:1; }
            .crm-finance-workspace .category-edit { margin-top:12px; }
            .crm-finance-workspace .category-edit summary { cursor:pointer; width:fit-content; }
            .crm-finance-workspace .category-edit[open] { padding-top:4px; }
            .crm-finance-workspace .directory-search input { width:100%; border:1px solid var(--fw-line); background:var(--fw-bg); color:var(--fw-text); border-radius:10px; padding:11px 14px; font-size:13px; }
            .crm-finance-workspace [hidden] { display:none !important; }
            @media (max-width:900px) { .crm-finance-workspace .category-workspace { grid-template-columns:1fr; } }
            @media (max-width:480px) { .crm-finance-workspace .finance-form-grid { grid-template-columns:1fr; } .crm-finance-workspace .directory-search { width:100%; } }
        </style>
    @endpush
@endonce
