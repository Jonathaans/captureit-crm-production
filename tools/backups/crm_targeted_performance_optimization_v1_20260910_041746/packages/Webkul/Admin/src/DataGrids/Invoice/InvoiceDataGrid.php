<?php

namespace Webkul\Admin\DataGrids\Invoice;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\DataGrid\DataGrid;
use Webkul\User\Repositories\UserRepository;

class InvoiceDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('invoices')
            ->addSelect(
                'invoices.id',
                'invoices.invoice_number',
                'invoices.project_code',
                'invoices.business_unit',
                'invoices.subject',
                'invoices.grand_total',
                'invoices.status',
                'invoices.event_status',
                'invoices.issued_at',
                'invoices.created_at',

                'users.id as user_id',
                'users.name as sales_person',

                'persons.id as person_id',
                'persons.name as person_name',

                'quotes.id as quote_id',
                'quotes.quote_number'
            )
            ->leftJoin(
                'users',
                'invoices.user_id',
                '=',
                'users.id'
            )
            ->leftJoin(
                'persons',
                'invoices.person_id',
                '=',
                'persons.id'
            )
            ->leftJoin(
                'quotes',
                'invoices.quote_id',
                '=',
                'quotes.id'
            );

        /**
         * Native DataGrid filter mapping.
         */
        
        /*
         * Product reporting/filter dimension.
         * invoice_items.name is the historical commercial snapshot.
         */
        $queryBuilder
            ->leftJoin(
                'invoice_items as invoice_product_items',
                'invoices.id',
                '=',
                'invoice_product_items.invoice_id'
            )
            ->addSelect(
                DB::raw(
                    "(SELECT GROUP_CONCAT(DISTINCT ii_product.name ORDER BY ii_product.name SEPARATOR ', ')"
                    ." FROM invoice_items ii_product"
                    ." WHERE ii_product.invoice_id = invoices.id"
                    .") as products"
                )
            )
            ->distinct();

        $this->addFilter(
            'products',
            'invoice_product_items.name'
        );
$this->addFilter(
            'invoice_number',
            'invoices.invoice_number'
        );

        $this->addFilter(
            'project_code',
            'invoices.project_code'
        );

        $this->addFilter(
            'business_unit',
            'invoices.business_unit'
        );

        $this->addFilter(
            'subject',
            'invoices.subject'
        );

        $this->addFilter(
            'person_name',
            'persons.name'
        );

        $this->addFilter(
            'sales_person',
            'users.name'
        );

        $this->addFilter(
            'issued_at',
            'invoices.issued_at'
        );

        $this->addFilter(
            'status',
            'invoices.status'
        );

        $this->addFilter(
            'event_status',
            'invoices.event_status'
        );

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns(): void
    {
        /**
         * Invoice Number.
         */
        $this->addColumn([
            'index' => 'invoice_number',
            'label' => 'Invoice Number',
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        /**
         * Project Code.
         */
        $this->addColumn([
            'index' => 'project_code',
            'label' => 'Project Code',
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
            'closure' => fn ($row) => $row->project_code ?: '-',
        ]);

        /**
 * Business Unit.
 */
$this->addColumn([
    'index' => 'business_unit',
    'label' => 'Business Unit',
    'type' => 'string',

    'searchable' => false,
    'sortable' => false,
    'filterable' => true,

    'visibility' => false,

    'filterable_type' => 'dropdown',

    'filterable_options' => [
        [
            'label' => 'Varbel - EO',
            'value' => 'varbel',
        ],
        [
            'label' => 'Vartech - Event Tech',
            'value' => 'vartech',
        ],
        [
            'label' => 'Capture It - Photobooth',
            'value' => 'capture_it',
        ],
    ],
]);

        /**
         * Project Name.
         */
        $this->addColumn([
            'index' => 'subject',
            'label' => 'Project Name',
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        /**
         * Bill To.
         */
        $this->addColumn([
            'index' => 'person_name',
            'label' => 'Bill To',
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'searchable_dropdown',
            'filterable_options' => [
                'repository' => PersonRepository::class,
                'column' => [
                    'label' => 'name',
                    'value' => 'name',
                ],
            ],
            'closure' => function ($row) {
                if (! $row->person_id) {
                    return '-';
                }

                $route = route(
                    'admin.contacts.persons.view',
                    $row->person_id
                );

                return '<a class="text-brandColor transition-all hover:underline" href="'
                    .$route
                    .'">'
                    .e($row->person_name)
                    .'</a>';
            },
        ]);

        /**
         * Sales Person.
         */
        $this->addColumn([
            'index' => 'sales_person',
            'label' => 'Sales Person',
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'searchable_dropdown',
            'filterable_options' => [
                'repository' => UserRepository::class,
                'column' => [
                    'label' => 'name',
                    'value' => 'name',
                ],
            ],
            'closure' => fn ($row) => $row->sales_person ?: '-',
        ]);

        /**
         * Quote Number.
         *
         * Searchable globally, but not added to the filter drawer.
         */
        $this->addColumn([
            'index' => 'quote_number',
            'label' => 'Quote Number',
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => false,
            'closure' => function ($row) {
                if (! $row->quote_id) {
                    return '-';
                }

                $label = $row->quote_number
                    ?: '#'.$row->quote_id;

                $route = route(
                    'admin.quotes.edit',
                    $row->quote_id
                );

                return '<a class="text-brandColor transition-all hover:underline" href="'
                    .$route
                    .'">'
                    .e($label)
                    .'</a>';
            },
        ]);

        /**
         * Grand Total.
         *
         * Keep this as a string because the displayed value is
         * formatted as Indonesian Rupiah by the closure.
         */
        
        $this->addColumn([
            'index'      => 'products',
            'label'      => 'Product',
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => false,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => DB::table('invoice_items')
                ->whereNotNull('name')
                ->where('name', '<>', '')
                ->select('name')
                ->distinct()
                ->orderBy('name')
                ->pluck('name')
                ->map(
                    fn ($name) => [
                        'label' => (string) $name,
                        'value' => (string) $name,
                    ]
                )
                ->values()
                ->all(),
            'closure' => function ($row) {
                $products = trim(
                    (string) (
                        $row->products
                        ?? ''
                    )
                );

                if ($products === '') {
                    return '-';
                }

                $badges = collect(
                    array_filter(
                        array_map(
                            'trim',
                            explode(
                                ',',
                                $products
                            )
                        )
                    )
                )
                    ->unique()
                    ->map(
                        fn ($product) => sprintf(
                            '<span style="display:inline-flex;margin:2px;padding:4px 8px;border-radius:9999px;background:#eff6ff;color:#1d4ed8;font-weight:600;font-size:10px;">%s</span>',
                            e($product)
                        )
                    )
                    ->implode('');

                return $badges;
            },
        ]);
$this->addColumn([
            'index' => 'grand_total',
            'label' => 'Grand Total',
            'type' => 'string',
            'searchable' => false,
            'sortable' => true,
            'filterable' => false,
            'closure' => function ($row) {
                return 'Rp '.number_format(
                    (float) $row->grand_total,
                    0,
                    ',',
                    '.'
                );
            },
        ]);

        /**
         * Issued At.
         *
         * Same pattern as the working Quote "Valid Until" column:
         * type=date + filterable=true.
         */
        $this->addColumn([
            'index' => 'issued_at',
            'label' => 'Issued At',
            'type' => 'date',
            'searchable' => false,
            'sortable' => true,
            'filterable' => true,
            'closure' => function ($row) {
                return $row->issued_at
                    ? core()->formatDate(
                        $row->issued_at,
                        'd M Y'
                    )
                    : '-';
            },
        ]);

        /**
         * Payment.
         */
        $this->addColumn([
            'index' => 'status',
            'label' => 'Payment',
            'type' => 'string',
            'searchable' => false,
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                [
                    'label' => 'Unpaid',
                    'value' => 'unpaid',
                ],
                [
                    'label' => 'Partial',
                    'value' => 'partial',
                ],
                [
                    'label' => 'Paid',
                    'value' => 'paid',
                ],
            ],
            'closure' => function ($row) {
                $status = strtolower($row->status ?: 'unpaid');

                [$background, $color, $border] = match ($status) {
                    'paid' => ['#dcfce7', '#15803d', '#86efac'],
                    'partial' => ['#fef3c7', '#b45309', '#fcd34d'],
                    default => ['#fee2e2', '#b91c1c', '#fca5a5'],
                };

                return sprintf(
                    '<span style="
                        display:inline-flex;
                        align-items:center;
                        justify-content:center;
                        min-width:76px;
                        padding:5px 10px;
                        border-radius:9999px;
                        border:1px solid %s;
                        background:%s;
                        color:%s;
                        font-size:11px;
                        font-weight:700;
                        line-height:1;
                        letter-spacing:.04em;
                        text-transform:uppercase;
                    ">%s</span>',
                    $border,
                    $background,
                    $color,
                    e($status)
                );
            },
        ]);

        /**
         * Event Status.
         */
        $this->addColumn([
            'index' => 'event_status',
            'label' => 'Event Status',
            'type' => 'string',
            'searchable' => false,
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                [
                    'label' => 'Prospect',
                    'value' => 'prospect',
                ],
                [
                    'label' => 'Confirm',
                    'value' => 'confirm',
                ],
                [
                    'label' => 'Cancel',
                    'value' => 'cancel',
                ],
            ],
            'closure' => function ($row) {
                $status = strtolower($row->event_status ?: 'confirm');

                [$background, $color, $border] = match ($status) {
                    'confirm' => ['#dcfce7', '#15803d', '#86efac'],
                    'prospect' => ['#dbeafe', '#1d4ed8', '#93c5fd'],
                    'cancel' => ['#fee2e2', '#b91c1c', '#fca5a5'],
                    default => ['#f3f4f6', '#4b5563', '#d1d5db'],
                };

                return sprintf(
                    '<span style="
                        display:inline-flex;
                        align-items:center;
                        justify-content:center;
                        min-width:82px;
                        padding:5px 10px;
                        border-radius:9999px;
                        border:1px solid %s;
                        background:%s;
                        color:%s;
                        font-size:11px;
                        font-weight:700;
                        line-height:1;
                        letter-spacing:.04em;
                        text-transform:uppercase;
                    ">%s</span>',
                    $border,
                    $background,
                    $color,
                    e($status)
                );
            },
        ]);
    }

    /**
     * Prepare row actions.
     *
     * Eye   => Manage invoice (payment, expense, event status, history).
     * Edit  => Edit invoice header, customer, project details, and items.
     * Print => Generate / print invoice PDF.
     */
    public function prepareActions(): void
    {
        $this->addAction([
            'index' => 'view',
            'icon' => 'icon-eye',
            'title' => 'Manage Invoice',
            'method' => 'GET',
            'url' => fn ($row) => route(
                'admin.invoices.show',
                $row->id
            ),
        ]);

        /**
         * Edit invoice.
         */
        $this->addAction([
            'index' => 'edit',
            'icon' => 'icon-edit',
            'title' => 'Edit Invoice',
            'method' => 'GET',
            'url' => fn ($row) => route(
                'admin.invoices.edit',
                $row->id
            ),
        ]);

        if (bouncer()->hasPermission('invoices.print')) {
            $this->addAction([
                'index' => 'print',
                'icon' => 'icon-print',
                'title' => 'Print Invoice',
                'method' => 'GET',
                'url' => fn ($row) => route(
                    'admin.invoices.print',
                    $row->id
                ),
            ]);
        }
    }
}