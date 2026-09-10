<?php

declare(strict_types=1);

/**
 * Launcher V3: load Composer before running the existing, root-fixed V1 checker.
 * This is not a replacement checker or a source-code patch.
 * It does not suppress failures or run migrations, seeders, or cleanup commands.
 * The existing checker's arguments, checks, output and exit status are retained.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Tool ini hanya untuk PHP CLI.');
}

$crmV3Arguments = $_SERVER['argv'] ?? [];
$crmV3Usage = 'php tools/check_crm_targeted_performance_bootstrap_v3.php '.
    '--database=captureit_crm_performance';

if (count($crmV3Arguments) === 2 && $crmV3Arguments[1] === '--help') {
    echo $crmV3Usage.PHP_EOL;
    echo 'Memuat Composer, lalu menjalankan checker V1 yang sudah ada.'.PHP_EOL;
    echo 'Tidak menimpa file atau menjalankan migrasi.'.PHP_EOL;
    exit(0);
}

// Keep this launcher scoped to the exact test database used in this project.
if (
    count($crmV3Arguments) !== 2
    || $crmV3Arguments[1] !== '--database=captureit_crm_performance'
) {
    fwrite(STDERR, '[STOP] Gunakan argumen database testing yang tepat:'.PHP_EOL);
    fwrite(STDERR, $crmV3Usage.PHP_EOL);
    exit(1);
}

$crmV3Root = dirname(__DIR__);
$crmV3Autoload = $crmV3Root.'/vendor/autoload.php';
$crmV3Checker = __DIR__.'/check_crm_targeted_performance_optimization_v1.php';

echo 'CRM CHECKER BOOTSTRAP LAUNCHER V3'.PHP_EOL;
echo 'Root: '.$crmV3Root.PHP_EOL.PHP_EOL;

try {
    foreach ([
        $crmV3Root.'/artisan',
        $crmV3Root.'/composer.json',
        $crmV3Root.'/bootstrap/app.php',
        $crmV3Autoload,
        $crmV3Checker,
    ] as $crmV3RequiredPath) {
        if (! is_file($crmV3RequiredPath) || ! is_readable($crmV3RequiredPath)) {
            throw new RuntimeException('File tidak tersedia/dapat dibaca: '.$crmV3RequiredPath);
        }
    }

    // Load the autoloader only; the original checker still owns Laravel bootstrapping.
    require_once $crmV3Autoload;

    if (! class_exists('Illuminate\\Foundation\\Application')) {
        throw new RuntimeException(
            'Composer sudah dimuat, tetapi kelas Illuminate\\Foundation\\Application '.
            'tetap tidak ditemukan. Kirim output ini dan file checker lengkap.'
        );
    }

    echo '[OK] Autoloader dimuat dan kelas Laravel Application tersedia.'.PHP_EOL;
    echo '[INFO] Pemeriksaan kode dan indeks tetap dijalankan oleh checker V1.'.PHP_EOL.PHP_EOL;

    // Include at global scope to retain the standalone checker's variable behaviour.
    $argv = [$crmV3Checker, $crmV3Arguments[1]];
    $argc = count($argv);
    $_SERVER['argv'] = $argv;
    $_SERVER['argc'] = $argc;
    require $crmV3Checker;

    // Known V1 ends with exit(0) or exit(1). Never manufacture a success if it changes.
    throw new RuntimeException(
        'Checker selesai tanpa exit status eksplisit yang diharapkan. '.
        'Kirim file checker lengkap untuk diperiksa.'
    );
} catch (Throwable $crmV3Exception) {
    fwrite(STDERR, '[FAIL] Launcher V3: '.$crmV3Exception->getMessage().PHP_EOL);
    exit(1);
}
