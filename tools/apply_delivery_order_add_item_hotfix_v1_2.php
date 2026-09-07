<?php

declare(strict_types=1);

echo "DELIVERY ORDER ADD ITEM HOTFIX V1.2\n";
echo "=====================================\n\n";

$root = realpath(dirname(__DIR__));

if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "HOTFIX GAGAL: Jalankan dari root Laravel.\n");
    exit(1);
}

$relativeView = 'packages/Webkul/Admin/src/Resources/views/delivery-orders/partials/equipment-edit.blade.php';
$relativeAsset = 'public/js/crm-delivery-order-equipment-v1-2.js';
$payloadPath = __DIR__.DIRECTORY_SEPARATOR.'payloads'.DIRECTORY_SEPARATOR.'crm-delivery-order-equipment-v1-2.js';
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
    $assetPayload = $read($payloadPath, 'Payload JavaScript V1.2');

    if (! str_contains($view, 'CRM_DELIVERY_ORDER_UNLIMITED_ITEMS_UI_V1')) {
        throw new RuntimeException('UI unlimited item V1 belum terpasang.');
    }

    if (! str_contains($assetPayload, 'CRM_DELIVERY_ORDER_EQUIPMENT_EXTERNAL_JS_V1_2')) {
        throw new RuntimeException('Payload JavaScript V1.2 tidak valid. Ekstrak seluruh isi ZIP.');
    }

    if (is_file($assetPath)) {
        $existingAsset = $read($assetPath, 'Asset JavaScript V1.2 saat ini');
        if (! str_contains($existingAsset, 'CRM_DELIVERY_ORDER_EQUIPMENT_EXTERNAL_JS_V1_2')) {
            throw new RuntimeException($relativeAsset.' sudah ada dan bukan milik hotfix ini.');
        }
    }

    $newMarker = 'CRM_DELIVERY_ORDER_CONTROLS_EXTERNAL_LOADER_V1_2';
    $patchedView = $view;

    if (! str_contains($view, $newMarker)) {
        $oldMarker = 'CRM_DELIVERY_ORDER_CONTROLS_EXTERNAL_LOADER_V1_1';
        $oldAsset = "asset('js/crm-delivery-order-equipment-v1-1.js')";

        if (substr_count($view, $oldMarker) !== 1 || substr_count($view, $oldAsset) !== 1) {
            throw new RuntimeException(
                'Preflight loader V1.1 gagal. Jalankan hotfix V1.1 dan checkernya terlebih dahulu.'
            );
        }

        $patchedView = str_replace($oldMarker, $newMarker, $view);
        $patchedView = str_replace(
            $oldAsset,
            "asset('js/crm-delivery-order-equipment-v1-2.js')",
            $patchedView
        );
        $patchedView = str_replace('?v=1.1.0', '?v=1.2.0', $patchedView, $versionCount);

        if ($versionCount !== 1) {
            throw new RuntimeException('Preflight cache-buster V1.1 gagal; file tidak diubah.');
        }
    }

    $viewChanged = $patchedView !== $view;
    $assetBefore = is_file($assetPath) ? $read($assetPath, 'Asset JavaScript V1.2 saat ini') : null;
    $assetChanged = $assetBefore !== $assetPayload;

    if (! $viewChanged && ! $assetChanged) {
        echo "[OK] Hotfix V1.2 sudah terpasang.\n";
        echo "Jalankan: php tools/check_delivery_order_add_item_hotfix_v1_2.php\n";
        exit(0);
    }

    $backupRoot = $path('storage/app/private/patch-backups/delivery-order-add-item-hotfix-v1-2-'.date('Ymd-His'));
    if (! mkdir($backupRoot, 0775, true) && ! is_dir($backupRoot)) {
        throw new RuntimeException('Tidak dapat membuat folder backup hotfix.');
    }

    $manifest = [
        'patch' => 'delivery-order-add-item-hotfix-v1-2',
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

    echo "\nHOTFIX V1.2 BERHASIL.\n";
    echo "Tambah Item/Tambah Baris sekarang mengkloning baris form aktif tanpa native template.\n";
    echo "Backup: {$backupRoot}\n\n";
    echo "Lanjutkan dengan:\n";
    echo "php tools/check_delivery_order_add_item_hotfix_v1_2.php\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "\nHOTFIX GAGAL: ".$exception->getMessage()."\n");
    exit(1);
}
