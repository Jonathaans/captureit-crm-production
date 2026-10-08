<?php

namespace Webkul\Admin\DataGrids\DeliveryOrder;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;
use Webkul\DataGrid\Enums\DateRangeOptionEnum;

class DeliveryOrderDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('delivery_orders')
            ->addSelect(
                'delivery_orders.id',
                'delivery_orders.delivery_order_number',
                'delivery_orders.invoice_id',
                'delivery_orders.invoice_number',
                'delivery_orders.quote_id',
                'delivery_orders.quote_number',
                'delivery_orders.project_code',
                'delivery_orders.business_unit',
                'delivery_orders.project_name',
                'delivery_orders.customer_name',
                'delivery_orders.sales_person_name',
                'delivery_orders.event_date',
                'delivery_orders.delivery_date',
                'delivery_orders.status',
                'delivery_orders.created_at'
            );

        /*
        |--------------------------------------------------------------------------
        | Filter Mapping
        |--------------------------------------------------------------------------
        */

        $this->addFilter(
            'delivery_order_number',
            'delivery_orders.delivery_order_number'
        );

        $this->addFilter(
            'invoice_number',
            'delivery_orders.invoice_number'
        );

        $this->addFilter(
            'project_code',
            'delivery_orders.project_code'
        );

        $this->addFilter(
            'business_unit',
            'delivery_orders.business_unit'
        );
        $this->addFilter(
            'project_name',
            'delivery_orders.project_name'
        );

        $this->addFilter(
            'customer_name',
            'delivery_orders.customer_name'
        );

        $this->addFilter(
            'sales_person_name',
            'delivery_orders.sales_person_name'
        );

        $this->addFilter(
            'event_date',
            'delivery_orders.event_date'
        );

        $this->addFilter(
            'delivery_date',
            'delivery_orders.delivery_date'
        );

        $this->addFilter(
            'status',
            'delivery_orders.status'
        );

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Surat Jalan Number
        |--------------------------------------------------------------------------
        */

        $this->addColumn([
            'index' => 'delivery_order_number',
            'label' => 'Surat Jalan Number',
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,

            'closure' => function ($row) {
                $route = route(
                    'admin.delivery-orders.show',
                    $row->id
                );

                return sprintf(
                    '<a class="text-brandColor font-medium transition-all hover:underline" href="%s">%s</a>',
                    $route,
                    e($row->delivery_order_number)
                );
            },
        ]);

        /*
        |--------------------------------------------------------------------------
        | Project Code
        |--------------------------------------------------------------------------
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

        /*
        |--------------------------------------------------------------------------
        | Project Name
        |--------------------------------------------------------------------------
        */

        $this->addColumn([
            'index' => 'project_name',
            'label' => 'Project Name',
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,

            'closure' => fn ($row) => $row->project_name ?: '-',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Customer
        |--------------------------------------------------------------------------
        */

        $this->addColumn([
            'index' => 'customer_name',
            'label' => 'Customer',
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,

            'closure' => fn ($row) => $row->customer_name ?: '-',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Invoice
        |--------------------------------------------------------------------------
        */

        $this->addColumn([
            'index' => 'invoice_number',
            'label' => 'Invoice',
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,

            'closure' => function ($row) {
                if (! $row->invoice_id) {
                    return $row->invoice_number ?: '-';
                }

                $label =
                    $row->invoice_number
                    ?: '#'.$row->invoice_id;

                $route = route(
                    'admin.invoices.show',
                    $row->invoice_id
                );

                return sprintf(
                    '<a class="text-brandColor transition-all hover:underline" href="%s">%s</a>',
                    $route,
                    e($label)
                );
            },
        ]);

        /*
        |--------------------------------------------------------------------------
        | Sales Person
        |--------------------------------------------------------------------------
        */

        $this->addColumn([
            'index' => 'sales_person_name',
            'label' => 'Sales Person',
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,

            'closure' => fn ($row) => $row->sales_person_name ?: '-',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Event Date
        |--------------------------------------------------------------------------
        */

        $this->addColumn([
            'index' => 'event_date',
            'label' => 'Event Date',
            'type' => 'date',
            'searchable' => false,
            'sortable' => true,
            'filterable' => true,

            'filterable_type' => 'date_range',
            'filterable_options' => fn () => DateRangeOptionEnum::options('Y-m-d'),

            'closure' => function ($row) {
                return $row->event_date
                    ? core()->formatDate(
                        $row->event_date,
                        'd M Y'
                    )
                    : '-';
            },
        ]);

        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        $this->addColumn([
            'index' => 'status',
            'label' => 'Status',
            'type' => 'string',
            'searchable' => false,
            'sortable' => true,
            'filterable' => true,

            'filterable_type' => 'dropdown',

            'filterable_options' => [
                [
                    'label' => 'Draft',
                    'value' => 'draft',
                ],
                [
                    'label' => 'Issued',
                    'value' => 'issued',
                ],
                [
                    'label' => 'Delivered',
                    'value' => 'delivered',
                ],
                [
                    'label' => 'Returned',
                    'value' => 'returned',
                ],
                [
                    'label' => 'Cancelled',
                    'value' => 'cancelled',
                ],
            ],

            'closure' => function ($row) {
                $status = strtolower(
                    $row->status ?: 'draft'
                );

                [$background, $color, $border] =
                    match ($status) {
                        'issued' => ['#dbeafe', '#1d4ed8', '#93c5fd'],

                        'delivered' => ['#dcfce7', '#15803d', '#86efac'],

                        'returned' => ['#f3e8ff', '#7e22ce', '#d8b4fe'],

                        'cancelled' => ['#fee2e2', '#b91c1c', '#fca5a5'],

                        default => ['#fef3c7', '#b45309', '#fcd34d'],
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
     * Event dates are SQL DATE values: include both ends without adding times.
     */
    protected function processRequestedFilters(array $requestedFilters)
    {
        if (isset($requestedFilters['event_date'])) {
            $ranges = $requestedFilters['event_date'];

            if (is_string($ranges)) {
                $option = collect(DateRangeOptionEnum::options('Y-m-d'))->firstWhere('name', $ranges);
                $ranges = [[$option['from'] ?? $ranges, $option['to'] ?? $ranges]];
            }

            foreach ($ranges as $range) {
                [$from, $to] = [$range[0] ?? '', $range[1] ?? ''];

                if ($from !== '') {
                    $this->queryBuilder->whereDate('delivery_orders.event_date', '>=', $from);
                }

                if ($to !== '') {
                    $this->queryBuilder->whereDate('delivery_orders.event_date', '<=', $to);
                }
            }

            unset($requestedFilters['event_date']);
        }

        return parent::processRequestedFilters($requestedFilters);
    }

    /**
     * Row actions.
     */
    public function prepareActions(): void
    {
        $this->addAction([
            'index' => 'view',
            'icon' => 'icon-eye',
            'title' => 'View Surat Jalan',
            'method' => 'GET',

            'url' => fn ($row) => route(
                'admin.delivery-orders.show',
                $row->id
            ),
        ]);
    }
}
