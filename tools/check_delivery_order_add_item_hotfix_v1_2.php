<?php

declare(strict_types=1);

echo "CHECK DELIVERY ORDER ADD ITEM HOTFIX V1.2\n";
echo "===========================================\n\n";

$root = realpath(dirname(__DIR__));
$failures = 0;

$assert = static function (bool $condition, string $label) use (&$failures): void {
    echo ($condition ? '[OK]   ' : '[FAIL] ').$label.PHP_EOL;
    if (! $condition) {
        $failures++;
    }
};

if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "CHECK GAGAL: Jalankan dari root Laravel.\n");
    exit(1);
}

$path = static fn (string $relative): string => $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
$read = static fn (string $file): string => is_file($file)
    ? str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($file))
    : '';

$view = $read($path('packages/Webkul/Admin/src/Resources/views/delivery-orders/partials/equipment-edit.blade.php'));
$asset = $read($path('public/js/crm-delivery-order-equipment-v1-2.js'));

$assert($view !== '', 'UI Equipment Surat Jalan tersedia');
$assert($asset !== '', 'Asset JavaScript V1.2 tersedia');
$assert(str_contains($view, 'CRM_DELIVERY_ORDER_CONTROLS_EXTERNAL_LOADER_V1_2'), 'Loader V1.2 terpasang');
$assert(str_contains($view, "asset('js/crm-delivery-order-equipment-v1-2.js')"), 'Loader memakai asset V1.2');
$assert(str_contains($view, '?v=1.2.0'), 'Cache-buster V1.2 terpasang');
$assert(! str_contains($view, 'CRM_DELIVERY_ORDER_CONTROLS_EXTERNAL_LOADER_V1_1'), 'Loader V1.1 sudah tidak aktif');
$assert(str_contains($asset, 'CRM_DELIVERY_ORDER_EQUIPMENT_EXTERNAL_JS_V1_2'), 'Marker asset V1.2 tersedia');
$assert(str_contains($asset, 'existingRows[existingRows.length - 1].cloneNode(true)'), 'Tambah item mengkloning baris aktif');
$assert(str_contains($asset, 'resetClonedRow(newRow, index)'), 'Baris hasil clone dikosongkan');
$assert(str_contains($asset, "/items\\[[^\\]]+\\]/"), 'Index input clone diganti aman');
$assert(! str_contains($asset, 'template.content.cloneNode(true)'), 'Tambah item tidak bergantung native template');
$assert(str_contains($asset, "target.closest('[data-add-equipment]')"), 'Kedua tombol tambah ditangani');
$assert(str_contains($asset, "target.closest('[data-remove-equipment]')"), 'Tombol hapus tetap ditangani');
$assert(str_contains($asset, 'getRows(root).length === 1'), 'Baris terakhir dipertahankan dan dikosongkan');

$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.DIRECTORY_SEPARATOR.'artisan').' view:cache';
$output = [];
$exitCode = 1;
exec($command.' 2>&1', $output, $exitCode);
$assert($exitCode === 0, 'Semua Blade berhasil dikompilasi');

if ($exitCode !== 0) {
    echo '       '.implode(PHP_EOL.'       ', array_slice($output, -8)).PHP_EOL;
}

echo PHP_EOL;
if ($failures > 0) {
    echo "[FAIL] Checker menemukan {$failures} masalah.\n";
    exit(1);
}

echo "[PASS] Tambah Item, Tambah Baris, dan Hapus terpasang melalui logic clone V1.2.\n";
