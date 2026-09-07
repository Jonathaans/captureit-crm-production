<?php

namespace Webkul\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Webkul\Admin\Services\CrmSecureUploadService;

class CrmSecureUploadMiddleware
{
    public function __construct(protected CrmSecureUploadService $validator)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (! $request->is('admin/*')) {
            return $next($request);
        }

        foreach ($this->flatten($request->allFiles()) as $field => $file) {
            try {
                $this->validator->validate($file);
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages([
                    $field => $exception->getMessage(),
                ]);
            }
        }

        return $next($request);
    }

    /** @return array<string, UploadedFile> */
    private function flatten(array $files, string $prefix = ''): array
    {
        $result = [];

        foreach ($files as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if ($value instanceof UploadedFile) {
                $result[$name] = $value;
            } elseif (is_array($value)) {
                $result += $this->flatten($value, $name);
            }
        }

        return $result;
    }
}
