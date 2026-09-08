<?php

declare(strict_types=1);

const TITLE = 'CHECK INVENTORY MISSING RECOVERY SCANNER ALLOCATION HOTFIX V1.3';

$root = dirname(__DIR__);
$failures = 0;
$relative = 'packages/Webkul/Admin/src/Resources/views/inventory/assets/recover-missing-scan.blade.php';
$path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

function result(bool $condition, string $message): void
{
    global $failures;

    if ($condition) {
        echo '[OK]   '.$message.PHP_EOL;
    } else {
        $failures++;
        echo '[FAIL] '.$message.PHP_EOL;
    }
}

echo TITLE.PHP_EOL;
echo str_repeat('=', strlen(TITLE)).PHP_EOL.PHP_EOL;

result(is_file($path), 'Halaman Missing Recovery Scan tersedia');

$content = is_file($path) ? (string) file_get_contents($path) : '';

$checks = [
    'Marker behavior Manage Allocation V1.3 terpasang' => 'INVENTORY_MISSING_RECOVERY_SCANNER_ALLOCATION_BEHAVIOR_V1_3',
    'UI tidak lagi meminta tombol scanner' => 'Tidak perlu klik input atau tombol',
    'Status scanner READY tersedia' => 'READY — langsung scan QR',
    'Buffer scanner sama seperti Manage Allocation' => "let scanBuffer = '';",
    'Jeda antar-scan sama seperti Manage Allocation' => 'now - lastKeyAt > 800',
    'Listener keyboard memakai fase normal' => "document.addEventListener('keydown', (event) => {",
    'Input form tidak dirampas scanner' => "target.matches('[data-allow-typing]')",
    'Suffix Enter dan Tab didukung' => "event.key === 'Enter' || event.key === 'Tab'",
    'Scan diproses langsung ke validator' => 'acceptScan(code);',
    'Scan cocok masuk ke hidden server field' => 'hiddenBarcode.value = rawValue.trim();',
    'Reset menyiapkan scanner ulang' => "scannerState.textContent = 'READY — langsung scan QR';",
];

foreach ($checks as $label => $needle) {
    result(str_contains($content, $needle), $label);
}

result(! str_contains($content, 'id="usb-button"'), 'Tombol aktivasi scanner lama sudah dilepas');
result(! str_contains($content, 'usbActive'), 'State scanner lama sudah dilepas');
result(! str_contains($content, "}, true);"), 'Capture-phase yang memblokir scanner sudah dilepas');

$autoload = $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
$bootstrap = $root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';

if (! is_file($autoload) || ! is_file($bootstrap)) {
    result(false, 'Laravel bootstrap tersedia');
} else {
    try {
        require $autoload;
        $app = require $bootstrap;
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        result(
            Illuminate\Support\Facades\Route::has('admin.inventory.assets.missing-recovery.scan'),
            'Route halaman recovery scan tetap terdaftar'
        );

        Illuminate\Support\Facades\Artisan::call('view:clear');
        $viewResult = Illuminate\Support\Facades\Artisan::call('view:cache');
        result($viewResult === 0, 'Semua Blade berhasil dikompilasi');
    } catch (Throwable $exception) {
        result(false, 'Laravel runtime: '.$exception->getMessage());
    }
}

echo PHP_EOL;

if ($failures > 0) {
    echo '[FAIL] Checker menemukan '.$failures.' masalah.'.PHP_EOL;
    exit(1);
}

echo '[PASS] Scanner recovery sudah mengikuti behavior Manage Allocation Items.'.PHP_EOL;
echo 'Buka ulang halaman recovery, jangan klik field apa pun, lalu langsung scan barcode/QR.'.PHP_EOL;
