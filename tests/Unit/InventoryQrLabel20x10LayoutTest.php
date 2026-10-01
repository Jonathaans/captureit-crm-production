<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('keeps each printed inventory label at exactly 20 by 10 millimetres', function (): void {
    $view = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/inventory/assets/qr-labels.blade.php')
    );

    expect($view)
        ->toContain('INVENTORY QR LABEL 20X10MM V1')
        ->toContain('grid-template-columns: repeat(9, 20mm)')
        ->toContain('grid-template-rows: repeat(25, 10mm)')
        ->toContain('width: 20mm')
        ->toContain('height: 10mm')
        ->toContain('$assets->chunk(225)');
});

it('filters printed asset labels by the selected inventory item code', function (): void {
    $view = file_get_contents(
        base_path('packages/Webkul/Admin/src/Resources/views/inventory/assets/qr-labels.blade.php')
    );
    $controller = file_get_contents(
        base_path('packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryAssetController.php')
    );

    expect($view)
        ->toContain('name="inventory_item_id"')
        ->toContain('{{ $item->code }} — {{ $item->name }}')
        ->toContain("route('admin.inventory.assets.qr-labels.index')");

    expect($controller)
        ->toContain("\$request->integer('inventory_item_id')")
        ->toContain("'inventory_item_id',");
});
