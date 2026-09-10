<?php

declare(strict_types=1);

// Shared by the standalone installer and the read-only runtime checker.
function crmPerfTargets(): array
{
    return [
        'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php',
        'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatConversationController.php',
        'packages/Webkul/Admin/src/Services/InternalChatRealtimeService.php',
        'tools/check_crm_targeted_performance_optimization_v1.php',
        'packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php',
    ];
}

function crmPerfRead(string $path): string
{
    $bytes = file_get_contents($path);
    if ($bytes === false) {
        throw new RuntimeException('Tidak dapat membaca: '.$path);
    }
    return $bytes;
}

function crmPerfHash(string $bytes): string
{
    return hash('sha256', str_replace("\r\n", "\n", $bytes));
}

function crmPerfJson(string $path): array
{
    $value = json_decode(crmPerfRead($path), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($value)) {
        throw new RuntimeException('JSON tidak valid: '.$path);
    }
    return $value;
}

function crmPerfPath(string $root, string $relative): string
{
    if ($relative === '' || str_contains($relative, '\\') || str_contains($relative, ':')
        || str_starts_with($relative, '/') || in_array('..', explode('/', $relative), true)) {
        throw new RuntimeException('Path di luar scope patch.');
    }
    $resolved = realpath($root.'/'.$relative);
    $base = realpath($root);
    if ($resolved === false || $base === false) {
        throw new RuntimeException('File/folder tidak ditemukan: '.$relative);
    }
    $normalized = str_replace('\\', '/', $resolved);
    $prefix = rtrim(str_replace('\\', '/', $base), '/').'/';
    if (PHP_OS_FAMILY === 'Windows') {
        $normalized = strtolower($normalized);
        $prefix = strtolower($prefix);
    }
    if (! str_starts_with($normalized, $prefix)) {
        throw new RuntimeException('Symlink keluar dari root: '.$relative);
    }
    return $resolved;
}

function crmPerfRoot(): string
{
    $root = dirname(__DIR__, 2); // This file is in tools/crm_performance_completion_v2.
    crmPerfPath($root, 'artisan');
    crmPerfPath($root, 'composer.json');
    return $root;
}

function crmPerfManifest(): array
{
    $manifest = crmPerfJson(__DIR__.'/manifest.json');
    if (($manifest['patch'] ?? '') !== 'CRM_PERFORMANCE_COMPLETION_V2') {
        throw new RuntimeException('Manifest paket tidak valid.');
    }
    $paths = array_column($manifest['files'] ?? [], 'path');
    $expected = crmPerfTargets();
    sort($paths);
    sort($expected);
    if ($paths !== $expected) {
        throw new RuntimeException('Daftar payload tidak lengkap atau di luar scope.');
    }
    foreach ($manifest['files'] as $entry) {
        if (! preg_match('/^[0-9]+\.(php|blade\.php)$/D', $entry['payload'])) {
            throw new RuntimeException('Nama payload tidak valid.');
        }
        $payload = crmPerfRead(crmPerfPath(__DIR__, 'payload/'.$entry['payload']));
        if (crmPerfHash($payload) !== $entry['after_sha256']) {
            throw new RuntimeException('Payload rusak: '.$entry['path']);
        }
        if (! str_ends_with($entry['path'], '.blade.php')) {
            // Native PHP parser; does not load classes or run application code.
            token_get_all($payload, TOKEN_PARSE);
        }
    }
    return $manifest;
}

function crmPerfWrite(string $path, string $bytes): void
{
    $temporary = $path.'.crm-perf-'.bin2hex(random_bytes(6)).'.tmp';
    try {
        $stream = fopen($temporary, 'xb');
        if ($stream === false) {
            throw new RuntimeException('Gagal membuat file sementara: '.$path);
        }
        try {
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = fwrite($stream, substr($bytes, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Penulisan tidak lengkap: '.$path);
                }
                $offset += $written;
            }
            if (! fflush($stream)) {
                throw new RuntimeException('Flush gagal: '.$path);
            }
        } finally {
            fclose($stream);
        }
        if (hash_file('sha256', $temporary) !== hash('sha256', $bytes)) {
            throw new RuntimeException('Verifikasi file sementara gagal: '.$path);
        }
        if (is_file($path) && PHP_OS_FAMILY !== 'Windows') {
            $permissions = fileperms($path);
            if ($permissions === false || ! chmod($temporary, $permissions & 0777)) {
                throw new RuntimeException('Gagal mempertahankan permission: '.$path);
            }
        }
        if (! rename($temporary, $path) || hash_file('sha256', $path) !== hash('sha256', $bytes)) {
            throw new RuntimeException('Penggantian/verifikasi file gagal: '.$path);
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}
