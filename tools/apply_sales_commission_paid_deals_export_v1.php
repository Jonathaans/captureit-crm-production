<?php

declare(strict_types=1);

const PATCH_TITLE = 'SALES COMMISSION — PAID DEALS EXPORT V1';
const PATCH_MARKER = 'CRM_SALES_COMMISSION_PAID_DEALS_EXPORT_V1';

$root = dirname(__DIR__);
$payloadRoot = __DIR__.DIRECTORY_SEPARATOR.'sales_commission_paid_deals_export_v1_payload';
$backupDirectory = null;
$originals = [];
$writesStarted = false;

$serviceRelative = 'packages/Webkul/Admin/src/Services/SalesCommissionExportService.php';
$controllerRelative = 'packages/Webkul/Admin/src/Http/Controllers/Invoice/SalesCommissionExportController.php';
$providerRelative = 'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php';
$viewRelative = 'packages/Webkul/Admin/src/Resources/views/invoices/finance-sales-dashboard.blade.php';
$targets = [$serviceRelative, $controllerRelative, $providerRelative, $viewRelative];

function sceLine(string $message = ''): void
{
    echo $message.PHP_EOL;
}

function sceFail(string $message): never
{
    throw new RuntimeException($message);
}

function scePath(string $root, string $relative): string
{
    return $root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function sceRead(string $path): string
{
    if (! is_file($path)) {
        sceFail('File tidak ditemukan: '.$path);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        sceFail('File tidak dapat dibaca: '.$path);
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function sceWrite(string $path, string $content): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        sceFail('Folder tidak dapat dibuat: '.$directory);
    }

    $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        sceFail('File sementara tidak dapat ditulis: '.$temporary);
    }

    if (is_file($path) && ! unlink($path)) {
        @unlink($temporary);
        sceFail('File target tidak dapat diganti: '.$path);
    }

    if (! rename($temporary, $path)) {
        @unlink($temporary);
        sceFail('File target tidak dapat disimpan: '.$path);
    }
}

function sceReplaceOnce(string $content, string $search, string $replacement, string $label): string
{
    $count = substr_count($content, $search);

    if ($count !== 1) {
        sceFail('Preflight '.$label.' harus ditemukan tepat satu kali; count='.$count.'.');
    }

    return str_replace($search, $replacement, $content);
}

function sceRun(string $root, array $arguments): int
{
    $command = escapeshellarg(PHP_BINARY);

    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg((string) $argument);
    }

    sceLine('[RUN]   '.implode(' ', $arguments));
    $previous = getcwd();
    chdir($root);
    passthru($command, $exitCode);

    if ($previous !== false) {
        chdir($previous);
    }

    return (int) $exitCode;
}

sceLine(PATCH_TITLE);
sceLine(str_repeat('=', strlen(PATCH_TITLE)));
sceLine();

try {
    if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
        sceFail('Jalankan tool dari root project Laravel.');
    }

    $servicePath = scePath($root, $serviceRelative);
    $controllerPath = scePath($root, $controllerRelative);
    $providerPath = scePath($root, $providerRelative);
    $viewPath = scePath($root, $viewRelative);

    $installed = is_file($servicePath)
        && is_file($controllerPath)
        && str_contains(sceRead($servicePath), PATCH_MARKER)
        && str_contains(sceRead($controllerPath), PATCH_MARKER)
        && str_contains(sceRead($providerPath), PATCH_MARKER)
        && str_contains(sceRead($viewPath), PATCH_MARKER);

    if ($installed) {
        sceLine('[OK] Patch sudah terpasang.');
        sceLine('Lanjutkan: php tools/check_sales_commission_paid_deals_export_v1.php');
        exit(0);
    }

    foreach ([$servicePath, $controllerPath] as $newPath) {
        if (is_file($newPath)) {
            sceFail('Target baru sudah ada tetapi patch tidak lengkap: '.$newPath);
        }
    }

    $provider = sceRead($providerPath);
    $view = sceRead($viewPath);

    if (str_contains($provider, PATCH_MARKER) || str_contains($view, PATCH_MARKER)) {
        sceFail('Instalasi parsial terdeteksi. Pulihkan backup/rollback sebelum mengulang patch.');
    }

    if (! str_contains($provider, 'CRM_FINANCE_SALES_DASHBOARD_V1')) {
        sceFail('Dependency Finance & Sales Dashboard V1 tidak ditemukan pada provider.');
    }

    if (! str_contains($view, 'CRM_FINANCE_SALES_DASHBOARD_UI_HOTFIX_V1_1')) {
        sceFail('Dependency UI Finance & Sales Dashboard V1.1 tidak ditemukan.');
    }

    if (! str_contains($view, 'CRM_FINANCE_SALES_DASHBOARD_STYLE_STABILITY_V1_2')) {
        sceFail('Pasang Style Stability Hotfix V1.2 sebelum export komisi.');
    }

    $providerAnchor = "        if (\$this->app->runningInConsole()) {";
    $providerAddition = <<<'PHP'
        /* CRM_SALES_COMMISSION_PAID_DEALS_EXPORT_V1 */
        \Illuminate\Support\Facades\Route::middleware('web')
            ->get(
                'admin/finance-sales-dashboard/commission-export',
                [
                    \Webkul\Admin\Http\Controllers\Invoice\SalesCommissionExportController::class,
                    'export',
                ]
            )
            ->name('admin.finance-sales-dashboard.commission-export');

PHP;
    $provider = sceReplaceOnce(
        $provider,
        $providerAnchor,
        $providerAddition.$providerAnchor,
        'anchor route commission export',
    );

    $viewAnchor = <<<'BLADE'
        </form>

        <section class="fsd-kpi-grid">
BLADE;
    $viewAddition = <<<'BLADE'
        </form>

        {{-- CRM_SALES_COMMISSION_PAID_DEALS_EXPORT_V1 --}}
        <section class="fsd-panel" aria-labelledby="sales-commission-export-title">
            <div class="fsd-section-head">
                <div>
                    <span class="fsd-eyebrow">SALES COMMISSION</span>
                    <h2 id="sales-commission-export-title">Export Deal Lunas per Sales</h2>
                    <p>Komisi hanya memakai uang dari deal yang sudah lunas penuh. DP saja tidak dihitung; DP + Pelunasan diekspor satu kali setelah seluruh pembayaran diterima.</p>
                </div>
            </div>

            <form class="fsd-filters" method="GET" action="{{ route('admin.finance-sales-dashboard.commission-export') }}">
                <div class="fsd-filter-grid">
                    <div class="fsd-field">
                        <label for="fsd-commission-from">DARI TANGGAL LUNAS</label>
                        <input
                            id="fsd-commission-from"
                            class="fsd-control"
                            type="date"
                            name="paid_from"
                            value="{{ request('paid_from', now()->startOfYear()->toDateString()) }}"
                            required
                        >
                    </div>

                    <div class="fsd-field">
                        <label for="fsd-commission-to">SAMPAI TANGGAL LUNAS</label>
                        <input
                            id="fsd-commission-to"
                            class="fsd-control"
                            type="date"
                            name="paid_to"
                            value="{{ request('paid_to', now()->toDateString()) }}"
                            required
                        >
                    </div>

                    <div class="fsd-field">
                        <label for="fsd-commission-sales">SALES OWNER</label>
                        <select id="fsd-commission-sales" class="fsd-control" name="sales_user_id">
                            <option value="0">Semua Sales</option>
                            @foreach ($salesUsers as $salesUser)
                                <option value="{{ $salesUser->id }}" @selected((int) request('sales_user_id', $filters['sales_user_id'] ?? 0) === (int) $salesUser->id)>
                                    {{ $salesUser->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fsd-field">
                        <label for="fsd-commission-unit">BUSINESS UNIT</label>
                        <select id="fsd-commission-unit" class="fsd-control" name="business_unit">
                            <option value="">Semua Unit</option>
                            @foreach ($businessUnits as $unit)
                                <option value="{{ $unit['value'] }}" @selected(request('business_unit', $filters['business_unit'] ?? '') === $unit['value'])>
                                    {{ $unit['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="fsd-filter-footer">
                    <p class="fsd-filter-note">CSV berisi Sales, invoice, produk, kode project, nilai deal, uang diterima, tanggal lunas, dan dasar komisi.</p>
                    <div class="fsd-actions">
                        <button class="primary-button" type="submit">Export CSV Komisi Sales</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="fsd-kpi-grid">
BLADE;
    $view = sceReplaceOnce($view, $viewAnchor, $viewAddition, 'panel sebelum KPI');

    $servicePayload = scePath($payloadRoot, $serviceRelative);
    $controllerPayload = scePath($payloadRoot, $controllerRelative);

    if (! is_file($servicePayload) || ! is_file($controllerPayload)) {
        sceFail('Payload patch tidak lengkap. Ekstrak seluruh isi ZIP ke root project.');
    }

    $timestamp = date('Ymd-His');
    $backupDirectory = $root.DIRECTORY_SEPARATOR.'tools'
        .DIRECTORY_SEPARATOR.'backups'
        .DIRECTORY_SEPARATOR.'sales-commission-paid-deals-export-v1-'.$timestamp;
    $manifest = [
        'patch' => PATCH_TITLE,
        'created_at' => date(DATE_ATOM),
        'files' => [],
    ];

    foreach ($targets as $relative) {
        $targetPath = scePath($root, $relative);
        $exists = is_file($targetPath);
        $manifest['files'][$relative] = ['existed' => $exists];
        $originals[$relative] = $exists ? sceRead($targetPath) : null;

        if ($exists) {
            sceWrite(scePath($backupDirectory.DIRECTORY_SEPARATOR.'files', $relative), $originals[$relative]);
        }
    }

    sceWrite(
        $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
    );
    sceWrite(
        $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'backups'
            .DIRECTORY_SEPARATOR.'sales-commission-paid-deals-export-v1-latest.txt',
        $backupDirectory.PHP_EOL,
    );

    $writesStarted = true;
    sceWrite($servicePath, sceRead($servicePayload));
    sceLine('[WRITE] '.$serviceRelative);
    sceWrite($controllerPath, sceRead($controllerPayload));
    sceLine('[WRITE] '.$controllerRelative);
    sceWrite($providerPath, $provider);
    sceLine('[PATCH] '.$providerRelative);
    sceWrite($viewPath, $view);
    sceLine('[PATCH] '.$viewRelative);

    foreach ([$serviceRelative, $controllerRelative, $providerRelative] as $relative) {
        if (sceRun($root, ['-l', $relative]) !== 0) {
            sceFail('PHP lint gagal: '.$relative);
        }
    }

    if (sceRun($root, ['artisan', 'optimize:clear']) !== 0) {
        sceFail('artisan optimize:clear gagal.');
    }

    if (sceRun($root, ['artisan', 'view:cache']) !== 0) {
        sceFail('Blade gagal dikompilasi.');
    }

    if (sceRun($root, ['artisan', 'route:list', '--name=admin.finance-sales-dashboard.commission-export']) !== 0) {
        sceFail('Route export komisi tidak dapat dimuat.');
    }

    sceLine();
    sceLine('PATCH BERHASIL.');
    sceLine('Backup source: '.$backupDirectory);
    sceLine('Lanjutkan dengan:');
    sceLine('php tools/check_sales_commission_paid_deals_export_v1.php');
} catch (Throwable $exception) {
    if ($writesStarted) {
        foreach (array_reverse($targets) as $relative) {
            $targetPath = scePath($root, $relative);
            $original = $originals[$relative] ?? null;

            try {
                if ($original === null) {
                    if (is_file($targetPath)) {
                        unlink($targetPath);
                    }
                } else {
                    sceWrite($targetPath, $original);
                }
            } catch (Throwable $restoreException) {
                sceLine('PERINGATAN rollback otomatis gagal untuk '.$relative.': '.$restoreException->getMessage());
            }
        }

        sceLine('Perubahan source dipulihkan otomatis.');
    }

    sceLine();
    sceLine('PATCH GAGAL: '.$exception->getMessage());
    exit(1);
}
