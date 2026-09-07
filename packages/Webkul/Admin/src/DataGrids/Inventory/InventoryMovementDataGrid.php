<?php

namespace Webkul\Admin\DataGrids\Inventory;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class InventoryMovementDataGrid extends DataGrid
{
    /** INVENTORY MOVEMENT LIVE V1: newest movement first. */
    protected $sortColumn = 'occurred_at';

    protected $sortOrder = 'desc';

    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('inventory_stock_movements')
            ->join(
                'inventory_items',
                'inventory_stock_movements.inventory_item_id',
                '=',
                'inventory_items.id'
            )
            ->leftJoin(
                'inventory_assets',
                'inventory_stock_movements.inventory_asset_id',
                '=',
                'inventory_assets.id'
            )
            ->leftJoin(
                'warehouses',
                'inventory_stock_movements.warehouse_id',
                '=',
                'warehouses.id'
            )
            ->leftJoin(
                'users',
                'inventory_stock_movements.performed_by',
                '=',
                'users.id'
            )
            ->select(
                'inventory_stock_movements.id',
                'inventory_stock_movements.inventory_item_id',
                'inventory_stock_movements.inventory_asset_id',
                'inventory_stock_movements.movement_type',
                'inventory_stock_movements.quantity',
                'inventory_stock_movements.from_status',
                'inventory_stock_movements.to_status',
                'inventory_stock_movements.reference_type',
                'inventory_stock_movements.reference_id',
                'inventory_stock_movements.reference_number',
                'inventory_stock_movements.notes',
                'inventory_stock_movements.occurred_at',
                'inventory_items.code as item_code',
                'inventory_items.name as item_name',
                'inventory_items.tracking_type',
                'inventory_items.unit',
                'inventory_assets.asset_code',
                'warehouses.name as warehouse_name',
                'users.name as performed_by_name'
            );

        if (request()->filled('inventory_item_id')) {
            $queryBuilder->where(
                'inventory_stock_movements.inventory_item_id',
                request()->integer('inventory_item_id')
            );
        }

        if (request()->filled('inventory_asset_id')) {
            $queryBuilder->where(
                'inventory_stock_movements.inventory_asset_id',
                request()->integer('inventory_asset_id')
            );
        }

        $this->addFilter(
            'item_code',
            'inventory_items.code'
        );

        $this->addFilter(
            'item_name',
            'inventory_items.name'
        );

        $this->addFilter(
            'asset_code',
            'inventory_assets.asset_code'
        );

        $this->addFilter(
            'movement_type',
            'inventory_stock_movements.movement_type'
        );

        $this->addFilter(
            'reference_type',
            'inventory_stock_movements.reference_type'
        );

        $this->addFilter(
            'reference_number',
            'inventory_stock_movements.reference_number'
        );

        $this->addFilter(
            'occurred_at',
            'inventory_stock_movements.occurred_at'
        );

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'occurred_at',
            'label'      => 'Date / Time',
            'type'       => 'datetime',
            'searchable' => false,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => fn ($row) => date(
                'd M Y H:i',
                strtotime((string) $row->occurred_at)
            ),
        ]);

        $this->addColumn([
            'index'      => 'item_name',
            'label'      => 'Inventory Item',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => fn ($row) => sprintf(
                '%s — %s',
                e($row->item_code),
                e($row->item_name)
            ),
        ]);

        $this->addColumn([
            'index'      => 'asset_code',
            'label'      => 'Asset',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => fn ($row) => $row->asset_code ?: '-',
        ]);

        $this->addColumn([
            'index'              => 'movement_type',
            'label'              => 'Movement',
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => 'Opening', 'value' => 'opening'],
                ['label' => 'Stock In', 'value' => 'stock_in'],
                ['label' => 'Stock Out', 'value' => 'stock_out'],
                ['label' => 'Adjustment In', 'value' => 'adjustment_in'],
                ['label' => 'Adjustment Out', 'value' => 'adjustment_out'],
                ['label' => 'Allocated', 'value' => 'allocated'],
                ['label' => 'Released', 'value' => 'released'],
                ['label' => 'Picked', 'value' => 'picked'],
                ['label' => 'Out', 'value' => 'out'],
                ['label' => 'Return Pending', 'value' => 'return_pending'],
                ['label' => 'Returned', 'value' => 'returned'],
                ['label' => 'Damaged', 'value' => 'damaged'],
                ['label' => 'Missing', 'value' => 'missing'],
                ['label' => 'Maintenance', 'value' => 'maintenance'],
                ['label' => 'Retired', 'value' => 'retired'],
                ['label' => 'Maintenance Started', 'value' => 'maintenance_started'],
                ['label' => 'Maintenance Completed', 'value' => 'maintenance_completed'],
                ['label' => 'Asset Retired', 'value' => 'asset_retired'],
                ['label' => 'Stock Opname Missing', 'value' => 'stock_opname_missing'],
                // MISSING_ASSET_RECOVERY_V1_MOVEMENT
                /* INVENTORY_MISSING_RECOVERY_MOVEMENT_V1 */
                ['label' => 'Missing Recovered', 'value' => 'missing_recovered'],
                ['label' => 'Stock Opname Found', 'value' => 'stock_opname_found'],
                ['label' => 'Stock Opname Adjustment In', 'value' => 'stock_opname_adjustment_in'],
                ['label' => 'Stock Opname Adjustment Out', 'value' => 'stock_opname_adjustment_out'],
            ],
            'closure' => fn ($row) => $this->movementBadge(
                (string) $row->movement_type
            ),
        ]);

        $this->addColumn([
            'index'      => 'quantity',
            'label'      => 'Qty',
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => true,
            'filterable' => false,
            'closure'    => function ($row) {
                $qty = rtrim(
                    rtrim(
                        number_format(
                            (float) $row->quantity,
                            2,
                            '.',
                            ''
                        ),
                        '0'
                    ),
                    '.'
                );

                return $qty.' '.e((string) $row->unit);
            },
        ]);

        $this->addColumn([
            'index'      => 'status_change',
            'label'      => 'Status Change',
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => false,
            'filterable' => false,
            'closure'    => function ($row) {
                if (! $row->from_status && ! $row->to_status) {
                    return '-';
                }

                return sprintf(
                    '%s → %s',
                    e($row->from_status ?: '-'),
                    e($row->to_status ?: '-')
                );
            },
        ]);

        $this->addColumn([
            'index'              => 'reference_type',
            'label'              => 'Reference Type',
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => 'Surat Jalan', 'value' => 'delivery_order'],
                ['label' => 'Stock Opname', 'value' => 'stock_opname'],
                ['label' => 'Missing Recovery', 'value' => 'missing_recovery'],
                ['label' => 'Maintenance', 'value' => 'maintenance'],
                ['label' => 'Manual Stock Movement', 'value' => 'manual_stock_movement'],
                ['label' => 'Opening', 'value' => 'opening'],
            ],
            'closure' => fn ($row) => $this->referenceTypeBadge(
                (string) ($row->reference_type ?: '')
            ),
        ]);

        $this->addColumn([
            'index'      => 'reference_number',
            'label'      => 'Reference',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => fn ($row) => $this->referenceLink($row),
        ]);

        $this->addColumn([
            'index'      => 'performed_by_name',
            'label'      => 'Performed By',
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => true,
            'filterable' => false,
            'closure'    => fn ($row) => $row->performed_by_name ?: 'System / Migration',
        ]);

        $this->addColumn([
            'index'      => 'warehouse_name',
            'label'      => 'Warehouse',
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => false,
            'filterable' => false,
            'closure'    => fn ($row) => $row->warehouse_name ?: '-',
        ]);

        $this->addColumn([
            'index'      => 'notes',
            'label'      => 'Notes',
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => false,
            'filterable' => false,
            'closure'    => fn ($row) => $row->notes ?: '-',
        ]);
    }

    public function prepareActions(): void
    {
        // Ledger is intentionally read-only.
        // No edit or delete actions are registered.
    }

    /**
     * Render a human-readable source for each inventory movement.
     *
     * Event workflow movements are always identified as Surat Jalan.
     */
    private function referenceTypeBadge(string $referenceType): string
    {
        $types = [
            'delivery_order'        => ['SURAT JALAN', '#fff7ed', '#c2410c'],
            'stock_opname'          => ['STOCK OPNAME', '#ecfeff', '#0e7490'],
            'missing_recovery'      => ['MISSING RECOVERY', '#dcfce7', '#15803d'],
            'maintenance'           => ['MAINTENANCE', '#f3e8ff', '#7e22ce'],
            'manual_stock_movement' => ['MANUAL', '#f3f4f6', '#4b5563'],
            'opening'               => ['OPENING', '#e0f2fe', '#0369a1'],
        ];

        [$label, $background, $color] = $types[$referenceType]
            ?? [
                $referenceType !== ''
                    ? strtoupper(
                        str_replace('_', ' ', $referenceType)
                    )
                    : '-',
                '#f3f4f6',
                '#4b5563',
            ];

        return sprintf(
            '<span style="display:inline-flex;padding:4px 9px;border-radius:9999px;background:%s;color:%s;font-weight:700;font-size:10px;white-space:nowrap;">%s</span>',
            $background,
            $color,
            e($label)
        );
    }

    /**
     * Surat Jalan references become clickable when the current user is
     * allowed to view Delivery Orders.
     */
    private function referenceLink(object $row): string
    {
        $referenceNumber = trim(
            (string) ($row->reference_number ?: '')
        );

        if ($referenceNumber === '') {
            return '-';
        }

        if (
            $row->reference_type === 'delivery_order'
            && ! empty($row->reference_id)
            && bouncer()->hasPermission('delivery-orders.view')
        ) {
            return sprintf(
                '<a href="%s" style="color:#dc2626;font-weight:700;text-decoration:none;white-space:nowrap;" title="Open Surat Jalan %s">%s ↗</a>',
                e(
                    route(
                        'admin.delivery-orders.show',
                        $row->reference_id
                    )
                ),
                e($referenceNumber),
                e($referenceNumber)
            );
        }

        return sprintf(
            '<span style="font-weight:600;white-space:nowrap;">%s</span>',
            e($referenceNumber)
        );
    }

    private function movementBadge(string $movementType): string
    {
        $labels = [
            'opening'        => ['OPENING', '#e0f2fe', '#0369a1'],
            'stock_in'       => ['STOCK IN', '#dcfce7', '#15803d'],
            'stock_out'      => ['STOCK OUT', '#ffedd5', '#c2410c'],
            'adjustment_in'  => ['ADJ. IN', '#d1fae5', '#047857'],
            'adjustment_out' => ['ADJ. OUT', '#fee2e2', '#b91c1c'],
            'allocated'      => ['ALLOCATED', '#fef3c7', '#a16207'],
            'released'       => ['RELEASED', '#f3f4f6', '#4b5563'],
            'picked'         => ['PICKED', '#dbeafe', '#1d4ed8'],
            'out'            => ['OUT', '#e0e7ff', '#4338ca'],
            'return_pending' => ['RETURN PENDING', '#ffedd5', '#c2410c'],
            'returned'       => ['RETURNED', '#dcfce7', '#15803d'],
            'damaged'        => ['DAMAGED', '#fee2e2', '#b91c1c'],
            'missing'        => ['MISSING', '#fee2e2', '#991b1b'],
            'maintenance'    => ['MAINTENANCE', '#f3e8ff', '#7e22ce'],
            'retired'                     => ['RETIRED', '#f3f4f6', '#4b5563'],
            'maintenance_started'         => ['MAINT. START', '#f3e8ff', '#7e22ce'],
            'maintenance_completed'       => ['MAINT. DONE', '#dcfce7', '#15803d'],
            'asset_retired'               => ['ASSET RETIRED', '#f3f4f6', '#4b5563'],
            'missing_recovered' => ['MISSING RECOVERED', '#dcfce7', '#15803d'],
            'stock_opname_missing'        => ['OPNAME MISSING', '#fee2e2', '#991b1b'],
            'stock_opname_found'          => ['OPNAME FOUND', '#dcfce7', '#15803d'],
            'stock_opname_adjustment_in'  => ['OPNAME ADJ. IN', '#d1fae5', '#047857'],
            'stock_opname_adjustment_out' => ['OPNAME ADJ. OUT', '#fee2e2', '#b91c1c'],
        ];

        [$label, $background, $color] = $labels[$movementType]
            ?? [strtoupper($movementType), '#f3f4f6', '#4b5563'];

        return sprintf(
            '<span style="padding:4px 9px;border-radius:9999px;background:%s;color:%s;font-weight:700;font-size:11px;">%s</span>',
            $background,
            $color,
            e($label)
        );
    }
}
