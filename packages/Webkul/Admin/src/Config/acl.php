<?php

return [
    [
        'key' => 'dashboard',
        'name' => 'admin::app.layouts.dashboard',
        'route' => 'admin.dashboard.index',
        'sort' => 1,
    ], [
        'key' => 'leads',
        'name' => 'admin::app.acl.leads',
        'route' => 'admin.leads.index',
        'sort' => 2,
    ], [
        'key' => 'leads.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.leads.create', 'admin.leads.store'],
        'sort' => 1,
    ], [
        'key' => 'leads.create.quick-create',
        'name' => 'admin::app.acl.quick_add',
        'route' => ['admin.leads.create', 'admin.leads.store'],
        'sort' => 1,
    ], [
        'key' => 'leads.view',
        'name' => 'admin::app.acl.view',
        'route' => [
            'admin.leads.view',
            'admin.google-calendar.leads.edit',
            'admin.google-calendar.leads.update',
            'admin.google-calendar.leads.sync',
        ],
        'sort' => 2,
    ], [
        'key' => 'leads.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.leads.edit', 'admin.leads.update', 'admin.leads.mass_update'],
        'sort' => 3,
    ], [
        'key' => 'leads.delete',
        'name' => 'admin::app.acl.delete',
        'route' => ['admin.leads.delete', 'admin.leads.mass_delete'],
        'sort' => 4,
    ], [
        'key' => 'quotes',
        'name' => 'admin::app.acl.quotes',
        'route' => 'admin.quotes.index',
        'sort' => 3,
    ], [
        'key' => 'quotes.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.quotes.create', 'admin.quotes.store'],
        'sort' => 1,
    ], [
        'key' => 'quotes.mail',
        'name' => 'admin::app.acl.mail',
        'route' => ['admin.quotes.mail', 'admin.leads.quotes.mail'],
        'sort' => 2,
    ], [
        'key' => 'quotes.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.quotes.edit', 'admin.quotes.update'],
        'sort' => 3,
    ], [
        'key' => 'quotes.print',
        'name' => 'admin::app.acl.print',
        'route' => 'admin.quotes.print',
        'sort' => 4,
    ], [
        'key' => 'quotes.delete',
        'name' => 'admin::app.acl.delete',
        'route' => ['admin.quotes.delete', 'admin.quotes.mass_delete'],
        'sort' => 5,
    ], 
/**
 * Invoices.
 */
[
    'key'   => 'invoices',
    'name'  => 'admin::app.acl.invoices',
    'route' => 'admin.invoices.index',
    'sort'  => 4,
], [
    /**
     * View Invoice.
     */
    'key'   => 'invoices.view',
    'name'  => 'admin::app.acl.view',
    'route' => 'admin.invoices.show',
    'sort'  => 1,
], [
    /**
     * Generate Invoice from Quote.
     */
    'key'   => 'invoices.generate',
    'name'  => 'admin::app.acl.generate-invoice',
    'route' => 'admin.invoices.generate',
    'sort'  => 2,
], [
    /**
     * Add Payment.
     */
    'key'   => 'invoices.payment',
    'name'  => 'admin::app.acl.add-payment',
    'route' => 'admin.invoices.payments.store',
    'sort'  => 3,
], [
    /**
     * Update Event Status.
     *
     * Prospect / Confirm / Cancel
     */
    'key'   => 'invoices.event-status',
    'name'  => 'admin::app.acl.update-event-status',
    'route' => 'admin.invoices.event-status.update',
    'sort'  => 4,
], [
    /**
     * Add Expense.
     */
    'key'   => 'invoices.expense',
    'name'  => 'admin::app.acl.add-expense',
    'route' => 'admin.invoices.expenses.store',
    'sort'  => 5,
], [
    /**
     * Edit Expense.
     */
    'key'   => 'invoices.expense.edit',
    'name'  => 'admin::app.acl.edit-expense',
    'route' => 'admin.invoices.expenses.update',
    'sort'  => 1,
], [
    /**
     * Delete Expense.
     */
    'key'   => 'invoices.expense.delete',
    'name'  => 'admin::app.acl.delete-expense',
    'route' => 'admin.invoices.expenses.delete',
    'sort'  => 2,
], [
    /**
     * Print Invoice.
     */
    'key'   => 'invoices.print',
    'name'  => 'admin::app.acl.print',
    'route' => 'admin.invoices.print',
    'sort'  => 6,
], [
    /**
     * Financial Report.
     *
     * Permission ini nanti hanya kita aktifkan
     * untuk Head Finance.
     */
    'key'   => 'invoices.financial-report',
    'name'  => 'admin::app.acl.financial-report',
    'route' => 'admin.invoices.financial-report',
    'sort'  => 7,
], [
    /**
     * Export Financial Report.
     *
     * Child permission dari Financial Report.
     */
    'key'   => 'invoices.financial-report.export',
    'name'  => 'admin::app.acl.export-financial-report',
    'route' => 'admin.invoices.financial-report.export',
    'sort'  => 1,
],

/*
|--------------------------------------------------------------------------
| Delivery Orders
|--------------------------------------------------------------------------
|
| Simplified warehouse flow:
| Request -> Scan Allocation -> Issue/Auto OUT -> Delivered -> Scan Return.
|
*/
[
    'key'   => 'delivery-orders',
    'name'  => 'admin::app.acl.delivery-orders',
    'route' => 'admin.delivery-orders.index',
    'sort'  => 5,
], [
    'key'   => 'delivery-orders.view',
    'name'  => 'admin::app.acl.view',
    'route' => [
            'admin.invoices.delivery-orders.index',
            'admin.delivery-orders.show',
        ],
    'sort'  => 1,
], [
    'key'   => 'delivery-orders.generate',
    'name'  => 'admin::app.acl.generate-delivery-order',
    'route' => 'admin.invoices.delivery-order.generate',
    'sort'  => 2,
], [
    'key'   => 'delivery-orders.edit',
    'name'  => 'admin::app.acl.edit',
    'route' => [
        'admin.delivery-orders.edit',
        'admin.delivery-orders.update',
    ],
    'sort'  => 3,
], [
    'key'   => 'delivery-orders.print',
    'name'  => 'admin::app.acl.print',
    'route' => 'admin.delivery-orders.print',
    'sort'  => 4,
], [
    /*
     * Draft -> Issued and inventory automatically becomes OUT.
     */
    'key'   => 'delivery-orders.issue',
    'name'  => 'admin::app.acl.issue-delivery-order',
    'route' => 'admin.delivery-orders.issue',
    'sort'  => 5,
], [
    /*
     * Administrative delivery status only.
     */
    'key'   => 'delivery-orders.delivered',
    'name'  => 'admin::app.acl.mark-delivered',
    'route' => 'admin.delivery-orders.delivered',
    'sort'  => 6,
], [
    'key'   => 'delivery-orders.returned',
    'name'  => 'admin::app.acl.mark-returned',
    'route' => 'admin.delivery-orders.returned',
    'sort'  => 7,
], [
    'key'   => 'delivery-orders.cancel',
    'name'  => 'admin::app.acl.cancel-delivery-order',
    'route' => 'admin.delivery-orders.cancel',
    'sort'  => 8,
], [
    /*
     * Serialized asset allocation is primarily driven by global QR scan.
     */
    'key'   => 'delivery-orders.inventory-allocation',
    'name'  => 'Inventory Allocation',
    'route' => [
        'admin.delivery-orders.inventory-allocation.edit',
        'admin.delivery-orders.inventory-allocation.scan',
        'admin.delivery-orders.inventory-allocation.update',
        'admin.delivery-orders.inventory-allocation.release',
    ],
    'sort'  => 9,
], [
    /*
     * Return page only. No Start Return action is required.
     */
    'key'   => 'delivery-orders.return',
    'name'  => 'Return Inventory',
    'route' => [
        'admin.delivery-orders.return.show',
    ],
    'sort'  => 10,
], [
    /*
     * Direct QR Check-In, batch quantity return, and Missing fallback.
     */
    'key'   => 'delivery-orders.return.check-in',
    'name'  => 'Check-In Inventory',
    'route' => [
        'admin.delivery-orders.return.check-in',
        'admin.delivery-orders.return.scan-check-in',
        'admin.delivery-orders.return.finalize',
    ],
    'sort'  => 11,
],

/*
|--------------------------------------------------------------------------
| Inventory
|--------------------------------------------------------------------------
*/
    [
        'key'   => 'inventory',
        'name'  => 'Inventory',
        'route' => 'admin.inventory.dashboard',
        'sort'  => 6,
    ], [
        'key'   => 'inventory.dashboard',
        'name'  => 'Inventory Dashboard',
        'route' => 'admin.inventory.dashboard',
        'sort'  => 1,
    ], [
        'key'   => 'inventory.consumables',
        'name'  => 'Consumables',
        'route' => 'admin.inventory.consumables.index',
        'sort'  => 2,
    ], [
        'key'   => 'inventory.consumables.create',
        'name'  => 'Create Consumable',
        'route' => [
            'admin.inventory.consumables.create',
            'admin.inventory.consumables.store',
        ],
        'sort'  => 1,
    ], [
        'key'   => 'inventory.consumables.edit',
        'name'  => 'Edit Consumable',
        'route' => [
            'admin.inventory.consumables.edit',
            'admin.inventory.consumables.update',
        ],
        'sort'  => 2,
    ], [
        'key'   => 'inventory.items',
        'name'  => 'Inventory Items',
        'route' => 'admin.inventory.items.index',
        'sort'  => 2,
    ], [
        'key'   => 'inventory.items.create',
        'name'  => 'Create Inventory Item',
        'route' => [
            'admin.inventory.items.create',
            'admin.inventory.items.store',
        ],
        'sort'  => 1,
    ], [
        'key'   => 'inventory.items.edit',
        'name'  => 'Edit Inventory Item',
        'route' => [
            'admin.inventory.items.edit',
            'admin.inventory.items.update',
        ],
        'sort'  => 2,
    ], [
        'key'   => 'inventory.assets',
        'name'  => 'Inventory Assets',
        'route' => 'admin.inventory.assets.index',
        'sort'  => 3,
    ], [
        'key'   => 'inventory.assets.create',
        'name'  => 'Create Inventory Asset',
        'route' => [
            'admin.inventory.assets.create',
            'admin.inventory.assets.store',
            'admin.inventory.assets.bulk-create',
            'admin.inventory.assets.bulk-store',
        ],
        'sort'  => 1,
    ], [
        'key'   => 'inventory.assets.edit',
        'name'  => 'Edit Inventory Asset',
        'route' => [
            'admin.inventory.assets.edit',
            'admin.inventory.assets.update',
        ],
        'sort'  => 2,
    ], [
        'key'   => 'inventory.assets.qr-labels',
        'name'  => 'Print Asset QR Labels',
        'route' => [
            'admin.inventory.assets.qr-labels.index',
            'admin.inventory.assets.qr-labels.svg',
        ],
        'sort'  => 3,
    ], [
        'key'   => 'inventory.movements',
        'name'  => 'Inventory Movements',
        'route' => 'admin.inventory.movements.index',
        'sort'  => 4,
    ], [
        'key'   => 'inventory.movements.adjust-stock',
        'name'  => 'Adjust Quantity Stock',
        'route' => [
            'admin.inventory.movements.adjust-stock.create',
            'admin.inventory.movements.adjust-stock.store',
        ],
        'sort'  => 1,
    ], [
        'key'   => 'inventory.maintenance',
        'name'  => 'Maintenance & Repair',
        'route' => [
            'admin.inventory.maintenance.index',
            'admin.inventory.maintenance.show',
        ],
        'sort'  => 5,
    ], [
        'key'   => 'inventory.maintenance.start',
        'name'  => 'Start Maintenance',
        'route' => [
            'admin.inventory.maintenance.create',
            'admin.inventory.maintenance.store',
        ],
        'sort'  => 1,
    ], [
        'key'   => 'inventory.maintenance.complete',
        'name'  => 'Complete Repair',
        'route' => 'admin.inventory.maintenance.complete',
        'sort'  => 2,
    ], [
        'key'   => 'inventory.maintenance.retire',
        'name'  => 'Retire Asset',
        'route' => 'admin.inventory.maintenance.retire',
        'sort'  => 3,
    ], [
        'key'   => 'inventory.stock-opname',
        'name'  => 'Stock Opname',
        'route' => [
            'admin.inventory.stock-opname.index',
            'admin.inventory.stock-opname.show',
            'admin.inventory.stock-opname.export-csv',
        ],
        'sort'  => 6,
    ], [
        'key'   => 'inventory.stock-opname.create',
        'name'  => 'Create Stock Opname',
        'route' => [
            'admin.inventory.stock-opname.create',
            'admin.inventory.stock-opname.store',
        ],
        'sort'  => 1,
    ], [
        'key'   => 'inventory.stock-opname.count',
        'name'  => 'Count Stock Opname',
        'route' => [
            'admin.inventory.stock-opname.start',
            'admin.inventory.stock-opname.scan',
            'admin.inventory.stock-opname.quantity',
            'admin.inventory.stock-opname.review',
            'admin.inventory.stock-opname.resume',
        ],
        'sort'  => 2,
    ], [
        'key'   => 'inventory.stock-opname.finalize',
        'name'  => 'Finalize Stock Opname',
        'route' => 'admin.inventory.stock-opname.finalize',
        'sort'  => 3,
    ], [
        'key'   => 'inventory.alerts',
        'name'  => 'Inventory Alerts & Reorder',
        'route' => [
            'admin.inventory.alerts.index',
            'admin.inventory.alerts.export-csv',
        ],
        'sort'  => 7,
    ],
    [
        'key'   => 'inventory.qa',
        'name'  => 'Warehouse QA',
        'route' => [
            'admin.inventory.qa.index',
            'admin.inventory.qa.export-csv',
        ],
        'sort'  => 8,
    ],
    [
        'key' => 'mail',
        'name' => 'admin::app.acl.mail',
        'route' => 'admin.mail.index',
        'sort' => 7,
], [
        'key' => 'mail.inbox',
        'name' => 'admin::app.acl.inbox',
        'route' => 'admin.mail.index',
        'sort' => 1,
    ], [
        'key' => 'mail.draft',
        'name' => 'admin::app.acl.draft',
        'route' => 'admin.mail.index',
        'sort' => 2,
    ], [
        'key' => 'mail.outbox',
        'name' => 'admin::app.acl.outbox',
        'route' => 'admin.mail.index',
        'sort' => 3,
    ], [
        'key' => 'mail.sent',
        'name' => 'admin::app.acl.sent',
        'route' => 'admin.mail.index',
        'sort' => 4,
    ], [
        'key' => 'mail.trash',
        'name' => 'admin::app.acl.trash',
        'route' => 'admin.mail.index',
        'sort' => 5,
    ], [
        'key' => 'mail.compose',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.mail.store'],
        'sort' => 6,
    ], [
        'key' => 'mail.compose.quick-create',
        'name' => 'admin::app.acl.quick_add',
        'route' => ['admin.mail.store'],
        'sort' => 6,
    ], [
        'key' => 'mail.view',
        'name' => 'admin::app.acl.view',
        'route' => 'admin.mail.view',
        'sort' => 7,
    ], [
        'key' => 'mail.edit',
        'name' => 'admin::app.acl.edit',
        'route' => 'admin.mail.update',
        'sort' => 8,
    ], [
        'key' => 'mail.delete',
        'name' => 'admin::app.acl.delete',
        'route' => ['admin.mail.delete', 'admin.mail.mass_delete'],
        'sort' => 9,
    ], [
        'key' => 'activities',
        'name' => 'admin::app.acl.activities',
        'route' => 'admin.activities.index',
        'sort' => 8,
    ], [
        'key' => 'activities.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.activities.create', 'admin.activities.store'],
        'sort' => 1,
    ], [
        'key' => 'activities.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.activities.edit', 'admin.activities.update', 'admin.activities.mass_update'],
        'sort' => 2,
    ], [
        'key' => 'activities.delete',
        'name' => 'admin::app.acl.delete',
        'route' => ['admin.activities.delete', 'admin.activities.mass_delete'],
        'sort' => 3,
    ], [
        'key' => 'contacts',
        'name' => 'admin::app.acl.contacts',
        'route' => 'admin.contacts.users.index',
        'sort' => 9,
    ], [
        'key' => 'contacts.persons',
        'name' => 'admin::app.acl.persons',
        'route' => 'admin.contacts.persons.index',
        'sort' => 1,
    ], [
        'key' => 'contacts.persons.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.contacts.persons.create', 'admin.contacts.persons.store'],
        'sort' => 2,
    ], [
        'key' => 'contacts.persons.create.quick-create',
        'name' => 'admin::app.acl.quick_add',
        'route' => ['admin.contacts.persons.create', 'admin.contacts.persons.store'],
        'sort' => 2,
    ], [
        'key' => 'contacts.persons.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.contacts.persons.edit', 'admin.contacts.persons.update'],
        'sort' => 3,
    ], [
        'key' => 'contacts.persons.delete',
        'name' => 'admin::app.acl.delete',
        'route' => ['admin.contacts.persons.delete', 'admin.contacts.persons.mass_delete'],
        'sort' => 4,
    ], [
        'key' => 'contacts.persons.view',
        'name' => 'admin::app.acl.view',
        'route' => [
            'admin.contacts.persons.view',
            'admin.contacts.persons.ktp',
        ],
        'sort' => 5,
    ], [
        'key' => 'contacts.organizations',
        'name' => 'admin::app.acl.organizations',
        'route' => [
            'admin.contacts.organizations.index',
            'admin.contacts.persons.identity',
            'admin.contacts.persons.identity.update',
        ],
        'sort' => 2,
    ], [
        'key' => 'contacts.organizations.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.contacts.organizations.create', 'admin.contacts.organizations.store'],
        'sort' => 1,
    ], [
        'key' => 'contacts.organizations.create.quick-create',
        'name' => 'admin::app.acl.quick_add',
        'route' => ['admin.contacts.organizations.create', 'admin.contacts.organizations.store'],
        'sort' => 1,
    ], [
        'key' => 'contacts.organizations.edit',
        'name' => 'admin::app.acl.edit',
        'route' => [
            'admin.contacts.organizations.npwp','admin.contacts.organizations.edit', 'admin.contacts.organizations.update'],
        'sort' => 2,
    ], [
        'key' => 'contacts.organizations.delete',
        'name' => 'admin::app.acl.delete',
        'route' => ['admin.contacts.organizations.delete', 'admin.contacts.organizations.mass_delete'],
        'sort' => 3,
    ], [
        'key' => 'products',
        'name' => 'admin::app.acl.products',
        'route' => [
            'admin.products.index',
            'admin.contacts.organizations.identity',
            'admin.contacts.organizations.identity.update',
        ],
        'sort' => 10,
    ], [
        'key' => 'products.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.products.create', 'admin.products.store'],
        'sort' => 1,
    ], [
        'key' => 'products.create.quick-create',
        'name' => 'admin::app.acl.quick_add',
        // Quick Add shares the store endpoint; Bouncer checks this permission
        // only for quick_add requests so it cannot replace products.create.
        'route' => [],
        'sort' => 1,
    ], [
        'key' => 'products.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.products.edit', 'admin.products.update'],
        'sort' => 2,
    ], [
        'key' => 'products.delete',
        'name' => 'admin::app.acl.delete',
        'route' => ['admin.products.delete', 'admin.products.mass_delete'],
        'sort' => 3,
    ], [
        'key' => 'products.view',
        'name' => 'admin::app.acl.view',
        'route' => 'admin.products.view',
        'sort' => 4,
    ], [
        'key' => 'settings',
        'name' => 'admin::app.acl.settings',
        'route' => 'admin.settings.index',
        'sort' => 11,
    ], [
        'key' => 'settings.user',
        'name' => 'admin::app.acl.user',
        'route' => ['admin.settings.groups.index', 'admin.settings.roles.index', 'admin.settings.users.index'],
        'sort' => 1,
    ], [
        'key' => 'settings.user.groups',
        'name' => 'admin::app.acl.groups',
        'route' => 'admin.settings.groups.index',
        'sort' => 1,
    ], [
        'key' => 'settings.user.groups.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.settings.groups.create', 'admin.settings.groups.store'],
        'sort' => 1,
    ], [
        'key' => 'settings.user.groups.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.settings.groups.edit', 'admin.settings.groups.update'],
        'sort' => 2,
    ], [
        'key' => 'settings.user.groups.delete',
        'name' => 'admin::app.acl.delete',
        'route' => 'admin.settings.groups.delete',
        'sort' => 3,
    ], [
        'key' => 'settings.user.roles',
        'name' => 'admin::app.acl.roles',
        'route' => 'admin.settings.roles.index',
        'sort' => 2,
    ], [
        'key' => 'settings.user.roles.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.settings.roles.create', 'admin.settings.roles.store'],
        'sort' => 1,
    ], [
        'key' => 'settings.user.roles.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.settings.roles.edit', 'admin.settings.roles.update'],
        'sort' => 2,
    ], [
        'key' => 'settings.user.roles.delete',
        'name' => 'admin::app.acl.delete',
        'route' => 'admin.settings.roles.delete',
        'sort' => 3,
    ], [
        'key' => 'settings.user.users',
        'name' => 'admin::app.acl.users',
        'route' => 'admin.settings.users.index',
        'sort' => 3,
    ], [
        'key' => 'settings.user.users.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.settings.users.create', 'admin.settings.users.store'],
        'sort' => 1,
    ], [
        'key' => 'settings.user.users.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.settings.users.edit', 'admin.settings.users.update', 'admin.settings.users.mass_update'],
        'sort' => 2,
    ], [
        'key' => 'settings.user.users.delete',
        'name' => 'admin::app.acl.delete',
        'route' => ['admin.settings.users.delete', 'admin.settings.users.mass_delete'],
        'sort' => 3,
    ], [
        'key' => 'settings.lead',
        'name' => 'admin::app.acl.lead',
        'route' => ['admin.settings.pipelines.index', 'admin.settings.sources.index', 'admin.settings.types.index'],
        'sort' => 2,
    ], [
        'key' => 'settings.lead.pipelines',
        'name' => 'admin::app.acl.pipelines',
        'route' => 'admin.settings.pipelines.index',
        'sort' => 1,
    ], [
        'key' => 'settings.lead.pipelines.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.settings.pipelines.create', 'admin.settings.pipelines.store'],
        'sort' => 1,
    ], [
        'key' => 'settings.lead.pipelines.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.settings.pipelines.edit', 'admin.settings.pipelines.update'],
        'sort' => 2,
    ], [
        'key' => 'settings.lead.pipelines.delete',
        'name' => 'admin::app.acl.delete',
        'route' => 'admin.settings.pipelines.delete',
        'sort' => 3,
    ], [
        'key' => 'settings.lead.sources',
        'name' => 'admin::app.acl.sources',
        'route' => 'admin.settings.sources.index',
        'sort' => 2,
    ], [
        'key' => 'settings.lead.sources.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.settings.sources.store'],
        'sort' => 1,
    ], [
        'key' => 'settings.lead.sources.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.settings.sources.edit', 'admin.settings.sources.update'],
        'sort' => 2,
    ], [
        'key' => 'settings.lead.sources.delete',
        'name' => 'admin::app.acl.delete',
        'route' => 'admin.settings.sources.delete',
        'sort' => 3,
    ], [
        'key' => 'settings.lead.types',
        'name' => 'admin::app.acl.types',
        'route' => 'admin.settings.types.index',
        'sort' => 3,
    ], [
        'key' => 'settings.lead.types.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.settings.types.store'],
        'sort' => 1,
    ], [
        'key' => 'settings.lead.types.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.settings.types.edit', 'admin.settings.types.update'],
        'sort' => 2,
    ], [
        'key' => 'settings.lead.types.delete',
        'name' => 'admin::app.acl.delete',
        'route' => 'admin.settings.types.delete',
        'sort' => 3,
    ], [
        'key' => 'settings.inventory',
        'name' => 'admin::app.acl.inventory',
        'route' => ['admin.settings.warehouse.index'],
        'sort' => 3,
    ], [
        'key' => 'settings.inventory.warehouse',
        'name' => 'admin::app.acl.warehouses',
        'route' => ['admin.settings.warehouse.index'],
        'sort' => 1,
    ], [
        'key' => 'settings.inventory.warehouse.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.settings.warehouse.create', 'admin.settings.warehouse.store'],
        'sort' => 1,
    ], [
        'key' => 'settings.inventory.warehouse.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.settings.warehouse.edit', 'admin.settings.warehouse.update'],
        'sort' => 2,
    ], [
        'key' => 'settings.inventory.warehouse.delete',
        'name' => 'admin::app.acl.delete',
        'route' => 'admin.settings.warehouse.delete',
        'sort' => 3,
    ], [
        'key' => 'settings.automation',
        'name' => 'admin::app.acl.automation',
        'route' => ['admin.settings.attributes.index', 'admin.settings.email_templates.index', 'admin.settings.workflows.index'],
        'sort' => 4,
    ], [
        'key' => 'settings.automation.attributes',
        'name' => 'admin::app.acl.attributes',
        'route' => 'admin.settings.attributes.index',
        'sort' => 1,
    ], [
        'key' => 'settings.automation.attributes.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.settings.attributes.create', 'admin.settings.attributes.store'],
        'sort' => 1,
    ], [
        'key' => 'settings.automation.attributes.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.settings.attributes.edit', 'admin.settings.attributes.update', 'admin.settings.attributes.mass_update'],
        'sort' => 2,
    ], [
        'key' => 'settings.automation.attributes.delete',
        'name' => 'admin::app.acl.delete',
        'route' => 'admin.settings.attributes.delete',
        'sort' => 3,
    ], [
        'key' => 'settings.automation.email_templates',
        'name' => 'admin::app.acl.email-templates',
        'route' => 'admin.settings.email_templates.index',
        'sort' => 2,
    ], [
        'key' => 'settings.automation.email_templates.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.settings.email_templates.create', 'admin.settings.email_templates.store'],
        'sort' => 1,
    ], [
        'key' => 'settings.automation.email_templates.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.settings.email_templates.edit', 'admin.settings.email_templates.update'],
        'sort' => 2,
    ], [
        'key' => 'settings.automation.email_templates.delete',
        'name' => 'admin::app.acl.delete',
        'route' => 'admin.settings.email_templates.delete',
        'sort' => 3,
    ], [
        'key' => 'settings.automation.workflows',
        'name' => 'admin::app.acl.workflows',
        'route' => 'admin.settings.workflows.index',
        'sort' => 3,
    ], [
        'key' => 'settings.automation.workflows.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.settings.workflows.create', 'admin.settings.workflows.store'],
        'sort' => 1,
    ], [
        'key' => 'settings.automation.workflows.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.settings.workflows.edit', 'admin.settings.workflows.update'],
        'sort' => 2,
    ], [
        'key' => 'settings.automation.workflows.delete',
        'name' => 'admin::app.acl.delete',
        'route' => 'admin.settings.workflows.delete',
        'sort' => 3,
    ], [
        'key' => 'settings.automation.events',
        'name' => 'admin::app.acl.event',
        'route' => 'admin.settings.marketing.events.index',
        'sort' => 4,
    ], [
        'key' => 'settings.automation.events.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.settings.marketing.events.create', 'admin.settings.marketing.events.store'],
        'sort' => 1,
    ], [
        'key' => 'settings.automation.events.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.settings.marketing.events.edit', 'admin.settings.marketing.events.update'],
        'sort' => 2,
    ], [
        'key' => 'settings.automation.events.delete',
        'name' => 'admin::app.acl.delete',
        'route' => ['admin.settings.marketing.events.delete', 'admin.settings.marketing.events.mass_delete'],
        'sort' => 3,
    ], [
        'key' => 'settings.automation.campaigns',
        'name' => 'admin::app.acl.campaigns',
        'route' => 'admin.settings.marketing.campaigns.index',
        'sort' => 5,
    ], [
        'key' => 'settings.automation.campaigns.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.settings.marketing.campaigns.create', 'admin.settings.marketing.campaigns.store'],
        'sort' => 1,
    ], [
        'key' => 'settings.automation.campaigns.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.settings.marketing.campaigns.edit', 'admin.settings.marketing.campaigns.update'],
        'sort' => 2,
    ], [
        'key' => 'settings.automation.campaigns.delete',
        'name' => 'admin::app.acl.delete',
        'route' => ['admin.settings.marketing.campaigns.delete', 'admin.settings.marketing.campaigns.mass_delete'],
        'sort' => 3,
    ], [
        'key' => 'settings.automation.webhooks',
        'name' => 'admin::app.acl.webhook',
        'route' => 'admin.settings.webhooks.index',
        'sort' => 6,
    ], [
        'key' => 'settings.automation.webhooks.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.settings.webhooks.create', 'admin.settings.webhooks.store'],
        'sort' => 1,
    ], [
        'key' => 'settings.automation.webhooks.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.settings.webhooks.edit', 'admin.settings.webhooks.update'],
        'sort' => 2,
    ], [
        'key' => 'settings.automation.webhooks.delete',
        'name' => 'admin::app.acl.delete',
        'route' => 'admin.settings.webhooks.delete',
        'sort' => 3,
    ], [
        'key' => 'settings.automation.data_transfer',
        'name' => 'admin::app.acl.data-transfer',
        'route' => 'admin.settings.data_transfer.imports.index',
        'sort' => 7,
    ], [
        'key' => 'settings.automation.data_transfer.imports',
        'name' => 'admin::app.acl.imports',
        'route' => 'admin.settings.data_transfer.imports.index',
        'sort' => 1,
    ], [
        'key' => 'settings.automation.data_transfer.imports.create',
        'name' => 'admin::app.acl.create',
        'route' => 'admin.settings.data_transfer.imports.create',
        'sort' => 1,
    ], [
        'key' => 'settings.automation.data_transfer.imports.edit',
        'name' => 'admin::app.acl.edit',
        'route' => 'admin.settings.data_transfer.imports.edit',
        'sort' => 2,
    ], [
        'key' => 'settings.automation.data_transfer.imports.delete',
        'name' => 'admin::app.acl.delete',
        'route' => 'admin.settings.data_transfer.imports.delete',
        'sort' => 3,
    ], [
        'key' => 'settings.automation.data_transfer.imports.import',
        'name' => 'admin::app.acl.import',
        'route' => 'admin.settings.data_transfer.imports.imports',
        'sort' => 4,
    ], [
        'key' => 'settings.other_settings',
        'name' => 'admin::app.acl.other-settings',
        'route' => 'admin.settings.tags.index',
        'sort' => 5,
    ], [
        'key' => 'settings.other_settings.tags',
        'name' => 'admin::app.acl.tags',
        'route' => 'admin.settings.tags.index',
        'sort' => 1,
    ], [
        'key' => 'settings.other_settings.tags.create',
        'name' => 'admin::app.acl.create',
        'route' => ['admin.settings.tags.create', 'admin.settings.tags.store', 'admin.leads.tags.attach'],
        'sort' => 1,
    ], [
        'key' => 'settings.other_settings.tags.edit',
        'name' => 'admin::app.acl.edit',
        'route' => ['admin.settings.tags.edit', 'admin.settings.tags.update'],
        'sort' => 2,
    ], [
        'key' => 'settings.other_settings.tags.delete',
        'name' => 'admin::app.acl.delete',
        'route' => ['admin.settings.tags.delete', 'admin.settings.tags.mass_delete', 'admin.leads.tags.detach'],
        'sort' => 3,
    ], [
        'key' => 'configuration',
        'name' => 'admin::app.acl.configuration',
        'route' => 'admin.configuration.index',
        'sort' => 12,
    ], [
        'key' => 'help',
        'name' => 'admin::app.acl.help',
        'route' => 'admin.help.index',
        'sort' => 13,
    ],

    [
        'key'   => 'contacts.vendors',
        'name'  => 'Vendor Master',
        'route' => [
            'admin.vendors.index',
            'admin.vendors.create',
            'admin.vendors.store',
            'admin.vendors.edit',
            'admin.vendors.update',
            'admin.vendors.npwp-image',
        ],
        'sort'  => 3,
    ],
    [
        'key'   => 'purchase-orders',
        'name'  => 'Purchase Orders',
        'route' => 'admin.purchase-orders.index',
        'sort'  => 10,
    ], [
        'key'   => 'purchase-orders.create',
        'name'  => 'Create Purchase Order',
        'route' => [
            'admin.purchase-orders.create',
            'admin.purchase-orders.store',
        ],
        'sort'  => 1,
    ], [
        'key'   => 'purchase-orders.edit',
        'name'  => 'Edit Purchase Order',
        'route' => [
            'admin.purchase-orders.edit',
            'admin.purchase-orders.update',
        ],
        'sort'  => 2,
    ], [
        'key'   => 'purchase-orders.view',
        'name'  => 'View Purchase Order',
        'route' => [
            'admin.purchase-orders.show',
            'admin.purchase-orders.payment-proof',
        ],
        'sort'  => 3,
    ], [
        'key'   => 'purchase-orders.release',
        'name'  => 'Release Purchase Order',
        'route' => 'admin.purchase-orders.release',
        'sort'  => 4,
    ], [
        /* PURCHASE ORDER PAID WORKFLOW V1 ACL */
        'key'   => 'purchase-orders.paid',
        'name'  => 'Mark Purchase Order Paid',
        'route' => 'admin.purchase-orders.paid',
        'sort'  => 5,
    ], [
        'key'   => 'purchase-orders.complete',
        'name'  => 'Complete Purchase Order',
        'route' => 'admin.purchase-orders.complete',
        'sort'  => 5,
    ], [
        'key'   => 'purchase-orders.cancel',
        'name'  => 'Cancel Purchase Order',
        'route' => 'admin.purchase-orders.cancel',
        'sort'  => 6,
    ], [
        'key'   => 'purchase-orders.print',
        'name'  => 'Print Purchase Order',
        'route' => 'admin.purchase-orders.print',
        'sort'  => 7,
    ],
    [
        'key'   => 'purchase-orders.export',
        'name'  => 'Export PO Expense CSV',
        'route' => 'admin.purchase-orders.export-expense',
        'sort'  => 8,
    ],
    [
        'key'   => 'system-control',
        'name'  => 'System Control',
        'route' => [
            'admin.system-control.index',
            'admin.system-control.audit-logs',
            'admin.system-control.incidents',
            'admin.system-control.incidents.resolve',
        ],
        'sort'  => 99,
    ],
    [
        'key'   => 'vendors',
        'name'  => 'Vendor Master',
        'route' => [
            'admin.vendors.index',
            'admin.vendors.create',
            'admin.vendors.store',
            'admin.vendors.edit',
            'admin.vendors.update',
        ],
        'sort'  => 90,
    ],
    [
        'key'   => 'crm-notifications',
        'name'  => 'CRM Notifications',
        'route' => [
            'admin.crm-notifications.index',
            'admin.crm-notifications.read',
            'admin.crm-notifications.read-all',
        ],
        'sort'  => 91,
    ],
    [
        'key'   => 'operations-dashboard',
        'name'  => 'Operations Dashboard',
        'route' => 'admin.operations-dashboard.index',
        'sort'  => 92,
    ],
    [
        'key'   => 'financial-periods',
        'name'  => 'Financial Period Lock',
        'route' => [
            'admin.financial-periods.index',
            'admin.financial-periods.store',
            'admin.financial-periods.destroy',
        ],
        'sort'  => 93,
    ],
    [
        'key'   => 'my-email',
        'name'  => 'My Email',
        'route' => [
            'admin.my-email.inbox',
            'admin.my-email.sync',
            'admin.my-email.messages.show',
            'admin.my-email.settings',
            'admin.my-email.settings.update',
            'admin.my-email.test-imap',
            'admin.my-email.test-smtp',
        ],
        'sort'  => 94,
    ],
    [
        'key'   => 'system-control.email-accounts',
        'name'  => 'User Email Connections',
        'route' => 'admin.system-control.email-accounts',
        'sort'  => 100,
    ],
    [
        'key'   => 'work-orders',
        'name'  => 'Surat Perintah Kerja',
        'route' => 'admin.work-orders.index',
        'sort'  => 65,
    ], [
        'key'   => 'work-orders.view',
        'name'  => 'View SPK',
        'route' => [
            'admin.work-orders.index',
            'admin.work-orders.show',
            'admin.invoices.work-orders.open',
        ],
        'sort'  => 1,
    ], [
        'key'   => 'work-orders.generate',
        'name'  => 'Generate SPK from Invoice',
        'route' => [
            'admin.invoices.work-orders.store',
            'admin.invoices.delivery-order.generate',
        ],
        'sort'  => 2,
    ], [
        'key'   => 'work-orders.edit',
        'name'  => 'Edit SPK',
        'route' => [
            'admin.work-orders.edit',
            'admin.work-orders.update',
        ],
        'sort'  => 3,
    ], [
        'key'   => 'work-orders.print',
        'name'  => 'Print SPK',
        'route' => 'admin.work-orders.print',
        'sort'  => 4,
    ], [
        'key'   => 'work-orders.delivery-orders',
        'name'  => 'Generate Surat Jalan from SPK',
        'route' => 'admin.work-orders.delivery-orders.generate',
        'sort'  => 5,
    ], [
        'key'   => 'work-orders.status',
        'name'  => 'Update SPK Status',
        'route' => [
            'admin.work-orders.release',
            'admin.work-orders.complete',
            'admin.work-orders.cancel',
        ],
        'sort'  => 6,
    ],

    [
        'key'   => 'internal-chat-audit',
        'name'  => 'Internal Chat Audit',
        'route' => [
            'admin.operational-dashboard.internal-chat-audit.index',
            'admin.operational-dashboard.internal-chat-audit.show',
        ],
        'sort'  => 999,
    ],
[
    /**
     * Export All Expenses CSV.
     */
    'key'   => 'invoices.expense.export-all',
    'name'  => 'Export All Expenses',
    'route' => 'admin.invoices.expenses.export-all',
    'sort'  => 3,
],
];
