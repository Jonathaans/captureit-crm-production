<?php

namespace Webkul\Admin\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Admin\Models\Vendor;

/** VENDOR NPWP PRIVATE SERVICE V1 */
class VendorNpwpImageService
{
    public static function store(Vendor $vendor, UploadedFile $file): string
    {
        $extension = strtolower((string) ($file->extension() ?: 'jpg'));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (! in_array($extension, $allowed, true)) {
            throw new \RuntimeException('Format image NPWP tidak didukung.');
        }

        $directory = 'vendor-npwp/'.(int) $vendor->id;
        $filename = 'npwp-'.(int) $vendor->id.'-'.Str::uuid().'.'.$extension;
        $oldPath = trim((string) ($vendor->npwp_image_path ?? ''));
        $path = $file->storeAs($directory, $filename, 'local');

        if (! $path) {
            throw new \RuntimeException('Gagal menyimpan image NPWP.');
        }

        $vendor->forceFill(['npwp_image_path' => $path])->save();
        self::deletePath($oldPath);

        return $path;
    }

    public static function delete(Vendor $vendor): void
    {
        $path = trim((string) ($vendor->npwp_image_path ?? ''));
        $vendor->forceFill(['npwp_image_path' => null])->save();
        self::deletePath($path);
    }

    public static function response(Vendor $vendor)
    {
        $path = trim((string) ($vendor->npwp_image_path ?? ''));

        if ($path === '') {
            abort(404, 'Image NPWP belum tersedia.');
        }

        $name = 'npwp-vendor-'.(int) $vendor->id.'.'.pathinfo($path, PATHINFO_EXTENSION);

        if (Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))->exists($path)) {
            return Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))->response($path, $name, [], 'inline');
        }

        // Backward-compatible viewer for files created by older public-storage patches.
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->response($path, $name, [], 'inline');
        }

        abort(404, 'File image NPWP tidak ditemukan.');
    }

    private static function deletePath(string $path): void
    {
        if ($path === '') {
            return;
        }

        if (str_starts_with($path, 'vendor-npwp/')) {
            Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))->delete($path);
        }

        if (str_starts_with($path, 'vendors/')) {
            Storage::disk('public')->delete($path);
        }
    }
}