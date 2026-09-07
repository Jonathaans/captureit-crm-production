<?php

declare(strict_types=1);

namespace Webkul\Invoice\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * PURCHASE ORDER PAID PDF PROOF V1
 *
 * Dedicated private storage for PO payment proofs. New uploads accept PDF
 * only. This service does not alter uploads in any other CRM module.
 */
final class PurchaseOrderPaymentProofService
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function store(UploadedFile $file, int $purchaseOrderId): string
    {
        $realPath = $file->getRealPath();

        if (! is_string($realPath) || $realPath === '' || ! is_file($realPath)) {
            throw ValidationException::withMessages([
                'payment_proof' => 'PDF bukti transfer tidak dapat dibaca.',
            ]);
        }

        $size = filesize($realPath);

        if ($size === false || $size <= 0 || $size > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'payment_proof' => 'Ukuran PDF bukti transfer harus antara 1 byte dan 10 MB.',
            ]);
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($realPath);
        $handle = fopen($realPath, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'payment_proof' => 'PDF bukti transfer tidak dapat dibuka.',
            ]);
        }

        try {
            $header = fread($handle, 5);
            rewind($handle);

            if ($mimeType !== 'application/pdf' || $header !== '%PDF-') {
                throw ValidationException::withMessages([
                    'payment_proof' => 'Isi bukti transfer harus berupa PDF yang valid.',
                ]);
            }

            $relativePath = sprintf(
                'purchase-orders/payment-proofs/%s/po-%d-%s.pdf',
                now()->format('Y/m'),
                $purchaseOrderId,
                bin2hex(random_bytes(16))
            );

            if (! Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))->put($relativePath, $handle)) {
                throw new RuntimeException('Gagal menyimpan PDF bukti transfer.');
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return $relativePath;
    }

    public function delete(?string $path): void
    {
        $path = trim((string) ($path ?? ''));

        if ($path !== '') {
            Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))->delete($path);
        }
    }

    public function absolutePath(string $path): string
    {
        if (! Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))->exists($path)) {
            throw new RuntimeException('File bukti transfer tidak ditemukan.');
        }

        return Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))->path($path);
    }
}
