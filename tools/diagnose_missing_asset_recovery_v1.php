<?php
declare(strict_types=1);

echo "MISSING ASSET RECOVERY DIAGNOSTIC V1\n";
echo "====================================\n\n";

$root = dirname(__DIR__);
chdir($root);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function sec(string $t): void { echo "\n=== {$t} ===\n"; }

sec('ROUTES');
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' route:list --json 2>&1', $out, $code);
if ($code === 0) {
    $routes = json_decode(implode("\n",$out), true);
    foreach (($routes ?: []) as $r) {
        $h = strtolower(($r['uri']??'').' '.($r['name']??'').' '.($r['action']??''));
        if (str_contains($h,'inventory') || str_contains($h,'stock-opname') || str_contains($h,'asset') || str_contains($h,'warehouse')) {
            echo ($r['method']??'').' | '.($r['uri']??'').' | '.($r['name']??'').' | '.($r['action']??'')."\n";
        }
    }
} else {
    echo implode("\n",$out)."\n";
}

sec('CANDIDATE TABLES');
$tables = collect(DB::select('SHOW TABLES'))
    ->map(fn($r)=>array_values((array)$r)[0])
    ->filter(function($t){
        $t = strtolower((string)$t);
        foreach (['inventory','asset','warehouse','movement','allocation','stock','opname'] as $n) {
            if (str_contains($t,$n)) return true;
        }
        return false;
    })->values()->all();

foreach ($tables as $t) echo "- {$t}\n";

sec('SCHEMAS + STATUS COUNTS');
foreach ($tables as $t) {
    echo "\n[{$t}]\n";
    try {
        $cols = Schema::getColumnListing($t);
        echo implode(', ', $cols)."\n";
        echo 'rows='.DB::table($t)->count()."\n";
        if (in_array('status',$cols,true)) {
            foreach (DB::table($t)->selectRaw('status, COUNT(*) total')->groupBy('status')->orderBy('status')->get() as $r) {
                echo '  '.var_export($r->status,true).' => '.$r->total."\n";
            }
        }
    } catch (Throwable $e) {
        echo 'ERROR: '.$e->getMessage()."\n";
    }
}

sec('FOREIGN KEYS');
if ($tables) {
    $ph = implode(',', array_fill(0,count($tables),'?'));
    $sql = "SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE CONSTRAINT_SCHEMA=DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL
              AND (TABLE_NAME IN ({$ph}) OR REFERENCED_TABLE_NAME IN ({$ph}))
            ORDER BY TABLE_NAME,COLUMN_NAME";
    foreach (DB::select($sql,array_merge($tables,$tables)) as $r) {
        echo "{$r->TABLE_NAME}.{$r->COLUMN_NAME} -> {$r->REFERENCED_TABLE_NAME}.{$r->REFERENCED_COLUMN_NAME} [{$r->CONSTRAINT_NAME}]\n";
    }
}

sec('SOURCE HITS');
$base = $root.'/packages/Webkul/Admin/src';
$needles = ['MISSING','AVAILABLE','DAMAGED','MAINTENANCE','StockOpname','stock-opname','movement','Missing Assets'];
$hits = 0;
if (is_dir($base)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $name = strtolower($f->getFilename());
        if (!str_ends_with($name,'.php') && !str_ends_with($name,'.blade.php')) continue;
        $lines = @file($f->getPathname(), FILE_IGNORE_NEW_LINES);
        if (!$lines) continue;
        foreach ($lines as $i=>$line) {
            foreach ($needles as $n) {
                if (stripos($line,$n)!==false) {
                    echo str_replace('\\','/',$f->getPathname()).':'.($i+1).': '.trim($line)."\n";
                    $hits++;
                    if ($hits>=180) break 3;
                    break;
                }
            }
        }
    }
}
if ($hits===0) echo "(tidak ada hit)\n";

sec('READ-ONLY COMPLETE');
echo "Tidak ada file/database yang diubah.\n";
echo "Kirim seluruh output ini.\n";
