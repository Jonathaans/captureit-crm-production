<?php

declare(strict_types=1);

/**
 * Read-only structural checker for Delivery Order Return Manual Input V1.
 *
 * Run from the project root:
 * php tools/check_delivery_order_return_manual_input_v1.php
 */

$root = dirname(__DIR__);
$errors = [];
$checks = 0;

function returnManualInputCheck(
    bool $condition,
    string $description
): void {
    global $checks, $errors;

    if ($condition) {
        $checks++;
        echo '[PASS] '.$description.PHP_EOL;

        return;
    }

    $errors[] = $description;
    echo '[FAIL] '.$description.PHP_EOL;
}

$paths = [
    'controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/DeliveryOrderReturnController.php',
    'view' => $root.'/packages/Webkul/Admin/src/Resources/views/delivery-orders/return.blade.php',
    'routes' => $root.'/packages/Webkul/Admin/src/Routes/Admin/delivery-order-routes.php',
];

$contents = [];

foreach ($paths as $key => $path) {
    returnManualInputCheck(
        is_file($path),
        ucfirst($key).' file tersedia'
    );

    $contents[$key] = is_file($path)
        ? (string) file_get_contents($path)
        : '';
}

$controller = $contents['controller'];
$view = $contents['view'];
$routes = $contents['routes'];

returnManualInputCheck(
    str_contains($view, '<details')
    && str_contains($view, 'id="return-manual-disclosure"')
    && str_contains($view, 'id="return-manual-toggle"')
    && str_contains($view, 'id="return-manual-panel"')
    && str_contains($view, 'id="return-manual-form"'),
    'UI fallback manual native tersedia'
);

returnManualInputCheck(
    str_contains($view, "bouncer()->hasPermission('delivery-orders.return.check-in')")
    && str_contains($view, 'name="barcode"')
    && str_contains($view, 'data-allow-typing')
    && str_contains($view, "@method('PUT')"),
    'Form manual mengikuti izin dan kontrak input scan'
);

returnManualInputCheck(
    str_contains($view, "admin.delivery-orders.return.scan-check-in")
    && str_contains($routes, "Route::put('{id}/return/scan-check-in', 'scanCheckIn')")
    && str_contains($routes, "->name('admin.delivery-orders.return.scan-check-in')"),
    'Input manual memakai endpoint scan-check-in yang sama'
);

returnManualInputCheck(
    str_contains($view, "enqueue(\n                            code,\n                            'manual'")
    && str_contains($view, "function enqueue(code, source = 'scanner')")
    && str_contains($view, 'const job = queue.shift();'),
    'Scanner dan manual memakai antrean proses yang sama'
);

returnManualInputCheck(
    str_contains($controller, 'findAssetByReturnIdentifier')
    && str_contains($controller, "'barcode_value'")
    && str_contains($controller, "'asset_code'")
    && str_contains($controller, "'serial_number'")
    && str_contains($controller, '$identifier'),
    'Asset Code, Barcode, dan Serial Number dapat diresolusi'
);

returnManualInputCheck(
    str_contains($controller, '$serialMatches->count() > 1')
    && str_contains($controller, 'Serial Number digunakan oleh lebih dari satu asset'),
    'Serial Number ambigu ditolak'
);

returnManualInputCheck(
    str_contains($controller, "->where('delivery_order_id'")
    && str_contains($controller, '$deliveryOrder->id')
    && str_contains($controller, "->where('tracking_type', 'serialized')")
    && str_contains($controller, "->where('inventory_asset_id'")
    && str_contains($controller, '$asset->id')
    && str_contains($controller, "'return_pending'")
    && str_contains($controller, "'returned'"),
    'Validasi Surat Jalan, jenis tracking, asset, dan status tetap digunakan'
);

returnManualInputCheck(
    str_contains($controller, '$this->returnService')
    && str_contains($controller, 'scanReturnSerialized('),
    'Perubahan status tetap melalui DeliveryOrderReturnService'
);

returnManualInputCheck(
    ! str_contains($view, 'DB::')
    && ! str_contains($view, 'InventoryAsset::query()'),
    'View tidak melakukan perubahan database langsung'
);

echo PHP_EOL;

if ($errors !== []) {
    echo '[FAIL] '.count($errors).' pemeriksaan gagal.'.PHP_EOL;
    exit(1);
}

echo '[PASS] '.$checks.' pemeriksaan manual return lulus.'.PHP_EOL;
