<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;
use Webkul\Warehouse\Models\InventoryConsumableUsage;
use Webkul\Warehouse\Models\InventoryItem;
use Webkul\Warehouse\Models\InventoryStockMovement;
use Webkul\Warehouse\Services\InventoryConsumableUsageService;

uses(TestCase::class);

beforeEach(function () {
    $this->previousConnection = DB::getDefaultConnection();
    config(['database.connections.consumable_usage_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ], 'app.key' => str_repeat('a', 32)]);
    DB::setDefaultConnection('consumable_usage_test');
    Schema::clearResolvedInstance('db.schema');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    Event::fake();
    $this->withoutMiddleware(CanInstall::class);

    foreach (['warehouses', 'warehouse_locations', 'inventory_assets', 'users'] as $name) {
        Schema::create($name, function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });
    }
    Schema::create('delivery_orders', function (Blueprint $table) {
        $table->increments('id');
        $table->string('delivery_order_number');
        $table->string('status')->default('issued');
        foreach (['invoice_number', 'quote_number', 'project_code', 'business_unit', 'project_name', 'customer_name', 'sales_person_name'] as $name) {
            $table->string($name)->nullable();
        }
        $table->unsignedInteger('invoice_id')->nullable();
        $table->unsignedInteger('quote_id')->nullable();
        $table->date('event_date')->nullable();
        $table->date('delivery_date')->nullable();
        $table->timestamps();
    });
    Schema::create('delivery_order_inventory_allocations', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('delivery_order_id');
        $table->unsignedInteger('inventory_item_id');
        $table->string('tracking_type')->default('quantity');
        $table->string('status');
        $table->decimal('quantity', 15, 2);
        $table->decimal('returned_quantity', 15, 2)->nullable();
    });
    foreach ([
        'database/migrations/2026_08_28_120500_create_inventory_items_table.php',
        'database/migrations/2026_08_28_120520_create_inventory_stock_movements_table.php',
        'database/migrations/2026_10_26_120000_create_inventory_consumable_usages_table.php',
        'packages/Webkul/Core/src/Database/Migrations/2021_03_12_060658_create_core_config_table.php',
    ] as $migration) {
        (require base_path($migration))->up();
    }

    DB::table('warehouses')->insert(['id' => 1, 'name' => 'Gudang']);
    DB::table('users')->insert(['id' => 7, 'name' => 'Warehouse']);
    DB::table('delivery_orders')->insert([
        ['id' => 1, 'delivery_order_number' => 'SJ-2610-0001', 'status' => 'issued', 'project_code' => 'PRJ-001', 'project_name' => 'Wedding', 'customer_name' => 'Nova', 'invoice_number' => 'INV-001', 'sales_person_name' => 'Sales A', 'event_date' => '2026-10-26'],
        ['id' => 2, 'delivery_order_number' => 'SJ-2610-0002', 'status' => 'issued', 'project_code' => 'PRJ-002', 'project_name' => 'Birthday', 'customer_name' => 'Varbel', 'invoice_number' => 'INV-002', 'sales_person_name' => 'Sales B', 'event_date' => '2026-10-27'],
    ]);
    foreach ([1 => ['RIBBON', 'Ribbon', 'roll', 20], 2 => ['FRAME', 'Frame', 'pcs', 100]] as $id => [$code, $name, $unit, $stock]) {
        InventoryItem::create(['id' => $id, 'code' => $code, 'name' => $name, 'unit' => $unit,
            'tracking_type' => 'quantity', 'quantity_on_hand' => $stock, 'warehouse_id' => 1, 'is_active' => true]);
    }
    $this->actingAs(consumableUsageUser(), 'user');
    $this->service = app(InventoryConsumableUsageService::class);
});

afterEach(function () {
    DB::purge('consumable_usage_test');
    DB::setDefaultConnection($this->previousConnection);
    Schema::clearResolvedInstance('db.schema');
});

function consumableUsageUser(?array $permissions = null): User
{
    return (new User)->forceFill(['id' => 7, 'name' => 'Warehouse', 'status' => 1])
        ->setRelation('role', (new Role)->forceFill(['permission_type' => $permissions === null ? 'all' : 'custom', 'permissions' => $permissions ?? []]));
}

function consumableUsagePayload(array $items, int $orderId = 1): array
{
    return ['usage_revision' => app(InventoryConsumableUsageService::class)->revision(
        InventoryConsumableUsage::where('delivery_order_id', $orderId)->get()
    ), 'items' => array_map(fn ($id, $quantity) => ['inventory_item_id' => $id, 'quantity' => $quantity, 'notes' => 'Event consumable'], array_keys($items), array_values($items))];
}

it('deducts multiple consumables atomically and records the SJ and operator in the ledger', function () {
    $this->put(route('admin.inventory.consumable-usage.update', 1), consumableUsagePayload([1 => 2, 2 => 10]))
        ->assertRedirect(route('admin.inventory.consumable-usage.show', 1))->assertSessionHasNoErrors();

    expect((float) InventoryItem::find(1)->quantity_on_hand)->toBe(18.0)
        ->and((float) InventoryItem::find(2)->quantity_on_hand)->toBe(90.0)
        ->and(InventoryConsumableUsage::count())->toBe(2)
        ->and(InventoryStockMovement::count())->toBe(2);
    $movement = InventoryStockMovement::first();
    expect($movement->movement_type)->toBe('stock_out')->and($movement->reference_type)->toBe('consumable_usage')
        ->and($movement->reference_id)->toBe(1)->and($movement->reference_number)->toBe('SJ-2610-0001')
        ->and($movement->performed_by)->toBe(7)->and($movement->notes)->toContain('Event consumable');
});

it('applies only the difference on corrections and restores removed rows', function () {
    $this->service->save(1, consumableUsagePayload([1 => 2, 2 => 10]), 7);
    $this->service->save(1, consumableUsagePayload([1 => 3, 2 => 4]), 7);
    expect((float) InventoryItem::find(1)->quantity_on_hand)->toBe(17.0)
        ->and((float) InventoryItem::find(2)->quantity_on_hand)->toBe(96.0)
        ->and(InventoryStockMovement::where('movement_type', 'stock_in')->sole()->quantity)->toBe('6.00');
    $this->service->save(1, consumableUsagePayload([1 => 3]), 7);
    expect((float) InventoryItem::find(2)->quantity_on_hand)->toBe(100.0)
        ->and(InventoryConsumableUsage::where('inventory_item_id', 2)->sole()->quantity)->toBe('0.00');
    $this->put(route('admin.inventory.consumable-usage.update', 1), ['usage_revision' => consumableUsagePayload([])['usage_revision']])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect((float) InventoryItem::find(1)->quantity_on_hand)->toBe(20.0);
});

it('does not deduct twice for unchanged totals or replayed and stale forms', function () {
    $stale = consumableUsagePayload([1 => 2]);
    $this->service->save(1, $stale, 7);
    expect(fn () => $this->service->save(1, $stale, 7))->toThrow(ValidationException::class);
    $this->service->save(1, consumableUsagePayload([1 => 2]), 7);
    expect((float) InventoryItem::find(1)->quantity_on_hand)->toBe(18.0)->and(InventoryStockMovement::count())->toBe(1);
    $stale['items'][0]['quantity'] = 5;
    expect(fn () => $this->service->save(1, $stale, 7))->toThrow(ValidationException::class);
    expect((float) InventoryItem::find(1)->quantity_on_hand)->toBe(18.0);
});

it('rolls back all rows if an item has insufficient stock', function () {
    expect(fn () => $this->service->save(1, consumableUsagePayload([1 => 2, 2 => 101]), 7))->toThrow(ValidationException::class);
    expect((float) InventoryItem::find(1)->quantity_on_hand)->toBe(20.0)
        ->and(InventoryConsumableUsage::count())->toBe(0)->and(InventoryStockMovement::count())->toBe(0);
});

it('preserves stock reserved for other surat jalan', function () {
    DB::table('delivery_order_inventory_allocations')->insert(['delivery_order_id' => 2, 'inventory_item_id' => 1, 'status' => 'allocated', 'quantity' => 19]);
    expect(fn () => $this->service->save(1, consumableUsagePayload([1 => 2]), 7))->toThrow(ValidationException::class);
    $this->service->save(1, consumableUsagePayload([1 => 1]), 7);
    expect((float) InventoryItem::find(1)->quantity_on_hand)->toBe(19.0);
});

it('does not deduct a consumable already handled by SJ allocation or return', function ($status) {
    DB::table('delivery_order_inventory_allocations')->insert(['delivery_order_id' => 1, 'inventory_item_id' => 1, 'status' => $status, 'quantity' => 2]);
    expect(fn () => $this->service->save(1, consumableUsagePayload([1 => 2]), 7))->toThrow(ValidationException::class);
    expect((float) InventoryItem::find(1)->quantity_on_hand)->toBe(20.0)->and(InventoryStockMovement::count())->toBe(0);
})->with(['allocated', 'picked', 'out', 'return_pending', 'returned']);

it('allows released reservations and keeps event usage separate', function () {
    DB::table('delivery_order_inventory_allocations')->insert(['delivery_order_id' => 1, 'inventory_item_id' => 1, 'status' => 'released', 'quantity' => 2]);
    $this->service->save(1, consumableUsagePayload([1 => 1.25]), 7);
    $this->service->save(2, consumableUsagePayload([1 => 0.75], 2), 7);
    $this->service->save(1, consumableUsagePayload([1 => 0.25]), 7);
    expect(InventoryConsumableUsage::where('delivery_order_id', 2)->sole()->quantity)->toBe('0.75')
        ->and(InventoryItem::find(1)->quantity_on_hand)->toBe('19.00');
});

it('rejects invalid precision negative quantities duplicate items and serialized inventory', function () {
    foreach ([-1, 0.001, 1.234, 'not-a-number', '1e2'] as $quantity) {
        expect(fn () => $this->service->save(1, consumableUsagePayload([1 => $quantity]), 7))->toThrow(ValidationException::class);
    }
    $data = consumableUsagePayload([1 => 2]);
    $data['items'][] = $data['items'][0];
    expect(fn () => $this->service->save(1, $data, 7))->toThrow(ValidationException::class);
    InventoryItem::find(1)->update(['tracking_type' => 'serialized']);
    expect(fn () => $this->service->save(1, consumableUsagePayload([1 => 2]), 7))->toThrow(ValidationException::class);
    expect(InventoryStockMovement::count())->toBe(0);
});

it('uses fresh order state and permits only reductions after cancellation or deactivation', function () {
    DB::table('delivery_orders')->where('id', 1)->update(['status' => 'draft']);
    expect(fn () => $this->service->save(1, consumableUsagePayload([1 => 2]), 7))->toThrow(ValidationException::class);
    DB::table('delivery_orders')->where('id', 1)->update(['status' => 'issued']);
    $this->service->save(1, consumableUsagePayload([1 => 2]), 7);
    InventoryItem::find(1)->update(['is_active' => false]);
    expect(fn () => $this->service->save(1, consumableUsagePayload([1 => 3]), 7))->toThrow(ValidationException::class);
    $this->service->save(1, consumableUsagePayload([1 => 1]), 7);
    DB::table('delivery_orders')->where('id', 1)->update(['status' => 'cancelled']);
    expect(fn () => $this->service->save(1, consumableUsagePayload([2 => 2]), 7))->toThrow(ValidationException::class);
    $this->service->save(1, consumableUsagePayload([]), 7);
    expect(InventoryItem::find(1)->quantity_on_hand)->toBe('20.00');
});

it('enforces permissions for the list form and save routes', function () {
    $this->actingAs(consumableUsageUser(['inventory.consumables']), 'user');
    $this->getJson(route('admin.inventory.consumable-usage.index'))->assertStatus(401);
    $this->getJson(route('admin.inventory.consumable-usage.show', 1))->assertStatus(401);
    $this->putJson(route('admin.inventory.consumable-usage.update', 1), consumableUsagePayload([1 => 2]))->assertStatus(401);
    $this->actingAs(consumableUsageUser(['inventory.consumable-usage']), 'user');
    $this->getJson(route('admin.inventory.consumable-usage.index'))->assertOk();
    $this->putJson(route('admin.inventory.consumable-usage.update', 1), consumableUsagePayload([1 => 2]))->assertStatus(401);
    $this->actingAs(consumableUsageUser(['inventory.consumable-usage', 'inventory.consumable-usage.edit']), 'user');
    $this->put(route('admin.inventory.consumable-usage.update', 1), consumableUsagePayload([1 => 2]))->assertRedirect()->assertSessionHasNoErrors();
    expect(InventoryItem::find(1)->quantity_on_hand)->toBe('18.00');
});

it('renders the list and event form and displays existing allocated consumption separately', function () {
    $this->actingAs(consumableUsageUser(['inventory.consumable-usage', 'inventory.consumable-usage.edit']), 'user');
    $this->service->save(1, consumableUsagePayload([1 => 2]), 7);
    DB::table('delivery_order_inventory_allocations')->insert(['delivery_order_id' => 1, 'inventory_item_id' => 2, 'status' => 'returned', 'quantity' => 10, 'returned_quantity' => 4]);
    $this->get(route('admin.inventory.consumable-usage.index'))->assertOk()->assertSee('Penggunaan Consumable');
    $this->get(route('admin.inventory.consumable-usage.show', 1))->assertOk()
        ->assertSee('SJ-2610-0001')->assertSee('INV-001')->assertSee('Wedding')->assertSee('Nova')
        ->assertSee('usage_revision')->assertSee('Simpan Penggunaan')->assertSee('Consumable dari alokasi surat jalan');
    $this->getJson(route('admin.inventory.consumable-usage.index'))->assertOk()
        ->assertJsonPath('columns.0.label', 'Surat Jalan Number')->assertJsonPath('columns.6.label', 'Event Date');
});

it('enforces one current usage per consumable and surat jalan at the database level', function () {
    $this->service->save(1, consumableUsagePayload([1 => 2]), 7);
    expect(fn () => DB::table('inventory_consumable_usages')->insert(['delivery_order_id' => 1, 'inventory_item_id' => 1, 'quantity' => 1]))
        ->toThrow(QueryException::class);
});
