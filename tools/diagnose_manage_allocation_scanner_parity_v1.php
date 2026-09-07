<?php
declare(strict_types=1);

echo "MANAGE ALLOCATION SCANNER PARITY DIAGNOSTIC V1\n";
echo "==============================================\n\n";

$root = dirname(__DIR__);
chdir($root);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function out(string $text = ''): void
{
    echo $text.PHP_EOL;
}

function section(string $title): void
{
    out();
    out("=== {$title} ===");
}

function normalize(string $path): string
{
    return str_replace('\\', '/', $path);
}

function snippet(string $file, int $line, int $before = 12, int $after = 22): void
{
    $lines = @file($file, FILE_IGNORE_NEW_LINES);

    if (! is_array($lines)) {
        out("(gagal membaca {$file})");
        return;
    }

    $start = max(1, $line - $before);
    $end = min(count($lines), $line + $after);

    for ($i = $start; $i <= $end; $i++) {
        $prefix = $i === $line ? '>>' : '  ';
        out(sprintf(
            "%s %4d | %s",
            $prefix,
            $i,
            $lines[$i - 1]
        ));
    }
}

function searchFiles(string $base, array $needles, int $limit = 120): array
{
    $hits = [];

    if (! is_dir($base)) {
        return $hits;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $base,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($it as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $name = strtolower($file->getFilename());

        if (
            ! str_ends_with($name, '.php')
            && ! str_ends_with($name, '.blade.php')
            && ! str_ends_with($name, '.js')
        ) {
            continue;
        }

        $lines = @file($file->getPathname(), FILE_IGNORE_NEW_LINES);

        if (! is_array($lines)) {
            continue;
        }

        foreach ($lines as $index => $line) {
            foreach ($needles as $needle) {
                if (stripos($line, $needle) !== false) {
                    $hits[] = [
                        'file' => $file->getPathname(),
                        'line' => $index + 1,
                        'text' => trim($line),
                        'needle' => $needle,
                    ];

                    if (count($hits) >= $limit) {
                        return $hits;
                    }

                    break;
                }
            }
        }
    }

    return $hits;
}

section('ROUTES: ALLOCATION / SCAN / RETURN / INVENTORY');

exec(
    escapeshellarg(PHP_BINARY).' '
    .escapeshellarg($root.'/artisan')
    .' route:list --json 2>&1',
    $routeOut,
    $routeCode
);

if ($routeCode === 0) {
    $routes = json_decode(implode("\n", $routeOut), true);

    foreach (($routes ?: []) as $route) {
        $haystack = strtolower(
            ($route['uri'] ?? '').' '
            .($route['name'] ?? '').' '
            .($route['action'] ?? '')
        );

        if (
            str_contains($haystack, 'allocation')
            || str_contains($haystack, 'scan')
            || str_contains($haystack, 'delivery-order')
            || str_contains($haystack, 'inventory/assets')
        ) {
            out(
                ($route['method'] ?? '')
                .' | '
                .($route['uri'] ?? '')
                .' | '
                .($route['name'] ?? '')
                .' | '
                .($route['action'] ?? '')
            );
        }
    }
} else {
    out(implode("\n", $routeOut));
}

section('CANDIDATE FILES: MANAGE ALLOCATION');

$allocationHits = searchFiles(
    $root.'/packages/Webkul/Admin/src',
    [
        'Manage Allocation',
        'manage allocation',
        'manage-allocation',
        'allocation',
        'scanner',
        'scan barcode',
        'scan qr',
        'barcode',
        'keydown',
        'keyup',
        'keypress',
        'input event',
        'focus()',
        'requestSubmit',
    ],
    160
);

$ranked = [];

foreach ($allocationHits as $hit) {
    $file = $hit['file'];

    if (! isset($ranked[$file])) {
        $ranked[$file] = 0;
    }

    $score = 1;

    if (stripos($hit['text'], 'manage allocation') !== false) {
        $score += 10;
    }

    if (
        stripos($hit['text'], 'scan') !== false
        || stripos($hit['text'], 'barcode') !== false
        || stripos($hit['text'], 'keydown') !== false
    ) {
        $score += 4;
    }

    if (stripos($file, 'delivery') !== false) {
        $score += 2;
    }

    $ranked[$file] += $score;
}

arsort($ranked);

foreach (array_slice($ranked, 0, 15, true) as $file => $score) {
    out(normalize($file)." score={$score}");
}

section('TOP MANAGE ALLOCATION SCANNER SNIPPETS');

$shown = 0;

foreach ($allocationHits as $hit) {
    $text = strtolower($hit['text']);

    if (
        str_contains($text, 'scan')
        || str_contains($text, 'barcode')
        || str_contains($text, 'keydown')
        || str_contains($text, 'keyup')
        || str_contains($text, 'keypress')
        || str_contains($text, 'focus')
        || str_contains($text, 'allocation')
    ) {
        out();
        out(
            normalize($hit['file'])
            .':'
            .$hit['line']
            .'  ['.$hit['needle'].']'
        );

        snippet(
            $hit['file'],
            $hit['line'],
            10,
            20
        );

        $shown++;

        if ($shown >= 18) {
            break;
        }
    }
}

section('CURRENT MISSING ASSET RECOVERY FILES');

$currentHits = searchFiles(
    $root.'/packages/Webkul/Admin/src',
    [
        'MISSING_ASSET_QR_RECOVERY_V2',
        'MISSING_ASSET_SCANNER_GLOBAL_CAPTURE_V2_3',
        'missing-recover-scan',
        'missing_recovered',
    ],
    80
);

foreach ($currentHits as $hit) {
    out(
        normalize($hit['file'])
        .':'
        .$hit['line']
        .': '
        .$hit['text']
    );
}

section('CURRENT MISSING ASSET RECOVERY SNIPPETS');

$shown = 0;

foreach ($currentHits as $hit) {
    out();
    out(
        normalize($hit['file'])
        .':'
        .$hit['line']
    );

    snippet(
        $hit['file'],
        $hit['line'],
        8,
        18
    );

    $shown++;

    if ($shown >= 8) {
        break;
    }
}

section('RECOMMENDED NEXT STEP');

out('Diagnostic READ-ONLY selesai.');
out('Tidak ada file atau database yang diubah.');
out('Kirim seluruh output ini.');
out('Patch berikutnya akan MENYALIN POLA SCANNER manage allocation yang sudah terbukti bekerja,');
out('bukan menambahkan listener scanner baru lagi.');
