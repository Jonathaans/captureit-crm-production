<?php
declare(strict_types=1);

echo "MANAGE ALLOCATION SCANNER EXACT DIAGNOSTIC V2\n";
echo "=============================================\n\n";

$root = dirname(__DIR__);
chdir($root);

function sec(string $title): void
{
    echo "\n=== {$title} ===\n";
}

function norm(string $path): string
{
    return str_replace('\\', '/', $path);
}

function showFile(string $file, int $maxLines = 500): void
{
    if (! is_file($file)) {
        echo "(file tidak ditemukan: ".norm($file).")\n";
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES);

    if (! is_array($lines)) {
        echo "(gagal membaca file)\n";
        return;
    }

    $count = min(count($lines), $maxLines);

    for ($i = 0; $i < $count; $i++) {
        echo sprintf("%4d | %s\n", $i + 1, $lines[$i]);
    }

    if (count($lines) > $maxLines) {
        echo "... dipotong pada {$maxLines} baris\n";
    }
}

function grepFile(string $file, array $needles, int $before = 8, int $after = 22): void
{
    if (! is_file($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES);

    if (! is_array($lines)) {
        return;
    }

    $printedRanges = [];

    foreach ($lines as $index => $line) {
        foreach ($needles as $needle) {
            if (stripos($line, $needle) === false) {
                continue;
            }

            $start = max(0, $index - $before);
            $end = min(count($lines) - 1, $index + $after);
            $key = $start.':'.$end;

            if (isset($printedRanges[$key])) {
                break;
            }

            $printedRanges[$key] = true;

            echo "\n".norm($file).":".($index + 1)." [{$needle}]\n";

            for ($i = $start; $i <= $end; $i++) {
                $prefix = $i === $index ? '>>' : '  ';
                echo sprintf(
                    "%s %4d | %s\n",
                    $prefix,
                    $i + 1,
                    $lines[$i]
                );
            }

            break;
        }
    }
}

function findFiles(string $base, callable $accept): array
{
    $result = [];

    if (! is_dir($base)) {
        return $result;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $base,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($it as $file) {
        if ($file->isFile() && $accept($file)) {
            $result[] = $file->getPathname();
        }
    }

    sort($result);

    return $result;
}

$inventoryController =
    $root.'/packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/DeliveryOrderInventoryController.php';

$pickingController =
    $root.'/packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/DeliveryOrderPickingController.php';

$returnController =
    $root.'/packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/DeliveryOrderReturnController.php';

sec('EXACT ROUTE DEFINITIONS');

$routeFiles = findFiles(
    $root.'/routes',
    fn (SplFileInfo $file) =>
        strtolower($file->getExtension()) === 'php'
);

foreach ($routeFiles as $file) {
    grepFile(
        $file,
        [
            'inventory-allocation.edit',
            'inventory-allocation.scan',
            'inventory-allocation.update',
            'inventory-allocation.release',
            'DeliveryOrderInventoryController',
            'DeliveryOrderPickingController',
        ],
        12,
        28
    );
}

sec('DELIVERY ORDER INVENTORY CONTROLLER - RELEVANT METHODS');

grepFile(
    $inventoryController,
    [
        'function edit',
        'function scan',
        'function update',
        'function release',
        'return view',
        'barcode',
        'asset_code',
        'scan_code',
        'request->',
    ],
    18,
    45
);

sec('DELIVERY ORDER PICKING CONTROLLER - RELEVANT METHODS');

grepFile(
    $pickingController,
    [
        'function',
        'barcode',
        'asset_code',
        'scan',
        'request->',
        'return view',
    ],
    15,
    40
);

sec('DELIVERY ORDER RETURN CONTROLLER - SCAN PATTERN');

grepFile(
    $returnController,
    [
        'function',
        'barcode',
        'asset_code',
        'scan',
        'check-in',
        'request->',
        'return view',
    ],
    15,
    40
);

sec('VIEW REFERENCES FROM CONTROLLERS');

foreach ([
    $inventoryController,
    $pickingController,
    $returnController,
] as $file) {
    if (! is_file($file)) {
        continue;
    }

    $text = (string) file_get_contents($file);

    preg_match_all(
        "/(?:view|route)\\(\\s*['\"]([^'\"]+)['\"]/",
        $text,
        $matches
    );

    echo "\n".norm($file)."\n";

    foreach (array_unique($matches[1] ?? []) as $name) {
        echo " - {$name}\n";
    }
}

sec('BLADE FILES CONTAINING MANAGE ALLOCATION / INVENTORY-ALLOCATION ROUTES');

$viewFiles = findFiles(
    $root.'/packages/Webkul/Admin/src/Resources/views',
    fn (SplFileInfo $file) =>
        str_ends_with(
            strtolower($file->getFilename()),
            '.blade.php'
        )
);

$interestingViews = [];

foreach ($viewFiles as $file) {
    $text = @file_get_contents($file);

    if (! is_string($text)) {
        continue;
    }

    $score = 0;

    foreach ([
        'Manage Allocation',
        'inventory-allocation.scan',
        'inventory-allocation.update',
        'inventory-allocation.release',
        'Scan Allocation',
        'barcode',
        'scanner',
    ] as $needle) {
        if (stripos($text, $needle) !== false) {
            $score++;
        }
    }

    if ($score > 0) {
        $interestingViews[$file] = $score;
    }
}

arsort($interestingViews);

foreach ($interestingViews as $file => $score) {
    echo norm($file)." score={$score}\n";
}

sec('EXACT SCANNER BLADE SNIPPETS');

$shown = 0;

foreach ($interestingViews as $file => $score) {
    grepFile(
        $file,
        [
            'inventory-allocation.scan',
            'Manage Allocation',
            'Scan Allocation',
            'barcode',
            'scanner',
            'keydown',
            'keyup',
            'keypress',
            '@submit',
            'fetch(',
            'axios',
            'focus(',
            'autofocus',
            'input',
        ],
        18,
        55
    );

    $shown++;

    if ($shown >= 8) {
        break;
    }
}

sec('CURRENT MISSING RECOVERY VIEW - FOR PARITY REPLACEMENT');

$currentView =
    $root.'/packages/Webkul/Admin/src/Resources/views/inventory/assets/edit.blade.php';

grepFile(
    $currentView,
    [
        'MISSING_ASSET_SCANNER_GLOBAL_CAPTURE_V2_3',
        'Missing Asset Recovery',
        'missing-recover-scan',
    ],
    12,
    80
);

sec('DONE');

echo "READ-ONLY diagnostic selesai.\n";
echo "Tidak ada file/database yang diubah.\n";
echo "Kirim seluruh output ini.\n";
echo "Output V2 ini sengaja menargetkan EXACT route + controller + Blade scanner Manage Allocation.\n";
