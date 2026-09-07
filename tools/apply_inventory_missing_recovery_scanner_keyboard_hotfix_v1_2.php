<?php

declare(strict_types=1);

const TITLE = 'INVENTORY MISSING RECOVERY SCANNER KEYBOARD HOTFIX V1.2.1';
const MARKER = 'INVENTORY_MISSING_RECOVERY_SCANNER_KEYBOARD_HOTFIX_V1_2';
const BACKUP_SLUG = 'inventory-missing-recovery-scanner-keyboard-hotfix-v1_2';

$root = dirname(__DIR__);
$relative = 'packages/Webkul/Admin/src/Resources/views/inventory/assets/recover-missing-scan.blade.php';
$target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
$payload = __DIR__.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR
    .'inventory-missing-recovery-scan-v1'.DIRECTORY_SEPARATOR
    .str_replace('/', DIRECTORY_SEPARATOR, $relative);
$backupDirectory = null;
$original = null;
$writeStarted = false;

function line(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function fail(string $message): never
{
    throw new RuntimeException($message);
}

function writeFile(string $path, string $content): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true)) {
        fail('Tidak dapat membuat folder: '.$directory);
    }

    if (file_put_contents($path, $content) === false) {
        fail('Tidak dapat menulis file: '.$path);
    }
}

function runCommand(string $root, array $arguments): int
{
    $command = escapeshellarg(PHP_BINARY);

    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg((string) $argument);
    }

    line('[RUN]   '.implode(' ', $arguments));
    $current = getcwd();
    chdir($root);
    passthru($command, $exitCode);
    chdir($current ?: $root);

    return (int) $exitCode;
}

line(TITLE);
line(str_repeat('=', strlen(TITLE)));
line();

try {
    if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
        fail('Jalankan script ini dari root project Laravel CRM.');
    }

    if (! is_file($target)) {
        fail('Halaman recovery scan belum terpasang. Jalankan installer V1.1 terlebih dahulu.');
    }

    if (! is_file($payload)) {
        fail('Payload V1.2 tidak ditemukan. Extract seluruh isi ZIP ke root project.');
    }

    $currentContent = (string) file_get_contents($target);
    $payloadContent = (string) file_get_contents($payload);

    if (str_contains($currentContent, MARKER)) {
        runCommand($root, ['artisan', 'view:clear']);
        runCommand($root, ['artisan', 'view:cache']);
        line('[OK] Hotfix V1.2 sudah terpasang.');
        line('Jalankan: php tools/check_inventory_missing_recovery_scanner_keyboard_hotfix_v1_2.php');
        exit(0);
    }

    $targetMarkers = [
        'id="missing-recovery-form"',
        'id="scanned-barcode"',
        "let usbBuffer = '';",
        'BarcodeDetector',
        "admin.inventory.assets.missing-recovery.store",
    ];

    $missingTargetMarkers = array_values(array_filter(
        $targetMarkers,
        static fn (string $needle): bool => ! str_contains($currentContent, $needle)
    ));

    if ($missingTargetMarkers !== []) {
        fail(
            'Target bukan halaman Missing Recovery Scan yang dikenali. Marker kurang: '
            .implode(', ', $missingTargetMarkers)
        );
    }

    foreach ([
        MARKER,
        'let usbActive = true;',
        "event.key === 'Enter' || event.key === 'Tab'",
        'setTimeout(commitUsbBuffer, 350)',
        'armUsbScanner();',
    ] as $needle) {
        if (! str_contains($payloadContent, $needle)) {
            fail('Payload V1.2 tidak lengkap: '.$needle);
        }
    }

    $timestamp = date('Ymd-His');
    $backupDirectory = $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
        .DIRECTORY_SEPARATOR.BACKUP_SLUG.'-'.$timestamp;
    $backupFile = $backupDirectory.DIRECTORY_SEPARATOR.'recover-missing-scan.blade.php';

    writeFile($backupFile, $currentContent);
    writeFile(
        $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json',
        (string) json_encode([
            'patch' => TITLE,
            'created_at' => date(DATE_ATOM),
            'target' => $relative,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    writeFile(
        $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
            .DIRECTORY_SEPARATOR.BACKUP_SLUG.'-latest.txt',
        $backupDirectory
    );

    $original = $currentContent;
    $writeStarted = true;
    writeFile($target, $payloadContent);
    line('[WRITE] '.$relative);

    if (runCommand($root, ['artisan', 'view:clear']) !== 0) {
        fail('Compiled views tidak dapat dibersihkan.');
    }

    if (runCommand($root, ['artisan', 'view:cache']) !== 0) {
        fail('Blade compile gagal.');
    }

    runCommand($root, ['artisan', 'optimize:clear']);

    line();
    line('HOTFIX BERHASIL. Scanner keyboard otomatis aktif saat halaman dibuka.');
    line('Backup source: '.$backupDirectory);
    line('Lanjutkan dengan:');
    line('php tools/check_inventory_missing_recovery_scanner_keyboard_hotfix_v1_2.php');
} catch (Throwable $exception) {
    line();
    line('HOTFIX GAGAL: '.$exception->getMessage());

    if ($writeStarted && is_string($original)) {
        writeFile($target, $original);
        runCommand($root, ['artisan', 'view:clear']);
        runCommand($root, ['artisan', 'optimize:clear']);
        line('Halaman recovery dipulihkan otomatis dari backup.');
    }

    exit(1);
}
