<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Invoice\Models\DeliveryOrderItem;
use Webkul\Invoice\Services\DeliveryOrderInventoryAllocationService;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;
use Webkul\Warehouse\Models\InventoryAsset;

uses(TestCase::class);

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('s', 32))]);
    $this->previousConnection = DB::getDefaultConnection();
    config(['database.connections.allocation_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]]);
    DB::setDefaultConnection('allocation_test');
    Schema::clearResolvedInstance('db.schema');
    (require base_path('packages/Webkul/Core/src/Database/Migrations/2021_03_12_060658_create_core_config_table.php'))->up();
    foreach (['users', 'warehouses', 'warehouse_locations', 'attributes'] as $name) {
        Schema::create($name, fn (Blueprint $table) => $table->increments('id'));
    }
    DB::table('users')->insert(['id' => 7]);
    DB::table('warehouses')->insert(['id' => 1]);
    Schema::create('delivery_orders', function (Blueprint $table) {
        $table->increments('id');
        $table->string('delivery_order_number');
        $table->string('status')->default('draft');
        $table->timestamps();
    });
    Schema::create('inventory_items', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
        $table->string('name');
        $table->string('tracking_type');
        $table->unsignedInteger('warehouse_id')->default(1);
        $table->decimal('quantity_on_hand', 12, 2)->default(0);
    });
    Schema::create('inventory_assets', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('inventory_item_id');
        $table->unsignedInteger('warehouse_id')->default(1);
        $table->string('asset_code')->unique();
        $table->string('barcode_value')->nullable();
        $table->string('serial_number')->nullable();
        $table->string('status')->default('available');
        $table->timestamps();
    });
    Schema::create('delivery_order_items', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('delivery_order_id');
        $table->unsignedInteger('inventory_item_id');
        $table->string('name');
        $table->decimal('quantity', 12, 2);
        $table->boolean('requires_inventory')->default(true);
        $table->integer('sort_order')->default(0);
        $table->timestamps();
    });
    foreach (['2026_08_28_120520_create_inventory_stock_movements_table.php', '2026_08_28_145000_create_delivery_order_inventory_allocations_table.php'] as $migration) {
        (require base_path('database/migrations/'.$migration))->up();
    }
    $this->order = DeliveryOrder::create(['delivery_order_number' => 'SJ-SCAN-1', 'status' => 'draft']);
    DB::table('inventory_items')->insert(['id' => 1, 'code' => 'CAM', 'name' => 'Camera', 'tracking_type' => 'serialized']);
    $this->item = DeliveryOrderItem::create(['delivery_order_id' => $this->order->id, 'inventory_item_id' => 1, 'name' => 'Camera', 'quantity' => 4]);
    foreach (range(1, 5) as $id) {
        InventoryAsset::create(['inventory_item_id' => 1, 'asset_code' => 'CAM-'.$id, 'barcode_value' => 'QR-'.$id, 'serial_number' => 'SERIAL-'.$id]);
    }
    $this->withoutMiddleware(CanInstall::class);
    $this->role = (new Role)->forceFill(['permission_type' => 'custom', 'permissions' => ['delivery-orders.inventory-allocation']]);
    $this->actingAs((new User)->forceFill(['id' => 7, 'status' => 1])->setRelation('role', $this->role), 'user');
    $this->scanUrl = route('admin.delivery-orders.inventory-allocation.scan', $this->order->id);
});

afterEach(function (): void {
    DB::purge('allocation_test');
    DB::setDefaultConnection($this->previousConnection);
    Schema::clearResolvedInstance('db.schema');
});

it('accepts four consecutive scans on the same order and retries without double counting', function (): void {
    foreach (range(1, 4) as $id) {
        $this->putJson($this->scanUrl, ['barcode' => 'QR-'.$id])->assertOk()
            ->assertJsonPath('item.allocated', $id)->assertJsonPath('item.complete', $id === 4);
    }
    $this->putJson($this->scanUrl, ['barcode' => 'CAM-4'])->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('item.allocated', 4);
    $this->putJson($this->scanUrl, ['barcode' => 'CAM-5'])->assertUnprocessable();
    expect(DeliveryOrderInventoryAllocation::count())->toBe(4)
        ->and(DB::table('inventory_stock_movements')->count())->toBe(4)
        ->and(InventoryAsset::where('status', 'allocated')->count())->toBe(4)
        ->and(InventoryAsset::where('asset_code', 'CAM-5')->value('status'))->toBe('available');
});

it('accepts manually typed identifiers with the same allocation and duplicate checks', function (string $identifier): void {
    $this->put($this->scanUrl, ['barcode' => '  '.$identifier.'  '])
        ->assertRedirect(route('admin.delivery-orders.inventory-allocation.edit', $this->order->id));
    $this->putJson($this->scanUrl, ['barcode' => 'QR-1'])->assertOk()->assertJsonPath('duplicate', true);
    expect(DeliveryOrderInventoryAllocation::count())->toBe(1)
        ->and(DB::table('inventory_stock_movements')->value('performed_by'))->toBe(7);
})->with(['CAM-1', 'QR-1', 'SERIAL-1']);

it('rejects ambiguous serial numbers and invalid identifiers without inventing stock', function (): void {
    InventoryAsset::where('asset_code', 'CAM-2')->update(['serial_number' => 'SERIAL-1']);
    foreach (['SERIAL-1', 'DOES-NOT-EXIST', '', str_repeat('A', 256)] as $identifier) {
        $this->putJson($this->scanUrl, ['barcode' => $identifier])->assertUnprocessable()->assertJsonValidationErrors('barcode');
    }
    expect(DeliveryOrderInventoryAllocation::count())->toBe(0)->and(DB::table('inventory_stock_movements')->count())->toBe(0);
});

it('protects assets reserved on another order and non-available stock during manual entry', function (): void {
    $this->putJson($this->scanUrl, ['barcode' => 'CAM-1'])->assertOk();
    $other = DeliveryOrder::create(['delivery_order_number' => 'SJ-SCAN-2', 'status' => 'draft']);
    DeliveryOrderItem::create(['delivery_order_id' => $other->id, 'inventory_item_id' => 1, 'name' => 'Camera', 'quantity' => 4]);
    $this->putJson(route('admin.delivery-orders.inventory-allocation.scan', $other->id), ['barcode' => 'CAM-1'])->assertUnprocessable();
    InventoryAsset::where('asset_code', 'CAM-2')->update(['status' => 'maintenance']);
    $this->putJson($this->scanUrl, ['barcode' => 'CAM-2'])->assertUnprocessable();
    expect(DeliveryOrderInventoryAllocation::count())->toBe(1)->and(DB::table('inventory_stock_movements')->count())->toBe(1);
});

it('uses current requirements rather than stale allocations cached on the model', function (): void {
    $this->item->update(['quantity' => 1]);
    $next = DeliveryOrderItem::create(['delivery_order_id' => $this->order->id, 'inventory_item_id' => 1, 'name' => 'Camera extra', 'quantity' => 3, 'sort_order' => 1]);
    $stale = $this->order->load('items.allocations');
    $service = new DeliveryOrderInventoryAllocationService;
    foreach (range(1, 4) as $id) {
        $result = $service->allocateByIdentifier($stale, 'CAM-'.$id, 7);
        expect($result['allocation']->delivery_order_item_id)->toBe($id === 1 ? $this->item->id : $next->id);
    }
    expect($this->item->activeAllocations()->count())->toBe(1)->and($next->activeAllocations()->count())->toBe(3);
});

it('rejects scan and manual entry after the order leaves draft', function (string $status): void {
    $this->order->update(['status' => $status]);
    $this->putJson($this->scanUrl, ['barcode' => 'CAM-1'])->assertUnprocessable();
    expect(DeliveryOrderInventoryAllocation::count())->toBe(0);
})->with(['issued', 'delivered', 'returned', 'cancelled']);

it('enforces allocation permission for the shared scanner and manual endpoint', function (): void {
    $this->role->permissions = ['delivery-orders'];
    $this->putJson($this->scanUrl, ['barcode' => 'CAM-1'])->assertUnauthorized();
    expect(DeliveryOrderInventoryAllocation::count())->toBe(0);
});

it('rolls back asset and allocation changes if recording the stock movement fails', function (): void {
    DB::unprepared("CREATE TRIGGER fail_scan_movement BEFORE INSERT ON inventory_stock_movements BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
    expect(fn () => (new DeliveryOrderInventoryAllocationService)->allocateByIdentifier($this->order, 'CAM-1', 7))->toThrow(QueryException::class);
    expect(DeliveryOrderInventoryAllocation::count())->toBe(0)
        ->and(InventoryAsset::where('asset_code', 'CAM-1')->value('status'))->toBe('available');
});
