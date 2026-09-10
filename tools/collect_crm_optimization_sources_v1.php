<?php

declare(strict_types=1);

// Source collection only. No Laravel bootstrap, SQL, application writes or upload.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$crmReviewRoot = dirname(__DIR__);
$crmReviewMode = $argv[1] ?? '--help';

// Fixed source paths; .env, logs, database exports and vendor are not collected.
// true = needed for the outstanding review, false = helpful if present.
$crmReviewPaths = [
    'packages/Webkul/Admin/src/DataGrids/Invoice/InvoiceDataGrid.php' => true,
    'packages/Webkul/Admin/src/DataGrids/Quote/QuoteDataGrid.php' => true,
    'packages/Webkul/Admin/src/Services/InternalChatService.php' => true,
    'packages/Webkul/Admin/src/Services/InternalChatRealtimeService.php' => true,
    'tools/apply_crm_targeted_performance_optimization_v1.php' => true,
    'tools/check_crm_targeted_performance_optimization_v1.php' => true,
    'database/migrations/2026_09_09_200000_add_crm_targeted_performance_indexes_v1.php' => true,
    'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php' => true,
    'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatConversationController.php' => true,
    'packages/Webkul/Admin/src/Models/InternalMessage.php' => true,
    'packages/Webkul/Admin/src/Models/InternalConversationMember.php' => true,
    'packages/Webkul/Admin/src/Models/InternalConversation.php' => false,
    'packages/Webkul/Admin/src/Models/InternalMessageAttachment.php' => false,
    'packages/Webkul/Admin/src/Services/WorkflowNotificationService.php' => false,
    'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php' => false,
    'packages/Webkul/Admin/src/Resources/views/internal-communication/realtime-chat-listeners.blade.php' => false,
    'packages/Webkul/Admin/src/Resources/views/internal-communication/realtime-config.blade.php' => false,
    'packages/Webkul/Admin/src/Resources/views/internal-communication/chat-unread-badge.blade.php' => false,
    'packages/Webkul/DataGrid/src/DataGrid.php' => false,
    'packages/Webkul/Product/src/Models/Product.php' => false,
    'packages/Webkul/Product/src/Repositories/ProductRepository.php' => false,
    'packages/Webkul/Admin/src/Http/Controllers/Product/ProductController.php' => false,
];

function crmReviewResolve(string $root, string $relative): ?string
{
    $path = $root.'/'.$relative;
    if (! is_file($path)) {
        return null;
    }
    $resolved = realpath($path);
    $base = realpath($root);
    if ($resolved === false || $base === false) {
        throw new RuntimeException('Gagal memeriksa path: '.$relative);
    }
    $normalized = str_replace('\\', '/', $resolved);
    $prefix = rtrim(str_replace('\\', '/', $base), '/').'/';
    if (PHP_OS_FAMILY === 'Windows') {
        $normalized = strtolower($normalized);
        $prefix = strtolower($prefix);
    }
    if (! str_starts_with($normalized, $prefix)) {
        throw new RuntimeException('Source menunjuk ke luar project: '.$relative);
    }
    return $resolved;
}

echo 'CRM OPTIMIZATION SOURCE REVIEW V1'.PHP_EOL;

try {
    if (count($argv) > 2 || ! in_array($crmReviewMode, ['--help', '--list', '--collect'], true)) {
        throw new RuntimeException('Gunakan satu opsi: --list atau --collect.');
    }
    if ($crmReviewMode === '--help') {
        echo 'php tools/collect_crm_optimization_sources_v1.php --list'.PHP_EOL;
        echo 'php tools/collect_crm_optimization_sources_v1.php --collect'.PHP_EOL;
        echo 'Output: satu JSON source di folder tools; tidak dikirim otomatis.'.PHP_EOL;
        exit(0);
    }
    if (! is_file($crmReviewRoot.'/artisan') || ! is_file($crmReviewRoot.'/composer.json')) {
        throw new RuntimeException('Extract folder tools ke root project Laravel yang memiliki artisan.');
    }

    $crmReviewBundle = [
        'format' => 'crm-optimization-source-review',
        'version' => 1,
        'created_at' => date(DATE_ATOM),
        'purpose' => 'Review eight optimization requirements; this is source code, not a patch or database backup.',
        'files' => [],
        'missing_required' => [],
        'missing_optional' => [],
    ];
    $crmReviewTotal = 0;

    foreach ($crmReviewPaths as $relative => $required) {
        $resolved = crmReviewResolve($crmReviewRoot, $relative);
        if ($resolved === null) {
            $crmReviewBundle[$required ? 'missing_required' : 'missing_optional'][] = $relative;
            echo ($required ? '[MISSING] ' : '[OPTIONAL MISSING] ').$relative.PHP_EOL;
            continue;
        }
        if (! is_readable($resolved)) {
            throw new RuntimeException('File tidak dapat dibaca: '.$relative);
        }
        echo '[FOUND] '.$relative.PHP_EOL;
        if ($crmReviewMode === '--list') {
            continue;
        }
        $size = filesize($resolved);
        if ($size === false || $size > 5 * 1024 * 1024) {
            throw new RuntimeException('Ukuran source tidak valid atau melebihi 5 MiB: '.$relative);
        }
        $contents = file_get_contents($resolved);
        if ($contents === false) {
            throw new RuntimeException('Gagal membaca source: '.$relative);
        }
        $crmReviewTotal += strlen($contents);
        if (strlen($contents) > 5 * 1024 * 1024 || $crmReviewTotal > 20 * 1024 * 1024) {
            throw new RuntimeException('Batas ukuran source terlampaui. Tidak ada file aplikasi yang diubah.');
        }
        $crmReviewBundle['files'][] = [
            'path' => $relative,
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'encoding' => 'base64',
            'content_base64' => base64_encode($contents),
        ];
    }

    if ($crmReviewMode === '--list') {
        echo 'Pemeriksaan daftar selesai; tidak ada file ditulis.'.PHP_EOL;
        exit(0);
    }
    if ($crmReviewBundle['files'] === []) {
        throw new RuntimeException('Tidak ada source yang ditemukan. Periksa lokasi project.');
    }

    $json = json_encode($crmReviewBundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    $outputName = 'crm-optimization-sources-'.date('Ymd_His').'-'.bin2hex(random_bytes(4)).'.json';
    $output = __DIR__.'/'.$outputName;
    $handle = fopen($output, 'xb');
    if ($handle === false) {
        throw new RuntimeException('Gagal membuat berkas output baru: '.$output);
    }
    try {
        $offset = 0;
        $length = strlen($json);
        while ($offset < $length) {
            $written = fwrite($handle, substr($json, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Penulisan output tidak lengkap: '.$output);
            }
            $offset += $written;
        }
        if (! fflush($handle)) {
            throw new RuntimeException('Gagal menyelesaikan output: '.$output);
        }
    } finally {
        fclose($handle);
    }
    if (hash_file('sha256', $output) !== hash('sha256', $json)) {
        throw new RuntimeException('Verifikasi output gagal; jangan unggah file ini: '.$output);
    }

    echo PHP_EOL.'[OK] Berkas review dibuat; '.count($crmReviewBundle['files']).' file source.'.PHP_EOL;
    if ($crmReviewBundle['missing_required'] !== []) {
        echo '[PARTIAL] '.count($crmReviewBundle['missing_required']).' file wajib belum ditemukan; daftar tercatat dalam JSON.'.PHP_EOL;
    }
    echo 'OUTPUT: '.$output.PHP_EOL;
    echo 'Unggah JSON tersebut ke percakapan ini. Tidak ada pengiriman otomatis.'.PHP_EOL;
    echo 'Ini pengumpulan source; belum memasang perubahan optimasi.'.PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] '.$exception->getMessage().PHP_EOL);
    exit(1);
}
