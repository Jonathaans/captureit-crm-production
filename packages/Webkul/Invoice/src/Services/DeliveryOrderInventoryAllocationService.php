<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Invoice\Models\DeliveryOrderItem;
use Webkul\Warehouse\Models\InventoryAsset;
use Webkul\Warehouse\Models\InventoryItem;
use Webkul\Warehouse\Models\InventoryStockMovement;

class DeliveryOrderInventoryAllocationService
{
    /**
     * Allocate satu serialized asset dari hasil scan QR / barcode.
     *
     * Scan tidak mengganti allocation lain yang sudah ada. Ia hanya
     * menambahkan satu asset ke requirement yang masih kurang.
     */
    public function allocateSerializedAsset(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderItem $deliveryOrderItem,
        InventoryAsset $inventoryAsset,
        ?int $performedBy = null
    ): DeliveryOrderInventoryAllocation {
        return DB::transaction(function () use (
            $deliveryOrder,
            $deliveryOrderItem,
            $inventoryAsset,
            $performedBy
        ) {
            [$deliveryOrder, $deliveryOrderItem] = $this->lockDraftRequirement($deliveryOrder, $deliveryOrderItem);

            $inventoryItem = $this->getMappedInventoryItem(
                $deliveryOrderItem
            );

            if (! $inventoryItem->isSerialized()) {
                throw ValidationException::withMessages([
                    'barcode' => 'Requirement ini bukan serialized asset.',
                ]);
            }

            $need = (float) $deliveryOrderItem->quantity;

            if (floor($need) !== $need) {
                throw ValidationException::withMessages([
                    'barcode' => 'Requirement serialized harus menggunakan quantity bilangan bulat.',
                ]);
            }

            $requiredCount = (int) $need;

            /*
             * Lock allocation requirement terlebih dahulu supaya dua scan
             * yang hampir bersamaan tidak dapat melewati batas NEED.
             */
            $currentAllocations = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->where('delivery_order_item_id', $deliveryOrderItem->id)
                ->where('tracking_type', 'serialized')
                ->whereIn(
                    'status',
                    DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                )
                ->lockForUpdate()
                ->get();

            $alreadyAllocated = $currentAllocations
                ->firstWhere(
                    'inventory_asset_id',
                    $inventoryAsset->id
                );

            if ($alreadyAllocated) {
                return $alreadyAllocated;
            }

            if ($currentAllocations->count() >= $requiredCount) {
                throw ValidationException::withMessages([
                    'barcode' => sprintf(
                        'Requirement %s sudah lengkap: %d / %d asset.',
                        $deliveryOrderItem->name,
                        $currentAllocations->count(),
                        $requiredCount
                    ),
                ]);
            }

            $lockedAsset = InventoryAsset::query()
                ->lockForUpdate()
                ->findOrFail($inventoryAsset->id);

            if (
                (int) $lockedAsset->inventory_item_id
                !== (int) $inventoryItem->id
            ) {
                throw ValidationException::withMessages([
                    'barcode' => sprintf(
                        'Asset %s tidak cocok dengan Inventory Item %s.',
                        $lockedAsset->asset_code,
                        $inventoryItem->code
                    ),
                ]);
            }

            /*
             * Hard lock against DOUBLE EVENT usage.
             *
             * Serialized asset is exclusive while its allocation is:
             * allocated / picked / out / return_pending.
             *
             * It can only be reused after the previous workflow releases
             * or returns the allocation and the asset itself is AVAILABLE.
             *
             * Asset row is locked before this query, so two nearly
             * simultaneous scans cannot both win.
             */
            $otherActiveAllocation = $this->activeSerializedConflict(
                $lockedAsset,
                $deliveryOrder
            );

            if ($otherActiveAllocation) {
                throw ValidationException::withMessages([
                    'barcode' => $this->doubleEventConflictMessage(
                        $lockedAsset,
                        $otherActiveAllocation
                    ),
                ]);
            }

            /*
             * The same physical asset may not satisfy two different
             * requirements in the same Surat Jalan either.
             */
            $sameDeliveryOrderAllocation = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->where('inventory_asset_id', $lockedAsset->id)
                ->whereIn(
                    'status',
                    DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                )
                ->lockForUpdate()
                ->first();

            if ($sameDeliveryOrderAllocation) {
                if (
                    (int) $sameDeliveryOrderAllocation->delivery_order_item_id
                    === (int) $deliveryOrderItem->id
                ) {
                    return $sameDeliveryOrderAllocation;
                }

                throw ValidationException::withMessages([
                    'barcode' => sprintf(
                        'Asset %s sudah dipakai untuk request lain di %s. Satu asset fisik hanya boleh dihitung sekali.',
                        $lockedAsset->asset_code,
                        $deliveryOrder->delivery_order_number
                    ),
                ]);
            }

            if ($lockedAsset->status !== 'available') {
                throw ValidationException::withMessages([
                    'barcode' => sprintf(
                        'Asset %s tidak AVAILABLE. Status saat ini: %s.',
                        $lockedAsset->asset_code,
                        strtoupper($lockedAsset->status)
                    ),
                ]);
            }

            $lockedAsset->update([
                'status' => 'allocated',
            ]);

            $allocation = DeliveryOrderInventoryAllocation::create([
                'delivery_order_id' => $deliveryOrder->id,
                'delivery_order_item_id' => $deliveryOrderItem->id,
                'inventory_item_id' => $inventoryItem->id,
                'inventory_asset_id' => $lockedAsset->id,
                'tracking_type' => 'serialized',
                'quantity' => 1,
                'status' => 'allocated',
                'allocated_by' => $performedBy,
                'allocated_at' => now(),
                'notes' => sprintf(
                    'Allocated by scan for %s / %s.',
                    $deliveryOrder->delivery_order_number,
                    $deliveryOrderItem->name
                ),
            ]);

            $this->recordMovement(
                $deliveryOrder,
                $inventoryItem,
                $lockedAsset,
                'allocated',
                1,
                'available',
                'allocated',
                $performedBy,
                sprintf(
                    'Scanned allocation to %s / %s.',
                    $deliveryOrder->delivery_order_number,
                    $deliveryOrderItem->name
                )
            );

            return $allocation;
        });
    }

    public function syncSerialized(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderItem $deliveryOrderItem,
        array $assetIds,
        ?int $performedBy = null
    ): void {
        DB::transaction(function () use (
            $deliveryOrder,
            $deliveryOrderItem,
            $assetIds,
            $performedBy
        ) {
            [$deliveryOrder, $deliveryOrderItem] = $this->lockDraftRequirement($deliveryOrder, $deliveryOrderItem);

            $inventoryItem = $this->getMappedInventoryItem(
                $deliveryOrderItem
            );

            if (! $inventoryItem->isSerialized()) {
                throw ValidationException::withMessages([
                    'asset_ids' => 'Inventory Item ini bukan serialized asset.',
                ]);
            }

            $need = (float) $deliveryOrderItem->quantity;

            if (floor($need) !== $need) {
                throw ValidationException::withMessages([
                    'asset_ids' => 'Requirement serialized harus menggunakan quantity bilangan bulat.',
                ]);
            }

            $requiredCount = (int) $need;

            $assetIds = collect($assetIds)
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if (count($assetIds) > $requiredCount) {
                throw ValidationException::withMessages([
                    'asset_ids' => sprintf(
                        'Maksimal %d asset dapat dialokasikan untuk %s.',
                        $requiredCount,
                        $deliveryOrderItem->name
                    ),
                ]);
            }

            $currentAllocations = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->where('delivery_order_item_id', $deliveryOrderItem->id)
                ->where('tracking_type', 'serialized')
                ->whereIn(
                    'status',
                    DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                )
                ->lockForUpdate()
                ->get();

            $currentAssetIds = $currentAllocations
                ->pluck('inventory_asset_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $toRelease = array_values(
                array_diff($currentAssetIds, $assetIds)
            );

            $toAllocate = array_values(
                array_diff($assetIds, $currentAssetIds)
            );

            foreach ($toRelease as $assetId) {
                $allocation = $currentAllocations
                    ->firstWhere('inventory_asset_id', $assetId);

                if (! $allocation) {
                    continue;
                }

                $asset = InventoryAsset::query()
                    ->lockForUpdate()
                    ->find($assetId);

                if (
                    $asset
                    && $asset->status === 'allocated'
                ) {
                    $asset->update([
                        'status' => 'available',
                    ]);

                    $this->recordMovement(
                        $deliveryOrder,
                        $inventoryItem,
                        $asset,
                        'released',
                        1,
                        'allocated',
                        'available',
                        $performedBy,
                        sprintf(
                            'Released from %s / %s.',
                            $deliveryOrder->delivery_order_number,
                            $deliveryOrderItem->name
                        )
                    );
                }

                $allocation->update([
                    'status' => 'released',
                    'released_by' => $performedBy,
                    'released_at' => now(),
                ]);
            }

            foreach ($toAllocate as $assetId) {
                $asset = InventoryAsset::query()
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->lockForUpdate()
                    ->find($assetId);

                if (! $asset) {
                    throw ValidationException::withMessages([
                        'asset_ids' => "Asset ID {$assetId} tidak cocok dengan Inventory Item.",
                    ]);
                }

                $otherActiveAllocation = $this->activeSerializedConflict(
                    $asset,
                    $deliveryOrder
                );

                if ($otherActiveAllocation) {
                    throw ValidationException::withMessages([
                        'asset_ids' => $this->doubleEventConflictMessage(
                            $asset,
                            $otherActiveAllocation
                        ),
                    ]);
                }

                if ($asset->status !== 'available') {
                    throw ValidationException::withMessages([
                        'asset_ids' => sprintf(
                            'Asset %s tidak AVAILABLE. Status saat ini: %s.',
                            $asset->asset_code,
                            strtoupper($asset->status)
                        ),
                    ]);
                }

                $asset->update([
                    'status' => 'allocated',
                ]);

                DeliveryOrderInventoryAllocation::create([
                    'delivery_order_id' => $deliveryOrder->id,
                    'delivery_order_item_id' => $deliveryOrderItem->id,
                    'inventory_item_id' => $inventoryItem->id,
                    'inventory_asset_id' => $asset->id,
                    'tracking_type' => 'serialized',
                    'quantity' => 1,
                    'status' => 'allocated',
                    'allocated_by' => $performedBy,
                    'allocated_at' => now(),
                    'notes' => sprintf(
                        'Allocated for %s / %s.',
                        $deliveryOrder->delivery_order_number,
                        $deliveryOrderItem->name
                    ),
                ]);

                $this->recordMovement(
                    $deliveryOrder,
                    $inventoryItem,
                    $asset,
                    'allocated',
                    1,
                    'available',
                    'allocated',
                    $performedBy,
                    sprintf(
                        'Allocated to %s / %s.',
                        $deliveryOrder->delivery_order_number,
                        $deliveryOrderItem->name
                    )
                );
            }
        });
    }

    /**
     * Auto reserve semua quantity-tracked requirement yang stoknya cukup.
     *
     * Tidak ada tombol Save per quantity pada simplified warehouse flow.
     * Bila stock tidak cukup, item dibiarkan INCOMPLETE agar Surat Jalan
     * tidak dapat di-issue.
     *
     * @return array<int, string> Error per Delivery Order Item ID.
     */
    public function autoReserveQuantityRequirements(
        DeliveryOrder $deliveryOrder,
        ?int $performedBy = null
    ): array {
        $this->assertDraft($deliveryOrder);

        $deliveryOrder->loadMissing([
            'items.inventoryItem',
        ]);

        $errors = [];

        foreach ($deliveryOrder->items as $item) {
            $inventoryItem = $item->inventoryItem;

            if (
                ! $inventoryItem
                || ! $inventoryItem->isQuantityTracked()
            ) {
                continue;
            }

            try {
                $this->syncQuantity(
                    $deliveryOrder,
                    $item,
                    (float) $item->quantity,
                    $performedBy
                );
            } catch (ValidationException $exception) {
                $messages = $exception->errors();

                $errors[$item->id] = collect($messages)
                    ->flatten()
                    ->first()
                    ?: 'Stock quantity tidak cukup untuk auto reserve.';
            }
        }

        return $errors;
    }

    public function syncQuantity(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderItem $deliveryOrderItem,
        float $quantity,
        ?int $performedBy = null
    ): void {
        DB::transaction(function () use (
            $deliveryOrder,
            $deliveryOrderItem,
            $quantity,
            $performedBy
        ) {
            [$deliveryOrder, $deliveryOrderItem] = $this->lockDraftRequirement($deliveryOrder, $deliveryOrderItem);

            $inventoryItem = $this->getMappedInventoryItem(
                $deliveryOrderItem
            );

            if (! $inventoryItem->isQuantityTracked()) {
                throw ValidationException::withMessages([
                    'quantity' => 'Inventory Item ini bukan quantity tracked item.',
                ]);
            }

            $need = (float) $deliveryOrderItem->quantity;
            $quantity = round($quantity, 2);

            if ($quantity < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Allocation quantity tidak boleh negatif.',
                ]);
            }

            if ($quantity > $need) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'Allocation maksimal %s %s sesuai requirement Surat Jalan.',
                        $this->formatQuantity($need),
                        $inventoryItem->unit
                    ),
                ]);
            }

            $lockedItem = InventoryItem::query()
                ->lockForUpdate()
                ->findOrFail($inventoryItem->id);

            $currentAllocations = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->where('delivery_order_item_id', $deliveryOrderItem->id)
                ->where('tracking_type', 'quantity')
                ->whereIn(
                    'status',
                    DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                )
                ->lockForUpdate()
                ->get();

            $currentQuantity = (float) $currentAllocations->sum('quantity');

            $reservedOther = (float) DeliveryOrderInventoryAllocation::query()
                ->where('inventory_item_id', $lockedItem->id)
                ->where('tracking_type', 'quantity')
                ->whereIn(
                    'status',
                    DeliveryOrderInventoryAllocation::RESERVATION_STATUSES
                )
                ->where(function ($query) use (
                    $deliveryOrder,
                    $deliveryOrderItem
                ) {
                    $query
                        ->where('delivery_order_id', '!=', $deliveryOrder->id)
                        ->orWhere('delivery_order_item_id', '!=', $deliveryOrderItem->id);
                })
                ->sum('quantity');

            $maxForThisItem = max(
                (float) $lockedItem->quantity_on_hand - $reservedOther,
                0
            );

            if ($quantity > $maxForThisItem) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'Stock bebas tidak cukup. Maksimal allocation saat ini %s %s.',
                        $this->formatQuantity($maxForThisItem),
                        $lockedItem->unit
                    ),
                ]);
            }

            if (abs($currentQuantity - $quantity) < 0.0001) {
                return;
            }

            if ($currentQuantity > 0) {
                foreach ($currentAllocations as $allocation) {
                    $allocation->update([
                        'status' => 'released',
                        'released_by' => $performedBy,
                        'released_at' => now(),
                    ]);
                }

                $this->recordMovement(
                    $deliveryOrder,
                    $lockedItem,
                    null,
                    'released',
                    $currentQuantity,
                    'allocated',
                    'available',
                    $performedBy,
                    sprintf(
                        'Released quantity reservation from %s / %s.',
                        $deliveryOrder->delivery_order_number,
                        $deliveryOrderItem->name
                    )
                );
            }

            if ($quantity <= 0) {
                return;
            }

            DeliveryOrderInventoryAllocation::create([
                'delivery_order_id' => $deliveryOrder->id,
                'delivery_order_item_id' => $deliveryOrderItem->id,
                'inventory_item_id' => $lockedItem->id,
                'inventory_asset_id' => null,
                'tracking_type' => 'quantity',
                'quantity' => $quantity,
                'status' => 'allocated',
                'allocated_by' => $performedBy,
                'allocated_at' => now(),
                'notes' => sprintf(
                    'Reserved for %s / %s.',
                    $deliveryOrder->delivery_order_number,
                    $deliveryOrderItem->name
                ),
            ]);

            $this->recordMovement(
                $deliveryOrder,
                $lockedItem,
                null,
                'allocated',
                $quantity,
                'available',
                'allocated',
                $performedBy,
                sprintf(
                    'Reserved %s %s for %s / %s.',
                    $this->formatQuantity($quantity),
                    $lockedItem->unit,
                    $deliveryOrder->delivery_order_number,
                    $deliveryOrderItem->name
                )
            );
        });
    }

    public function releaseItem(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderItem $deliveryOrderItem,
        ?int $performedBy = null
    ): void {
        $inventoryItem = $deliveryOrderItem->inventoryItem;

        if (! $inventoryItem) {
            return;
        }

        if ($inventoryItem->isSerialized()) {
            $this->syncSerialized(
                $deliveryOrder,
                $deliveryOrderItem,
                [],
                $performedBy
            );

            return;
        }

        $this->syncQuantity(
            $deliveryOrder,
            $deliveryOrderItem,
            0,
            $performedBy
        );
    }

    public function releaseAll(
        DeliveryOrder $deliveryOrder,
        ?int $performedBy = null
    ): void {
        $deliveryOrder->loadMissing([
            'items.inventoryItem',
        ]);

        foreach ($deliveryOrder->items as $item) {
            if (! $item->inventoryItem) {
                continue;
            }

            if ($item->inventoryItem->isSerialized()) {
                $this->releaseSerializedWithoutDraftGuard(
                    $deliveryOrder,
                    $item,
                    $performedBy
                );

                continue;
            }

            $this->releaseQuantityWithoutDraftGuard(
                $deliveryOrder,
                $item,
                $performedBy
            );
        }
    }

    public function isComplete(
        DeliveryOrder $deliveryOrder
    ): bool {
        $deliveryOrder->loadMissing([
            'items.inventoryItem',
        ]);

        $mappedItems = $deliveryOrder->items
            ->filter(fn ($item) => $item->inventoryItem || $item->requires_inventory);

        if ($mappedItems->isEmpty()) {
            return true;
        }

        foreach ($mappedItems as $item) {
            $need = (float) $item->quantity;
            if (! $item->inventoryItem || ($item->requires_inventory && $need <= 0)) {
                return false;
            }

            if ($item->inventoryItem->isSerialized()) {
                if (floor($need) !== $need) {
                    return false;
                }

                $allocated = DeliveryOrderInventoryAllocation::query()
                    ->where('delivery_order_item_id', $item->id)
                    ->where('tracking_type', 'serialized')
                    ->whereIn(
                        'status',
                        DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                    )
                    ->count();

                if ($allocated < (int) $need) {
                    return false;
                }

                continue;
            }

            $allocated = (float) DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_item_id', $item->id)
                ->where('tracking_type', 'quantity')
                ->whereIn(
                    'status',
                    DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                )
                ->sum('quantity');

            if ($allocated + 0.0001 < $need) {
                return false;
            }
        }

        return true;
    }

    public function incompleteItemNames(
        DeliveryOrder $deliveryOrder
    ): array {
        $deliveryOrder->loadMissing([
            'items.inventoryItem',
        ]);

        $names = [];

        foreach ($deliveryOrder->items as $item) {
            if ($item->requires_inventory && (! $item->inventoryItem || (float) $item->quantity <= 0)) {
                $names[] = $item->name;

                continue;
            }
            if (! $item->inventoryItem) {
                continue;
            }

            $need = (float) $item->quantity;

            if ($item->inventoryItem->isSerialized()) {
                $allocated = DeliveryOrderInventoryAllocation::query()
                    ->where('delivery_order_item_id', $item->id)
                    ->where('tracking_type', 'serialized')
                    ->whereIn(
                        'status',
                        DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                    )
                    ->count();

                if (
                    floor($need) !== $need
                    || $allocated < (int) $need
                ) {
                    $names[] = $item->name;
                }

                continue;
            }

            $allocated = (float) DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_item_id', $item->id)
                ->where('tracking_type', 'quantity')
                ->whereIn(
                    'status',
                    DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                )
                ->sum('quantity');

            if ($allocated + 0.0001 < $need) {
                $names[] = $item->name;
            }
        }

        return array_values(array_unique($names));
    }

    /** Serialize edits, scans and issue, then re-read quantities and mapping. */
    private function lockDraftRequirement(DeliveryOrder $deliveryOrder, DeliveryOrderItem $item): array
    {
        $lockedOrder = DeliveryOrder::query()->lockForUpdate()->findOrFail($deliveryOrder->id);
        $this->assertDraft($lockedOrder);
        $lockedItem = DeliveryOrderItem::query()
            ->where('delivery_order_id', $lockedOrder->id)
            ->lockForUpdate()->find($item->id);
        if (! $lockedItem) {
            throw ValidationException::withMessages([
                'inventory' => 'Daftar equipment sudah berubah. Muat ulang halaman Allocation sebelum scan kembali.',
            ]);
        }

        return [$lockedOrder, $lockedItem];
    }

    private function getMappedInventoryItem(
        DeliveryOrderItem $deliveryOrderItem
    ): InventoryItem {
        if ($deliveryOrderItem->requires_inventory && (float) $deliveryOrderItem->quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Isi jumlah sesuai pesanan pada Surat Jalan sebelum alokasi.',
            ]);
        }
        $inventoryItem = $deliveryOrderItem->inventoryItem;

        if (! $inventoryItem) {
            throw ValidationException::withMessages([
                'inventory_item_id' => 'Delivery Order item belum terhubung ke Inventory Item.',
            ]);
        }

        return $inventoryItem;
    }

    private function assertDraft(
        DeliveryOrder $deliveryOrder
    ): void {
        if (
            strtolower($deliveryOrder->status ?: 'draft')
            !== 'draft'
        ) {
            throw ValidationException::withMessages([
                'delivery_order' => 'Allocation hanya dapat diubah ketika Surat Jalan masih DRAFT.',
            ]);
        }
    }

    private function releaseSerializedWithoutDraftGuard(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderItem $deliveryOrderItem,
        ?int $performedBy
    ): void {
        DB::transaction(function () use (
            $deliveryOrder,
            $deliveryOrderItem,
            $performedBy
        ) {
            $allocations = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->where('delivery_order_item_id', $deliveryOrderItem->id)
                ->where('tracking_type', 'serialized')
                ->where('status', 'allocated')
                ->lockForUpdate()
                ->get();

            foreach ($allocations as $allocation) {
                $asset = InventoryAsset::query()
                    ->lockForUpdate()
                    ->find($allocation->inventory_asset_id);

                if ($asset && $asset->status === 'allocated') {
                    $asset->update([
                        'status' => 'available',
                    ]);

                    $this->recordMovement(
                        $deliveryOrder,
                        $deliveryOrderItem->inventoryItem,
                        $asset,
                        'released',
                        1,
                        'allocated',
                        'available',
                        $performedBy,
                        "Released because {$deliveryOrder->delivery_order_number} was cancelled."
                    );
                }

                $allocation->update([
                    'status' => 'released',
                    'released_by' => $performedBy,
                    'released_at' => now(),
                ]);
            }
        });
    }

    private function releaseQuantityWithoutDraftGuard(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderItem $deliveryOrderItem,
        ?int $performedBy
    ): void {
        DB::transaction(function () use (
            $deliveryOrder,
            $deliveryOrderItem,
            $performedBy
        ) {
            $allocations = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->where('delivery_order_item_id', $deliveryOrderItem->id)
                ->where('tracking_type', 'quantity')
                ->where('status', 'allocated')
                ->lockForUpdate()
                ->get();

            $quantity = (float) $allocations->sum('quantity');

            if ($quantity <= 0) {
                return;
            }

            foreach ($allocations as $allocation) {
                $allocation->update([
                    'status' => 'released',
                    'released_by' => $performedBy,
                    'released_at' => now(),
                ]);
            }

            $this->recordMovement(
                $deliveryOrder,
                $deliveryOrderItem->inventoryItem,
                null,
                'released',
                $quantity,
                'allocated',
                'available',
                $performedBy,
                "Released because {$deliveryOrder->delivery_order_number} was cancelled."
            );
        });
    }

    /**
     * Find active usage of one serialized physical asset in another SJ.
     *
     * ACTIVE_STATUSES intentionally does NOT include returned/released.
     * Therefore after Return is finalized (or a draft allocation is
     * released/cancelled), the asset may be used again.
     */
    private function activeSerializedConflict(
        InventoryAsset $asset,
        DeliveryOrder $currentDeliveryOrder
    ): ?DeliveryOrderInventoryAllocation {
        return DeliveryOrderInventoryAllocation::query()
            ->with([
                'deliveryOrder',
                'deliveryOrderItem',
            ])
            ->where('inventory_asset_id', $asset->id)
            ->where('tracking_type', 'serialized')
            ->where(
                'delivery_order_id',
                '!=',
                $currentDeliveryOrder->id
            )
            ->whereIn(
                'status',
                DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
            )
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Human-readable warning for warehouse scanner operators.
     */
    private function doubleEventConflictMessage(
        InventoryAsset $asset,
        DeliveryOrderInventoryAllocation $allocation
    ): string {
        $otherDeliveryOrder = $allocation->deliveryOrder;

        $deliveryOrderNumber = $otherDeliveryOrder?->delivery_order_number
            ?: 'SJ #'.$allocation->delivery_order_id;

        $project = trim(
            (string) (
                $otherDeliveryOrder?->project_name
                ?: $otherDeliveryOrder?->project_code
                ?: '-'
            )
        );

        $eventDate = $otherDeliveryOrder?->event_date
            ? $otherDeliveryOrder->event_date->format('d M Y')
            : '-';

        $eventTime = trim(
            (string) (
                $otherDeliveryOrder?->event_time
                ?: ''
            )
        );

        $event = $eventDate;

        if ($eventTime !== '') {
            $event .= ' '.$eventTime;
        }

        $sjStatus = strtoupper(
            (string) (
                $otherDeliveryOrder?->status
                ?: '-'
            )
        );

        $inventoryStatus = strtoupper(
            (string) $allocation->status
        );

        return sprintf(
            'DOUBLE EVENT BLOCKED: %s sedang dipakai oleh %s | Project: %s | Event: %s | SJ Status: %s | Inventory: %s. Asset baru dapat dipakai lagi setelah SJ sebelumnya selesai Return dan asset kembali AVAILABLE.',
            $asset->asset_code,
            $deliveryOrderNumber,
            $project,
            $event,
            $sjStatus,
            $inventoryStatus
        );
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
            'inventory_item_id' => $inventoryItem->id,
            'inventory_asset_id' => $inventoryAsset?->id,
            'warehouse_id' => $inventoryAsset?->warehouse_id
                ?? $inventoryItem->warehouse_id,
            'warehouse_location_id' => $inventoryAsset?->warehouse_location_id
                ?? $inventoryItem->warehouse_location_id,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reference_type' => 'delivery_order',
            'reference_id' => $deliveryOrder->id,
            'reference_number' => $deliveryOrder->delivery_order_number,
            'performed_by' => $performedBy,
            'notes' => $notes,
            'occurred_at' => now(),
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
