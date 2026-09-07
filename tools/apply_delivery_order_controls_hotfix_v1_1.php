<?php

declare(strict_types=1);

echo "DELIVERY ORDER CONTROLS HOTFIX V1.1\n";
echo "====================================\n\n";

$root = realpath(dirname(__DIR__));

if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "HOTFIX GAGAL: Jalankan dari root Laravel.\n");
    exit(1);
}

$relativeView = 'packages/Webkul/Admin/src/Resources/views/delivery-orders/partials/equipment-edit.blade.php';
$relativeAsset = 'public/js/crm-delivery-order-equipment-v1-1.js';
$payloadPath = __DIR__.DIRECTORY_SEPARATOR.'payloads'.DIRECTORY_SEPARATOR.'crm-delivery-order-equipment-v1-1.js';
$path = static fn (string $relative): string => $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

$read = static function (string $file, string $label): string {
    if (! is_file($file)) {
        throw new RuntimeException($label.' tidak ditemukan: '.$file);
    }

    $content = file_get_contents($file);
    if ($content === false) {
        throw new RuntimeException('Tidak dapat membaca '.$label.'.');
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
};

$write = static function (string $file, string $content): void {
    $directory = dirname($file);
    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        throw new RuntimeException('Tidak dapat membuat folder '.$directory);
    }

    if (file_put_contents($file, str_replace("\n", PHP_EOL, $content)) === false) {
        throw new RuntimeException('Tidak dapat menulis '.$file);
    }
};

try {
    $viewPath = $path($relativeView);
    $assetPath = $path($relativeAsset);
    $view = $read($viewPath, 'UI Equipment Surat Jalan');
    $assetPayload = $read($payloadPath, 'Payload JavaScript eksternal');

    if (! str_contains($view, 'CRM_DELIVERY_ORDER_UNLIMITED_ITEMS_UI_V1')) {
        throw new RuntimeException(
            'Patch unlimited item V1 belum terpasang. Jalankan installer V1 terlebih dahulu.'
        );
    }

    if (! str_contains($assetPayload, 'CRM_DELIVERY_ORDER_EQUIPMENT_EXTERNAL_JS_V1_1')) {
        throw new RuntimeException('Payload JavaScript V1.1 tidak valid. Ekstrak seluruh isi ZIP.');
    }

    if (is_file($assetPath)) {
        $existingAsset = $read($assetPath, 'Asset JavaScript saat ini');
        if (! str_contains($existingAsset, 'CRM_DELIVERY_ORDER_EQUIPMENT_EXTERNAL_JS_V1_1')) {
            throw new RuntimeException(
                $relativeAsset.' sudah ada dan bukan milik hotfix ini; file tidak ditimpa.'
            );
        }
    }

    $externalMarker = 'CRM_DELIVERY_ORDER_CONTROLS_EXTERNAL_LOADER_V1_1';
    $patchedView = $view;

    if (! str_contains($view, $externalMarker)) {
        $pattern = '~<script>\s*/\* CRM_DELIVERY_ORDER_UNLIMITED_ITEMS_SCRIPT_V1 \*/.*?</script>~s';
        $matchCount = preg_match_all($pattern, $view);

        if ($matchCount !== 1) {
            throw new RuntimeException(
                'Preflight script lama gagal: blok JavaScript harus ditemukan tepat satu kali; count='.(string) $matchCount.'.'
            );
        }

        $loader = <<<'BLADE'
{{-- CRM_DELIVERY_ORDER_CONTROLS_EXTERNAL_LOADER_V1_1 --}}
<script
    src="{{ asset('js/crm-delivery-order-equipment-v1-1.js') }}?v=1.1.0"
    defer
></script>
BLADE;

        $patchedView = (string) preg_replace($pattern, $loader, $view, 1);
    }

    $viewChanged = $patchedView !== $view;
    $assetBefore = is_file($assetPath) ? $read($assetPath, 'Asset JavaScript saat ini') : null;
    $assetChanged = $assetBefore !== $assetPayload;

    if (! $viewChanged && ! $assetChanged) {
        echo "[OK] Hotfix sudah terpasang; tidak ada perubahan ulang.\n";
        echo "Jalankan: php tools/check_delivery_order_controls_hotfix_v1_1.php\n";
        exit(0);
    }

    $backupRoot = $path('storage/app/private/patch-backups/delivery-order-controls-hotfix-v1-1-'.date('Ymd-His'));
    if (! mkdir($backupRoot, 0775, true) && ! is_dir($backupRoot)) {
        throw new RuntimeException('Tidak dapat membuat folder backup hotfix.');
    }

    $manifest = [
        'patch' => 'delivery-order-controls-hotfix-v1-1',
        'created_at' => date(DATE_ATOM),
        'files' => [
            $relativeView => ['existed' => true],
            $relativeAsset => ['existed' => $assetBefore !== null],
        ],
    ];

    $write($backupRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeView), $view);
    if ($assetBefore !== null) {
        $write($backupRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeAsset), $assetBefore);
    }
    $write(
        $backupRoot.DIRECTORY_SEPARATOR.'manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'
    );

    try {
        if ($viewChanged) {
            $write($viewPath, $patchedView);
            echo '[WRITE] '.$relativeView.PHP_EOL;
        }

        if ($assetChanged) {
            $write($assetPath, $assetPayload);
            echo '[WRITE] '.$relativeAsset.PHP_EOL;
        }
    } catch (Throwable $exception) {
        $write($viewPath, $view);

        if ($assetBefore !== null) {
            $write($assetPath, $assetBefore);
        } elseif (is_file($assetPath)) {
            unlink($assetPath);
        }

        throw new RuntimeException($exception->getMessage().' Semua perubahan sudah dipulihkan.');
    }

    echo "\nHOTFIX BERHASIL.\n";
    echo "Logic tambah/hapus/search sekarang dimuat sebagai JavaScript eksternal dan memakai event delegation.\n";
    echo "Backup: {$backupRoot}\n\n";
    echo "Lanjutkan dengan:\n";
    echo "php tools/check_delivery_order_controls_hotfix_v1_1.php\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "\nHOTFIX GAGAL: ".$exception->getMessage()."\n");
    exit(1);
}
