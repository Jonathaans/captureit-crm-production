<?php

declare(strict_types=1);

echo "CHECK CRM PRODUCTION OPERATIONS V2\n";
echo "===================================\n\n";

$root = realpath(__DIR__.DIRECTORY_SEPARATOR.'..');

if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "CHECK GAGAL: Jalankan dari root Laravel; file harus berada di tools.\n");
    exit(1);
}

function checkV2Path(string $root, string $relative): string
{
    return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function checkV2Run(string $root, array $arguments): array
{
    if (! function_exists('exec')) {
        return [null, ['exec() tidak tersedia']];
    }

    $parts = [escapeshellarg(PHP_BINARY)];
    foreach ($arguments as $argument) {
        $parts[] = escapeshellarg((string) $argument);
    }

    $output = [];
    $code = 0;
    $previous = getcwd();
    chdir($root);

    try {
        exec(implode(' ', $parts).' 2>&1', $output, $code);
    } finally {
        if ($previous !== false) {
            chdir($previous);
        }
    }

    return [$code, $output];
}

$failures = 0;
$warnings = 0;

$assert = function (bool $condition, string $label, bool $warning = false) use (&$failures, &$warnings): void {
    if ($condition) {
        echo "[OK]   {$label}\n";
        return;
    }

    if ($warning) {
        $warnings++;
        echo "[WARN] {$label}\n";
    } else {
        $failures++;
        echo "[FAIL] {$label}\n";
    }
};

$files = [
    'config/crm-production-operations.php',
    'database/migrations/2026_09_04_230000_create_crm_operational_heartbeats_table.php',
    'database/migrations/2026_09_04_231000_add_crm_production_query_indexes.php',
    'packages/Webkul/Admin/src/Services/CrmOperationalHealthService.php',
    'packages/Webkul/Admin/src/Services/CrmBackupRetentionService.php',
    'packages/Webkul/Admin/src/Services/CrmBackupObjectStorageService.php',
    'packages/Webkul/Admin/src/Services/CrmSecureUploadService.php',
    'packages/Webkul/Admin/src/Services/CrmOperationalAlertService.php',
    'packages/Webkul/Admin/src/Http/Middleware/CrmSecureUploadMiddleware.php',
    'packages/Webkul/Admin/src/Jobs/CrmQueueHeartbeatJob.php',
    'packages/Webkul/Admin/src/Console/Commands/CrmManagedBackupCommand.php',
    'packages/Webkul/Admin/src/Console/Commands/CrmManagedEmailSyncCommand.php',
    'packages/Webkul/Admin/src/Console/Commands/CrmProductionOperationsCheckCommand.php',
];

foreach ($files as $relative) {
    $exists = is_file(checkV2Path($root, $relative));
    $assert($exists, 'File tersedia: '.$relative);

    if ($exists && str_ends_with($relative, '.php')) {
        [$code, $output] = checkV2Run($root, ['-l', checkV2Path($root, $relative)]);
        $assert($code === null || $code === 0, 'PHP lint: '.$relative.($code ? ' | '.implode(' ', $output) : ''));
    }
}

$markerFiles = [
    'routes/console.php',
    'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php',
    'packages/Webkul/Admin/src/Services/OperationsDashboardService.php',
    'packages/Webkul/Admin/src/Resources/views/operations-dashboard/index.blade.php',
];

foreach ($markerFiles as $relative) {
    $contents = is_file(checkV2Path($root, $relative)) ? (string) file_get_contents(checkV2Path($root, $relative)) : '';
    $assert(str_contains($contents, 'CRM_PRODUCTION_OPERATIONS_V2'), 'Marker terpasang: '.$relative);
}

$console = (string) @file_get_contents(checkV2Path($root, 'routes/console.php'));
foreach ([
    'crm:health:scheduler',
    'crm:health:queue-dispatch',
    'crm:email-sync-managed',
    'crm:backup-managed --database-only',
    "Schedule::command('crm:backup-managed')",
    'crm:backup-retention',
    'crm:operations-alerts',
] as $needle) {
    $assert(str_contains($console, $needle), 'Schedule terpasang: '.$needle);
}
$assert(! str_contains($console, 'CRM_DAILY_FULL_BACKUP_SCHEDULE_V1'), 'Jadwal full-backup V1 yang bentrok sudah dilepas');

$hardening = (string) @file_get_contents(checkV2Path($root, 'config/crm-hardening.php'));
$assert(str_contains($hardening, 'CRM_GFS_RETENTION_V2'), 'Backup lama tidak menghapus arsip sebelum kebijakan GFS');

$appConfig = (string) @file_get_contents(checkV2Path($root, 'config/app.php'));
$assert(str_contains($appConfig, 'CRM_APP_TIMEZONE_V2'), 'Timezone aplikasi dapat dikonfigurasi melalui APP_TIMEZONE');

$manualBackupPath = checkV2Path($root, 'packages/Webkul/Admin/src/Http/Controllers/System/CrmBackupController.php');
if (is_file($manualBackupPath)) {
    $manualBackup = (string) file_get_contents($manualBackupPath);
    $assert(str_contains($manualBackup, "Artisan::call('crm:backup-managed')"), 'Tombol backup manual ikut mencatat health');
}

$emailAttachmentPath = checkV2Path($root, 'packages/Webkul/Admin/src/Services/UserEmailAttachmentService.php');
if (is_file($emailAttachmentPath)) {
    $emailAttachment = (string) file_get_contents($emailAttachmentPath);
    $assert(
        str_contains($emailAttachment, "crm-production-operations.attachments.disk"),
        'Email attachment memakai disk yang dapat dipindah ke object storage'
    );
}

$archiveInstalled = false;
$archiveRoot = checkV2Path($root, 'packages/Webkul');
if (is_dir($archiveRoot)) {
    $archiveIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($archiveRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($archiveIterator as $archiveFile) {
        if (
            $archiveFile->isFile()
            && strtolower($archiveFile->getExtension()) === 'php'
            && str_contains((string) file_get_contents($archiveFile->getPathname()), 'CRM_READ_ONLY_ARCHIVE_POLICY_V1')
        ) {
            $archiveInstalled = true;
            break;
        }
    }
}
$assert($archiveInstalled, 'Policy arsip transaksi read-only V1 tetap tersedia', true);

[$listCode, $listOutput] = checkV2Run($root, ['artisan', 'list', '--raw']);
$listText = implode("\n", $listOutput);
$assert($listCode === null || $listCode === 0, 'Laravel command registry dapat dimuat');

foreach ([
    'crm:health:scheduler',
    'crm:health:queue-dispatch',
    'crm:email-sync-managed',
    'crm:backup-managed',
    'crm:backup-retention',
    'crm:operations-alerts',
    'crm:storage:audit',
    'crm:attachments-migrate',
    'crm:production-operations-check',
] as $command) {
    $assert($listCode === null || str_contains($listText, $command), 'Command terdaftar: '.$command);
}

[$scheduleCode, $scheduleOutput] = checkV2Run($root, ['artisan', 'schedule:list']);
$scheduleText = implode("\n", $scheduleOutput);
$assert($scheduleCode === null || $scheduleCode === 0, 'Laravel schedule:list dapat dimuat');
foreach ([
    'crm:health:scheduler',
    'crm:health:queue-dispatch',
    'crm:email-sync-managed',
    'crm:backup-managed',
    'crm:backup-retention',
    'crm:operations-alerts',
] as $scheduledCommand) {
    $assert(
        $scheduleCode === null || str_contains($scheduleText, $scheduledCommand),
        'Schedule aktif: '.$scheduledCommand
    );
}

[$migrationCode, $migrationOutput] = checkV2Run($root, ['artisan', 'migrate:status']);
$migrationText = implode("\n", $migrationOutput);
$assert(
    $migrationCode === null || ($migrationCode === 0 && str_contains($migrationText, 'create_crm_operational_heartbeats_table')),
    'Migration operational heartbeat tercatat'
);
$assert(
    $migrationCode === null || ($migrationCode === 0 && str_contains($migrationText, 'add_crm_production_query_indexes')),
    'Migration index production tercatat'
);

[$viewCode, $viewOutput] = checkV2Run($root, ['artisan', 'view:cache']);
$assert($viewCode === null || $viewCode === 0, 'Semua Blade berhasil dikompilasi'.($viewCode ? ': '.implode(' ', $viewOutput) : ''));
checkV2Run($root, ['artisan', 'view:clear']);

[$heartbeatCode, $heartbeatOutput] = checkV2Run($root, ['artisan', 'crm:health:scheduler']);
$assert($heartbeatCode === null || $heartbeatCode === 0, 'Scheduler heartbeat dapat ditulis'.($heartbeatCode ? ': '.implode(' ', $heartbeatOutput) : ''));

[$readinessCode, $readinessOutput] = checkV2Run($root, ['artisan', 'crm:production-operations-check']);
if ($readinessCode !== null) {
    echo "\nPRODUCTION READINESS (tidak memengaruhi hasil instalasi):\n";
    echo implode("\n", $readinessOutput)."\n";
    if ($readinessCode !== 0) {
        $warnings++;
    }
}

echo "\n";
if ($failures > 0) {
    echo "[FAIL] Checker menemukan {$failures} masalah instalasi dan {$warnings} warning.\n";
    exit(1);
}

echo "[PASS] Instalasi lengkap. Warning production: {$warnings}.\n";
echo "Buka Operations Dashboard dan pastikan empat kartu health tampil.\n";
