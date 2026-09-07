<?php

return [
    'audited_tables' => [
        'persons',
        'organizations',
        'leads',
        'quotes',
        'quote_items',
        'invoices',
        'invoice_items',
        'payments',
        'expenses',
        'purchase_orders',
        'purchase_order_items',
        'delivery_orders',
        'delivery_order_items',
        'inventory_items',
        'inventory_assets',
        'inventory_consumables',
        'inventory_stock_movements',
        'inventory_stock_opname_sessions',
        'inventory_stock_opname_entries',
        'warehouses',
        'users',
        'roles',
        'google_calendar_events',
    ],

    'sensitive_keys' => [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'access_token',
        'refresh_token',
        'private_key',
        'secret',
        'client_secret',
    ],

    'backup' => [
        'retention_days' => 2555, // CRM_GFS_RETENTION_V2: deletion is managed by crm:backup-retention

        'directory' => storage_path(
            'app/private/crm-backups'
        ),

        'exclude_relative_paths' => [
            'private/crm-backups',
            'private/google-calendar/service-account.json',
        ],
    ],
];
