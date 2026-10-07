<?php

namespace Tests\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Invoice\Models\DeliveryOrderItem;
use Webkul\Invoice\Services\DeliveryOrderEquipmentService;
use Webkul\Invoice\Services\DeliveryOrderInventoryAllocationService;
use Webkul\Invoice\Services\DeliveryOrderWarehouseReleaseService;
use Webkul\Warehouse\Models\InventoryAsset;

class DeliveryOrderEquipmentScenarios
{
    public static function run(string $scenario): void
    {
        CrmCorrectionScenarios::run(fn () => self::$scenario());
    }

    public static function editAfterScan(): void
    {
        [$order, $camera, $ribbon] = self::seed();
        $data = self::form($order); // The form is opened before the warehouse scans.
        self::scan($order, $camera, $ribbon);
        $before = self::snapshot(['delivery_order_inventory_allocations', 'inventory_assets', 'inventory_stock_movements', 'inventory_items']);
        $data['items'][0]['quantity'] = 3;
        $data['items'][0]['name'] = 'Camera tambahan';
        $data['items'][0]['notes'] = 'Tambah satu unit';
        $data['items'][] = ['name' => 'Lighting', 'inventory_item_id' => 3, 'quantity' => 1, 'unit' => 'unit'];
        $data['recipient_name'] = 'Penerima baru';
        $data['notes'] = 'Catatan baru';
        (new DeliveryOrderEquipmentService)->update($order, $data);

        self::check($before === self::snapshot(['delivery_order_inventory_allocations', 'inventory_assets', 'inventory_stock_movements', 'inventory_items']), 'Saving requirements must retain every scan, movement and physical stock value.');
        self::check($camera->fresh()->sku === 'SOURCE-SKU' && $camera->fresh()->product_id === 36, 'Source product and SKU must survive edits.');
        self::check($order->fresh()->recipient_name === 'Penerima baru', 'Header edits must work with active allocations.');
        self::check((float) $camera->fresh()->quantity === 3.0 && $camera->fresh()->notes === 'Tambah satu unit', 'Existing allocated rows update in place.');
        $allocation = new DeliveryOrderInventoryAllocationService;
        $order->unsetRelation('items');
        self::check(! $allocation->isComplete($order), 'Added requirements must make a fully scanned delivery incomplete again.');
        self::rejects(fn () => (new DeliveryOrderWarehouseReleaseService)->releaseOnIssue($order, 7));

        // A stale model must use the newly increased requirement under the lock.
        $allocation->allocateSerializedAsset($order, $camera, InventoryAsset::findOrFail(3), 7);
        $light = DeliveryOrderItem::where('delivery_order_id', $order->id)->where('inventory_item_id', 3)->firstOrFail();
        $allocation->allocateSerializedAsset($order, $light, InventoryAsset::findOrFail(4), 7);
        $order->unsetRelation('items');
        self::check($allocation->isComplete($order), 'Only newly required assets need to be scanned.');
        (new DeliveryOrderWarehouseReleaseService)->releaseOnIssue($order, 7);
        self::check(InventoryAsset::where('status', 'out')->count() === 4, 'Original and added assets release together.');
        self::check((float) DB::table('inventory_items')->where('id', 2)->value('quantity_on_hand') === 18.0, 'Ribbon is deducted once at release, never when editing.');
    }

    public static function allocatedItemGuards(): void
    {
        [$order, $camera, $ribbon] = self::seed();
        self::scan($order, $camera, $ribbon);
        $foreign = DeliveryOrderItem::create(['delivery_order_id' => 999, 'name' => 'Other order', 'quantity' => 1]);
        $base = self::form($order);
        $cases = [];
        $data = $base;
        $data['items'][0]['quantity'] = 1;
        $cases[] = $data;
        $data = $base;
        $data['items'][1]['quantity'] = 1;
        $cases[] = $data;
        $data = $base;
        $data['items'][0]['inventory_item_id'] = 3;
        $cases[] = $data;
        $data = $base;
        unset($data['items'][0]);
        $cases[] = $data;
        $data = $base;
        $data['items'][0]['name'] = '';
        $cases[] = $data;
        $data = $base;
        $data['items'][] = $data['items'][0];
        $cases[] = $data;
        $data = $base;
        $data['items'][0]['id'] = $foreign->id;
        $cases[] = $data;
        $data = $base;
        $data['items'][] = ['name' => 'Fractional camera', 'inventory_item_id' => 1, 'quantity' => 1.5];
        $cases[] = $data;
        $data = $base;
        $data['equipment_revision'] = '';
        $cases[] = $data;
        $before = self::snapshot();
        foreach ($cases as $data) {
            $data['notes'] = 'Must roll back';
            self::rejects(fn () => (new DeliveryOrderEquipmentService)->update($order, $data));
            self::check($before === self::snapshot(), 'A rejected edit must leave all requirements, scans, metadata and stock unchanged.');
        }
    }

    public static function unallocatedAndManualItems(): void
    {
        [$order, $camera, $ribbon] = self::seed();
        $data = self::form($order);
        $data['items'][0]['inventory_item_id'] = 3;
        $data['items'][0]['quantity'] = 1;
        unset($data['items'][1]);
        $data['items'][] = ['name' => 'Laptop manual', 'quantity' => 1, 'requires_inventory' => true];
        (new DeliveryOrderEquipmentService)->update($order, $data);
        self::check($camera->fresh()->inventory_item_id === 3 && ! $ribbon->fresh(), 'Unallocated requirements may be remapped or removed.');
        $manual = DeliveryOrderItem::where('name', 'Laptop manual')->firstOrFail();
        $data = self::form($order);
        $data['items'][1]['requires_inventory'] = false;
        (new DeliveryOrderEquipmentService)->update($order, $data);
        self::check($manual->fresh()->requires_inventory, 'Editing may not silently remove a required manual selection.');
        $allocation = new DeliveryOrderInventoryAllocationService;
        $order->unsetRelation('items');
        self::check(! $allocation->isComplete($order), 'A manual laptop still requires an actual inventory selection.');
        $allocation->allocateSerializedAsset($order, $camera, InventoryAsset::findOrFail(4), 7);
        $allocation->releaseItem($order, $camera->fresh(), 7);
        $data = self::form($order);
        unset($data['items'][0]);
        (new DeliveryOrderEquipmentService)->update($order, $data);
        self::check(! $camera->fresh() && InventoryAsset::findOrFail(4)->status === 'available', 'After per-item reset, the row can be removed without stranding an asset.');
        self::check(DeliveryOrderInventoryAllocation::where('status', 'released')->count() === 1, 'Reset movement history remains recorded.');
    }

    public static function staleFormsAndOrderState(): void
    {
        [$order, $camera, $ribbon] = self::seed();
        $stale = self::form($order);
        DeliveryOrderItem::create(['delivery_order_id' => $order->id, 'name' => 'Added by another staff member', 'quantity' => 1]);
        $before = self::snapshot();
        self::rejects(fn () => (new DeliveryOrderEquipmentService)->update($order, $stale));
        self::check($before === self::snapshot(), 'An older tab cannot delete a colleague\'s new row.');

        $data = self::form($order);
        $allocation = new DeliveryOrderInventoryAllocationService;
        foreach (['issued', 'delivered', 'returned', 'cancelled'] as $status) {
            DB::table('delivery_orders')->where('id', $order->id)->update(['status' => $status]);
            $before = self::snapshot();
            self::rejects(fn () => (new DeliveryOrderEquipmentService)->update($order, $data));
            self::rejects(fn () => $allocation->allocateSerializedAsset($order, $camera, InventoryAsset::findOrFail(1), 7));
            self::rejects(fn () => $allocation->syncSerialized($order, $camera, [1], 7));
            self::rejects(fn () => $allocation->syncQuantity($order, $ribbon, 1, 7));
            self::check($before === self::snapshot(), 'Fresh order state must guard edits and scans even when their input model is stale.');
        }
    }

    public static function staleAllocationRequirements(): void
    {
        [$order, $camera, $ribbon] = self::seed();
        $data = self::form($order);
        $data['items'][0]['quantity'] = 1;
        $data['items'][1]['quantity'] = 1;
        (new DeliveryOrderEquipmentService)->update($order, $data);
        $allocation = new DeliveryOrderInventoryAllocationService;
        $allocation->allocateSerializedAsset($order, $camera, InventoryAsset::findOrFail(1), 7);
        self::rejects(fn () => $allocation->allocateSerializedAsset($order, $camera, InventoryAsset::findOrFail(2), 7));
        self::rejects(fn () => $allocation->syncSerialized($order, $camera, [1, 2], 7));
        self::rejects(fn () => $allocation->syncQuantity($order, $ribbon, 2, 7));
        self::check(InventoryAsset::findOrFail(2)->status === 'available', 'A stale quantity cannot over-allocate after an edit.');
        $allocation->releaseItem($order, $camera, 7);
        $data = self::form($order);
        $data['items'][0]['inventory_item_id'] = 3;
        (new DeliveryOrderEquipmentService)->update($order, $data);
        self::rejects(fn () => $allocation->allocateSerializedAsset($order, $camera, InventoryAsset::findOrFail(2), 7));
        $data = self::form($order);
        unset($data['items'][0]);
        (new DeliveryOrderEquipmentService)->update($order, $data);
        self::rejects(fn () => $allocation->allocateSerializedAsset($order, $camera, InventoryAsset::findOrFail(4), 7));
    }

    public static function editRollback(): void
    {
        [$order] = self::seed();
        $data = self::form($order);
        $data['items'][0]['name'] = 'Must roll back';
        $data['items'][] = ['name' => 'Broken', 'quantity' => 1];
        DB::unprepared("CREATE TRIGGER fail_new_equipment BEFORE INSERT ON delivery_order_items WHEN NEW.name = 'Broken' BEGIN SELECT RAISE(ABORT, 'simulated write failure'); END");
        $before = self::snapshot();
        try {
            (new DeliveryOrderEquipmentService)->update($order, $data);
            throw new RuntimeException('Expected database failure.');
        } catch (QueryException) {
            self::check($before === self::snapshot(), 'A late insert failure must roll back earlier row edits.');
        }
    }

    private static function seed(): array
    {
        $order = new class extends DeliveryOrder
        {
            public function items()
            {
                return $this->hasMany(DeliveryOrderItem::class, 'delivery_order_id')->orderBy('sort_order');
            }
        };
        $order->setTable('delivery_orders')->fill(['delivery_order_number' => 'SJ-EDIT-TEST', 'status' => 'draft', 'notes' => 'Original'])->save();
        foreach ([1 => ['Camera', 'serialized', 'unit'], 2 => ['Ribbon', 'quantity', 'roll'], 3 => ['Lighting', 'serialized', 'unit']] as $id => [$name, $tracking, $unit]) {
            DB::table('inventory_items')->insert(['id' => $id, 'code' => strtoupper($name), 'name' => $name, 'tracking_type' => $tracking, 'unit' => $unit, 'is_active' => true, 'warehouse_id' => 1, 'quantity_on_hand' => 20]);
        }
        $camera = DeliveryOrderItem::create(['delivery_order_id' => $order->id, 'product_id' => 36, 'sku' => 'SOURCE-SKU', 'inventory_item_id' => 1, 'name' => 'Camera', 'quantity' => 2, 'unit' => 'unit', 'sort_order' => 0]);
        $ribbon = DeliveryOrderItem::create(['delivery_order_id' => $order->id, 'inventory_item_id' => 2, 'name' => 'Ribbon', 'quantity' => 2, 'unit' => 'roll', 'sort_order' => 1]);
        foreach ([1 => 1, 2 => 1, 3 => 1, 4 => 3] as $assetId => $inventoryId) {
            DB::table('inventory_assets')->insert(['id' => $assetId, 'inventory_item_id' => $inventoryId, 'asset_code' => 'ASSET-'.$assetId, 'status' => 'available']);
        }

        return [$order, $camera, $ribbon];
    }

    private static function form(DeliveryOrder $order): array
    {
        $items = DeliveryOrderItem::where('delivery_order_id', $order->id)->orderBy('id')->get();

        return ['equipment_revision' => DeliveryOrderEquipmentService::revision($items), 'items' => $items->toArray()];
    }

    private static function scan(DeliveryOrder $order, DeliveryOrderItem $camera, DeliveryOrderItem $ribbon): void
    {
        $allocation = new DeliveryOrderInventoryAllocationService;
        foreach ([1, 2] as $id) {
            $allocation->allocateSerializedAsset($order, $camera, InventoryAsset::findOrFail($id), 7);
        }
        $allocation->syncQuantity($order, $ribbon, 2, 7);
    }

    private static function snapshot(array $tables = ['delivery_orders', 'delivery_order_items', 'delivery_order_inventory_allocations', 'inventory_assets', 'inventory_items', 'inventory_stock_movements']): string
    {
        return json_encode(array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->all(), $tables), JSON_THROW_ON_ERROR);
    }

    private static function rejects(callable $operation): void
    {
        try {
            $operation();
        } catch (ValidationException) {
            return;
        }
        throw new RuntimeException('Expected a validation error.');
    }

    private static function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
