<?php

namespace Webkul\Warehouse\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Warehouse\Models\InventoryConsumableUsage;
use Webkul\Warehouse\Models\InventoryItem;
use Webkul\Warehouse\Models\InventoryStockMovement;

class InventoryConsumableUsageService
{
    public const EDITABLE_STATUSES = ['issued', 'delivered', 'returned', 'cancelled'];

    public function revision(Collection $usages): string
    {
        return hash('sha256', $usages->sortBy('inventory_item_id')->map(fn ($usage) => [
            (int) $usage->inventory_item_id,
            (string) $usage->quantity,
            $usage->notes,
        ])->values()->toJson());
    }

    /**
     * The submitted quantities are event totals, never additional stock withdrawals.
     * Lock the order and items so retries and simultaneous events cannot overdraw stock.
     */
    public function save(int $deliveryOrderId, array $data, ?int $performedBy = null): void
    {
        $data = Validator::make($data, [
            'usage_revision' => ['required', 'string', 'size:64'],
            'items' => ['present', 'array', 'max:200'],
            'items.*.inventory_item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        DB::transaction(function () use ($deliveryOrderId, $data, $performedBy) {
            $order = DeliveryOrder::query()->lockForUpdate()->findOrFail($deliveryOrderId);

            if (! in_array($order->status, self::EDITABLE_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'items' => 'Rilis Surat Jalan sebelum mencatat penggunaan consumable.',
                ]);
            }

            $usages = InventoryConsumableUsage::query()
                ->where('delivery_order_id', $order->id)
                ->orderBy('inventory_item_id')->lockForUpdate()->get();

            if (! hash_equals($this->revision($usages), $data['usage_revision'])) {
                throw ValidationException::withMessages([
                    'usage_revision' => 'Penggunaan consumable sudah berubah. Muat ulang halaman sebelum menyimpan kembali.',
                ]);
            }

            $previous = $usages->keyBy('inventory_item_id');
            $requested = collect($data['items'])->keyBy('inventory_item_id');
            $ids = $previous->keys()->merge($requested->keys())->unique()->sort()->values();
            $items = InventoryItem::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($ids as $id) {
                $item = $items->get($id);
                $usage = $previous->get($id);
                $row = $requested->get($id);
                $beforeUsage = $this->hundredths($usage?->quantity ?? 0);
                $quantity = $this->hundredths($row['quantity'] ?? 0);
                $difference = $quantity - $beforeUsage;
                $notes = $row ? (trim($row['notes'] ?? '') ?: null) : $usage?->notes;

                if (! $item || ! $item->isQuantityTracked()) {
                    throw ValidationException::withMessages(['items' => 'Pilih item dari daftar consumable (quantity).']);
                }

                if ($difference > 0 && (! $item->is_active || $order->status === 'cancelled')) {
                    throw ValidationException::withMessages([
                        'items' => 'Pemakaian tidak dapat ditambah untuk consumable nonaktif atau Surat Jalan yang dibatalkan.',
                    ]);
                }

                // Allocated consumables already leave physical stock in the existing SJ/return flow.
                if ($difference > 0 && DeliveryOrderInventoryAllocation::query()
                    ->where('delivery_order_id', $order->id)
                    ->where('inventory_item_id', $id)
                    ->where('tracking_type', 'quantity')
                    ->whereIn('status', [...DeliveryOrderInventoryAllocation::ACTIVE_STATUSES, 'returned'])
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'items' => "{$item->name} sudah dicatat melalui alokasi Surat Jalan. Gunakan proses Return untuk mencatat sisa kembali; stok tidak boleh dipotong dua kali.",
                    ]);
                }

                $beforeStock = $this->hundredths($item->quantity_on_hand);
                $afterStock = $beforeStock - $difference;
                $reserved = $this->hundredths(DeliveryOrderInventoryAllocation::query()
                    ->where('inventory_item_id', $id)
                    ->where('tracking_type', 'quantity')
                    ->whereIn('status', DeliveryOrderInventoryAllocation::RESERVATION_STATUSES)
                    ->sum('quantity'));

                if ($difference > 0 && ($afterStock < 0 || $afterStock < $reserved)) {
                    throw ValidationException::withMessages([
                        'items' => sprintf('Stok %s tidak cukup. Tersedia %s %s setelah reservasi Surat Jalan.',
                            $item->name, max(0, $beforeStock - $reserved) / 100, $item->unit),
                    ]);
                }

                if ($afterStock > 999999999999999) {
                    throw ValidationException::withMessages(['items' => "Stok {$item->name} melebihi batas penyimpanan."]);
                }

                if ($difference !== 0) {
                    $item->update(['quantity_on_hand' => $afterStock / 100]);

                    InventoryStockMovement::create([
                        'inventory_item_id' => $id,
                        'inventory_asset_id' => null,
                        'warehouse_id' => $item->warehouse_id,
                        'warehouse_location_id' => $item->warehouse_location_id,
                        'movement_type' => $difference > 0 ? 'stock_out' : 'stock_in',
                        'quantity' => abs($difference) / 100,
                        'reference_type' => 'consumable_usage',
                        'reference_id' => $order->id,
                        'reference_number' => $order->delivery_order_number,
                        'performed_by' => $performedBy,
                        'notes' => sprintf('Penggunaan consumable %s: %s -> %s %s. Stok %s -> %s %s.%s',
                            $order->delivery_order_number, $beforeUsage / 100, $quantity / 100, $item->unit,
                            $beforeStock / 100, $afterStock / 100, $item->unit, $notes ? ' '.$notes : ''),
                        'occurred_at' => now(),
                    ]);
                }

                if (! $usage && $quantity === 0) {
                    continue;
                }

                $usage ??= new InventoryConsumableUsage([
                    'delivery_order_id' => $order->id,
                    'inventory_item_id' => $id,
                ]);
                $usage->fill(['quantity' => $quantity / 100, 'notes' => $notes]);

                if ($usage->isDirty()) {
                    $usage->updated_by = $performedBy;
                    $usage->save();
                }
            }
        }, 3);
    }

    private function hundredths(mixed $quantity): int
    {
        return (int) round((float) $quantity * 100);
    }
}
