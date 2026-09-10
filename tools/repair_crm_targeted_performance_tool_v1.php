<?php

declare(strict_types=1);

/**
 * CRM Targeted Performance Tool Repair V1
 *
 * Memperbaiki perhitungan root pada:
 * tools/apply_crm_targeted_performance_optimization_v1.php
 *
 * Script ini hanya mengubah satu baris yang sudah diverifikasi dan selalu
 * membuat backup sebelum menulis perubahan.
 */

echo "CRM TARGETED PERFORMANCE TOOL REPAIR V1\n";
echo "=======================================\n\n";

$toolsDirectory = __DIR__;
$projectRoot = dirname($toolsDirectory);
$target = $toolsDirectory.'/apply_crm_targeted_performance_optimization_v1.php';

$brokenLine = '$root = dirname(__DIR__, 2);';
$fixedLine = '$root = dirname(__DIR__);';

try {
    if (! is_file($projectRoot.'/artisan')) {
        throw new RuntimeException(
            "Root Laravel tidak ditemukan: {$projectRoot}\n".
            "Extract folder tools dari ZIP ini ke root project Laravel, lalu jalankan kembali."
        );
    }

    if (! is_file($target)) {
        throw new RuntimeException(
            "Script target tidak ditemukan: {$target}\n".
            "Pastikan apply_crm_targeted_performance_optimization_v1.php berada di folder tools yang sama."
        );
    }

    $contents = file_get_contents($target);

    if ($contents === false) {
        throw new RuntimeException("Tidak dapat membaca script target: {$target}");
    }

    $brokenCount = substr_count($contents, $brokenLine);
    $fixedCount = substr_count($contents, $fixedLine);

    if ($brokenCount === 0 && $fixedCount === 1) {
        echo "[OK] Script target sudah menggunakan root project yang benar.\n";
        echo "Tidak ada perubahan yang diperlukan.\n\n";
        echo "Langkah berikutnya:\n";
        echo "php tools/apply_crm_targeted_performance_optimization_v1.php\n";
        exit(0);
    }

    if ($brokenCount !== 1) {
        throw new RuntimeException(
            "Pola lama tidak ditemukan secara unik (ditemukan {$brokenCount} kali).\n".
            "Script dihentikan agar tidak mengubah file yang versinya berbeda."
        );
    }

    if ($fixedCount !== 0) {
        throw new RuntimeException(
            "Pola lama dan pola baru ditemukan bersamaan. Script target perlu diperiksa manual."
        );
    }

    $backup = $target.'.before_root_fix_'.date('Ymd_His').'.bak';

    if (! copy($target, $backup)) {
        throw new RuntimeException("Gagal membuat backup: {$backup}");
    }

    $updated = str_replace($brokenLine, $fixedLine, $contents, $replacementCount);

    if ($replacementCount !== 1) {
        throw new RuntimeException(
            "Jumlah perubahan tidak sesuai ({$replacementCount}). File asli belum diubah."
        );
    }

    $bytesWritten = file_put_contents($target, $updated, LOCK_EX);

    if ($bytesWritten === false || $bytesWritten !== strlen($updated)) {
        copy($backup, $target);
        throw new RuntimeException(
            "Gagal menulis perubahan secara lengkap. File asli telah dipulihkan dari backup."
        );
    }

    $verification = file_get_contents($target);

    if ($verification === false || substr_count($verification, $fixedLine) !== 1) {
        copy($backup, $target);
        throw new RuntimeException(
            "Verifikasi perubahan gagal. File asli telah dipulihkan dari backup."
        );
    }

    echo "[OK] Root project diperbaiki.\n";
    echo "[OK] Backup dibuat: {$backup}\n";
    echo "[OK] Script target lolos verifikasi.\n\n";
    echo "Langkah berikutnya:\n";
    echo "php tools/apply_crm_targeted_performance_optimization_v1.php\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'REPAIR GAGAL: '.$exception->getMessage().PHP_EOL);
    exit(1);
}
