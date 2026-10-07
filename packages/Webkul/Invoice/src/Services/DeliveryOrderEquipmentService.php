<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Invoice\Models\DeliveryOrderItem;
use Webkul\Warehouse\Models\InventoryItem;

class DeliveryOrderEquipmentService
{
    /** Only requirements participate: scanning while the form is open is allowed. */
    public static function revision(iterable $items): string
    {
        $rows = collect($items)->sortBy('id')->map(function (DeliveryOrderItem $item) {
            $row = $item->getRawOriginal();
            ksort($row);

            return $row;
        })->values()->all();

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function update(DeliveryOrder $deliveryOrder, array $data): void
    {
        DB::transaction(function () use ($deliveryOrder, $data) {
            // Use the same parent lock as allocation and issue before touching items.
            $lockedOrder = DeliveryOrder::query()->lockForUpdate()->findOrFail($deliveryOrder->id);
            if (strtolower($lockedOrder->status ?: 'draft') !== 'draft') {
                throw ValidationException::withMessages([
                    'items' => 'Equipment hanya dapat diubah sebelum Surat Jalan dirilis. Gunakan Surat Jalan tambahan untuk pengiriman berikutnya.',
                ]);
            }

            if (array_key_exists('items', $data)) {
                $this->syncItems($lockedOrder, $data['items'] ?? [], $data['equipment_revision'] ?? '');
            }

            $lockedOrder->update(Arr::only($data, [
                'recipient_name', 'recipient_phone', 'pic_name', 'pic_phone',
                'event_date', 'event_time', 'location', 'delivery_address',
                'delivery_date', 'delivery_time', 'notes',
            ]));
        });
    }

    private function syncItems(DeliveryOrder $deliveryOrder, array $rows, string $revision): void
    {
        $existing = DeliveryOrderItem::query()
            ->where('delivery_order_id', $deliveryOrder->id)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        if (! hash_equals(self::revision($existing), $revision)) {
            throw ValidationException::withMessages([
                'items' => 'Daftar equipment sudah berubah atau form masih versi lama. Muat ulang halaman Edit lalu ulangi perubahan. Hasil scan tetap tersimpan.',
            ]);
        }

        $allocations = DeliveryOrderInventoryAllocation::query()
            ->where('delivery_order_id', $deliveryOrder->id)
            ->whereIn('status', DeliveryOrderInventoryAllocation::ACTIVE_STATUSES)
            ->orderBy('id')->lockForUpdate()->get()->groupBy('delivery_order_item_id');

        $seen = [];
        $kept = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id && (! $existing->has($id) || isset($seen[$id]))) {
                throw ValidationException::withMessages([
                    'items' => 'Baris equipment tidak valid atau terduplikasi. Muat ulang halaman Edit.',
                ]);
            }
            if ($id) {
                $seen[$id] = true;
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $item = $id ? $existing->get($id) : new DeliveryOrderItem(['delivery_order_id' => $deliveryOrder->id]);
            $inventoryId = ! empty($row['inventory_item_id']) ? (int) $row['inventory_item_id'] : null;
            $inventory = $inventoryId ? InventoryItem::find($inventoryId) : null;
            $quantity = (float) ($row['quantity'] ?? 1);
            if (($inventoryId && ! $inventory) || ! is_finite($quantity) || $quantity < 0.01
                || ($inventory?->isSerialized() && floor($quantity) !== $quantity)) {
                throw ValidationException::withMessages([
                    'items' => "Periksa inventory dan jumlah {$name}. Jumlah alat serialized harus berupa bilangan bulat.",
                ]);
            }

            $active = $allocations->get($id, collect());
            if ($active->isNotEmpty()) {
                $allocated = (float) $active->sum('quantity');
                if ($inventoryId !== $item->inventory_item_id || $quantity + 0.0001 < $allocated) {
                    throw ValidationException::withMessages([
                        'items' => "{$item->name} sudah dialokasikan sebanyak {$allocated}. Untuk mengganti inventory, menghapus, atau mengurangi di bawah jumlah tersebut, reset alokasi item ini terlebih dahulu. Alokasi item lain tetap tersimpan.",
                    ]);
                }
            }

            // Updating in place preserves item IDs, source product/SKU, and scan links.
            $item->fill([
                'inventory_item_id' => $inventoryId,
                'name' => $name,
                'description' => $row['description'] ?? null,
                'quantity' => $quantity,
                'requires_inventory' => $item->requires_inventory || ! empty($row['requires_inventory']) || $inventoryId !== null,
                'unit' => ! empty($row['unit']) ? $row['unit'] : 'unit',
                'notes' => $row['notes'] ?? null,
                'sort_order' => count($kept),
            ])->save();
            $kept[] = $item->id;
        }

        foreach ($existing as $item) {
            if (in_array($item->id, $kept, true)) {
                continue;
            }
            if ($allocations->has($item->id)) {
                throw ValidationException::withMessages([
                    'items' => "{$item->name} masih memiliki alokasi. Reset alokasi item ini sebelum menghapusnya. Penambahan item lain tidak memerlukan reset.",
                ]);
            }
            $item->delete();
        }
    }
}
