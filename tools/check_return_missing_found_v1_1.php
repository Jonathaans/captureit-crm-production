<?php
declare(strict_types=1);

echo "CHECK RETURN MISSING -> FOUND V1.1\n";
echo "==================================\n\n";

$root = dirname(__DIR__);
chdir($root);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$fails = 0;

function ckV11(bool $ok, string $label): void
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
    $root.'/routes/web.php';

$c = is_file($controller)
    ? (string) file_get_contents($controller)
    : '';

$r = is_file($routes)
    ? (string) file_get_contents($routes)
    : '';

ckV11(
    str_contains($c, 'RETURN_MISSING_FOUND_V1_1'),
    'Controller V1.1 tersedia'
);

if (is_file($controller)) {
    exec(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($controller).' 2>&1',
        $lintOut,
        $lintCode
    );

    ckV11(
        $lintCode === 0,
        'Controller PHP lint PASS'
    );
} else {
    ckV11(false, 'Controller PHP lint PASS');
}

ckV11(
    str_contains($c, "!== 'missing'"),
    'Hanya return MISSING yang dapat dipulihkan'
);

ckV11(
    str_contains($c, "'checked_in'"),
    'Found melakukan physical check-in'
);

ckV11(
    str_contains($c, "'missing_recovered'"),
    'Movement missing_recovered dibuat'
);

ckV11(
    str_contains($c, "'available'")
    && str_contains($c, "'damaged'"),
    'GOOD / DAMAGED mapping tersedia'
);

ckV11(
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

ckV11(
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

        $content =
            (string) @file_get_contents(
                $file->getPathname()
            );

        if (
            str_contains(
                $content,
                'RETURN_MISSING_FOUND_V1_1_UI'
            )
        ) {
            $uiFound = true;
            break;
        }
    }
}

ckV11(
    $uiFound,
    'UI Missing Return Exception terpasang'
);

$current = DB::table(
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

$recent = DB::table('inventory_stock_movements')
    ->where('movement_type', 'missing_recovered')
    ->orderByDesc('id')
    ->limit(5)
    ->get([
        'id',
        'inventory_asset_id',
        'from_status',
        'to_status',
        'reference_number',
        'occurred_at',
    ]);

if ($recent->isNotEmpty()) {
    echo "\nRecent recovered movements:\n";

    foreach ($recent as $row) {
        echo "#{$row->id} asset={$row->inventory_asset_id} "
            ."{$row->from_status} -> {$row->to_status} "
            ."ref={$row->reference_number} at={$row->occurred_at}\n";
    }
}

echo "\n";

if ($fails > 0) {
    echo "HASIL: FAIL ({$fails} masalah)\n";
    exit(1);
}

echo "HASIL: PASS\n\n";
echo "QA:\n";
echo "1. Buka /admin/delivery-orders/<id>/return yang punya MISSING.\n";
echo "2. Section Missing Return Exception harus tampil.\n";
echo "3. Pilih GOOD atau DAMAGED + isi catatan.\n";
echo "4. Klik Found / Check In.\n";
echo "5. GOOD => AVAILABLE; DAMAGED => DAMAGED.\n";
echo "6. Movement missing_recovered tercatat.\n";
