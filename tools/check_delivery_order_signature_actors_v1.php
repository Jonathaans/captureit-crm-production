<?php

declare(strict_types=1);

/**
 * Read-only structural checker for Delivery Order signature actors V1.
 *
 * Run from the project root:
 * php tools/check_delivery_order_signature_actors_v1.php
 */

$root = dirname(__DIR__);
$errors = [];
$checks = 0;

function deliveryOrderSignatureCheck(
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
    'controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/DeliveryOrderController.php',
    'model' => $root.'/packages/Webkul/Invoice/src/Models/DeliveryOrder.php',
    'migration' => $root.'/database/migrations/2026_09_15_140000_add_release_actor_to_delivery_orders_table.php',
    'print view' => $root.'/packages/Webkul/Admin/src/Resources/views/delivery-orders/print.blade.php',
];

$contents = [];

foreach ($paths as $key => $path) {
    deliveryOrderSignatureCheck(
        is_file($path),
        ucfirst($key).' tersedia'
    );

    $contents[$key] = is_file($path)
        ? (string) file_get_contents($path)
        : '';
}

$controller = $contents['controller'];
$model = $contents['model'];
$migration = $contents['migration'];
$printView = $contents['print view'];

deliveryOrderSignatureCheck(
    str_contains($controller, "\$releaseUser = auth()->guard('user')->user()")
    && str_contains($controller, "'released_by' => \$releaseUser?->id")
    && str_contains($controller, "'released_by_name' => \$releaseUser?->name"),
    'Akun yang login disimpan saat Surat Jalan dirilis'
);

deliveryOrderSignatureCheck(
    str_contains($controller, 'lockForUpdate()')
    && str_contains($controller, "!== 'draft'"),
    'Snapshot releaser dilindungi dari release ulang'
);

deliveryOrderSignatureCheck(
    str_contains($migration, "unsignedInteger('released_by')")
    && str_contains($migration, "string('released_by_name')")
    && str_contains($migration, "'delivery_orders_released_by_fk'"),
    'Kolom ID dan nama snapshot releaser tersedia'
);

deliveryOrderSignatureCheck(
    str_contains($model, "'released_by'")
    && str_contains($model, "'released_by_name'")
    && str_contains($model, 'function releaser()'),
    'Model Delivery Order mengakui audit releaser'
);

deliveryOrderSignatureCheck(
    str_contains($printView, '<div class="label">Recipient</div>')
    && str_contains($printView, '$deliveryOrder->recipient_name'),
    'Recipient tetap memakai nama client'
);

$receivedByStart = strpos($printView, '{{-- 4. PIC --}}');
$receivedByEnd = $receivedByStart === false
    ? false
    : strpos($printView, '</td>', $receivedByStart);
$receivedByBlock = (
    $receivedByStart !== false
    && $receivedByEnd !== false
)
    ? substr(
        $printView,
        $receivedByStart,
        $receivedByEnd - $receivedByStart
    )
    : '';

deliveryOrderSignatureCheck(
    str_contains($receivedByBlock, '$deliveryOrder->pic_name')
    && ! str_contains($receivedByBlock, '$deliveryOrder->recipient_name'),
    'Received By hanya memakai nama PIC'
);

deliveryOrderSignatureCheck(
    str_contains($printView, '$deliveryOrder->released_by_name')
    && ! str_contains($printView, "auth()->guard('user')"),
    'PDF memakai snapshot Released By, bukan akun pencetak'
);

echo PHP_EOL;

if ($errors !== []) {
    echo '[FAIL] '.count($errors).' pemeriksaan gagal.'.PHP_EOL;
    exit(1);
}

echo '[PASS] '.$checks.' pemeriksaan signature Surat Jalan lulus.'.PHP_EOL;
