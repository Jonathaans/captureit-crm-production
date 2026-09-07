<?php
declare(strict_types=1);

echo "CHECK MISSING ASSET RECOVERY V1\n";
echo "===============================\n\n";

$root = dirname(__DIR__);
chdir($root);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$fails = 0;

function checkIt(bool $ok, string $label): void
{
    global $fails;

    echo ($ok ? '[OK]   ' : '[FAIL] ').$label."\n";

    if (! $ok) {
        $fails++;
    }
}

$controller = $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryAssetController.php';
$routes     = $root.'/routes/web.php';

$c = is_file($controller) ? (string) file_get_contents($controller) : '';
$r = is_file($routes) ? (string) file_get_contents($routes) : '';

checkIt(
    str_contains($c, 'MISSING_ASSET_RECOVERY_V1'),
    'Controller recovery terpasang'
);

checkIt(
    str_contains($c, "!== 'missing'"),
    'Recovery hanya untuk status MISSING'
);

checkIt(
    str_contains($c, 'delivery_order_inventory_allocations'),
    'Active allocation guard terpasang'
);

checkIt(
    str_contains($c, "'movement_type' =>")
    && str_contains($c, "'missing_recovered'"),
    'Movement missing_recovered dibuat'
);

checkIt(
    str_contains($c, "'from_status' =>")
    && str_contains($c, "'to_status' =>"),
    'Movement menyimpan from/to status'
);

checkIt(
    str_contains($r, 'admin.inventory.assets.recover'),
    'Route source terpasang'
);

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' route:list --name=admin.inventory.assets.recover 2>&1',
    $routeOut,
    $routeCode
);

checkIt(
    $routeCode === 0
    && str_contains(
        implode("\n", $routeOut),
        'admin.inventory.assets.recover'
    ),
    'Laravel route aktif'
);

$bladeFound = false;
$viewBase = $root.'/packages/Webkul/Admin/src/Resources/views/inventory';

if (is_dir($viewBase)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $viewBase,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($it as $file) {
        if (
            $file->isFile()
            && str_ends_with(
                strtolower($file->getFilename()),
                '.blade.php'
            )
        ) {
            $content =
                (string) @file_get_contents(
                    $file->getPathname()
                );

            if (
                str_contains(
                    $content,
                    'MISSING_ASSET_RECOVERY_V1_UI'
                )
            ) {
                $bladeFound = true;
                break;
            }
        }
    }
}

checkIt(
    $bladeFound,
    'UI Confirm Found terpasang'
);

$missingCount =
    DB::table('inventory_assets')
        ->where(
            'status',
            'missing'
        )
        ->count();

echo "\nCurrent MISSING assets: {$missingCount}\n";

$recent =
    DB::table('inventory_stock_movements')
        ->where(
            'movement_type',
            'missing_recovered'
        )
        ->orderByDesc('id')
        ->limit(5)
        ->get([
            'id',
            'inventory_asset_id',
            'from_status',
            'to_status',
            'reference_number',
            'performed_by',
            'notes',
            'occurred_at',
        ]);

if ($recent->isNotEmpty()) {
    echo "\nRecent recovery movements:\n";

    foreach ($recent as $row) {
        echo "#{$row->id} asset={$row->inventory_asset_id} "
            ."{$row->from_status} -> {$row->to_status} "
            ."ref={$row->reference_number} "
            ."at={$row->occurred_at}\n";
    }
}

echo "\n";

if ($fails > 0) {
    echo "HASIL: FAIL ({$fails} masalah)\n";
    exit(1);
}

echo "HASIL: PASS\n\n";
echo "QA:\n";
echo "1. Gunakan satu asset yang benar-benar berstatus MISSING.\n";
echo "2. Buka detail asset tersebut.\n";
echo "3. Card Missing Asset Recovery harus muncul.\n";
echo "4. Pilih AVAILABLE atau DAMAGED + isi catatan.\n";
echo "5. Confirm Found.\n";
echo "6. Status asset berubah dan movement RECOVERED tercatat.\n";
echo "7. Asset AVAILABLE kembali masuk perhitungan Ready tanpa Stock Opname seluruh gudang.\n";
