<?php

namespace Webkul\Admin\DataGrids\Inventory;

use Webkul\Admin\DataGrids\DeliveryOrder\DeliveryOrderDataGrid;

class InventoryConsumableUsageDataGrid extends DeliveryOrderDataGrid
{
    public function prepareColumns(): void
    {
        foreach ([
            'delivery_order_number' => 'Surat Jalan Number',
            'project_code' => 'Project Code',
            'project_name' => 'Project Name',
            'customer_name' => 'Customer',
            'invoice_number' => 'Invoice',
            'sales_person_name' => 'Sales Person',
            'event_date' => 'Event Date',
        ] as $index => $label) {
            $column = [
                'index' => $index,
                'label' => $label,
                'type' => $index === 'event_date' ? 'date' : 'string',
                'searchable' => $index !== 'event_date',
                'sortable' => true,
                'filterable' => true,
                'closure' => fn ($row) => e($row->{$index} ?: '-'),
            ];

            if ($index === 'delivery_order_number') {
                $column['closure'] = fn ($row) => sprintf(
                    '<a class="font-medium text-brandColor hover:underline" href="%s">%s</a>',
                    route('admin.inventory.consumable-usage.show', $row->id),
                    e($row->delivery_order_number)
                );
            }

            if ($index === 'event_date') {
                $column['closure'] = fn ($row) => $row->event_date ? date('d M Y', strtotime($row->event_date)) : '-';
            }

            $this->addColumn($column);
        }
    }

    public function prepareActions(): void
    {
        $this->addAction([
            'index' => 'usage',
            'icon' => '',
            'title' => 'Input Penggunaan',
            'method' => 'GET',
            'url' => fn ($row) => route('admin.inventory.consumable-usage.show', $row->id),
        ]);
    }
}
