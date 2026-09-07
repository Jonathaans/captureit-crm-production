<?php
declare(strict_types=1);

echo "CHECK MISSING ASSET DEDICATED SCANNER V3\n";
echo "========================================\n\n";

$root = dirname(__DIR__);
chdir($root);

$fails = 0;

function ckV3(bool $ok, string $label): void
{
    global $fails;

    echo ($ok ? '[OK]   ' : '[FAIL] ').$label."\n";

    if (! $ok) {
        $fails++;
    }
}

$pageController =
    $root
    .'/packages/Webkul/Admin/src/Http/Controllers/Inventory/MissingAssetRecoveryScannerPageController.php';

$scanView =
    $root
    .'/packages/Webkul/Admin/src/Resources/views/inventory/assets/missing-recovery-scan.blade.php';

$editView =
    $root
    .'/packages/Webkul/Admin/src/Resources/views/inventory/assets/edit.blade.php';

$controllerText =
    is_file($pageController)
        ? (string) file_get_contents($pageController)
        : '';

$scanText =
    is_file($scanView)
        ? (string) file_get_contents($scanView)
        : '';

$editText =
    is_file($editView)
        ? (string) file_get_contents($editView)
        : '';

ckV3(
    str_contains(
        $controllerText,
        'MISSING_ASSET_DEDICATED_SCANNER_V3'
    ),
    'Dedicated scanner page controller tersedia'
);

if (is_file($pageController)) {
    exec(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($pageController).' 2>&1',
        $lintOut,
        $lintCode
    );

    ckV3(
        $lintCode === 0,
        'Scanner page controller PHP lint PASS'
    );
} else {
    ckV3(false, 'Scanner page controller PHP lint PASS');
}

ckV3(
    str_contains(
        $scanText,
        'MISSING_ASSET_DEDICATED_SCANNER_V3_VIEW'
    ),
    'Dedicated scanner Blade tersedia'
);

ckV3(
    str_contains(
        $scanText,
        'type="text"'
    )
    && str_contains(
        $scanText,
        'autofocus'
    )
    && str_contains(
        $scanText,
        'name="scan_code"'
    ),
    'Scanner memakai real autofocus text input'
);

ckV3(
    str_contains(
        $scanText,
        "'input'"
    )
    && str_contains(
        $scanText,
        'requestSubmit()'
    ),
    'Scanner memakai input event + native form submit'
);

ckV3(
    ! str_contains(
        $scanText,
        "document.addEventListener(\n                    'keydown'"
    ),
    'Tidak memakai global keydown experiment'
);

ckV3(
    str_contains(
        $editText,
        'MISSING_ASSET_DEDICATED_SCANNER_V3_LINK'
    )
    && str_contains(
        $editText,
        'Open Recovery Scanner'
    ),
    'Edit Asset membuka dedicated scanner page'
);

ckV3(
    ! str_contains(
        $editText,
        'MISSING_ASSET_SCANNER_GLOBAL_CAPTURE_V2_3'
    ),
    'Old global scanner V2.3 dibersihkan'
);

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' route:list --name=admin.inventory.assets.missing-recovery.scanner 2>&1',
    $getOut,
    $getCode
);

ckV3(
    $getCode === 0
    && str_contains(
        implode("\n", $getOut),
        'admin.inventory.assets.missing-recovery.scanner'
    ),
    'Dedicated GET scanner route aktif'
);

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' route:list --name=admin.inventory.assets.missing-recover-scan 2>&1',
    $postOut,
    $postCode
);

ckV3(
    $postCode === 0
    && str_contains(
        implode("\n", $postOut),
        'admin.inventory.assets.missing-recover-scan'
    ),
    'Existing backend POST verification aktif'
);

$movementGrid =
    $root
    .'/packages/Webkul/Admin/src/DataGrids/Inventory/InventoryMovementDataGrid.php';

if (is_file($movementGrid)) {
    $grid =
        (string) file_get_contents(
            $movementGrid
        );

    ckV3(
        substr_count(
            $grid,
            "['label' => 'Missing Recovered', 'value' => 'missing_recovered'],"
        ) <= 1,
        'Duplicate Missing Recovered filter sudah dibersihkan'
    );
}

echo "\n";

if ($fails > 0) {
    echo "HASIL: FAIL ({$fails} masalah)\n";
    exit(1);
}

echo "HASIL: PASS\n\n";
echo "TEST:\n";
echo "1. Buka asset MISSING.\n";
echo "2. Klik Open Recovery Scanner.\n";
echo "3. Halaman scanner harus langsung WAITING FOR SCAN.\n";
echo "4. Jangan klik field apa pun; scan QR fisik.\n";
echo "5. Scanner mengisi real autofocus input dan Enter/native submit memverifikasi.\n";
echo "6. QR salah => SCAN REJECTED, status tetap MISSING.\n";
echo "7. QR benar => AVAILABLE + missing_recovered movement + reference SJ.\n";
