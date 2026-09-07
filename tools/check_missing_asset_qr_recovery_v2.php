<?php
declare(strict_types=1);

echo "CHECK MISSING ASSET QR RECOVERY V2\n";
echo "==================================\n\n";

$root = dirname(__DIR__);
chdir($root);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$fails = 0;

function checkV2(bool $ok, string $label): void
{
    global $fails;

    echo ($ok ? '[OK]   ' : '[FAIL] ').$label."\n";

    if (! $ok) {
        $fails++;
    }
}

$controller =
    $root
    .'/packages/Webkul/Admin/src/Http/Controllers/Inventory/MissingAssetQrRecoveryController.php';

$routes = $root.'/routes/web.php';

$c = is_file($controller)
    ? (string) file_get_contents($controller)
    : '';

$r = is_file($routes)
    ? (string) file_get_contents($routes)
    : '';

checkV2(
    str_contains($c, 'MISSING_ASSET_QR_RECOVERY_V2'),
    'QR recovery controller tersedia'
);

if (is_file($controller)) {
    exec(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($controller).' 2>&1',
        $lintOut,
        $lintCode
    );

    checkV2(
        $lintCode === 0,
        'Controller PHP lint PASS'
    );
} else {
    checkV2(false, 'Controller PHP lint PASS');
}

checkV2(
    str_contains($c, "!== 'missing'"),
    'Hanya asset MISSING yang dapat direcover'
);

checkV2(
    str_contains($c, 'barcode_value')
    && str_contains($c, 'asset_code')
    && str_contains($c, 'serial_number'),
    'QR diverifikasi terhadap identitas asset'
);

checkV2(
    str_contains($c, "'status'     => 'available'"),
    'QR benar mengubah asset ke AVAILABLE'
);

checkV2(
    str_contains($c, "'missing_recovered'"),
    'Movement missing_recovered dibuat'
);

checkV2(
    str_contains($c, "'delivery_order_missing_recovery'"),
    'Movement menyimpan reference Surat Jalan asal'
);

checkV2(
    str_contains(
        $r,
        'admin.inventory.assets.missing-recover-scan'
    ),
    'Route QR recovery source terpasang'
);

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' route:list --name=admin.inventory.assets.missing-recover-scan 2>&1',
    $routeOut,
    $routeCode
);

checkV2(
    $routeCode === 0
    && str_contains(
        implode("\n", $routeOut),
        'admin.inventory.assets.missing-recover-scan'
    ),
    'Laravel route QR recovery aktif'
);

checkV2(
    ! str_contains(
        $r,
        'admin.delivery-orders.return.missing-found'
    ),
    'Old Found-from-Return route sudah tidak aktif'
);

$viewBase =
    $root
    .'/packages/Webkul/Admin/src/Resources/views';

$assetUi = false;
$returnSummary = false;
$oldReturnUi = false;

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
                'MISSING_ASSET_QR_RECOVERY_V2_UI'
            )
        ) {
            $assetUi = true;
        }

        if (
            str_contains(
                $text,
                'RETURN_RECEIVED_MISSING_SUMMARY_V2'
            )
        ) {
            $returnSummary = true;
        }

        if (
            str_contains(
                $text,
                'RETURN_MISSING_FOUND_V1_1_UI'
            )
            || str_contains(
                $text,
                'RETURN_MISSING_FOUND_V1_UI'
            )
        ) {
            $oldReturnUi = true;
        }
    }
}

checkV2(
    $assetUi,
    'Asset detail mempunyai Scan QR Recovery UI'
);

checkV2(
    $returnSummary,
    'Return Warehouse mempunyai RECEIVED / MISSING summary'
);

checkV2(
    ! $oldReturnUi,
    'Old Found / Check In UI di Return sudah tidak ada'
);

$missingAssets =
    DB::table('inventory_assets')
        ->where('status', 'missing')
        ->get([
            'id',
            'asset_code',
            'barcode_value',
            'serial_number',
        ]);

echo "\nCurrent MISSING assets: ".$missingAssets->count()."\n";

foreach ($missingAssets as $asset) {
    $allocation = DB::table(
        'delivery_order_inventory_allocations'
    )
        ->where('inventory_asset_id', $asset->id)
        ->where('return_condition', 'missing')
        ->orderByDesc('id')
        ->first();

    $reference = null;

    if ($allocation) {
        $do = DB::table('delivery_orders')
            ->where('id', $allocation->delivery_order_id)
            ->first();

        if ($do) {
            $arr = (array) $do;

            foreach ([
                'delivery_order_number',
                'do_number',
                'sj_number',
                'reference_number',
                'number',
            ] as $col) {
                if (
                    isset($arr[$col])
                    && trim((string) $arr[$col]) !== ''
                ) {
                    $reference = (string) $arr[$col];
                    break;
                }
            }
        }

        if (! $reference) {
            $reference = 'DO-'.$allocation->delivery_order_id;
        }
    }

    echo '- '
        .$asset->asset_code
        .' | missing_from='
        .($reference ?: 'no-return-reference')
        .' | allocation='
        .($allocation->id ?? '-')
        ."\n";
}

$recent =
    DB::table('inventory_stock_movements')
        ->where('movement_type', 'missing_recovered')
        ->orderByDesc('id')
        ->limit(5)
        ->get([
            'id',
            'inventory_asset_id',
            'from_status',
            'to_status',
            'reference_type',
            'reference_number',
            'occurred_at',
        ]);

if ($recent->isNotEmpty()) {
    echo "\nRecent missing_recovered movements:\n";

    foreach ($recent as $row) {
        echo '#'
            .$row->id
            .' asset='
            .$row->inventory_asset_id
            .' '
            .$row->from_status
            .' -> '
            .$row->to_status
            .' ref='
            .($row->reference_number ?: '-')
            .' at='
            .$row->occurred_at
            ."\n";
    }
}

echo "\n";

if ($fails > 0) {
    echo "HASIL: FAIL ({$fails} masalah)\n";
    exit(1);
}

echo "HASIL: PASS\n\n";
echo "QA FLOW:\n";
echo "1. Finalize Return dengan satu serialized asset MISSING.\n";
echo "2. Return page tetap final dan menampilkan RECEIVED + MISSING.\n";
echo "3. Missing asset tetap muncul di Inventory Dashboard / Alerts.\n";
echo "4. Open Asset yang MISSING.\n";
echo "5. Scan QR asset yang benar.\n";
echo "6. QR salah => status tetap MISSING.\n";
echo "7. QR benar => status AVAILABLE.\n";
echo "8. Historical return tetap MISSING pada Surat Jalan asal.\n";
echo "9. Movement missing_recovered menyimpan reference Surat Jalan asal.\n";
