<?php
declare(strict_types=1);

echo "MISSING ASSET RECOVERY V1\n";
echo "=========================\n\n";

$root = dirname(__DIR__);
chdir($root);

$controller = $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryAssetController.php';
$routes     = $root.'/routes/web.php';
$grid       = $root.'/packages/Webkul/Admin/src/DataGrids/Inventory/InventoryMovementDataGrid.php';

$required = [$controller, $routes];

foreach ($required as $file) {
    if (! is_file($file)) {
        fwrite(STDERR, "[FAIL] File wajib tidak ditemukan: {$file}\n");
        exit(1);
    }
}

function backupFile(string $file, string $tag): string
{
    $backup = $file.'.bak-'.$tag.'-'.date('Ymd-His');

    if (! copy($file, $backup)) {
        throw new RuntimeException("Gagal backup {$file}");
    }

    return $backup;
}

function writeFileSafe(string $file, string $content): void
{
    if (file_put_contents($file, $content) === false) {
        throw new RuntimeException("Gagal menulis {$file}");
    }
}

function phpLint(string $file): array
{
    $cmd = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file);
    exec($cmd.' 2>&1', $out, $code);

    return [$code === 0, implode(PHP_EOL, $out)];
}

function findAssetShowBlade(string $root): ?string
{
    $candidates = [
        $root.'/packages/Webkul/Admin/src/Resources/views/inventory/assets/show.blade.php',
        $root.'/packages/Webkul/Admin/src/Resources/views/inventory/asset/show.blade.php',
        $root.'/packages/Webkul/Admin/src/Resources/views/inventory/assets/view.blade.php',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    $base = $root.'/packages/Webkul/Admin/src/Resources/views/inventory';

    if (! is_dir($base)) {
        return null;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $file) {
        if (! $file->isFile() || ! str_ends_with(strtolower($file->getFilename()), '.blade.php')) {
            continue;
        }

        $content = @file_get_contents($file->getPathname());

        if (
            is_string($content)
            && str_contains($content, '$asset')
            && (
                str_contains($content, 'asset_code')
                || str_contains($content, 'Inventory Asset')
            )
        ) {
            return $file->getPathname();
        }
    }

    return null;
}

$backups = [];

try {
    /*
     * ----------------------------------------------------------------------
     * CONTROLLER
     * ----------------------------------------------------------------------
     */
    $source = file_get_contents($controller);

    if ($source === false) {
        throw new RuntimeException('Gagal membaca InventoryAssetController.');
    }

    if (! str_contains($source, 'MISSING_ASSET_RECOVERY_V1')) {
        $backups[$controller] = backupFile($controller, 'missing-asset-recovery-v1');

        $permission = str_contains(
            (string) @file_get_contents($root.'/packages/Webkul/Admin/src/Config/acl.php'),
            "'inventory.assets.edit'"
        )
            ? 'inventory.assets.edit'
            : 'inventory.assets';

        $method = <<<PHP

    /**
     * MISSING_ASSET_RECOVERY_V1
     *
     * Recover one serialized asset without forcing a full stock opname.
     * A recovery is allowed only from MISSING and is recorded as an
     * inventory movement. Active Delivery Order allocations block recovery.
     */
    public function recover(
        \Illuminate\Http\Request \$request,
        int \$id
    ): \Illuminate\Http\RedirectResponse {
        if (
            function_exists('bouncer')
            && ! bouncer()->hasPermission('{$permission}')
        ) {
            abort(403);
        }

        \$validated = \$request->validate([
            'recovery_status' => [
                'required',
                \Illuminate\Validation\Rule::in([
                    'available',
                    'damaged',
                ]),
            ],
            'recovery_notes' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        \Illuminate\Support\Facades\DB::transaction(
            function () use (
                \$id,
                \$validated
            ): void {
                \$asset =
                    \Webkul\Warehouse\Models\InventoryAsset::query()
                        ->lockForUpdate()
                        ->findOrFail(\$id);

                if (
                    strtolower((string) \$asset->status)
                    !== 'missing'
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'recovery_status' =>
                            'Hanya asset berstatus MISSING yang dapat dipulihkan.',
                    ]);
                }

                \$hasActiveAllocation =
                    \Illuminate\Support\Facades\DB::table(
                        'delivery_order_inventory_allocations'
                    )
                        ->where(
                            'inventory_asset_id',
                            \$asset->id
                        )
                        ->whereNotIn(
                            'status',
                            [
                                'checked_in',
                                'released',
                            ]
                        )
                        ->exists();

                if (\$hasActiveAllocation) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'recovery_status' =>
                            'Asset masih memiliki allocation / Delivery Order aktif. '
                            .'Selesaikan transaksi tersebut sebelum recovery.',
                    ]);
                }

                \$fromStatus =
                    strtolower(
                        (string) \$asset->status
                    );

                \$toStatus =
                    (string) \$validated[
                        'recovery_status'
                    ];

                \$asset->status =
                    \$toStatus;

                if (\$toStatus === 'damaged') {
                    \$asset->condition =
                        'damaged';
                } elseif (
                    strtolower(
                        (string) \$asset->condition
                    ) === 'damaged'
                ) {
                    \$asset->condition =
                        'good';
                }

                \$asset->save();

                \Webkul\Warehouse\Models\InventoryStockMovement::query()
                    ->create([
                        'inventory_item_id' =>
                            \$asset->inventory_item_id,

                        'inventory_asset_id' =>
                            \$asset->id,

                        'warehouse_id' =>
                            \$asset->warehouse_id,

                        'warehouse_location_id' =>
                            \$asset->warehouse_location_id,

                        'movement_type' =>
                            'missing_recovered',

                        'quantity' =>
                            1,

                        'from_status' =>
                            \$fromStatus,

                        'to_status' =>
                            \$toStatus,

                        'reference_type' =>
                            'asset_recovery',

                        'reference_id' =>
                            \$asset->id,

                        'reference_number' =>
                            \$asset->asset_code,

                        'performed_by' =>
                            auth()
                                ->guard('user')
                                ->id(),

                        'notes' =>
                            trim(
                                (string) \$validated[
                                    'recovery_notes'
                                ]
                            ),

                        'occurred_at' =>
                            now(),
                    ]);
            }
        );

        session()->flash(
            'success',
            'Asset ditemukan dan status inventory berhasil diperbarui.'
        );

        return back();
    }
PHP;

        $pos = strrpos($source, "\n}");

        if ($pos === false) {
            throw new RuntimeException('Penutup class InventoryAssetController tidak ditemukan.');
        }

        $source = substr($source, 0, $pos).$method.substr($source, $pos);

        writeFileSafe($controller, $source);

        [$lintOk, $lintOut] = phpLint($controller);

        if (! $lintOk) {
            throw new RuntimeException("PHP lint controller gagal:\n{$lintOut}");
        }

        echo "[OK] Controller recovery terpasang.\n";
    } else {
        echo "[OK] Controller recovery sudah ada.\n";
    }

    /*
     * ----------------------------------------------------------------------
     * ROUTE
     * ----------------------------------------------------------------------
     */
    $routeSource = file_get_contents($routes);

    if ($routeSource === false) {
        throw new RuntimeException('Gagal membaca routes/web.php.');
    }

    if (! str_contains($routeSource, 'admin.inventory.assets.recover')) {
        $backups[$routes] = backupFile($routes, 'missing-asset-recovery-v1');

        $routeBlock = <<<'PHP'


/*
|--------------------------------------------------------------------------
| MISSING_ASSET_RECOVERY_V1
|--------------------------------------------------------------------------
| Recover a single serialized MISSING asset without running a full
| warehouse stock opname. Uses the same authenticated admin middleware.
*/
\Illuminate\Support\Facades\Route::post(
    'admin/inventory/assets/{id}/recover',
    [
        \Webkul\Admin\Http\Controllers\Inventory\InventoryAssetController::class,
        'recover',
    ]
)
    ->middleware([
        'web',
        'admin_locale',
        'user',
    ])
    ->name(
        'admin.inventory.assets.recover'
    );
PHP;

        if (preg_match('/\?>\s*$/', $routeSource) === 1) {
            $routeSource = preg_replace('/\?>\s*$/', $routeBlock."\n?>", $routeSource, 1);
        } else {
            $routeSource = rtrim($routeSource).$routeBlock."\n";
        }

        writeFileSafe($routes, $routeSource);

        [$lintOk, $lintOut] = phpLint($routes);

        if (! $lintOk) {
            throw new RuntimeException("PHP lint routes/web.php gagal:\n{$lintOut}");
        }

        echo "[OK] Route recovery terpasang.\n";
    } else {
        echo "[OK] Route recovery sudah ada.\n";
    }

    /*
     * ----------------------------------------------------------------------
     * ASSET DETAIL UI
     * ----------------------------------------------------------------------
     */
    $show = findAssetShowBlade($root);

    if ($show === null) {
        echo "[WARN] Asset detail Blade tidak terdeteksi. Backend + route tetap terpasang.\n";
    } else {
        $blade = file_get_contents($show);

        if ($blade === false) {
            throw new RuntimeException("Gagal membaca {$show}");
        }

        if (! str_contains($blade, 'MISSING_ASSET_RECOVERY_V1_UI')) {
            $backups[$show] = backupFile($show, 'missing-asset-recovery-v1');

            $card = <<<'BLADE'

    {{-- MISSING_ASSET_RECOVERY_V1_UI --}}
    @if (strtolower((string) $asset->status) === 'missing')
        <div class="mb-6 rounded-lg border border-red-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4">
                <p class="text-base font-semibold text-gray-800 dark:text-white">
                    Missing Asset Recovery
                </p>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Asset ini berstatus MISSING. Pulihkan asset satuan tanpa menjalankan Stock Opname seluruh gudang.
                </p>
            </div>

            <form
                method="POST"
                action="{{ route('admin.inventory.assets.recover', $asset->id) }}"
                class="grid gap-4"
            >
                @csrf

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-800 dark:text-white">
                        Kondisi saat ditemukan *
                    </label>

                    <select
                        name="recovery_status"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        required
                    >
                        <option value="available">Baik / Ready → AVAILABLE</option>
                        <option value="damaged">Rusak → DAMAGED</option>
                    </select>

                    <p class="mt-1 text-xs text-gray-500">
                        Jika DAMAGED, lanjutkan melalui workflow Maintenance & Repair yang sudah ada.
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-800 dark:text-white">
                        Catatan ditemukan *
                    </label>

                    <textarea
                        name="recovery_notes"
                        rows="3"
                        maxlength="2000"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        placeholder="Contoh: ditemukan kembali di rak kamera Gudang Utama."
                        required
                    ></textarea>
                </div>

                <div>
                    <button
                        type="submit"
                        class="primary-button"
                        onclick="return confirm('Konfirmasi asset ini sudah ditemukan?')"
                    >
                        Confirm Found
                    </button>
                </div>
            </form>
        </div>
    @endif
BLADE;

            $layoutClose = strrpos($blade, '</x-admin::layouts>');

            if ($layoutClose !== false) {
                $blade = substr($blade, 0, $layoutClose)
                    .$card."\n"
                    .substr($blade, $layoutClose);
            } else {
                $blade = rtrim($blade)."\n".$card."\n";
            }

            writeFileSafe($show, $blade);

            echo "[OK] UI Asset Recovery terpasang di:\n     {$show}\n";
        } else {
            echo "[OK] UI Asset Recovery sudah ada.\n";
        }
    }

    /*
     * ----------------------------------------------------------------------
     * MOVEMENT LABEL
     * ----------------------------------------------------------------------
     */
    if (is_file($grid)) {
        $gridSource = file_get_contents($grid);

        if (
            is_string($gridSource)
            && ! str_contains($gridSource, 'MISSING_ASSET_RECOVERY_V1_MOVEMENT')
        ) {
            $backups[$grid] = backupFile($grid, 'missing-asset-recovery-v1');

            $filterAnchor =
                "['label' => 'Stock Opname Missing', 'value' => 'stock_opname_missing'],";

            if (str_contains($gridSource, $filterAnchor)) {
                $gridSource = str_replace(
                    $filterAnchor,
                    $filterAnchor
                    ."\n                // MISSING_ASSET_RECOVERY_V1_MOVEMENT"
                    ."\n                ['label' => 'Missing Recovered', 'value' => 'missing_recovered'],",
                    $gridSource,
                    $filterCount
                );
            } else {
                $gridSource = str_replace(
                    "'stock_opname_missing'        => ['OPNAME MISSING'",
                    "// MISSING_ASSET_RECOVERY_V1_MOVEMENT\n"
                    ."            'missing_recovered'          => ['RECOVERED', '#dcfce7', '#15803d'],\n"
                    ."            'stock_opname_missing'        => ['OPNAME MISSING'",
                    $gridSource,
                    $badgeCount
                );
            }

            /*
             * Add the badge wherever the main movement map exists.
             */
            if (
                ! str_contains(
                    $gridSource,
                    "'missing_recovered'          => ['RECOVERED'"
                )
            ) {
                $gridSource = str_replace(
                    "'stock_opname_missing'        => ['OPNAME MISSING'",
                    "'missing_recovered'          => ['RECOVERED', '#dcfce7', '#15803d'],\n"
                    ."            'stock_opname_missing'        => ['OPNAME MISSING'",
                    $gridSource
                );
            }

            writeFileSafe($grid, $gridSource);

            [$lintOk, $lintOut] = phpLint($grid);

            if (! $lintOk) {
                throw new RuntimeException("PHP lint movement grid gagal:\n{$lintOut}");
            }

            echo "[OK] Label movement RECOVERED ditambahkan.\n";
        } else {
            echo "[OK] Movement grid tidak perlu diubah / sudah dipatch.\n";
        }
    }

    /*
     * ----------------------------------------------------------------------
     * CLEAR + ROUTE CHECK
     * ----------------------------------------------------------------------
     */
    exec(
        escapeshellarg(PHP_BINARY).' '
        .escapeshellarg($root.'/artisan')
        .' optimize:clear 2>&1',
        $clearOut,
        $clearCode
    );

    if ($clearCode !== 0) {
        throw new RuntimeException(
            "artisan optimize:clear gagal:\n"
            .implode(PHP_EOL, $clearOut)
        );
    }

    exec(
        escapeshellarg(PHP_BINARY).' '
        .escapeshellarg($root.'/artisan')
        .' route:list --name=admin.inventory.assets.recover 2>&1',
        $routeOut,
        $routeCode
    );

    if (
        $routeCode !== 0
        || ! str_contains(
            implode("\n", $routeOut),
            'admin.inventory.assets.recover'
        )
    ) {
        throw new RuntimeException(
            "Route recovery tidak terdeteksi setelah patch:\n"
            .implode(PHP_EOL, $routeOut)
        );
    }

    echo "[OK] optimize:clear selesai.\n";
    echo "[OK] Route recovery aktif.\n\n";
    echo "HASIL: PASS\n";
    echo "Lanjutkan:\n";
    echo "  php tools\\check_missing_asset_recovery_v1.php\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\n[FAIL] ".$e->getMessage()."\n");

    foreach (array_reverse($backups, true) as $original => $backup) {
        if (is_file($backup)) {
            @copy($backup, $original);
            fwrite(STDERR, "Rollback: {$original}\n");
        }
    }

    exit(1);
}
