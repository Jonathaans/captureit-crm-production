<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('persists the authenticated release actor and prints the correct signature names', function (): void {
    $controller = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/DeliveryOrderController.php')
    );
    $model = file_get_contents(
        base_path('packages/Webkul/Invoice/src/Models/DeliveryOrder.php')
    );
    $migration = file_get_contents(
        base_path('database/migrations/2026_09_15_140000_add_release_actor_to_delivery_orders_table.php')
    );
    $printView = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/delivery-orders/print.blade.php')
    );

    expect($controller)
        ->toContain("\$releaseUser = auth()->guard('user')->user()")
        ->toContain("'released_by' => \$releaseUser?->id")
        ->toContain("'released_by_name' => \$releaseUser?->name")
        ->toContain('lockForUpdate()')
        ->toContain("!== 'draft'");

    expect($model)
        ->toContain("'released_by'")
        ->toContain("'released_by_name'")
        ->toContain("'released_by'\n        );");

    expect($migration)
        ->toContain("unsignedInteger('released_by')")
        ->toContain("string('released_by_name')")
        ->toContain("'delivery_orders_released_by_fk'")
        ->toContain("->onDelete('set null')");

    expect($printView)
        ->toContain('$deliveryOrder->released_by_name')
        ->toContain('$deliveryOrder->recipient_name')
        ->toContain('$deliveryOrder->pic_name');

    $receivedByStart = strpos($printView, '{{-- 4. PIC --}}');
    $receivedByEnd = strpos($printView, '</td>', $receivedByStart);
    $receivedByBlock = substr(
        $printView,
        $receivedByStart,
        $receivedByEnd - $receivedByStart
    );

    expect($receivedByBlock)
        ->toContain('$deliveryOrder->pic_name')
        ->not->toContain('$deliveryOrder->recipient_name')
        ->not->toContain("auth()->guard('user')");
});
