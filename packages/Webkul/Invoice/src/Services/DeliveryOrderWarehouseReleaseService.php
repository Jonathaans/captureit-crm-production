<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Warehouse\Models\InventoryAsset;
use Webkul\Warehouse\Models\InventoryItem;
use Webkul\Warehouse\Models\InventoryStockMovement;

class DeliveryOrderWarehouseReleaseService
{
    /**
     * Release all allocated inventory when Surat Jalan is issued.
     *
     * Simplified warehouse flow:
     * serialized: ALLOCATED -> OUT
     * quantity:   ALLOCATED -> OUT and physical stock decreases
     *
     * No separate Picking / Scan OUT step.
     */
    public function releaseOnIssue(
        DeliveryOrder $deliveryOrder,
        ?int $performedBy = null
    ): void {
        if (
            strtolower(
                $deliveryOrder->status ?: 'draft'
            ) !== 'draft'
        ) {
            throw ValidationException::withMessages([
                'delivery_order' => 'Warehouse release hanya dapat dilakukan dari Surat Jalan DRAFT.',
            ]);
        }

        DB::transaction(function () use (
            $deliveryOrder,
            $performedBy
        ) {
            $deliveryOrder->unsetRelation('items');
            if (! (new DeliveryOrderInventoryAllocationService)->isComplete($deliveryOrder)) {
                throw ValidationException::withMessages([
                    'inventory' => 'Lengkapi pilihan inventory, jumlah pesanan, dan alokasi sebelum rilis Surat Jalan.',
                ]);
            }
            $allocations = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->whereIn('status', [
                    'allocated',
                    'picked',
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($allocations->isEmpty()) {
                throw ValidationException::withMessages([
                    'inventory' => 'Tidak ada inventory allocation yang dapat dirilis.',
                ]);
            }

            foreach ($allocations as $allocation) {
                $fromStatus = $allocation->status;

                $inventoryItem = InventoryItem::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $allocation->inventory_item_id
                    );

                $inventoryAsset = null;

                if ($allocation->tracking_type === 'serialized') {
                    $inventoryAsset = InventoryAsset::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $allocation->inventory_asset_id
                        );

                    if (
                        ! in_array(
                            $inventoryAsset->status,
                            [
                                'allocated',
                                'picked',
                            ],
                            true
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'inventory' => sprintf(
                                'Asset %s tidak dapat dirilis dari status %s.',
                                $inventoryAsset->asset_code,
                                strtoupper($inventoryAsset->status)
                            ),
                        ]);
                    }

                    $inventoryAsset->update([
                        'status' => 'out',
                    ]);

                    $notes = sprintf(
                        'Released with %s. Asset %s leaves warehouse.',
                        $deliveryOrder->delivery_order_number,
                        $inventoryAsset->asset_code
                    );
                } else {
                    $quantity = (float) $allocation->quantity;
                    $before = (float) $inventoryItem->quantity_on_hand;
                    $after = $before - $quantity;

                    if ($after < -0.0001) {
                        throw ValidationException::withMessages([
                            'inventory' => sprintf(
                                'Stock %s tidak cukup. Perlu %s %s, stock fisik %s %s.',
                                $inventoryItem->name,
                                $this->formatQuantity($quantity),
                                $inventoryItem->unit,
                                $this->formatQuantity($before),
                                $inventoryItem->unit
                            ),
                        ]);
                    }

                    $after = max($after, 0);

                    $inventoryItem->update([
                        'quantity_on_hand' => $after,
                    ]);

                    $notes = sprintf(
                        'Released with %s. Stock %s %s -> %s %s.',
                        $deliveryOrder->delivery_order_number,
                        $this->formatQuantity($before),
                        $inventoryItem->unit,
                        $this->formatQuantity($after),
                        $inventoryItem->unit
                    );
                }

                $allocation->update([
                    'status' => 'out',
                    'out_by' => $performedBy,
                    'out_at' => now(),
                ]);

                $this->recordMovement(
                    $deliveryOrder,
                    $inventoryItem,
                    $inventoryAsset,
                    'out',
                    (float) $allocation->quantity,
                    $fromStatus,
                    'out',
                    $performedBy,
                    $notes
                );
            }
        });
    }

    public function allOut(
        DeliveryOrder $deliveryOrder
    ): bool {
        $active = DeliveryOrderInventoryAllocation::query()
            ->where('delivery_order_id', $deliveryOrder->id)
            ->whereIn(
                'status',
                DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
            );

        if (! (clone $active)->exists()) {
            return false;
        }

        return ! (clone $active)
            ->where('status', '!=', 'out')
            ->exists();
    }

    private function recordMovement(
        DeliveryOrder $deliveryOrder,
        InventoryItem $inventoryItem,
        ?InventoryAsset $inventoryAsset,
        string $movementType,
        float $quantity,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $performedBy,
        string $notes
    ): InventoryStockMovement {
        return InventoryStockMovement::create([
            'inventory_item_id'     => $inventoryItem->id,
            'inventory_asset_id'    => $inventoryAsset?->id,
            'warehouse_id'          => $inventoryAsset?->warehouse_id
                ?? $inventoryItem->warehouse_id,
            'warehouse_location_id' => $inventoryAsset?->warehouse_location_id
                ?? $inventoryItem->warehouse_location_id,
            'movement_type'         => $movementType,
            'quantity'              => $quantity,
            'from_status'           => $fromStatus,
            'to_status'             => $toStatus,
            'reference_type'        => 'delivery_order',
            'reference_id'          => $deliveryOrder->id,
            'reference_number'      => $deliveryOrder->delivery_order_number,
            'performed_by'          => $performedBy,
            'notes'                 => $notes,
            'occurred_at'           => now(),
        ]);
    }

    private function formatQuantity(float $value): string
    {
        return rtrim(
            rtrim(
                number_format($value, 2, '.', ''),
                '0'
            ),
            '.'
        );
    }
}
