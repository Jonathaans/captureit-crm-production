<?php
declare(strict_types=1);

echo "CHECK MISSING ASSET SCANNER GLOBAL CAPTURE V2.3\n";
echo "================================================\n\n";

$root = dirname(__DIR__);
chdir($root);

$fails = 0;

function ckV23(bool $ok, string $label): void
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

        $candidate =
            (string) @file_get_contents(
                $file->getPathname()
            );

        if (
            str_contains(
                $candidate,
                'MISSING_ASSET_SCANNER_GLOBAL_CAPTURE_V2_3'
            )
        ) {
            $view =
                $file->getPathname();

            $text =
                $candidate;

            break;
        }
    }
}

ckV23(
    $view !== null,
    'View scanner V2.3 ditemukan'
);

ckV23(
    str_contains(
        $text,
        'scannerV23Ready'
    ),
    'V2.3 ready marker tersedia'
);

ckV23(
    str_contains(
        $text,
        "document.addEventListener(\n                    'keydown'"
    )
    || str_contains(
        $text,
        "document.addEventListener(\r\n                    'keydown'"
    ),
    'Global document keydown listener terpasang'
);

ckV23(
    str_contains(
        $text,
        'event.stopImmediatePropagation()'
    ),
    'Scanner event diprioritaskan dari control lain'
);

ckV23(
    ! str_contains(
        $text,
        'crm-missing-scan-capture-'
    ),
    'Tidak lagi bergantung hidden focus input'
);

ckV23(
    str_contains(
        $text,
        "event.key === 'Enter'"
    )
    && str_contains(
        $text,
        "event.key === 'Tab'"
    ),
    'Enter / Tab suffix didukung'
);

ckV23(
    str_contains(
        $text,
        'VERIFYING SCAN...'
    ),
    'Verifying feedback tersedia'
);

ckV23(
    str_contains(
        $text,
        'form.submit()'
    ),
    'Auto submit tersedia'
);

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' route:list --name=admin.inventory.assets.missing-recover-scan 2>&1',
    $routeOut,
    $routeCode
);

ckV23(
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
echo "TEST CEPAT:\n";
echo "1. Ctrl+Shift+R halaman asset MISSING.\n";
echo "2. Tekan satu huruf keyboard: WAITING harus berubah SCANNING.\n";
echo "3. Tunggu: slow single-key akan ditolak lalu kembali WAITING.\n";
echo "4. Scan QR fisik tanpa klik apa pun.\n";
echo "5. QR benar => AVAILABLE + movement missing_recovered.\n";
