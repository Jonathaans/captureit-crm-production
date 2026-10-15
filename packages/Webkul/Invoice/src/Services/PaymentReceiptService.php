<?php

namespace Webkul\Invoice\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\Payment;

class PaymentReceiptService
{
    public function __construct(private PaymentService $payments) {}

    public function addPayment(Invoice $invoice, array $data, ?UploadedFile $receipt = null): Payment
    {
        $metadata = $receipt ? $this->store($invoice, $receipt) : [];

        try {
            return DB::transaction(function () use ($invoice, $data, $metadata) {
                $payment = $this->payments->addPayment($invoice, $data);
                if ($metadata) {
                    $payment->forceFill($metadata)->save();
                }

                return $payment;
            });
        } catch (Throwable $exception) {
            if (isset($metadata['receipt_path'])) {
                Storage::disk('local')->delete($metadata['receipt_path']);
            }

            throw $exception;
        }
    }

    private function store(Invoice $invoice, UploadedFile $file): array
    {
        $mime = $file->isValid() ? $file->getMimeType() : null;
        $bytes = $file->isValid() ? $file->getSize() : 0;
        $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
        $valid = $extension && in_array(strtolower($file->getClientOriginalExtension()), $extension === 'jpg' ? ['jpg', 'jpeg'] : [$extension], true)
            && $bytes > 0 && $bytes <= 5 * 1024 * 1024;

        if ($valid && $extension === 'pdf') {
            $valid = file_get_contents($file->getRealPath(), false, null, 0, 5) === '%PDF-';
        } elseif ($valid) {
            $size = @getimagesize($file->getRealPath());
            $valid = $size && ($size['mime'] ?? null) === $mime;
        }

        if (! $valid) {
            throw ValidationException::withMessages(['receipt' => 'Bon harus berupa PDF, JPG, atau PNG yang valid, maksimal 5 MB.']);
        }

        $name = mb_substr(preg_replace('/[\x00-\x1F\x7F\\\\\/]/u', '_', mb_scrub($file->getClientOriginalName(), 'UTF-8')), 0, 240);
        $path = 'invoice-payment-receipts/'.$invoice->id.'/'.Str::uuid().'.'.$extension;
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw ValidationException::withMessages(['receipt' => 'Bon tidak dapat dibaca. Pilih kembali file.']);
        }
        try {
            $stored = Storage::disk('local')->put($path, $stream, ['visibility' => 'private']);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            report($exception);
            throw ValidationException::withMessages(['receipt' => 'Bon gagal disimpan. Silakan coba lagi.']);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if (! $stored) {
            Storage::disk('local')->delete($path);
            throw ValidationException::withMessages(['receipt' => 'Bon gagal disimpan. Silakan coba lagi.']);
        }

        return [
            'receipt_path' => $path,
            'receipt_name' => $name,
            'receipt_mime' => $mime,
            'receipt_size' => $bytes,
        ];
    }
}
