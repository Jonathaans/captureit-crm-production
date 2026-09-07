<?php

namespace Webkul\Admin\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use Symfony\Component\Process\Process;

class CrmSecureUploadService
{
    /** @var array<string, array<int, string>> */
    private array $mimeByExtension = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'eml' => ['message/rfc822', 'text/plain'],
    ];

    public function validate(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Upload gagal atau file tidak lengkap.');
        }

        $path = $file->getRealPath();
        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('File upload sementara tidak ditemukan.');
        }

        $maxBytes = max(1, (int) config('crm-production-operations.attachments.max_kilobytes', 20480)) * 1024;
        if (($file->getSize() ?: filesize($path) ?: 0) > $maxBytes) {
            throw new RuntimeException('Ukuran file melebihi batas '.number_format($maxBytes / 1048576, 0).' MB.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = (array) config('crm-production-operations.attachments.allowed_extensions', []);

        if ($extension === '' || ! in_array($extension, $allowed, true)) {
            throw new RuntimeException('Ekstensi file tidak diizinkan: '.($extension ?: '(tanpa ekstensi)').'.');
        }

        $mime = strtolower((string) ($file->getMimeType() ?: 'application/octet-stream'));
        $expected = $this->mimeByExtension[$extension] ?? [];

        if ($expected === [] || ! in_array($mime, $expected, true)) {
            throw new RuntimeException("Tipe isi file {$mime} tidak cocok dengan ekstensi .{$extension}.");
        }

        $header = (string) file_get_contents($path, false, null, 0, 16);

        if ($extension === 'pdf' && ! str_starts_with($header, '%PDF-')) {
            throw new RuntimeException('File PDF tidak memiliki signature PDF yang valid.');
        }

        if (in_array($extension, ['docx', 'xlsx', 'zip'], true) && ! str_starts_with($header, "PK\x03\x04")) {
            throw new RuntimeException('File ZIP/Office tidak memiliki signature yang valid.');
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) && @getimagesize($path) === false) {
            throw new RuntimeException('Isi file gambar tidak valid.');
        }

        if (str_starts_with($header, 'MZ') || str_starts_with($header, "\x7fELF")) {
            throw new RuntimeException('Executable tidak boleh diunggah.');
        }

        $this->scanAntivirus($path);
    }

    private function scanAntivirus(string $path): void
    {
        if (! config('crm-production-operations.antivirus.enabled', false)) {
            return;
        }

        $binary = (string) config('crm-production-operations.antivirus.binary', 'clamscan');

        if (! class_exists(Process::class)) {
            $this->antivirusUnavailable('Symfony Process tidak tersedia.');
            return;
        }

        try {
            $process = new Process([$binary, '--no-summary', '--infected', $path]);
            $process->setTimeout((int) config('crm-production-operations.antivirus.timeout_seconds', 60));
            $process->run();

            if ($process->getExitCode() === 1) {
                throw new RuntimeException('Upload ditolak: antivirus mendeteksi malware.');
            }

            if ($process->getExitCode() !== 0) {
                $this->antivirusUnavailable(trim($process->getErrorOutput() ?: $process->getOutput()));
            }
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->antivirusUnavailable($exception->getMessage());
        }
    }

    private function antivirusUnavailable(string $message): void
    {
        if (config('crm-production-operations.antivirus.fail_closed', true)) {
            throw new RuntimeException('Antivirus tidak siap; upload dihentikan. '.$message);
        }

        report(new RuntimeException('Antivirus upload dilewati: '.$message));
    }
}
