<?php
declare(strict_types=1);

echo "CHECK MISSING ASSET SCANNER-ONLY V2.1\n";
echo "=====================================\n\n";

$root = dirname(__DIR__);
chdir($root);

$fails = 0;

function ckScanner(bool $ok, string $label): void
{
    global $fails;

    echo ($ok ? '[OK]   ' : '[FAIL] ').$label."\n";

    if (! $ok) {
        $fails++;
    }
}

$viewBase =
    $root
    .'/packages/Webkul/Admin/src/Resources/views/inventory';

$view = null;

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
            || ! str_ends_with(
                strtolower($file->getFilename()),
                '.blade.php'
            )
        ) {
            continue;
        }

        $text =
            (string) @file_get_contents(
                $file->getPathname()
            );

        if (
            str_contains(
                $text,
                'MISSING_ASSET_SCANNER_ONLY_V2_1'
            )
        ) {
            $view =
                $file->getPathname();

            break;
        }
    }
}

ckScanner(
    $view !== null,
    'Scanner-only view ditemukan'
);

$text =
    $view
        ? (string) file_get_contents($view)
        : '';

ckScanner(
    str_contains(
        $text,
        'WAITING FOR SCAN'
    ),
    'WAITING FOR SCAN UI tersedia'
);

ckScanner(
    str_contains(
        $text,
        'type="hidden"'
    )
    && str_contains(
        $text,
        'name="scan_code"'
    ),
    'scan_code hanya menggunakan hidden input'
);

ckScanner(
    ! preg_match(
        '/type=["\']text["\'][^>]*name=["\']scan_code["\']/i',
        $text
    ),
    'Tidak ada visible text input scan_code'
);

ckScanner(
    ! str_contains(
        $text,
        'Verify & Mark Found'
    ),
    'Tidak ada tombol manual Verify & Mark Found'
);

ckScanner(
    str_contains(
        $text,
        "document.addEventListener(\n                    'keydown'"
    )
    || str_contains(
        $text,
        "document.addEventListener(\r\n                    'keydown'"
    ),
    'Global scanner key listener terpasang'
);

ckScanner(
    str_contains(
        $text,
        'MAX_INTER_KEY_MS'
    )
    && str_contains(
        $text,
        'averageInterval'
    ),
    'Slow manual typing guard terpasang'
);

ckScanner(
    str_contains(
        $text,
        "'paste'"
    )
    && str_contains(
        $text,
        'PASTE DISABLED'
    ),
    'Paste guard terpasang'
);

ckScanner(
    str_contains(
        $text,
        'form.submit()'
    ),
    'Scanner auto-submit terpasang'
);

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' route:list --name=admin.inventory.assets.missing-recover-scan 2>&1',
    $routeOut,
    $routeCode
);

ckScanner(
    $routeCode === 0
    && str_contains(
        implode("\n", $routeOut),
        'admin.inventory.assets.missing-recover-scan'
    ),
    'Backend QR recovery route tetap aktif'
);

echo "\n";

if ($fails > 0) {
    echo "HASIL: FAIL ({$fails} masalah)\n";
    exit(1);
}

echo "HASIL: PASS\n\n";
echo "QA:\n";
echo "1. Buka asset MISSING.\n";
echo "2. Tidak boleh ada textbox barcode atau tombol submit recovery.\n";
echo "3. UI harus menampilkan WAITING FOR SCAN.\n";
echo "4. Scan QR fisik tanpa klik apa pun.\n";
echo "5. QR salah => backend menolak, status tetap MISSING.\n";
echo "6. QR benar => status AVAILABLE + movement missing_recovered.\n";
