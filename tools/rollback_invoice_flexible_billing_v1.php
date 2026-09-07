<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$pointer = $root.DIRECTORY_SEPARATOR.'tools'
    .DIRECTORY_SEPARATOR.'backups'
    .DIRECTORY_SEPARATOR.'invoice-flexible-billing-v1-latest.txt';

echo 'ROLLBACK INVOICE FLEXIBLE DP + PELUNASAN V1'.PHP_EOL;
echo '============================================='.PHP_EOL.PHP_EOL;

if (! is_file($pointer)) {
    echo 'ROLLBACK GAGAL: pointer backup tidak ditemukan.'.PHP_EOL;
    exit(1);
}

$backupDirectory = trim((string) file_get_contents($pointer));
$manifestPath = $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json';

if (! is_file($manifestPath)) {
    echo 'ROLLBACK GAGAL: manifest backup tidak ditemukan.'.PHP_EOL;
    exit(1);
}

$manifest = json_decode(
    (string) file_get_contents($manifestPath),
    true
);

if (! is_array($manifest) || ! isset($manifest['files'])) {
    echo 'ROLLBACK GAGAL: manifest backup tidak valid.'.PHP_EOL;
    exit(1);
}

$keptMigration = false;

foreach ($manifest['files'] as $relative => $meta) {
    $target = $root.DIRECTORY_SEPARATOR
        .str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if ($meta['existed']) {
        $backup = $backupDirectory.DIRECTORY_SEPARATOR.'files'
            .DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (! is_file($backup)) {
            echo '[WARN] Backup file tidak ditemukan: '.$relative.PHP_EOL;
            continue;
        }

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        file_put_contents(
            $target,
            (string) file_get_contents($backup),
            LOCK_EX
        );

        echo '[RESTORE] '.$relative.PHP_EOL;
        continue;
    }

    /*
     * Never delete an already-recorded migration file without rolling its
     * schema back. Billing data and multiple invoices per quote are preserved.
     */
    if (
        str_starts_with($relative, 'database/migrations/')
        && is_file($target)
    ) {
        $keptMigration = true;
        echo '[KEEP] '.$relative.' (schema/data dipertahankan)'.PHP_EOL;
        continue;
    }

    if (is_file($target)) {
        unlink($target);
        echo '[REMOVE] '.$relative.PHP_EOL;
    }
}

$command = escapeshellarg(PHP_BINARY)
    .' artisan optimize:clear';

$previous = getcwd();
chdir($root);
passthru($command);

if ($previous !== false) {
    chdir($previous);
}

echo PHP_EOL;
echo 'ROLLBACK SOURCE BERHASIL.'.PHP_EOL;

if ($keptMigration) {
    echo 'Kolom dan data billing di database sengaja tidak dihapus agar histori aman.'.PHP_EOL;
}

