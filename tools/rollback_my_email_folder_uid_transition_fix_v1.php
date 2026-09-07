<?php
declare(strict_types=1);

echo "ROLLBACK MY EMAIL FOLDER UID TRANSITION FIX V1\n";
echo "==============================================\n\n";

$root = dirname(__DIR__);
$target = $root . '/packages/Webkul/Admin/src/Services/UserEmailDeliveryService.php';
$pattern = $target . '.bak-my-email-folder-uid-transition-v1-*';

$backups = glob($pattern) ?: [];

if ($backups === []) {
    fwrite(STDERR, "[FAIL] Backup V1 tidak ditemukan.\n");
    exit(1);
}

usort(
    $backups,
    static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a)
);

$backup = $backups[0];

if (! copy($backup, $target)) {
    fwrite(STDERR, "[FAIL] Gagal restore backup.\n");
    exit(1);
}

$lintCmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target);
exec($lintCmd . ' 2>&1', $lintOut, $lintExit);

if ($lintExit !== 0) {
    fwrite(STDERR, "[FAIL] File hasil rollback gagal lint.\n");
    fwrite(STDERR, implode(PHP_EOL, $lintOut) . PHP_EOL);
    exit(1);
}

echo "Restored:\n{$backup}\n\n";
echo "[OK] Rollback selesai.\n";
echo "Jalankan: php artisan optimize:clear\n";
