<?php
declare(strict_types=1);

echo "MY EMAIL FOLDER UID TRANSITION FIX V1\n";
echo "=====================================\n\n";

$root = dirname(__DIR__);
$target = $root . '/packages/Webkul/Admin/src/Services/UserEmailDeliveryService.php';

if (! is_file($target)) {
    fwrite(STDERR, "[FAIL] Target tidak ditemukan: {$target}\n");
    exit(1);
}

$source = file_get_contents($target);

if ($source === false) {
    fwrite(STDERR, "[FAIL] Tidak dapat membaca target.\n");
    exit(1);
}

$marker = 'MY_EMAIL_FOLDER_UID_TRANSITION_FIX_V1';

if (substr_count($source, $marker) >= 2) {
    echo "[OK] Patch tampaknya sudah terpasang. Tidak ada perubahan.\n";
    exit(0);
}

$backup = $target . '.bak-my-email-folder-uid-transition-v1-' . date('Ymd-His');

if (! copy($target, $backup)) {
    fwrite(STDERR, "[FAIL] Gagal membuat backup.\n");
    exit(1);
}

echo "Backup:\n{$backup}\n\n";

$draftAnchor = <<<'PHP'
            $message->direction =
                'outgoing';
        }

        $message->folder =
            'OUTBOX';
PHP;

$draftReplacement = <<<'PHP'
            $message->direction =
                'outgoing';
        } else {
            /*
             * MY_EMAIL_FOLDER_UID_TRANSITION_FIX_V1
             *
             * Draft already owns an imap_uid in the DRAFT namespace.
             * When it moves to OUTBOX, give the existing local message a
             * deterministic UID based on its immutable database id.
             * This avoids carrying a DRAFT UID into another folder namespace.
             */
            $message->imap_uid =
                (int) $message->id;
        }

        $message->folder =
            'OUTBOX';
PHP;

$draftCount = substr_count($source, $draftAnchor);

if ($draftCount !== 1) {
    copy($backup, $target);
    fwrite(STDERR, "[FAIL] Anchor DRAFT -> OUTBOX ditemukan {$draftCount} kali; expected 1.\n");
    fwrite(STDERR, "Source dipulihkan dari backup.\n");
    exit(1);
}

$source = str_replace($draftAnchor, $draftReplacement, $source, $draftReplaced);

$sentAnchor = <<<'PHP'
            $message->folder =
                'CRM_SENT';

            $message->delivery_status =
                'sent';
PHP;

$sentReplacement = <<<'PHP'
            /*
             * MY_EMAIL_FOLDER_UID_TRANSITION_FIX_V1
             *
             * imap_uid is unique inside (account_id, folder).  The OUTBOX UID
             * must therefore never be carried into CRM_SENT.  This message
             * already has a database id, which is monotonic and unique, so it
             * is a safe local UID for the CRM_SENT namespace and avoids the
             * duplicate-key failure seen when OUTBOX uid 1/2 collided with
             * existing CRM_SENT uid 1/2.
             */
            $message->imap_uid =
                (int) $message->id;

            $message->folder =
                'CRM_SENT';

            $message->delivery_status =
                'sent';
PHP;

$sentCount = substr_count($source, $sentAnchor);

if ($sentCount !== 1) {
    copy($backup, $target);
    fwrite(STDERR, "[FAIL] Anchor OUTBOX -> CRM_SENT ditemukan {$sentCount} kali; expected 1.\n");
    fwrite(STDERR, "Source dipulihkan dari backup.\n");
    exit(1);
}

$source = str_replace($sentAnchor, $sentReplacement, $source, $sentReplaced);

if ($draftReplaced !== 1 || $sentReplaced !== 1) {
    copy($backup, $target);
    fwrite(STDERR, "[FAIL] Replacement count tidak sesuai.\n");
    exit(1);
}

if (file_put_contents($target, $source) === false) {
    copy($backup, $target);
    fwrite(STDERR, "[FAIL] Gagal menulis target; source dipulihkan.\n");
    exit(1);
}

$lintCmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target);
exec($lintCmd . ' 2>&1', $lintOut, $lintExit);

if ($lintExit !== 0) {
    copy($backup, $target);
    fwrite(STDERR, "[FAIL] PHP lint gagal. Source dipulihkan.\n");
    fwrite(STDERR, implode(PHP_EOL, $lintOut) . PHP_EOL);
    exit(1);
}

echo "[OK] Draft -> OUTBOX sekarang mendapat UID namespace baru.\n";
echo "[OK] OUTBOX -> CRM_SENT sekarang mengganti imap_uid memakai message id.\n";
echo "[OK] Unique index database tidak diubah.\n";
echo "[OK] Schema database tidak diubah.\n";
echo "[OK] PHP lint PASS.\n\n";
echo "SELESAI.\n";
echo "Lanjutkan:\n";
echo "  php artisan optimize:clear\n";
echo "  php tools/check_my_email_folder_uid_transition_fix_v1.php\n";
