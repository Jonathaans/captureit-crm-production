<?php

namespace Webkul\Admin\Http\Controllers\DeliveryOrder;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Invoice\Services\DeliveryOrderInventoryAllocationService;
use Webkul\Warehouse\Models\InventoryAsset;

class DeliveryOrderInventoryController extends Controller
{
    public function __construct(
        protected DeliveryOrderInventoryAllocationService $allocationService
    ) {
    }

    public function edit(int $id): View
    {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $editable = strtolower(
            $deliveryOrder->status ?: 'draft'
        ) === 'draft';

        /*
         * Quantity allocation is manual in Phase 3G.
         *
         * Serialized / barcode assets:
         *     scan -> ALLOCATED
         *
         * Quantity items such as Paper Roll / Frame:
         *     operator enters prepared quantity manually.
         */
        $summaries = [];

        foreach ($deliveryOrder->items as $item) {
            $inventoryItem = $item->inventoryItem;
            $need = (float) $item->quantity;

            if (! $inventoryItem || ($item->requires_inventory && $need <= 0)) {
                $summaries[$item->id] = [
                    'tracking_type'  => null,
                    'need'           => $need,
                    'allocated'      => 0,
                    'free_available' => 0,
                    'shortage'       => $need,
                    'complete'       => false,
                ];

                continue;
            }

            $activeAllocations = $item->allocations
                ->whereIn(
                    'status',
                    DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                );

            if ($inventoryItem->isSerialized()) {
                $allocated = $activeAllocations
                    ->where('tracking_type', 'serialized')
                    ->count();

                $freeAvailable = InventoryAsset::query()
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->where('status', 'available')
                    ->count();

                $shortage = max(
                    $need - ($allocated + $freeAvailable),
                    0
                );

                $summaries[$item->id] = [
                    'tracking_type'  => 'serialized',
                    'need'           => $need,
                    'allocated'      => $allocated,
                    'free_available' => $freeAvailable,
                    'shortage'       => $shortage,
                    'complete'       => floor($need) === $need
                        && $allocated >= (int) $need,
                ];

                continue;
            }

            $allocated = (float) $activeAllocations
                ->where('tracking_type', 'quantity')
                ->sum('quantity');

            $totalReserved = (float) DeliveryOrderInventoryAllocation::query()
                ->where('inventory_item_id', $inventoryItem->id)
                ->where('tracking_type', 'quantity')
                ->whereIn(
                    'status',
                    DeliveryOrderInventoryAllocation::RESERVATION_STATUSES
                )
                ->sum('quantity');

            $freeAvailable = max(
                (float) $inventoryItem->quantity_on_hand
                - $totalReserved,
                0
            );

            $shortage = max(
                $need - ($allocated + $freeAvailable),
                0
            );

            $summaries[$item->id] = [
                'tracking_type'  => 'quantity',
                'need'           => $need,
                'allocated'      => $allocated,
                'free_available' => $freeAvailable,
                'shortage'       => $shortage,
                'complete'       => $allocated + 0.0001 >= $need,
            ];
        }

        return view(
            'admin::delivery-orders.inventory-allocation',
            compact(
                'deliveryOrder',
                'summaries',
                'editable'
            )
        );
    }

    /**
     * Scan serialized asset langsung menjadi ALLOCATED.
     *
     * Scanner USB bekerja seperti keyboard:
     * QR -> kode -> Enter -> request ini.
     *
     * Sistem otomatis mencari requirement Surat Jalan yang Inventory Item-nya
     * cocok dengan asset yang discan dan masih INCOMPLETE.
     */
    public function scanAllocate(
        Request $request,
        int $id
    ): JsonResponse|RedirectResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        if (
            strtolower(
                $deliveryOrder->status ?: 'draft'
            ) !== 'draft'
        ) {
            throw ValidationException::withMessages([
                'barcode' => 'Scan Allocation hanya dapat digunakan saat Surat Jalan masih DRAFT.',
            ]);
        }

        $validated = $request->validate([
            'barcode' => [
                'required',
                'string',
                'max:100',
            ],
        ]);

        $barcode = trim(
            $validated['barcode']
        );

        $asset = InventoryAsset::query()
            ->with('item')
            ->where(function ($query) use ($barcode) {
                $query
                    ->where('barcode_value', $barcode)
                    ->orWhere('asset_code', $barcode);
            })
            ->first();

        if (! $asset) {
            throw ValidationException::withMessages([
                'barcode' => 'QR / Barcode / Asset Code tidak ditemukan: '.$barcode,
            ]);
        }

        $existingAllocation = DeliveryOrderInventoryAllocation::query()
            ->with([
                'deliveryOrderItem',
                'inventoryAsset',
            ])
            ->where('delivery_order_id', $deliveryOrder->id)
            ->where('inventory_asset_id', $asset->id)
            ->whereIn(
                'status',
                DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
            )
            ->first();

        if ($existingAllocation) {
            $item = $existingAllocation->deliveryOrderItem;
            $snapshot = $this->serializedSnapshot(
                $item
            );

            $message = sprintf(
                '%s sudah masuk ke %s.',
                $asset->asset_code,
                $item?->name ?: 'Surat Jalan ini'
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'duplicate' => true,
                    'message' => $message,
                    'asset' => [
                        'id' => $asset->id,
                        'code' => $asset->asset_code,
                    ],
                    'item' => $snapshot,
                ]);
            }

            return redirect()
                ->route(
                    'admin.delivery-orders.inventory-allocation.edit',
                    $deliveryOrder->id
                )
                ->with('success', $message);
        }

        $matchingItems = $deliveryOrder->items
            ->filter(function ($item) use ($asset) {
                return $item->inventoryItem
                    && $item->inventoryItem->isSerialized()
                    && (int) $item->inventory_item_id
                        === (int) $asset->inventory_item_id;
            })
            ->values();

        if ($matchingItems->isEmpty()) {
            throw ValidationException::withMessages([
                'barcode' => sprintf(
                    'Asset %s (%s) tidak termasuk request barang Surat Jalan ini.',
                    $asset->asset_code,
                    $asset->item?->name ?: 'Inventory Item tidak diketahui'
                ),
            ]);
        }

        $targetItem = $matchingItems
            ->first(function ($item) {
                $need = (float) $item->quantity;

                if (floor($need) !== $need) {
                    return false;
                }

                $allocated = $item->allocations
                    ->whereIn(
                        'status',
                        DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                    )
                    ->count();

                return $allocated < (int) $need;
            });

        if (! $targetItem) {
            throw ValidationException::withMessages([
                'barcode' => sprintf(
                    'Request %s sudah lengkap. Asset %s tidak diperlukan.',
                    $matchingItems->first()?->name
                        ?: $asset->item?->name
                        ?: 'Inventory Item ini',
                    $asset->asset_code
                ),
            ]);
        }

        $this->allocationService->allocateSerializedAsset(
            $deliveryOrder,
            $targetItem,
            $asset,
            auth()->guard('user')->id()
        );

        /*
         * Reload item relationships for a fresh AJAX snapshot.
         */
        $targetItem->load([
            'inventoryItem',
            'allocations.inventoryAsset',
        ]);

        $snapshot = $this->serializedSnapshot(
            $targetItem
        );

        $message = sprintf(
            '%s -> %s (%d/%d%s)',
            $asset->asset_code,
            $targetItem->name,
            $snapshot['allocated'],
            (int) $snapshot['need'],
            $snapshot['complete']
                ? ' COMPLETE'
                : ''
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'duplicate' => false,
                'message' => $message,
                'asset' => [
                    'id' => $asset->id,
                    'code' => $asset->asset_code,
                ],
                'item' => $snapshot,
            ]);
        }

        return redirect()
            ->route(
                'admin.delivery-orders.inventory-allocation.edit',
                $deliveryOrder->id
            )
            ->with('success', 'SCAN ALLOCATED: '.$message);
    }

    private function serializedSnapshot(
        $item
    ): array {
        $activeAllocations = $item->allocations
            ->whereIn(
                'status',
                DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
            )
            ->where('tracking_type', 'serialized');

        $allocated = $activeAllocations->count();
        $need = (float) $item->quantity;

        $freeAvailable = InventoryAsset::query()
            ->where(
                'inventory_item_id',
                $item->inventory_item_id
            )
            ->where('status', 'available')
            ->count();

        return [
            'id' => $item->id,
            'name' => $item->name,
            'need' => $need,
            'allocated' => $allocated,
            'free_available' => $freeAvailable,
            'shortage' => max(
                $need - ($allocated + $freeAvailable),
                0
            ),
            'complete' => floor($need) === $need
                && $allocated >= (int) $need,
            'assets' => $activeAllocations
                ->map(
                    fn ($allocation) => [
                        'id' => $allocation->inventoryAsset?->id,
                        'code' => $allocation->inventoryAsset?->asset_code,
                    ]
                )
                ->filter(
                    fn ($asset) => ! empty($asset['id'])
                )
                ->values()
                ->all(),
        ];
    }

    public function update(
        Request $request,
        int $id,
        int $itemId
    ): RedirectResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $deliveryOrderItem = $deliveryOrder->items
            ->firstWhere('id', $itemId);

        if (! $deliveryOrderItem) {
            abort(404);
        }

        $inventoryItem = $deliveryOrderItem->inventoryItem;

        if (! $inventoryItem) {
            throw ValidationException::withMessages([
                'inventory_item_id' => 'Item Surat Jalan belum terhubung ke Inventory Item.',
            ]);
        }

        if ($inventoryItem->isSerialized()) {
            $validated = $request->validate([
                'asset_ids'   => ['nullable', 'array'],
                'asset_ids.*' => [
                    'integer',
                    'distinct',
                    'exists:inventory_assets,id',
                ],
            ]);

            $this->allocationService->syncSerialized(
                $deliveryOrder,
                $deliveryOrderItem,
                $validated['asset_ids'] ?? [],
                auth()->guard('user')->id()
            );
        } else {
            $validated = $request->validate([
                'quantity' => [
                    'required',
                    'numeric',
                    'min:0',
                ],
            ]);

            $this->allocationService->syncQuantity(
                $deliveryOrder,
                $deliveryOrderItem,
                (float) $validated['quantity'],
                auth()->guard('user')->id()
            );
        }

        session()->flash(
            'success',
            "Allocation {$deliveryOrderItem->name} berhasil diperbarui."
        );

        return redirect()->route(
            'admin.delivery-orders.inventory-allocation.edit',
            $deliveryOrder->id
        );
    }

    public function release(
        int $id,
        int $itemId
    ): RedirectResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $deliveryOrderItem = $deliveryOrder->items
            ->firstWhere('id', $itemId);

        if (! $deliveryOrderItem) {
            abort(404);
        }

        $this->allocationService->releaseItem(
            $deliveryOrder,
            $deliveryOrderItem,
            auth()->guard('user')->id()
        );

        session()->flash(
            'success',
            "Allocation {$deliveryOrderItem->name} berhasil dilepas."
        );

        return redirect()->route(
            'admin.delivery-orders.inventory-allocation.edit',
            $deliveryOrder->id
        );
    }

    private function findDeliveryOrder(
        int $id
    ): DeliveryOrder {
        return DeliveryOrder::with([
            'items.inventoryItem',
            'items.allocations.inventoryAsset',
        ])->findOrFail($id);
    }
}
