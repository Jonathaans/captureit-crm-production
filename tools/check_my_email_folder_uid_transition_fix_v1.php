<?php
declare(strict_types=1);

echo "CHECK MY EMAIL FOLDER UID TRANSITION FIX V1\n";
echo "===========================================\n\n";

$root = dirname(__DIR__);
$target = $root . '/packages/Webkul/Admin/src/Services/UserEmailDeliveryService.php';

$fails = 0;

$check = static function (bool $ok, string $label) use (&$fails): void {
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $label . PHP_EOL;
    if (! $ok) {
        $fails++;
    }
};

$check(is_file($target), 'UserEmailDeliveryService tersedia');

if (! is_file($target)) {
    echo "\nHASIL: FAIL\n";
    exit(1);
}

$source = file_get_contents($target) ?: '';

$check(
    substr_count($source, 'MY_EMAIL_FOLDER_UID_TRANSITION_FIX_V1') === 2,
    'Marker patch tepat dua kali'
);

$check(
    str_contains(
        $source,
        "\$message->imap_uid =\n                (int) \$message->id;"
    ),
    'UID berbasis message id tersedia'
);

$draftPos = strpos($source, 'Draft already owns an imap_uid in the DRAFT namespace.');
$outboxPos = strpos($source, "\$message->folder =\n            'OUTBOX';");

$check(
    $draftPos !== false && $outboxPos !== false && $draftPos < $outboxPos,
    'Draft -> OUTBOX melakukan re-key sebelum pindah folder'
);

$sentCommentPos = strpos($source, 'The OUTBOX UID');
$crmSentPos = strpos($source, "\$message->folder =\n                'CRM_SENT';");

$check(
    $sentCommentPos !== false && $crmSentPos !== false && $sentCommentPos < $crmSentPos,
    'OUTBOX -> CRM_SENT melakukan re-key sebelum pindah folder'
);

$check(
    str_contains($source, "private function nextLocalUid("),
    'Generator UID lokal existing tetap tersedia'
);

$lintCmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target);
exec($lintCmd . ' 2>&1', $lintOut, $lintExit);
$check($lintExit === 0, 'PHP lint PASS');

echo PHP_EOL;

if ($fails > 0) {
    echo "HASIL: FAIL ({$fails} masalah)\n";
    exit(1);
}

echo "HASIL: PASS\n\n";
echo "QA yang benar:\n";
echo "1. JANGAN Retry email FAILED lama dulu.\n";
echo "2. Kirim SATU email test baru dari My Email.\n";
echo "3. Pastikan penerima menerima tepat satu email.\n";
echo "4. Pastikan email baru pindah ke Sent dan tidak tinggal FAILED di Outbox.\n";
echo "5. Baru tentukan penanganan record FAILED lama setelah memastikan apakah SMTP sebelumnya sudah mengirimnya.\n";
