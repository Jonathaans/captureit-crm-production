<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'dashboard',
        'name'       => 'admin::app.layouts.dashboard',
        'route'      => 'admin.dashboard.index',
        'sort'       => 10,
        'icon-class' => 'icon-dashboard',
    ],

    /*
    |--------------------------------------------------------------------------
    | Leads
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'leads',
        'name'       => 'admin::app.layouts.leads',
        'route'      => 'admin.leads.index',
        'sort'       => 20,
        'icon-class' => 'icon-leads',
    ],

    /*
    |--------------------------------------------------------------------------
    | Quotes
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'quotes',
        'name'       => 'admin::app.layouts.quotes',
        'route'      => 'admin.quotes.index',
        'sort'       => 30,
        'icon-class' => 'icon-quote',
    ],

/*
|--------------------------------------------------------------------------
| Invoices
|--------------------------------------------------------------------------
*/
[
    'key'        => 'invoices',
    'name'       => 'admin::app.layouts.invoices',
    'route'      => 'admin.invoices.index',
    'sort'       => 40,
    'icon-class' => 'icon-quote',
],

    /*
    |--------------------------------------------------------------------------
    | Financial Report
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'financial-report',
        'name'       => 'admin::app.layouts.financial-report',
        'route'      => 'admin.invoices.financial-report',
        'sort'       => 170,
        'icon-class' => 'icon-activity',
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery Orders
    |--------------------------------------------------------------------------
    */

    [
        'key'        => 'delivery-orders',
        'name'       => 'admin::app.layouts.delivery-orders',
        'route'      => 'admin.delivery-orders.index',
        'sort'       => 60,
        'icon-class' => 'icon-quote',
],

/*
|--------------------------------------------------------------------------
| Inventory
|--------------------------------------------------------------------------
*/

    [
        'key'        => 'purchase-orders',
        'name'       => 'Purchase Orders',
        'route'      => 'admin.purchase-orders.index',
        'sort'       => 160,
        'icon-class' => 'icon-quote',
    ],[
    'key'        => 'inventory',
    'name'       => 'Inventory',
    'route'      => 'admin.inventory.items.index',
    'sort'       => 70,
    'icon-class' => 'icon-settings-warehouse',
],

[
    'key'        => 'inventory.items',
    'name'       => 'Inventory Items',
    'route'      => 'admin.inventory.items.index',
    'sort'       => 2,
    'icon-class' => '',
],

[
    'key'        => 'inventory.assets',
    'name'       => 'Assets',
    'route'      => 'admin.inventory.assets.index',
    'sort'       => 3,
    'icon-class' => '',
],

[
    'key'        => 'inventory.movements',
    'name'       => 'Movements',
    'route'      => 'admin.inventory.movements.index',
    'sort'       => 4,
    'icon-class' => '',
],


    /*
    |--------------------------------------------------------------------------
    | Mail
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'mail',
        'name'       => 'admin::app.layouts.mail.title',
        'route'      => 'admin.mail.index',
        'params'     => [
            'route' => 'inbox',
        ],
        'sort'       => 90,
        'icon-class' => 'icon-mail',
    ],

    [
        'key'        => 'mail.inbox',
        'name'       => 'admin::app.layouts.mail.inbox',
        'route'      => 'admin.mail.index',
        'params'     => [
            'route' => 'inbox',
        ],
        'sort'       => 1,
        'icon-class' => '',
    ],

    [
        'key'        => 'mail.draft',
        'name'       => 'admin::app.layouts.mail.draft',
        'route'      => 'admin.mail.index',
        'params'     => [
            'route' => 'draft',
        ],
        'sort'       => 2,
        'icon-class' => '',
    ],

    [
        'key'        => 'mail.outbox',
        'name'       => 'admin::app.layouts.mail.outbox',
        'route'      => 'admin.mail.index',
        'params'     => [
            'route' => 'outbox',
        ],
        'sort'       => 3,
        'icon-class' => '',
    ],

    [
        'key'        => 'mail.sent',
        'name'       => 'admin::app.layouts.mail.sent',
        'route'      => 'admin.mail.index',
        'params'     => [
            'route' => 'sent',
        ],
        'sort'       => 4,
        'icon-class' => '',
    ],

    [
        'key'        => 'mail.trash',
        'name'       => 'admin::app.layouts.mail.trash',
        'route'      => 'admin.mail.index',
        'params'     => [
            'route' => 'trash',
        ],
        'sort'       => 5,
        'icon-class' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Activities
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'activities',
        'name'       => 'admin::app.layouts.activities',
        'route'      => 'admin.activities.index',
        'sort'       => 100,
        'icon-class' => 'icon-activity',
    ],

    /*
    |--------------------------------------------------------------------------
    | Contacts
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'contacts',
        'name'       => 'admin::app.layouts.contacts',
        'route'      => 'admin.contacts.persons.index',
        'sort'       => 110,
        'icon-class' => 'icon-contact',
    ],

    [
        'key'        => 'contacts.persons',
        'name'       => 'admin::app.layouts.persons',
        'route'      => 'admin.contacts.persons.index',
        'sort'       => 1,
        'icon-class' => '',
    ],

    [
        'key'        => 'contacts.organizations',
        'name'       => 'admin::app.layouts.organizations',
        'route'      => 'admin.contacts.organizations.index',
        'sort'       => 2,
        'icon-class' => '',
    ],

    [
        'key'        => 'contacts.vendors',
        'name'       => 'Vendor Master',
        'route'      => 'admin.vendors.index',
        'sort'       => 3,
        'icon-class' => '',
    ],
    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'products',
        'name'       => 'admin::app.layouts.products',
        'route'      => 'admin.products.index',
        'sort'       => 120,
        'icon-class' => 'icon-product',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'settings',
        'name'       => 'admin::app.layouts.settings',
        'route'      => 'admin.settings.index',
        'sort'       => 130,
        'icon-class' => 'icon-setting',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings > User
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'settings.user',
        'name'       => 'admin::app.layouts.user',
        'route'      => 'admin.settings.groups.index',
        'info'       => 'admin::app.layouts.user-info',
        'sort'       => 1,
        'icon-class' => 'icon-settings-group',
    ],

    [
        'key'        => 'settings.user.groups',
        'name'       => 'admin::app.layouts.groups',
        'info'       => 'admin::app.layouts.groups-info',
        'route'      => 'admin.settings.groups.index',
        'sort'       => 1,
        'icon-class' => 'icon-settings-group',
    ],

    [
        'key'        => 'settings.user.roles',
        'name'       => 'admin::app.layouts.roles',
        'info'       => 'admin::app.layouts.roles-info',
        'route'      => 'admin.settings.roles.index',
        'sort'       => 2,
        'icon-class' => 'icon-role',
    ],

    [
        'key'        => 'settings.user.users',
        'name'       => 'admin::app.layouts.users',
        'info'       => 'admin::app.layouts.users-info',
        'route'      => 'admin.settings.users.index',
        'sort'       => 3,
        'icon-class' => 'icon-user',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings > Lead
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'settings.lead',
        'name'       => 'admin::app.layouts.lead',
        'info'       => 'admin::app.layouts.lead-info',
        'route'      => 'admin.settings.pipelines.index',
        'sort'       => 2,
        'icon-class' => '',
    ],

    [
        'key'        => 'settings.lead.pipelines',
        'name'       => 'admin::app.layouts.pipelines',
        'info'       => 'admin::app.layouts.pipelines-info',
        'route'      => 'admin.settings.pipelines.index',
        'sort'       => 1,
        'icon-class' => 'icon-settings-pipeline',
    ],

    [
        'key'        => 'settings.lead.sources',
        'name'       => 'admin::app.layouts.sources',
        'info'       => 'admin::app.layouts.sources-info',
        'route'      => 'admin.settings.sources.index',
        'sort'       => 2,
        'icon-class' => 'icon-settings-sources',
    ],

    [
        'key'        => 'settings.lead.types',
        'name'       => 'admin::app.layouts.types',
        'info'       => 'admin::app.layouts.types-info',
        'route'      => 'admin.settings.types.index',
        'sort'       => 3,
        'icon-class' => 'icon-settings-type',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings > Inventory
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'settings.inventory',
        'name'       => 'admin::app.layouts.inventory',
        'info'       => 'admin::app.layouts.inventory-info',
        'route'      => 'admin.settings.warehouses.index',
        'sort'       => 3,
        'icon-class' => '',
    ],

    [
        'key'        => 'settings.inventory.warehouse',
        'name'       => 'admin::app.layouts.warehouses',
        'info'       => 'admin::app.layouts.warehouses-info',
        'route' => 'admin.settings.warehouses.index',
        'sort'       => 1,
        'icon-class' => 'icon-settings-warehouse',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings > Automation
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'settings.automation',
        'name'       => 'admin::app.layouts.automation',
        'info'       => 'admin::app.layouts.automation-info',
        'route'      => 'admin.settings.attributes.index',
        'sort'       => 4,
        'icon-class' => '',
    ],

    /*
    | Attributes
    */
    [
        'key'        => 'settings.automation.attributes',
        'name'       => 'admin::app.layouts.attributes',
        'info'       => 'admin::app.layouts.attributes-info',
        'route'      => 'admin.settings.attributes.index',
        'sort'       => 1,
        'icon-class' => 'icon-attribute',
    ],

    /*
    | Email Templates
    */
    [
        'key'        => 'settings.automation.email_templates',
        'name'       => 'admin::app.layouts.email-templates',
        'info'       => 'admin::app.layouts.email-templates-info',
        'route'      => 'admin.settings.email_templates.index',
        'sort'       => 2,
        'icon-class' => 'icon-settings-mail',
    ],

    /*
    | Workflows
    */
    [
        'key'        => 'settings.automation.workflows',
        'name'       => 'admin::app.layouts.workflows',
        'info'       => 'admin::app.layouts.workflows-info',
        'route'      => 'admin.settings.workflows.index',
        'sort'       => 3,
        'icon-class' => 'icon-settings-flow',
    ],

    /*
    | Marketing Events
    */
    [
        'key'        => 'settings.automation.events',
        'name'       => 'admin::app.layouts.events',
        'info'       => 'admin::app.layouts.events-info',
        'route'      => 'admin.settings.marketing.events.index',
        'sort'       => 4,
        'icon-class' => 'icon-calendar',
    ],

    /*
    | Campaigns
    */
    [
        'key'        => 'settings.automation.campaigns',
        'name'       => 'admin::app.layouts.campaigns',
        'info'       => 'admin::app.layouts.campaigns-info',
        'route'      => 'admin.settings.marketing.campaigns.index',
        'sort'       => 5,
        'icon-class' => 'icon-note',
    ],

    /*
    | Webhooks
    */
    [
        'key'        => 'settings.automation.webhooks',
        'name'       => 'admin::app.layouts.webhooks',
        'info'       => 'admin::app.layouts.webhooks-info',
        'route'      => 'admin.settings.webhooks.index',
        'sort'       => 6,
        'icon-class' => 'icon-settings-webhooks',
    ],

    /*
    | Data Transfer
    */
    [
        'key'        => 'settings.automation.data_transfer',
        'name'       => 'admin::app.layouts.data_transfer',
        'info'       => 'admin::app.layouts.data_transfer_info',
        'route'      => 'admin.settings.data_transfer.imports.index',
        'sort'       => 7,
        'icon-class' => 'icon-download',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings > Other Settings
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'settings.other_settings',
        'name'       => 'admin::app.layouts.other-settings',
        'info'       => 'admin::app.layouts.other-settings-info',
        'route'      => 'admin.settings.tags.index',
        'sort'       => 5,
        'icon-class' => 'icon-settings',
    ],

    [
        'key'        => 'settings.other_settings.tags',
        'name'       => 'admin::app.layouts.tags',
        'info'       => 'admin::app.layouts.tags-info',
        'route'      => 'admin.settings.tags.index',
        'sort'       => 1,
        'icon-class' => 'icon-settings-tag',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'configuration',
        'name'       => 'admin::app.layouts.configuration',
        'route'      => 'admin.configuration.index',
        'sort'       => 140,
        'icon-class' => 'icon-configuration',
    ],

    /*
    |--------------------------------------------------------------------------
    | Help
    |--------------------------------------------------------------------------
    */
    [
        'key'        => 'help',
        'name'       => 'admin::app.layouts.help',
        'route'      => 'admin.help.index',
        'sort'       => 150,
        'icon-class' => 'icon-help',
    ],

    [
        'key'        => 'operations-dashboard',
        'name'       => 'Operations Dashboard',
        'route'      => 'admin.operations-dashboard.index',
        'sort'       => 180,
        'icon-class' => 'icon-dashboard',
    ],
    [
        'key'        => 'my-email',
        'name'       => 'My Mail',
        'route'      => 'admin.my-email.inbox',
        'sort'       => 80,
        'icon-class' => 'icon-mail',
    ],
    [
        'key'        => 'work-orders',
        'name'       => 'Surat Perintah Kerja',
        'route'      => 'admin.work-orders.index',
        'sort'       => 50,
        'icon-class' => 'icon-note',
    ],


    [
        'key'   => 'internal-chat-audit',
        'name'  => 'Internal Chat Audit',
        'route' => 'admin.operational-dashboard.internal-chat-audit.index',
        'sort'       => 190,
        'icon-class' => 'icon-message',
    ],
];