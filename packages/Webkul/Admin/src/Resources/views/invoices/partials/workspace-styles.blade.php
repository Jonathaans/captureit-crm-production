@once
    @push('styles')
        <style>
            .crm-finance-workspace { --fw-bg:#fff; --fw-muted:#64748b; --fw-text:#172033; --fw-line:#e6e9ef; --fw-soft:#f7f8fa; --fw-accent:#a65316; color:var(--fw-text); font-family:Inter,Arial,sans-serif; max-width:1440px; margin:0 auto; display:flex; flex-direction:column; gap:24px; }
            .dark .crm-finance-workspace { --fw-bg:#111827; --fw-muted:#a4b0c1; --fw-text:#f1f5f9; --fw-line:#293548; --fw-soft:#172133; --fw-accent:#fdba74; }
            .crm-finance-workspace * { min-width:0; }
            .crm-finance-workspace .fw-header, .crm-finance-workspace .fw-between { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
            .crm-finance-workspace .fw-eyebrow { color:var(--fw-accent); font-size:11px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; margin-bottom:7px; }
            .crm-finance-workspace h1 { font-size:28px; line-height:1.25; font-weight:700; letter-spacing:-.6px; overflow-wrap:anywhere; }
            .crm-finance-workspace .fw-muted { color:var(--fw-muted); font-size:13px; line-height:1.7; }
            .crm-finance-workspace .fw-subtitle { margin-top:8px; color:var(--fw-muted); font-size:13px; line-height:1.7; }
            .crm-finance-workspace .fw-card { border:1px solid var(--fw-line); border-radius:16px; background:var(--fw-bg); }
            .crm-finance-workspace .fw-pad { padding:24px; }
            .crm-finance-workspace .fw-card-heading { padding:20px 24px; border-bottom:1px solid var(--fw-line); }
            .crm-finance-workspace .fw-title { font-size:15px; font-weight:650; line-height:1.5; }
            .crm-finance-workspace .fw-actions { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
            .crm-finance-workspace .fw-link { color:var(--fw-accent); font-size:13px; font-weight:600; text-decoration:none; }
            .crm-finance-workspace .fw-link:hover { text-decoration:underline; }
            .crm-finance-workspace .fw-metrics { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:14px; }
            .crm-finance-workspace .fw-metrics-three { grid-template-columns:repeat(3,minmax(0,1fr)); }
            .crm-finance-workspace .fw-metric { padding:22px 20px; border:1px solid var(--fw-line); border-radius:16px; background:var(--fw-bg); }
            .crm-finance-workspace .fw-metric-label { font-size:12px; font-weight:600; color:var(--fw-muted); }
            .crm-finance-workspace .fw-value { margin:12px 0 7px; font-size:clamp(19px,1.55vw,27px); font-weight:700; letter-spacing:-.65px; font-variant-numeric:tabular-nums; overflow-wrap:anywhere; }
            .crm-finance-workspace .fw-metric small { display:block; color:var(--fw-muted); font-size:11px; line-height:1.6; }
            .crm-finance-workspace .fw-positive { color:#187057; }
            .crm-finance-workspace .fw-negative { color:#b34242; }
            .dark .crm-finance-workspace .fw-positive { color:#6ee7b7; }
            .dark .crm-finance-workspace .fw-negative { color:#fda4af; }
            .crm-finance-workspace .fw-highlight { background:var(--fw-soft); }
            .crm-finance-workspace .fw-inline-stats { display:flex; flex-wrap:wrap; align-items:center; gap:12px 24px; font-size:12px; color:var(--fw-muted); }
            .crm-finance-workspace .fw-inline-stats strong { color:var(--fw-text); font-weight:650; }
            .crm-finance-workspace .fw-badge { display:inline-flex; align-items:center; gap:6px; border-radius:7px; padding:5px 9px; background:var(--fw-soft); color:var(--fw-muted); font-size:11px; font-weight:600; white-space:nowrap; flex-shrink:0; }
            .crm-finance-workspace .fw-badge-issued { background:#fff5e7; color:#965a10; }
            .crm-finance-workspace .fw-badge-converted { background:#eaf6f0; color:#21654d; }
            .crm-finance-workspace .fw-badge-cancelled { background:#f8eeee; color:#9f4040; }
            .dark .crm-finance-workspace .fw-badge-issued { background:#3c2d1b; color:#fcd08d; }
            .dark .crm-finance-workspace .fw-badge-converted { background:#16392e; color:#8ae0b5; }
            .dark .crm-finance-workspace .fw-badge-cancelled { background:#40272f; color:#fda4af; }
            .crm-finance-workspace .fw-details > summary { cursor:pointer; list-style:none; display:flex; align-items:center; gap:14px; padding:20px 24px; }
            .crm-finance-workspace .fw-details > summary::-webkit-details-marker, .crm-finance-workspace .fw-export > summary::-webkit-details-marker { display:none; }
            .crm-finance-workspace .fw-details > summary::after { content:'+'; margin-left:auto; color:var(--fw-muted); font-size:21px; font-weight:400; flex-shrink:0; }
            .crm-finance-workspace .fw-details[open] > summary::after { content:'−'; }
            .crm-finance-workspace .fw-details[open] > summary { border-bottom:1px solid var(--fw-line); }
            .crm-finance-workspace .fw-section-note { color:var(--fw-muted); font-size:12px; line-height:1.7; padding:16px 24px; }
            .crm-finance-workspace .fw-scroll { overflow-x:auto; border-radius:0 0 16px 16px; }
            .crm-finance-workspace .fw-table { width:100%; border-collapse:collapse; font-size:13px; }
            .crm-finance-workspace .fw-table th { padding:13px 20px; background:var(--fw-soft); color:var(--fw-muted); font-size:11px; font-weight:600; white-space:nowrap; flex-shrink:0; text-align:left; }
            .crm-finance-workspace .fw-table th.text-right { text-align:right; }
            .crm-finance-workspace .fw-table th.text-center { text-align:center; }
            .crm-finance-workspace .fw-table td { padding:17px 20px; border-top:1px solid var(--fw-line); vertical-align:top; line-height:1.55; }
            .crm-finance-workspace .fw-table tbody tr:hover { background:var(--fw-soft); }
            .crm-finance-workspace .fw-table .fw-number { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
            .crm-finance-workspace .fw-table td small { display:block; color:var(--fw-muted); font-size:11px; margin-top:4px; }
            .crm-finance-workspace .fw-empty { text-align:center; padding:48px 24px !important; color:var(--fw-muted); }
            .crm-finance-workspace .fw-filter-grid { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:16px; }
            .crm-finance-workspace .fw-field { display:block; font-size:12px; font-weight:600; }
            .crm-finance-workspace .fw-field input, .crm-finance-workspace .fw-field select, .crm-finance-workspace .fw-field textarea { margin-top:8px; width:100%; border:1px solid var(--fw-line); border-radius:9px; background:var(--fw-bg); color:var(--fw-text); padding:11px 12px; font-size:13px; font-weight:400; }
            .crm-finance-workspace .fw-field textarea { min-height:90px; resize:vertical; }
            .crm-finance-workspace :is(a,button,summary,input,select,textarea):focus-visible { outline:2px solid #ca731e; outline-offset:3px; }
            .crm-finance-workspace .fw-export { position:relative; }
            .crm-finance-workspace .fw-export > summary { list-style:none; cursor:pointer; display:flex; align-items:center; gap:12px; padding:11px 18px; border-radius:9px; background:#172033; color:white; font-weight:600; font-size:13px; }
            .dark .crm-finance-workspace .fw-export > summary { background:#e2e8f0; color:#172033; }
            .crm-finance-workspace .fw-export-menu { position:absolute; right:0; top:calc(100% + 8px); z-index:20; width:min(270px,80vw); background:var(--fw-bg); border:1px solid var(--fw-line); border-radius:12px; padding:6px; box-shadow:0 12px 35px #0002; }
            .crm-finance-workspace .fw-export-menu a { display:block; padding:12px; border-radius:7px; font-size:13px; }
            .crm-finance-workspace .fw-export-menu a:hover { background:var(--fw-soft); }
            .crm-finance-workspace .fw-export-menu small { display:block; margin-top:3px; color:var(--fw-muted); font-size:11px; }
            .crm-finance-workspace .fw-tabs { display:flex; flex-wrap:wrap; gap:6px; padding:16px 20px; }
            .crm-finance-workspace .fw-tabs a { padding:9px 13px; border-radius:8px; color:var(--fw-muted); font-size:12px; font-weight:600; }
            .crm-finance-workspace .fw-tabs a[aria-current='page'] { background:var(--fw-soft); color:var(--fw-text); box-shadow:inset 0 0 0 1px var(--fw-line); }
            .crm-finance-workspace .fw-two-columns { display:grid; grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr); gap:24px; align-items:start; }
            .crm-finance-workspace .fw-stack { display:flex; flex-direction:column; gap:20px; }
            .crm-finance-workspace .fw-definition { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
            .crm-finance-workspace .fw-definition dt { font-size:11px; color:var(--fw-muted); margin-bottom:7px; }
            .crm-finance-workspace .fw-definition dd { font-size:13px; line-height:1.7; overflow-wrap:anywhere; }
            .crm-finance-workspace .fw-amount { font-size:30px; font-weight:700; letter-spacing:-.8px; margin:12px 0; overflow-wrap:anywhere; }
            .crm-finance-workspace .fw-note { padding:16px 20px; border-left:3px solid #d5aa2a; border-radius:0 10px 10px 0; background:var(--fw-soft); font-size:12px; line-height:1.8; color:var(--fw-muted); }
            .crm-finance-workspace .fw-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
            .crm-finance-workspace .fw-check { display:flex; align-items:center; gap:10px; font-size:13px; padding:16px 0; }
            .crm-finance-workspace .fw-check input { accent-color:#a65316; width:16px; height:16px; }
            .crm-finance-workspace .fw-divider { border-top:1px solid var(--fw-line); margin:20px 0; }
            .crm-finance-workspace.fw-billing { max-width:1150px; }
            .fw-billing #crm-flexible-billing-form { display:grid; grid-template-columns:minmax(0,1.5fr) minmax(290px,1fr); gap:24px; }
            .fw-billing #crm-flexible-billing-form section { box-shadow:none; border-color:var(--fw-line); }
            .fw-billing input[type='radio']:focus-visible + span { outline:2px solid #ca731e; outline-offset:3px; }
            @media (max-width:1200px) { .crm-finance-workspace .fw-metrics { grid-template-columns:repeat(3,minmax(0,1fr)); } .crm-finance-workspace .fw-filter-grid { grid-template-columns:repeat(3,minmax(0,1fr)); } }
            @media (max-width:900px) { .crm-finance-workspace .fw-two-columns, .fw-billing #crm-flexible-billing-form { grid-template-columns:1fr; } .crm-finance-workspace .fw-two-columns > aside { grid-row:1; } }
            @media (max-width:640px) { .crm-finance-workspace { gap:18px; } .crm-finance-workspace h1 { font-size:24px; } .crm-finance-workspace .fw-metrics { grid-template-columns:1fr; gap:10px; } .crm-finance-workspace .fw-metric { padding:18px; } .crm-finance-workspace .fw-value { font-size:25px; margin:8px 0; } .crm-finance-workspace .fw-filter-grid, .crm-finance-workspace .fw-form-grid, .crm-finance-workspace .fw-definition { grid-template-columns:1fr; } .crm-finance-workspace .fw-pad, .crm-finance-workspace .fw-details > summary { padding:18px; } .crm-finance-workspace .fw-export-menu { right:auto; left:0; } }
        </style>
    @endpush
@endonce
