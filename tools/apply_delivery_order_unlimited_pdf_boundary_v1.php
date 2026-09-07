<?php

declare(strict_types=1);

echo "DELIVERY ORDER UNLIMITED ITEMS + PDF BOUNDARY V1\n";
echo "==================================================\n\n";

$root = realpath(dirname(__DIR__));

if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "PATCH GAGAL: Jalankan file ini dari folder root Laravel melalui php tools/apply_delivery_order_unlimited_pdf_boundary_v1.php\n");
    exit(1);
}

$relativeEquipment = 'packages/Webkul/Admin/src/Resources/views/delivery-orders/partials/equipment-edit.blade.php';
$relativeQuote = 'packages/Webkul/Admin/src/Resources/views/quotes/pdf.blade.php';
$relativeInvoice = 'packages/Webkul/Admin/src/Resources/views/invoices/pdf.blade.php';
$payloadPath = __DIR__.DIRECTORY_SEPARATOR.'payloads'.DIRECTORY_SEPARATOR.'equipment-edit-unlimited-v1.blade.php';

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

$targets = [];
$originals = [];

try {
    $equipmentPath = $path($relativeEquipment);
    $quotePath = $path($relativeQuote);
    $invoicePath = $path($relativeInvoice);

    $equipment = $read($equipmentPath, 'Partial Equipment Surat Jalan');
    $quote = $read($quotePath, 'PDF Quote');
    $invoice = $read($invoicePath, 'PDF Invoice');
    $payload = $read($payloadPath, 'Payload UI unlimited item');

    foreach ([
        'Equipment' => $equipment,
        'Quote PDF' => $quote,
        'Invoice PDF' => $invoice,
    ] as $label => $content) {
        if (trim($content) === '') {
            throw new RuntimeException($label.' kosong.');
        }
    }

    if (! str_contains($payload, 'CRM_DELIVERY_ORDER_UNLIMITED_ITEMS_UI_V1')) {
        throw new RuntimeException('Payload UI tidak valid. Ekstrak seluruh folder tools/payloads dari ZIP.');
    }

    if (! str_contains($equipment, 'CRM_DELIVERY_ORDER_UNLIMITED_ITEMS_UI_V1')) {
        foreach ([
            'Equipment / Inventory Requirement',
            '$equipmentRowCount',
            '@for ($index = 0; $index < $equipmentRowCount; $index++)',
            'items[{{ $index }}][inventory_item_id]',
            'items[{{ $index }}][quantity]',
        ] as $signature) {
            if (! str_contains($equipment, $signature)) {
                throw new RuntimeException(
                    'Preflight UI Equipment gagal pada signature: '.$signature
                    .'. File lokal berbeda dari struktur yang didukung; tidak ada file yang diubah.'
                );
            }
        }

        $targets[$relativeEquipment] = $payload;
        $originals[$relativeEquipment] = $equipment;
    }

    $patchPdf = static function (string $content, string $label): string {
        if (str_contains($content, 'CRM_DOCUMENT_PDF_SAFE_TOP_BOUNDARY_V1')) {
            return $content;
        }

        $needle = 'margin: 22px 28px 72px 28px;';
        if (substr_count($content, $needle) !== 1) {
            throw new RuntimeException(
                'Preflight '.$label.' gagal: margin awal harus ditemukan tepat satu kali. '
                .'File lokal tidak diubah.'
            );
        }

        return str_replace(
            $needle,
            "/* CRM_DOCUMENT_PDF_SAFE_TOP_BOUNDARY_V1: 20 mm text-safe top boundary on every A4 page. */\n            margin: 76px 28px 72px 28px;",
            $content
        );
    };

    $patchedQuote = $patchPdf($quote, 'PDF Quote');
    if ($patchedQuote !== $quote) {
        $targets[$relativeQuote] = $patchedQuote;
        $originals[$relativeQuote] = $quote;
    }

    $patchedInvoice = $patchPdf($invoice, 'PDF Invoice');
    if ($patchedInvoice !== $invoice) {
        $targets[$relativeInvoice] = $patchedInvoice;
        $originals[$relativeInvoice] = $invoice;
    }

    if ($targets === []) {
        echo "[OK] Patch sudah terpasang; tidak ada perubahan ulang.\n";
        echo "Jalankan: php tools/check_delivery_order_unlimited_pdf_boundary_v1.php\n";
        exit(0);
    }

    $backupRoot = $path('storage/app/private/patch-backups/delivery-order-unlimited-pdf-boundary-v1-'.date('Ymd-His'));
    if (! mkdir($backupRoot, 0775, true) && ! is_dir($backupRoot)) {
        throw new RuntimeException('Tidak dapat membuat folder backup patch.');
    }

    foreach ($originals as $relative => $content) {
        $write($backupRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative), $content);
    }

    $manifest = [
        'patch' => 'delivery-order-unlimited-pdf-boundary-v1',
        'created_at' => date(DATE_ATOM),
        'files' => array_keys($originals),
    ];
    $write(
        $backupRoot.DIRECTORY_SEPARATOR.'manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'
    );

    try {
        foreach ($targets as $relative => $content) {
            $write($path($relative), $content);
            echo '[WRITE] '.$relative.PHP_EOL;
        }
    } catch (Throwable $exception) {
        foreach ($originals as $relative => $content) {
            $write($path($relative), $content);
        }

        throw new RuntimeException($exception->getMessage().' Semua file sudah dipulihkan.');
    }

    echo "\nPATCH BERHASIL.\n";
    echo "- Editor Surat Jalan sekarang dapat menambah/menghapus item tanpa batas 10.\n";
    echo "- Header tabel sticky, pencarian, counter, scroll area, dan auto-fill inventory terpasang.\n";
    echo "- PDF Quote dan Invoice memakai batas teks atas 20 mm pada setiap halaman A4.\n";
    echo "- Backup file lama: {$backupRoot}\n\n";
    echo "Lanjutkan dengan:\n";
    echo "php tools/check_delivery_order_unlimited_pdf_boundary_v1.php\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "\nPATCH GAGAL: ".$exception->getMessage()."\n");
    exit(1);
}

