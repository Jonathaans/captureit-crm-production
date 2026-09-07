<?php

declare(strict_types=1);

echo "CHECK DELIVERY ORDER CONTROLS HOTFIX V1.1\n";
echo "==========================================\n\n";

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
$asset = $read($path('public/js/crm-delivery-order-equipment-v1-1.js'));

$assert($view !== '', 'UI Equipment Surat Jalan tersedia');
$assert($asset !== '', 'JavaScript eksternal tersedia');
$assert(str_contains($view, 'CRM_DELIVERY_ORDER_CONTROLS_EXTERNAL_LOADER_V1_1'), 'Loader eksternal V1.1 terpasang');
$assert(str_contains($view, "asset('js/crm-delivery-order-equipment-v1-1.js')"), 'Loader menunjuk asset yang benar');
$assert(str_contains($view, 'defer'), 'Asset dimuat setelah dokumen diproses');
$assert(! str_contains($view, 'CRM_DELIVERY_ORDER_UNLIMITED_ITEMS_SCRIPT_V1'), 'Inline script lama sudah dilepas');
$assert(str_contains($asset, 'CRM_DELIVERY_ORDER_EQUIPMENT_EXTERNAL_JS_V1_1'), 'Marker JavaScript V1.1 tersedia');
$assert(str_contains($asset, "document.addEventListener('click'"), 'Delegasi klik terpasang');
$assert(str_contains($asset, "target.closest('[data-add-equipment]')"), 'Tombol Tambah Item/Tambah Baris ditangani');
$assert(str_contains($asset, "target.closest('[data-remove-equipment]')"), 'Tombol Hapus ditangani');
$assert(str_contains($asset, 'template.content.cloneNode(true)'), 'Baris baru dibuat dari template DOM');
$assert(str_contains($asset, "field.name.replace('__INDEX__', index)"), 'Index input baris baru diperbarui');
$assert(str_contains($asset, "document.addEventListener('input'"), 'Pencarian item ditangani');
$assert(str_contains($asset, "document.addEventListener('change'"), 'Auto-fill inventory ditangani');
$assert(str_contains($asset, "window.addEventListener('pageshow'"), 'Render ulang halaman ditangani');

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

echo "[PASS] Tombol Hapus, Tambah Baris, dan Tambah Item aktif melalui asset eksternal.\n";
