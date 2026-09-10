<?php

declare(strict_types=1);

/**
 * CRM Targeted Performance Tools Repair V2
 *
 * Memperbaiki perhitungan root project pada tool apply dan checker V1.
 * Tidak mengubah source CRM, database, migration, atau data bisnis.
 */

echo "CRM TARGETED PERFORMANCE TOOLS REPAIR V2\n";
echo "========================================\n\n";

$toolsDirectory = __DIR__;
$projectRoot = dirname($toolsDirectory);
$brokenLine = '$root = dirname(__DIR__, 2);';
$fixedLine = '$root = dirname(__DIR__);';
$timestamp = date('Ymd_His');

$targets = [
    'apply' => $toolsDirectory.'/apply_crm_targeted_performance_optimization_v1.php',
    'checker' => $toolsDirectory.'/check_crm_targeted_performance_optimization_v1.php',
];

$plans = [];
$changed = [];

try {
    if (! is_file($projectRoot.'/artisan')) {
        throw new RuntimeException(
            "Root Laravel tidak ditemukan: {$projectRoot}\n".
            "Extract folder tools dari ZIP ke root project Laravel."
        );
    }

    // Preflight seluruh target sebelum ada file yang diubah.
    foreach ($targets as $label => $path) {
        if (! is_file($path)) {
            throw new RuntimeException("Tool {$label} tidak ditemukan: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Tidak dapat membaca tool {$label}: {$path}");
        }

        $brokenCount = substr_count($contents, $brokenLine);
        $fixedCount = substr_count($contents, $fixedLine);

        if ($brokenCount === 0 && $fixedCount === 1) {
            $plans[$label] = [
                'path' => $path,
                'contents' => $contents,
                'action' => 'already-fixed',
            ];
            continue;
        }

        if ($brokenCount === 1 && $fixedCount === 0) {
            $plans[$label] = [
                'path' => $path,
                'contents' => $contents,
                'action' => 'repair',
            ];
            continue;
        }

        throw new RuntimeException(
            "Pola root tool {$label} tidak sesuai versi yang didukung ".
            "(lama={$brokenCount}, baru={$fixedCount}). Tidak ada file yang diubah."
        );
    }

    foreach ($plans as $label => $plan) {
        if ($plan['action'] === 'already-fixed') {
            echo "[OK] {$label}: root project sudah benar.\n";
            continue;
        }

        $path = $plan['path'];
        $backup = $path.'.before_root_fix_v2_'.$timestamp.'.bak';

        if (! copy($path, $backup)) {
            throw new RuntimeException("Gagal membuat backup tool {$label}: {$backup}");
        }

        $updated = str_replace(
            $brokenLine,
            $fixedLine,
            $plan['contents'],
            $replacementCount
        );

        if ($replacementCount !== 1) {
            throw new RuntimeException("Jumlah perubahan tool {$label} tidak sesuai.");
        }

        $bytesWritten = file_put_contents($path, $updated, LOCK_EX);

        if ($bytesWritten === false || $bytesWritten !== strlen($updated)) {
            copy($backup, $path);
            throw new RuntimeException("Gagal menulis tool {$label} secara lengkap.");
        }

        $verification = file_get_contents($path);

        if (
            $verification === false
            || substr_count($verification, $brokenLine) !== 0
            || substr_count($verification, $fixedLine) !== 1
        ) {
            copy($backup, $path);
            throw new RuntimeException("Verifikasi tool {$label} gagal.");
        }

        $changed[$label] = [
            'path' => $path,
            'backup' => $backup,
        ];

        echo "[OK] {$label}: root project diperbaiki.\n";
        echo "     Backup: {$backup}\n";
    }

    echo "\n[OK] Seluruh tool lolos pemeriksaan root.\n\n";
    echo "Jalankan checker kembali:\n";
    echo "php tools/check_crm_targeted_performance_optimization_v1.php ".
        "--database=captureit_crm_performance\n";
} catch (Throwable $exception) {
    foreach (array_reverse($changed) as $item) {
        copy($item['backup'], $item['path']);
    }

    if ($changed !== []) {
        fwrite(STDERR, "Perubahan pada tool lain telah dipulihkan dari backup.\n");
    }

    fwrite(STDERR, 'REPAIR GAGAL: '.$exception->getMessage().PHP_EOL);
    exit(1);
}
