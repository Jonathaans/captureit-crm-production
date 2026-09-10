<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require __DIR__.'/crm_performance_completion_v2/common.php';

echo 'CRM PERFORMANCE COMPLETION V2'.PHP_EOL;
$attempted = [];
$lock = null;

try {
    $mode = $argv[1] ?? '--help';
    if (count($argv) > 2) {
        throw new RuntimeException('Gunakan satu opsi saja.');
    }
    if ($mode === '--help') {
        echo "php tools/apply_crm_performance_completion_v2.php --check\n";
        echo "php tools/apply_crm_performance_completion_v2.php --apply\n";
        echo "php tools/apply_crm_performance_completion_v2.php --rollback=RUN_ID\n";
        exit(0);
    }
    $rollback = str_starts_with($mode, '--rollback=');
    if (! $rollback && ! in_array($mode, ['--check', '--apply'], true)) {
        throw new RuntimeException('Opsi tidak dikenal. Gunakan --help.');
    }
    $root = crmPerfRoot();
    $manifest = crmPerfManifest();
    $plans = [];
    $backupBase = 'storage/app/crm-performance-completion-v2-backups';

    if ($mode !== '--check') {
        $lock = fopen(__DIR__.'/crm_performance_completion_v2/install.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Installer/rollback lain sedang berjalan.');
        }
    }

    if ($rollback) {
        $runId = substr($mode, strlen('--rollback='));
        if (! preg_match('/^\d{8}_\d{6}_[a-f0-9]{8}$/D', $runId)) {
            throw new RuntimeException('Run ID tidak valid.');
        }
        $backupDirectory = crmPerfPath($root, $backupBase.'/'.$runId);
        $backup = crmPerfJson(crmPerfPath($backupDirectory, 'manifest.json'));
        if (($backup['patch'] ?? '') !== $manifest['patch']) {
            throw new RuntimeException('Backup bukan milik patch ini.');
        }
        $seen = [];
        foreach ($backup['files'] as $entry) {
            if (! in_array($entry['path'], crmPerfTargets(), true) || isset($seen[$entry['path']])
                || ! preg_match('/^\d+\.bak$/D', $entry['backup'])) {
                throw new RuntimeException('Target backup tidak valid.');
            }
            $seen[$entry['path']] = true;
            $path = crmPerfPath($root, $entry['path']);
            $original = crmPerfRead(crmPerfPath($backupDirectory, $entry['backup']));
            if (hash('sha256', $original) !== $entry['original_sha256']) {
                throw new RuntimeException('Backup rusak: '.$entry['path']);
            }
            $current = crmPerfRead($path);
            if ($current === $original) {
                echo '[OK] Sudah dipulihkan: '.$entry['path'].PHP_EOL;
                continue;
            }
            if (crmPerfHash($current) !== $entry['after_sha256']) {
                throw new RuntimeException('Ada edit setelah patch; rollback dihentikan: '.$entry['path']);
            }
            $plans[] = ['path' => $path, 'relative' => $entry['path'], 'before' => $current, 'after' => $original];
        }
    } else {
        foreach ($manifest['files'] as $entry) {
            $path = crmPerfPath($root, $entry['path']);
            $current = crmPerfRead($path);
            if (crmPerfHash($current) === $entry['after_sha256']) {
                echo '[OK] Sudah terpasang: '.$entry['path'].PHP_EOL;
                continue;
            }
            if (crmPerfHash($current) !== $entry['before_sha256']) {
                throw new RuntimeException('Source berbeda dari unggahan; tidak ditimpa: '.$entry['path']);
            }
            $payload = crmPerfRead(__DIR__.'/crm_performance_completion_v2/payload/'.$entry['payload']);
            $payload = str_replace("\r\n", "\n", $payload);
            if (str_contains($current, "\r\n")) {
                $payload = str_replace("\n", "\r\n", $payload);
            }
            $plans[] = ['path' => $path, 'relative' => $entry['path'], 'before' => $current, 'after' => $payload];
            echo '[READY] Source cocok: '.$entry['path'].PHP_EOL;
        }
    }

    foreach ($plans as $plan) {
        if (! is_writable($plan['path']) || ! is_writable(dirname($plan['path']))) {
            throw new RuntimeException('File/folder tidak writable: '.$plan['relative']);
        }
    }
    if ($mode === '--check') {
        echo '[OK] Preflight selesai; '.count($plans);
        echo ' file perlu dipasang. Belum ada file aplikasi diubah.'.PHP_EOL;
        exit(0);
    }

    if (! $rollback && $plans !== []) {
        crmPerfPath($root, 'storage/app');
        if (! is_dir($root.'/'.$backupBase) && ! mkdir($root.'/'.$backupBase, 0775)) {
            throw new RuntimeException('Gagal membuat direktori backup.');
        }
        crmPerfPath($root, $backupBase);
        $runId = date('Ymd_His').'_'.bin2hex(random_bytes(4));
        $backupDirectory = $root.'/'.$backupBase.'/'.$runId;
        if (! mkdir($backupDirectory, 0775)) {
            throw new RuntimeException('Gagal membuat backup baru.');
        }
        $backup = ['patch' => $manifest['patch'], 'files' => []];
        foreach ($plans as $i => $plan) {
            crmPerfWrite($backupDirectory.'/'.$i.'.bak', $plan['before']);
            $backup['files'][] = [
                'path' => $plan['relative'], 'backup' => $i.'.bak',
                'original_sha256' => hash('sha256', $plan['before']),
                'after_sha256' => crmPerfHash($plan['after']),
            ];
        }
        crmPerfWrite($backupDirectory.'/manifest.json', json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        echo '[OK] Semua backup diverifikasi: '.$backupDirectory.PHP_EOL;
        echo 'ROLLBACK: php tools/apply_crm_performance_completion_v2.php --rollback='.$runId.PHP_EOL;
    }

    foreach ($plans as $plan) {
        if (crmPerfRead($plan['path']) !== $plan['before']) {
            throw new RuntimeException('Source berubah saat installer berjalan: '.$plan['relative']);
        }
        $attempted[] = $plan;
        crmPerfWrite($plan['path'], $plan['after']);
        echo '[OK] Ditulis dan diverifikasi: '.$plan['relative'].PHP_EOL;
    }
    echo '[OK] Selesai. File berubah: '.count($plans).'. Tidak menjalankan SQL atau migrasi.'.PHP_EOL;
    echo 'Berikutnya: php artisan view:clear'.PHP_EOL;
} catch (Throwable $error) {
    foreach (array_reverse($attempted) as $plan) {
        try {
            $current = crmPerfRead($plan['path']);
            if ($current === $plan['before']) {
                continue;
            }
            if ($current !== $plan['after']) {
                throw new RuntimeException('File berubah di luar installer; pulihkan dari backup: '.$plan['relative']);
            }
            crmPerfWrite($plan['path'], $plan['before']);
            fwrite(STDERR, '[RESTORED] '.$plan['relative'].PHP_EOL);
        } catch (Throwable $restoreError) {
            fwrite(STDERR, '[RESTORE FAILED] '.$restoreError->getMessage().PHP_EOL);
        }
    }
    fwrite(STDERR, '[FAIL] '.$error->getMessage().PHP_EOL);
    exit(1);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
