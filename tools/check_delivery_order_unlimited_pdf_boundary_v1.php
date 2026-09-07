<?php

declare(strict_types=1);

echo "CHECK DELIVERY ORDER UNLIMITED ITEMS + PDF BOUNDARY V1\n";
echo "========================================================\n\n";

$root = realpath(dirname(__DIR__));
$failures = 0;

$assert = static function (bool $condition, string $label) use (&$failures): void {
    if ($condition) {
        echo '[OK]   '.$label.PHP_EOL;
        return;
    }

    $failures++;
    echo '[FAIL] '.$label.PHP_EOL;
};

if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "CHECK GAGAL: Jalankan dari root Laravel.\n");
    exit(1);
}

$file = static fn (string $relative): string => $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
$read = static fn (string $path): string => is_file($path) ? str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($path)) : '';

$equipmentPath = $file('packages/Webkul/Admin/src/Resources/views/delivery-orders/partials/equipment-edit.blade.php');
$quotePath = $file('packages/Webkul/Admin/src/Resources/views/quotes/pdf.blade.php');
$invoicePath = $file('packages/Webkul/Admin/src/Resources/views/invoices/pdf.blade.php');
$controllerPath = $file('packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/DeliveryOrderController.php');

$equipment = $read($equipmentPath);
$quote = $read($quotePath);
$invoice = $read($invoicePath);
$controller = $read($controllerPath);

$assert($equipment !== '', 'Partial Equipment Surat Jalan tersedia');
$assert($quote !== '', 'PDF Quote tersedia');
$assert($invoice !== '', 'PDF Invoice tersedia');
$assert($controller !== '', 'Delivery Order Controller tersedia');

$assert(str_contains($equipment, 'CRM_DELIVERY_ORDER_UNLIMITED_ITEMS_UI_V1'), 'UI unlimited item terpasang');
$assert(! str_contains($equipment, '$equipmentRowCount'), 'Default 10 baris statis sudah dilepas');
$assert(str_contains($equipment, 'data-add-equipment'), 'Tombol Tambah Item tersedia');
$assert(str_contains($equipment, 'data-remove-equipment'), 'Tombol Hapus per item tersedia');
$assert(str_contains($equipment, 'data-equipment-template'), 'Template baris dinamis tersedia');
$assert(str_contains($equipment, 'data-equipment-search'), 'Pencarian item tersedia');
$assert(str_contains($equipment, 'data-equipment-count'), 'Counter item tersedia');
$assert(str_contains($equipment, 'sticky top-0'), 'Header tabel sticky terpasang');
$assert(str_contains($equipment, 'max-h-[680px] overflow-auto'), 'Daftar panjang memakai scroll area');
$assert(str_contains($equipment, "replaceAll('__INDEX__'"), 'Index item baru dibuat dinamis');
$assert(str_contains($equipment, 'data-inventory-select'), 'Auto-fill dari Inventory Item tersedia');

$assert(str_contains($quote, 'CRM_DOCUMENT_PDF_SAFE_TOP_BOUNDARY_V1'), 'Batas atas aman PDF Quote terpasang');
$assert(str_contains($invoice, 'CRM_DOCUMENT_PDF_SAFE_TOP_BOUNDARY_V1'), 'Batas atas aman PDF Invoice terpasang');
$assert(substr_count($quote, 'margin: 76px 28px 72px 28px;') === 1, 'PDF Quote memakai top boundary 20 mm');
$assert(substr_count($invoice, 'margin: 76px 28px 72px 28px;') === 1, 'PDF Invoice memakai top boundary 20 mm');
$assert(! str_contains($quote, 'margin: 22px 28px 72px 28px;'), 'Margin PDF Quote lama sudah tidak aktif');
$assert(! str_contains($invoice, 'margin: 22px 28px 72px 28px;'), 'Margin PDF Invoice lama sudah tidak aktif');
$assert(str_contains($quote, 'display: table-header-group'), 'Header tabel Quote tetap berulang di halaman lanjutan');
$assert(str_contains($invoice, 'display: table-header-group'), 'Header tabel Invoice tetap berulang di halaman lanjutan');

if ($controller !== '') {
    $itemsBlockPosition = strpos($controller, "'items' => [");
    $itemsValidation = $itemsBlockPosition === false ? '' : substr($controller, $itemsBlockPosition, 240);
    $assert($itemsBlockPosition !== false, 'Validasi array item Surat Jalan tersedia');
    $assert(! preg_match('/[\'\"]max\s*:\s*10[\'\"]/', $itemsValidation), 'Backend tidak membatasi item maksimal 10');
}

$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.DIRECTORY_SEPARATOR.'artisan').' view:cache';
$output = [];
$exitCode = 1;
exec($command.' 2>&1', $output, $exitCode);
$assert($exitCode === 0, 'Semua Blade berhasil dikompilasi');

if ($exitCode !== 0) {
    echo "       ".implode(PHP_EOL.'       ', array_slice($output, -8)).PHP_EOL;
}

echo PHP_EOL;
if ($failures > 0) {
    echo "[FAIL] Checker menemukan {$failures} masalah.\n";
    exit(1);
}

echo "[PASS] Surat Jalan unlimited item dan PDF safe boundary terpasang lengkap.\n";
echo "Uji edit Surat Jalan dengan minimal 12 item, lalu cetak Quote dan Invoice dua halaman.\n";

