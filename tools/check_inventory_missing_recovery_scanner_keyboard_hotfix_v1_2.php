<?php

declare(strict_types=1);

const TITLE = 'CHECK INVENTORY MISSING RECOVERY SCANNER KEYBOARD HOTFIX V1.2.1';

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
    'Marker Hotfix V1.2 terpasang' => 'INVENTORY_MISSING_RECOVERY_SCANNER_KEYBOARD_HOTFIX_V1_2',
    'Scanner keyboard aktif sejak halaman dibuka' => 'let usbActive = true;',
    'Buffer scanner tersedia' => "let usbBuffer = '';",
    'Timer scanner tanpa suffix tersedia' => 'setTimeout(commitUsbBuffer, 350)',
    'Suffix Enter dan Tab didukung' => "event.key === 'Enter' || event.key === 'Tab'",
    'Keyboard ditangkap sebelum form/browser' => "}, true);",
    'Scanner otomatis dipersenjatai' => 'armUsbScanner();',
    'Scan sukses menonaktifkan capture' => 'usbActive = false;',
    'Scan mismatch otomatis siap scan ulang' => "usbButton.textContent = 'Scanner Keyboard Aktif — Scan Ulang';",
    'UI menjelaskan scanner otomatis' => 'Scanner Keyboard Otomatis Aktif — Scan Sekarang',
];

foreach ($checks as $label => $needle) {
    result(str_contains($content, $needle), $label);
}

result(
    ! str_contains($content, 'usbActive = !usbActive'),
    'Toggle manual scanner V1 sudah dilepas'
);

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

echo '[PASS] Scanner keyboard siap dipakai langsung tanpa tombol aktivasi.'.PHP_EOL;
echo 'Buka ulang halaman recovery lalu langsung scan barcode asset.'.PHP_EOL;
