<?php

// Shared form endpoints accept the permissions of their callers. They do not
// grant access to the corresponding module's list, edit, export or delete APIs.
$commercialForms = ['quotes.create', 'quotes.edit', 'invoices.edit', 'invoices.generate'];
$leadForms = ['leads.create', 'leads.create.quick-create', 'leads.edit'];
$personForms = ['contacts.persons.create', 'contacts.persons.create.quick-create', 'contacts.persons.edit'];
$organizationForms = ['contacts.organizations.create', 'contacts.organizations.create.quick-create', 'contacts.organizations.edit'];
$productForms = ['products.create', 'products.create.quick-create', 'products.edit'];
$people = array_merge(['contacts.persons', 'contacts.persons.view'], $personForms, $leadForms, $commercialForms, ['mail.compose', 'mail.compose.quick-create']);
$products = array_merge(['products', 'products.view'], $productForms, $leadForms, $commercialForms, ['purchase-orders.create', 'purchase-orders.edit', 'inventory.items.create', 'inventory.items.edit']);
$users = array_merge(['settings.user.users', 'settings.user.users.create', 'settings.user.users.edit', 'activities', 'activities.create', 'activities.edit'], $leadForms, $commercialForms, ['invoices.financial-report', 'purchase-orders.create', 'purchase-orders.edit']);
$warehouses = array_merge(['settings.inventory.warehouse', 'settings.inventory.warehouse.create', 'settings.inventory.warehouse.edit', 'inventory.assets.create', 'inventory.assets.edit', 'inventory.items', 'inventory.assets', 'inventory.movements.adjust-stock', 'inventory.stock-opname.create', 'delivery-orders.inventory-allocation'], $productForms, ['products', 'products.view']);

return [
    'aliases' => [
        ['invoices.edit', 'invoices.revise'],
        ['vendors', 'contacts.vendors'],
        // The legacy Generate Delivery Order endpoint now creates an SPK.
        ['delivery-orders.generate', 'work-orders.generate'],
    ],

    'quick_create' => [
        'admin.leads.store' => 'leads.create.quick-create',
        'admin.contacts.persons.store' => 'contacts.persons.create.quick-create',
        'admin.contacts.organizations.store' => 'contacts.organizations.create.quick-create',
        'admin.products.store' => 'products.create.quick-create',
        'admin.mail.store' => 'mail.compose.quick-create',
    ],

    'permissions' => [
        'admin.leads.get' => 'leads',
        'admin.leads.kanban.look_up' => 'leads',
        'admin.leads.search' => array_merge(['leads', 'leads.view'], $leadForms, $commercialForms),
        'admin.contacts.persons.search' => $people,
        'admin.products.search' => $products,
        'admin.products.warehouses' => $warehouses,
        'admin.settings.users.search' => $users,
        'admin.settings.warehouses.search' => $warehouses,
        'admin.settings.locations.search' => $warehouses,
        'admin.activities.get' => 'activities',
        'admin.activities.file_download' => ['activities', 'activities.edit', 'leads.view', 'contacts.persons.view', 'products.view', 'settings.inventory.warehouse'],
        'admin.quotes.search' => array_merge(['quotes'], $commercialForms),
        'admin.quotes.lead_products' => $commercialForms,
        'admin.quotes.bill_to_people' => $commercialForms,
        'admin.quotes.bill_to_person' => $commercialForms,
        'admin.quotes.sales_owners' => $commercialForms,
        'admin.settings.marketing.campaigns.events' => ['settings.automation.campaigns.create', 'settings.automation.campaigns.edit'],
        'admin.settings.marketing.campaigns.email-templates' => ['settings.automation.campaigns.create', 'settings.automation.campaigns.edit'],
        'admin.settings.data_transfer.imports.download_sample' => ['settings.automation.data_transfer.imports.create', 'settings.automation.data_transfer.imports.edit', 'settings.automation.data_transfer.imports.import'],
        'admin.finance-sales-dashboard.index' => ['invoices', 'invoices.view', 'invoices.financial-report'],
        'admin.tinymce.upload' => array_merge($leadForms, $commercialForms, ['mail.compose', 'mail.compose.quick-create', 'mail.edit', 'activities.create', 'activities.edit', 'settings.automation.email_templates.create', 'settings.automation.email_templates.edit', 'my-email']),
    ],

    'lookups' => [
        'leads' => array_merge(['leads', 'leads.view'], $leadForms, $commercialForms),
        'lead_sources' => array_merge(['leads', 'settings.lead.sources'], $leadForms),
        'lead_types' => array_merge(['leads', 'settings.lead.types'], $leadForms),
        'lead_pipelines' => array_merge(['leads', 'settings.lead.pipelines'], $leadForms),
        'lead_pipeline_stages' => array_merge(['leads', 'settings.lead.pipelines'], $leadForms),
        'persons' => $people,
        'organizations' => array_merge(['contacts.organizations'], $organizationForms, $personForms, $leadForms, $commercialForms),
        'users' => $users,
        'warehouses' => $warehouses,
        'locations' => $warehouses,
    ],

    // Account-scoped controllers retain ownership checks. These shared helpers
    // expose navigation, options or the current user's preferences only.
    'authenticated' => [
        'admin.session.destroy',
        'admin.user.account.edit',
        'admin.user.account.update',
        'admin.settings.search',
        'admin.settings.tags.search',
        'admin.settings.attributes.check_unique_validation',
        'admin.settings.attributes.options',
        // File ownership/module permission is resolved from its stored value.
        'admin.settings.attributes.download',
        'admin.datagrid.saved_filters.store',
        'admin.datagrid.saved_filters.index',
        'admin.datagrid.saved_filters.update',
        'admin.datagrid.saved_filters.destroy',
        'admin.datagrid.look_up',
        // Commission export has an existing Administrator-only controller gate.
        'admin.finance-sales-dashboard.commission-export',
        'admin.internal-notifications.index',
        'admin.internal-notifications.poll',
        'admin.internal-notifications.popup-ack',
        'admin.internal-notifications.open',
        'admin.internal-notifications.read-all',
        'admin.internal-chat.index',
        'admin.internal-chat.unread-summary',
        'admin.internal-chat.messages.update',
        'admin.internal-chat.messages.delete',
        'admin.internal-chat.direct',
        'admin.internal-chat.messages',
        'admin.internal-chat.send',
        'admin.internal-chat.attachments.download',
        'admin.internal-chat.search',
        'admin.internal-chat.typing',
        'admin.internal-chat.typing-status',
        'admin.internal-chat.attachments.preview',
        'admin.internal-chat.sidebar-summary',
        'admin.internal-chat.preference',
        'admin.internal-chat.presence.heartbeat',
    ],
];
