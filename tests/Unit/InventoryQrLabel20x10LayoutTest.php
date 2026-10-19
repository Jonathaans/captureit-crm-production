<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Webkul\Admin\Http\Controllers\Inventory\InventoryAssetController;

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

it('prints only the selected inventory assets and offers serialized items in the filter', function (array $filters, array $expectedAssets): void {
    $previousConnection = DB::getDefaultConnection();
    config(['database.connections.qr_label_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::setDefaultConnection('qr_label_test');
    Schema::clearResolvedInstance('db.schema');

    try {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code');
            $table->string('name');
            $table->string('tracking_type');
        });
        Schema::create('inventory_assets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('inventory_item_id');
            $table->string('asset_code');
            $table->string('qr_value')->nullable();
        });
        DB::table('inventory_items')->insert([
            ['id' => 1, 'code' => 'CAM', 'name' => 'Camera', 'tracking_type' => 'serialized'],
            ['id' => 2, 'code' => 'LIGHT', 'name' => 'Lighting', 'tracking_type' => 'serialized'],
            ['id' => 3, 'code' => 'PAPER', 'name' => 'Print paper', 'tracking_type' => 'quantity'],
        ]);
        DB::table('inventory_assets')->insert([
            ['id' => 11, 'inventory_item_id' => 1, 'asset_code' => 'CAM-001'],
            ['id' => 12, 'inventory_item_id' => 1, 'asset_code' => 'CAM-002'],
            ['id' => 21, 'inventory_item_id' => 2, 'asset_code' => 'LIGHT-001'],
        ]);

        $view = app(InventoryAssetController::class)->qrLabels(Request::create('/', 'GET', $filters));
        expect($view->getData()['assets']->pluck('id')->all())->toBe($expectedAssets);

        $document = new DOMDocument;
        @$document->loadHTML($view->render());
        $xpath = new DOMXPath($document);
        $inputs = $xpath->query('//input[@name="inventory_item_ids[]"]');
        expect(array_map(fn (DOMElement $input) => $input->getAttribute('value'), iterator_to_array($inputs)))
            ->toBe(['1', '2']);
    } finally {
        DB::purge('qr_label_test');
        DB::setDefaultConnection($previousConnection);
        Schema::clearResolvedInstance('db.schema');
    }
})->with([
    'legacy single item link' => [['inventory_item_id' => 1], [11, 12]],
    'multiple selected items' => [['inventory_item_ids' => [1, 2]], [11, 12, 21]],
    'one item in checklist' => [['inventory_item_ids' => [2]], [21]],
    'explicitly empty checklist' => [['inventory_item_filter' => 1], []],
    'initial selection' => [[], [11, 12, 21]],
    'bulk asset list' => [['ids' => '12,21'], [12, 21]],
]);
