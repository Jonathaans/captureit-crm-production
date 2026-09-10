<?php

declare(strict_types=1);

// Standalone file patcher: no Composer bootstrap, SQL, migration, or service call.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

function crmLiveHash(string $content): string
{
    return hash('sha256', str_replace("\r\n", "\n", $content));
}

function crmLiveRead(string $path): string
{
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException('Tidak dapat membaca: '.$path);
    }
    return $content;
}

function crmLiveJson(string $path): array
{
    $data = json_decode(crmLiveRead($path), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($data)) {
        throw new RuntimeException('Format JSON tidak valid: '.$path);
    }
    return $data;
}

function crmLiveContained(string $path, string $directory): bool
{
    $resolved = realpath($path);
    $base = realpath($directory);
    if ($resolved === false || $base === false) {
        return false;
    }
    $resolved = str_replace('\\', '/', $resolved);
    $base = rtrim(str_replace('\\', '/', $base), '/').'/';
    if (PHP_OS_FAMILY === 'Windows') {
        $resolved = strtolower($resolved);
        $base = strtolower($base);
    }
    return str_starts_with($resolved, $base);
}

function crmLiveWrite(string $path, string $content): void
{
    $temporary = $path.'.crm-ui-'.bin2hex(random_bytes(8)).'.tmp';
    try {
        $handle = fopen($temporary, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Gagal membuat file sementara untuk '.$path);
        }
        fclose($handle);
        if (file_put_contents($temporary, $content, LOCK_EX) !== strlen($content)) {
            throw new RuntimeException('Penulisan sementara tidak lengkap: '.$path);
        }
        if (hash_file('sha256', $temporary) !== hash('sha256', $content)) {
            throw new RuntimeException('Verifikasi sementara gagal: '.$path);
        }
        if (is_file($path)) {
            $permissions = fileperms($path);
            if ($permissions !== false && ! chmod($temporary, $permissions & 0777)) {
                throw new RuntimeException('Gagal mempertahankan permission: '.$path);
            }
        }
        if (! rename($temporary, $path)) {
            throw new RuntimeException('Gagal mengganti file: '.$path);
        }
        if (hash_file('sha256', $path) !== hash('sha256', $content)) {
            throw new RuntimeException('Verifikasi hasil gagal: '.$path);
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

$crmLiveRoot = dirname(__DIR__);
$crmLiveViews = $crmLiveRoot.'/packages/Webkul/Admin/src/Resources/views';
$crmLiveBundle = __DIR__.'/crm_chat_live_scroll_v2';
$crmLiveBackupBase = $crmLiveRoot.'/storage/app/crm-chat-live-scroll-backups';
$crmLiveMode = $argv[1] ?? '--help';
$crmLiveAttempted = [];

echo 'CRM CHAT LIVE SCROLL V2'.PHP_EOL;

try {
    if (count($argv) > 2) {
        throw new RuntimeException('Gunakan hanya satu opsi: --check, --apply, atau --rollback=RUN_ID.');
    }
    if ($crmLiveMode === '--help') {
        echo 'php tools/apply_crm_chat_live_scroll_v2.php --check'.PHP_EOL;
        echo 'php tools/apply_crm_chat_live_scroll_v2.php --apply'.PHP_EOL;
        echo 'php tools/apply_crm_chat_live_scroll_v2.php --rollback=RUN_ID'.PHP_EOL;
        exit(0);
    }
    if (! is_file($crmLiveRoot.'/artisan') || ! is_file($crmLiveRoot.'/composer.json') || ! is_dir($crmLiveViews)) {
        throw new RuntimeException('Extract folder tools ke root Laravel CRM. Root yang diperiksa: '.$crmLiveRoot);
    }

    $crmLivePlans = [];
    if (str_starts_with($crmLiveMode, '--rollback=')) {
        $runId = substr($crmLiveMode, strlen('--rollback='));
        if (! preg_match('/^\d{8}_\d{6}_[a-f0-9]{8}$/D', $runId)) {
            throw new RuntimeException('Run ID backup tidak valid.');
        }
        $backupDirectory = $crmLiveBackupBase.'/'.$runId;
        if (! crmLiveContained($backupDirectory, $crmLiveBackupBase)) {
            throw new RuntimeException('Backup tidak ditemukan di folder yang diizinkan.');
        }
        $backupManifest = crmLiveJson($backupDirectory.'/manifest.json');
        if (($backupManifest['patch'] ?? '') !== 'CRM_CHAT_LIVE_SCROLL_V2') {
            throw new RuntimeException('Manifest bukan milik patch ini.');
        }
        foreach ($backupManifest['files'] as $entry) {
            $path = $crmLiveRoot.'/'.$entry['relative'];
            if (! crmLiveContained($path, $crmLiveViews) || ! in_array(basename($path), ['chat.blade.php', 'widget.blade.php', 'chat-unread-badge.blade.php', 'realtime-chat-listeners.blade.php'], true)) {
                throw new RuntimeException('Target rollback berada di luar scope patch.');
            }
            if (! preg_match('/^\d+\.bak$/D', $entry['backup'])) {
                throw new RuntimeException('Nama file backup tidak valid.');
            }
            $original = crmLiveRead($backupDirectory.'/'.$entry['backup']);
            if (hash('sha256', $original) !== $entry['original_sha256']) {
                throw new RuntimeException('Backup rusak: '.$entry['backup']);
            }
            $current = crmLiveRead($path);
            if ($current === $original) {
                echo '[OK] Sudah dipulihkan: '.$entry['relative'].PHP_EOL;
                continue;
            }
            if (crmLiveHash($current) !== $entry['after_sha256']) {
                throw new RuntimeException('File berubah sejak patch. Rollback dihentikan: '.$entry['relative']);
            }
            $crmLivePlans[] = ['path' => $path, 'before' => $current, 'after' => $original];
        }
    } elseif (in_array($crmLiveMode, ['--check', '--apply'], true)) {
        $manifest = crmLiveJson($crmLiveBundle.'/manifest.json');
        foreach ($manifest['files'] as $entry) {
            if (! in_array($entry['name'], ['chat.blade.php', 'widget.blade.php', 'chat-unread-badge.blade.php', 'realtime-chat-listeners.blade.php'], true)) {
                throw new RuntimeException('Nama payload di luar scope patch.');
            }
            $payload = crmLiveRead($crmLiveBundle.'/'.$entry['name']);
            if (crmLiveHash($payload) !== $entry['after_sha256']) {
                throw new RuntimeException('Payload ZIP tidak cocok: '.$entry['name']);
            }
            $matches = [];
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($crmLiveViews, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->isLink() || $file->getFilename() !== $entry['name']) {
                    continue;
                }
                if (! crmLiveContained($file->getPathname(), $crmLiveViews)) {
                    continue;
                }
                $current = crmLiveRead($file->getPathname());
                $hash = crmLiveHash($current);
                if (in_array($hash, $entry['before_sha256'], true) || $hash === $entry['after_sha256']) {
                    $matches[] = ['path' => $file->getPathname(), 'before' => $current, 'hash' => $hash];
                }
            }
            if (count($matches) !== 1) {
                throw new RuntimeException('Harus ada tepat satu '.$entry['name'].' yang cocok dengan source unggahan; ditemukan '.count($matches).'. Tidak ada file diubah.');
            }
            $match = $matches[0];
            if ($match['hash'] === $entry['after_sha256']) {
                echo '[OK] Sudah terpasang: '.$match['path'].PHP_EOL;
                continue;
            }
            if (! is_writable($match['path']) || ! is_writable(dirname($match['path']))) {
                throw new RuntimeException('File/folder tidak writable: '.$match['path']);
            }
            $updated = str_contains($match['before'], "\r\n") ? str_replace("\n", "\r\n", $payload) : $payload;
            $crmLivePlans[] = ['path' => $match['path'], 'before' => $match['before'], 'after' => $updated];
            echo '[READY] Source cocok: '.$match['path'].PHP_EOL;
        }
        if ($crmLiveMode === '--check') {
            echo '[OK] Pemeriksaan file selesai. Perubahan tertunda: '.count($crmLivePlans).'. Belum menulis file.'.PHP_EOL;
            exit(0);
        }
        if ($crmLivePlans !== []) {
            $runId = date('Ymd_His').'_'.bin2hex(random_bytes(4));
            $backupDirectory = $crmLiveBackupBase.'/'.$runId;
            if (! mkdir($backupDirectory, 0775, true)) {
                throw new RuntimeException('Gagal membuat direktori backup.');
            }
            $backupManifest = ['patch' => 'CRM_CHAT_LIVE_SCROLL_V2', 'files' => []];
            foreach ($crmLivePlans as $i => $plan) {
                crmLiveWrite($backupDirectory.'/'.$i.'.bak', $plan['before']);
                $relative = substr(str_replace('\\', '/', $plan['path']), strlen(str_replace('\\', '/', $crmLiveRoot)) + 1);
                $backupManifest['files'][] = [
                    'relative' => $relative,
                    'backup' => $i.'.bak',
                    'original_sha256' => hash('sha256', $plan['before']),
                    'after_sha256' => crmLiveHash($plan['after']),
                ];
            }
            crmLiveWrite($backupDirectory.'/manifest.json', json_encode($backupManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            echo '[OK] Backup: '.$backupDirectory.PHP_EOL;
            echo 'Run ID: '.$runId.PHP_EOL;
        }
    } else {
        throw new RuntimeException('Opsi tidak dikenal. Gunakan --help.');
    }

    // Every source and payload is verified before the first replacement.
    foreach ($crmLivePlans as $plan) {
        if (crmLiveRead($plan['path']) !== $plan['before']) {
            throw new RuntimeException('Source berubah saat tool berjalan: '.$plan['path']);
        }
        $crmLiveAttempted[] = $plan;
        crmLiveWrite($plan['path'], $plan['after']);
        echo '[OK] Ditulis dan diverifikasi: '.$plan['path'].PHP_EOL;
    }
    echo '[OK] Selesai. File berubah: '.count($crmLivePlans).PHP_EOL;
    echo 'Berikutnya: php artisan view:clear'.PHP_EOL;
    if ($crmLiveMode === '--apply' && $crmLivePlans !== []) {
        echo 'Pemulihan: php tools/apply_crm_chat_live_scroll_v2.php --rollback='.$runId.PHP_EOL;
    }
} catch (Throwable $error) {
    foreach (array_reverse($crmLiveAttempted) as $attempt) {
        try {
            crmLiveWrite($attempt['path'], $attempt['before']);
            fwrite(STDERR, '[RESTORED] '.$attempt['path'].PHP_EOL);
        } catch (Throwable $restoreError) {
            fwrite(STDERR, '[RESTORE FAILED] '.$restoreError->getMessage().PHP_EOL);
        }
    }
    fwrite(STDERR, '[FAIL] '.$error->getMessage().PHP_EOL);
    exit(1);
}
