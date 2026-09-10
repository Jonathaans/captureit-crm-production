<?php

namespace Webkul\Admin\DataGrids\Quote;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\DataGrid\DataGrid;
use Webkul\User\Repositories\UserRepository;

class QuoteDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $tablePrefix = DB::getTablePrefix();

        $queryBuilder = DB::table('quotes')
            ->addSelect(
                'quotes.id',
                'quotes.quote_number',
                'quotes.project_code',
                'quotes.business_unit',
                'quotes.subject',
                'quotes.expired_at',
                'quotes.grand_total',
                'quotes.created_at',

                'users.id as user_id',
                'users.name as sales_person',

                'persons.id as person_id',
                'persons.name as person_name',

                'quotes.expired_at as expired_quotes'
            )
            ->leftJoin(
                'users',
                'quotes.user_id',
                '=',
                'users.id'
            )
            ->leftJoin(
                'persons',
                'quotes.person_id',
                '=',
                'persons.id'
            );

        /**
         * User authorization filter.
         */
        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $queryBuilder->whereIn(
                'quotes.user_id',
                $userIds
            );
        }

        /**
         * Filters.
         */
        $this->addFilter(
            'id',
            'quotes.id'
        );

        $this->addFilter(
        'business_unit',
        'quotes.business_unit'
        );

        $this->addFilter(
            'quote_number',
            'quotes.quote_number'
        );

        $this->addFilter(
            'project_code',
            'quotes.project_code'
        );

        $this->addFilter(
            'subject',
            'quotes.subject'
        );

        $this->addFilter(
            'user',
            'quotes.user_id'
        );

        $this->addFilter(
            'sales_person',
            'users.name'
        );

        $this->addFilter(
            'person_name',
            'persons.name'
        );

        $this->addFilter(
            'expired_at',
            'quotes.expired_at'
        );

        $this->addFilter(
            'created_at',
            'quotes.created_at'
        );

        /**
         * Expired quote filter.
         */
        if (request()->input('expired_quotes.in') == 1) {
            $this->addFilter(
                'expired_quotes',
                DB::raw(
                    'DATEDIFF(NOW(), '
                    .$tablePrefix
                    .'quotes.expired_at) >= '
                    .$tablePrefix
                    .'NOW()'
                )
            );
        } else {
            $this->addFilter(
                'expired_quotes',
                DB::raw(
                    'DATEDIFF(NOW(), '
                    .$tablePrefix
                    .'quotes.expired_at) < '
                    .$tablePrefix
                    .'NOW()'
                )
            );
        }

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns(): void
    {
        /**
         * Quote Number.
         */
        $this->addColumn([
            'index' => 'quote_number',
            'label' => 'Quote Number',
            'type' => 'string',

            'sortable' => true,
            'searchable' => true,
            'filterable' => true,

            /**
             * Legacy Quote yang belum punya quote_number
             * tetap bisa ditampilkan.
             */
            'closure' => function ($row) {
                return $row->quote_number
                    ?: '#'.$row->id;
            },
        ]);
/**
 * Business Unit.
 *
 * Digunakan sebagai filter backend.
 * Tidak perlu ditampilkan sebagai kolom utama.
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
         * Project Code.
         */
        $this->addColumn([
            'index' => 'project_code',
            'label' => 'Project Code',
            'type' => 'string',

            'sortable' => true,
            'searchable' => true,
            'filterable' => true,

            'closure' => function ($row) {
                return $row->project_code ?: '-';
            },
        ]);

        /**
         * Project Name.
         */
        $this->addColumn([
            'index' => 'subject',
            'label' => trans(
                'admin::app.quotes.index.datagrid.subject'
            ),
            'type' => 'string',

            'filterable' => true,
            'searchable' => true,
            'sortable' => true,
        ]);

        /**
         * Bill To.
         */
        $this->addColumn([
            'index' => 'person_name',
            'label' => trans(
                'admin::app.quotes.index.datagrid.person'
            ),
            'type' => 'string',

            'sortable' => true,
            'searchable' => true,
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

                return
                    '<a class="text-brandColor transition-all hover:underline"'
                    .' href="'.$route.'">'
                    .e($row->person_name)
                    .'</a>';
            },
        ]);

        /**
         * Sales Person.
         */
        $this->addColumn([
            'index' => 'sales_person',
            'label' => trans(
                'admin::app.quotes.index.datagrid.sales-person'
            ),
            'type' => 'string',

            'sortable' => true,
            'searchable' => true,
            'filterable' => true,

            'filterable_type' => 'searchable_dropdown',

            'filterable_options' => [
                'repository' => UserRepository::class,

                'column' => [
                    'label' => 'name',
                    'value' => 'name',
                ],
            ],

            'closure' => function ($row) {
                return $row->sales_person ?: '-';
            },
        ]);

        /**
         * Grand Total.
         */
        $this->addColumn([
            'index' => 'grand_total',
            'label' => trans(
                'admin::app.quotes.index.datagrid.grand-total'
            ),
            'type' => 'string',

            'sortable' => true,
            'filterable' => true,

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
         * Valid Until.
         */
        $this->addColumn([
            'index' => 'expired_at',
            'label' => trans(
                'admin::app.quotes.index.datagrid.expired-at'
            ),
            'type' => 'date',

            'searchable' => false,
            'sortable' => true,
            'filterable' => true,

            'closure' => function ($row) {
                return $row->expired_at
                    ? core()->formatDate(
                        $row->expired_at,
                        'd M Y'
                    )
                    : '-';
            },
        ]);

        /**
         * Created At.
         */
        $this->addColumn([
            'index' => 'created_at',
            'label' => trans(
                'admin::app.quotes.index.datagrid.created-at'
            ),
            'type' => 'date',

            'searchable' => false,
            'sortable' => true,
            'filterable' => true,

            'closure' => function ($row) {
                return core()->formatDate(
                    $row->created_at
                );
            },
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        /**
         * Edit Quote.
         */
        if (bouncer()->hasPermission('quotes.edit')) {
            $this->addAction([
                'index' => 'edit',
                'icon' => 'icon-edit',

                'title' => trans(
                    'admin::app.quotes.index.datagrid.edit'
                ),

                'method' => 'GET',

                'url' => fn ($row) => route(
                    'admin.quotes.edit',
                    $row->id
                ),
            ]);
        }

        /**
         * Print Quote.
         */
        if (bouncer()->hasPermission('quotes.print')) {
            $this->addAction([
                'index' => 'print',
                'icon' => 'icon-print',

                'title' => trans(
                    'admin::app.quotes.index.datagrid.print'
                ),

                'method' => 'GET',

                'url' => fn ($row) => route(
                    'admin.quotes.print',
                    $row->id
                ),
            ]);
        }

        /**
         * Send Quote Email.
         */
        if (bouncer()->hasPermission('quotes.mail')) {
            $this->addAction([
                'index' => 'mail',
                'icon' => 'icon-mail',

                'title' => trans(
                    'admin::app.quotes.index.datagrid.mail'
                ),

                'method' => 'POST',

                'url' => fn ($row) => route(
                    'admin.leads.quotes.mail',
                    [
                        'quote_id' => $row->id,
                    ]
                ),
            ]);
        }

        /**
         * Delete Quote.
         */
        if (bouncer()->hasPermission('quotes.delete')) {
            $this->addAction([
                'index' => 'delete',
                'icon' => 'icon-delete',

                'title' => trans(
                    'admin::app.quotes.index.datagrid.delete'
                ),

                'method' => 'DELETE',

                'url' => fn ($row) => route(
                    'admin.quotes.delete',
                    $row->id
                ),
            ]);
        }
    }

    /**
     * Prepare mass actions.
     */
    public function prepareMassActions(): void
    {
        if (bouncer()->hasPermission('quotes.delete')) {
            $this->addMassAction([
                'icon' => 'icon-delete',

                'title' => trans(
                    'admin::app.quotes.index.datagrid.delete'
                ),

                'method' => 'POST',

                'url' => route(
                    'admin.quotes.mass_delete'
                ),
            ]);
        }
    }
}