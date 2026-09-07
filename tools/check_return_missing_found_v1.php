<?php
declare(strict_types=1);

echo "CHECK RETURN MISSING -> FOUND V1\n";
echo "================================\n\n";

$root = dirname(__DIR__);
chdir($root);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$fails = 0;

function ck(bool $ok, string $label): void
{
    global $fails;

    echo ($ok ? '[OK]   ' : '[FAIL] ').$label."\n";

    if (! $ok) {
        $fails++;
    }
}

$controller =
    $root
    .'/packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/MissingAssetReturnRecoveryController.php';

$routes =
    $root
    .'/routes/web.php';

$c =
    is_file($controller)
        ? (string) file_get_contents($controller)
        : '';

$r =
    is_file($routes)
        ? (string) file_get_contents($routes)
        : '';

ck(
    str_contains(
        $c,
        'RETURN_MISSING_FOUND_V1'
    ),
    'Controller recovery tersedia'
);

ck(
    str_contains(
        $c,
        "return_condition"
    )
    && str_contains(
        $c,
        "!== 'missing'"
    ),
    'Hanya row MISSING yang dapat dikoreksi'
);

ck(
    str_contains(
        $c,
        "'checked_in'"
    )
    && str_contains(
        $c,
        "'returned_quantity'"
    ),
    'Found melakukan physical check-in'
);

ck(
    str_contains(
        $c,
        "'missing_recovered'"
    ),
    'Movement missing_recovered dibuat'
);

ck(
    str_contains(
        $c,
        "'available'"
    )
    && str_contains(
        $c,
        "'damaged'"
    ),
    'GOOD/DAMAGED mapping tersedia'
);

ck(
    str_contains(
        $r,
        'admin.delivery-orders.return.missing-found'
    ),
    'Route source terpasang'
);

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' route:list --name=admin.delivery-orders.return.missing-found 2>&1',
    $routeOut,
    $routeCode
);

ck(
    $routeCode === 0
    && str_contains(
        implode("\n", $routeOut),
        'admin.delivery-orders.return.missing-found'
    ),
    'Laravel route aktif'
);

$uiFound = false;
$viewBase =
    $root
    .'/packages/Webkul/Admin/src/Resources/views';

if (is_dir($viewBase)) {
    $it =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $viewBase,
                FilesystemIterator::SKIP_DOTS
            )
        );

    foreach ($it as $file) {
        if (
            ! $file->isFile()
            || ! str_ends_with(
                strtolower(
                    $file->getFilename()
                ),
                '.blade.php'
            )
        ) {
            continue;
        }

        $content =
            (string) @file_get_contents(
                $file->getPathname()
            );

        if (
            str_contains(
                $content,
                'RETURN_MISSING_FOUND_V1_UI'
            )
        ) {
            $uiFound = true;
            break;
        }
    }
}

ck(
    $uiFound,
    'UI Missing Return Exception terpasang'
);

$current =
    DB::table(
        'delivery_order_inventory_allocations as a'
    )
        ->join(
            'inventory_assets as ia',
            'ia.id',
            '=',
            'a.inventory_asset_id'
        )
        ->where(
            'a.return_condition',
            'missing'
        )
        ->where(
            'ia.status',
            'missing'
        )
        ->count();

echo "\nCurrent unresolved MISSING return: {$current}\n";

$recent =
    DB::table(
        'inventory_stock_movements'
    )
        ->where(
            'movement_type',
            'missing_recovered'
        )
        ->orderByDesc(
            'id'
        )
        ->limit(
            5
        )
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

if (
    $recent->isNotEmpty()
) {
    echo "\nRecent FOUND movements:\n";

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
            .$row->reference_number
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
echo "QA:\n";
echo "1. Buka Return Warehouse yang punya row MISSING.\n";
echo "2. Section Missing Return Exception harus tampil.\n";
echo "3. Pilih GOOD atau DAMAGED, isi catatan.\n";
echo "4. Klik Found / Check In.\n";
echo "5. GOOD => asset AVAILABLE; DAMAGED => asset DAMAGED.\n";
echo "6. Row tidak lagi tampil sebagai unresolved MISSING.\n";
echo "7. Movement missing_recovered harus tercatat.\n";
