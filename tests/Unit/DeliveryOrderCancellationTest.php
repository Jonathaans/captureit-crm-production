<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Webkul\Admin\Http\Controllers\DeliveryOrder\DeliveryOrderController;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Services\DeliveryOrderInventoryAllocationService;
use Webkul\User\Models\User;

uses(TestCase::class);

beforeEach(function (): void {
    $this->previousConnection = DB::getDefaultConnection();

    config([
        'database.connections.delivery_order_cancel_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    DB::setDefaultConnection('delivery_order_cancel_test');
    Schema::clearResolvedInstance('db.schema');

    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Only an in-memory test database may be used.');
    }

    // Exercise the controller without unrelated audit and notification listeners.
    Event::fake();

    Schema::create('delivery_orders', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('status');
        $table->timestamps();
    });

    Schema::create('delivery_order_inventory_allocations', function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('delivery_order_id');
        $table->string('status');
    });

    $user = new User;
    $user->setRawAttributes(['id' => 7]);
    $this->actingAs($user, 'user');

    $this->allocationService = Mockery::mock(DeliveryOrderInventoryAllocationService::class);
    $this->app->instance(DeliveryOrderInventoryAllocationService::class, $this->allocationService);
});

afterEach(function (): void {
    DB::purge('delivery_order_cancel_test');
    DB::setDefaultConnection($this->previousConnection);
    Schema::clearResolvedInstance('db.schema');
});

it('cancels eligible orders and releases their reservations', function (string $status): void {
    DB::table('delivery_orders')->insert([
        ['id' => 1, 'status' => $status],
        ['id' => 2, 'status' => 'issued'],
    ]);
    DB::table('delivery_order_inventory_allocations')->insert([
        ['delivery_order_id' => 1, 'status' => 'allocated'],
        ['delivery_order_id' => 1, 'status' => 'picked'],
        ['delivery_order_id' => 2, 'status' => 'out'],
    ]);

    $this->allocationService->shouldReceive('releaseAll')
        ->once()
        ->withArgs(fn (DeliveryOrder $order, ?int $actor): bool => $order->id === 1 && $actor === 7);

    $response = (new DeliveryOrderController)->cancel(1);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getTargetUrl())->toBe(route('admin.delivery-orders.show', 1))
        ->and(DB::table('delivery_orders')->where('id', 1)->value('status'))->toBe('cancelled')
        ->and(DB::table('delivery_orders')->where('id', 2)->value('status'))->toBe('issued')
        ->and(session('success'))->toBe('Surat Jalan dibatalkan.')
        ->and(session()->has('error'))->toBeFalse();
})->with(['draft', 'issued']);

it('blocks cancellation while inventory is outside the warehouse', function (string $allocationStatus): void {
    DB::table('delivery_orders')->insert(['id' => 1, 'status' => 'issued']);
    DB::table('delivery_order_inventory_allocations')->insert([
        'delivery_order_id' => 1,
        'status' => $allocationStatus,
    ]);
    $this->allocationService->shouldNotReceive('releaseAll');

    $response = (new DeliveryOrderController)->cancel(1);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getTargetUrl())->toBe(route('admin.delivery-orders.show', 1))
        ->and(session('error'))->toBe('Surat Jalan tidak dapat dibatalkan karena inventory sudah OUT dari warehouse.')
        ->and(session()->has('success'))->toBeFalse()
        ->and(DB::table('delivery_orders')->where('id', 1)->value('status'))->toBe('issued')
        ->and(DB::table('delivery_order_inventory_allocations')->value('status'))->toBe($allocationStatus);
})->with(['out', 'return_pending']);

it('preserves the existing restrictions on order status', function (string $status): void {
    DB::table('delivery_orders')->insert(['id' => 1, 'status' => $status]);
    $this->allocationService->shouldNotReceive('releaseAll');

    $response = (new DeliveryOrderController)->cancel(1);

    expect($response->getStatusCode())->toBe(302)
        ->and(session('error'))->toBe("Status tidak dapat diubah dari {$status} ke cancelled.")
        ->and(DB::table('delivery_orders')->where('id', 1)->value('status'))->toBe($status);
})->with(['delivered', 'returned', 'cancelled']);

it('reports a missing delivery order instead of a class resolution error', function (): void {
    $this->allocationService->shouldNotReceive('releaseAll');

    expect(fn () => (new DeliveryOrderController)->cancel(999))
        ->toThrow(ModelNotFoundException::class);
});
