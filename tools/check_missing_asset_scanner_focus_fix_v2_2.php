<?php
declare(strict_types=1);

echo "CHECK MISSING ASSET SCANNER FOCUS FIX V2.2\n";
echo "==========================================\n\n";

$root = dirname(__DIR__);
chdir($root);

$fails = 0;

function ckV22(bool $ok, string $label): void
{
    global $fails;

    echo ($ok ? '[OK]   ' : '[FAIL] ').$label."\n";

    if (! $ok) {
        $fails++;
    }
}

$viewBase = $root.'/packages/Webkul/Admin/src/Resources/views/inventory';
$view = null;
$text = '';

if (is_dir($viewBase)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $viewBase,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($it as $file) {
        if (
            ! $file->isFile()
            || ! str_ends_with(strtolower($file->getFilename()), '.blade.php')
        ) {
            continue;
        }

        $candidate = (string) @file_get_contents($file->getPathname());

        if (
            str_contains(
                $candidate,
                'MISSING_ASSET_SCANNER_FOCUS_FIX_V2_2'
            )
        ) {
            $view = $file->getPathname();
            $text = $candidate;
            break;
        }
    }
}

ckV22(
    $view !== null,
    'View scanner V2.2 ditemukan'
);

ckV22(
    str_contains($text, 'WAITING FOR SCAN'),
    'WAITING FOR SCAN UI tersedia'
);

ckV22(
    str_contains($text, 'crm-missing-scan-capture-')
    && str_contains($text, 'left: -10000px'),
    'Hidden scanner focus target tersedia'
);

ckV22(
    str_contains($text, 'capture.focus'),
    'Auto-focus scanner terpasang'
);

ckV22(
    str_contains($text, "event.key === 'Enter'")
    && str_contains($text, "event.key === 'Tab'"),
    'Enter / Tab scanner suffix didukung'
);

ckV22(
    str_contains($text, 'IDLE_SUBMIT_MS'),
    'Scanner tanpa suffix didukung'
);

ckV22(
    str_contains($text, 'MAX_AVERAGE_MS')
    && str_contains($text, 'MAX_SINGLE_GAP_MS'),
    'Slow manual typing guard tersedia'
);

ckV22(
    str_contains($text, 'SCAN REJECTED')
    && str_contains($text, "@error('scan_code')"),
    'Backend scan rejection tampil di UI'
);

ckV22(
    ! str_contains($text, 'Verify & Mark Found'),
    'Tidak ada tombol manual recovery'
);

$controller =
    $root
    .'/packages/Webkul/Admin/src/Http/Controllers/Inventory/MissingAssetQrRecoveryController.php';

$controllerText =
    is_file($controller)
        ? (string) file_get_contents($controller)
        : '';

ckV22(
    str_contains(
        $controllerText,
        'MISSING_ASSET_QR_RECOVERY_V2'
    ),
    'Backend QR verification tetap tersedia'
);

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' route:list --name=admin.inventory.assets.missing-recover-scan 2>&1',
    $routeOut,
    $routeCode
);

ckV22(
    $routeCode === 0
    && str_contains(
        implode("\n", $routeOut),
        'admin.inventory.assets.missing-recover-scan'
    ),
    'QR recovery route aktif'
);

echo "\n";

if ($fails > 0) {
    echo "HASIL: FAIL ({$fails} masalah)\n";
    exit(1);
}

echo "HASIL: PASS\n\n";
echo "TEST:\n";
echo "1. Ctrl+Shift+R pada halaman asset MISSING.\n";
echo "2. Jangan klik apa pun.\n";
echo "3. Scan QR fisik.\n";
echo "4. UI harus berubah WAITING FOR SCAN -> SCANNING -> VERIFYING.\n";
echo "5. Scan salah menampilkan SCAN REJECTED dan asset tetap MISSING.\n";
echo "6. Scan benar mengubah asset ke AVAILABLE.\n";
