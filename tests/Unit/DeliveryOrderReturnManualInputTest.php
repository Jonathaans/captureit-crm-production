<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('routes manual asset input through the existing return scan validation', function (): void {
    $controller = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/DeliveryOrderReturnController.php')
    );
    $view = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/delivery-orders/return.blade.php')
    );
    $routes = file_get_contents(
        base_path('packages/Webkul/Admin/src/Routes/Admin/delivery-order-routes.php')
    );

    expect($controller)
        ->toContain('findAssetByReturnIdentifier')
        ->toContain("'barcode_value'")
        ->toContain("'asset_code'")
        ->toContain("'serial_number'")
        ->toContain('$identifier')
        ->toContain('Serial Number digunakan oleh lebih dari satu asset')
        ->toContain("->where('delivery_order_id'")
        ->toContain('$deliveryOrder->id')
        ->toContain("->where('inventory_asset_id'")
        ->toContain('$asset->id')
        ->toContain('scanReturnSerialized(');

    expect($view)
        ->toContain('<details')
        ->toContain('id="return-manual-disclosure"')
        ->toContain('id="return-manual-toggle"')
        ->toContain('id="return-manual-form"')
        ->toContain("admin.delivery-orders.return.scan-check-in")
        ->toContain('name="barcode"')
        ->toContain('data-allow-typing')
        ->toContain("enqueue(\n                            code,\n                            'manual'")
        ->toContain("bouncer()->hasPermission('delivery-orders.return.check-in')");

    expect($routes)
        ->toContain("Route::put('{id}/return/scan-check-in', 'scanCheckIn')")
        ->toContain("->name('admin.delivery-orders.return.scan-check-in')");
});
