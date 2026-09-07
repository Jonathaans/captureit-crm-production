<?php
declare(strict_types=1);

echo "RETURN MISSING -> FOUND V1.1\n";
echo "============================\n\n";

$root = dirname(__DIR__);
chdir($root);

$controller = $root.'/packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/MissingAssetReturnRecoveryController.php';
$routes     = $root.'/routes/web.php';
$acl        = $root.'/packages/Webkul/Admin/src/Config/acl.php';

$backups = [];
$newControllerCreated = false;

function backupFileV11(string $file, string $tag): string
{
    $backup = $file.'.bak-'.$tag.'-'.date('Ymd-His');

    if (! copy($file, $backup)) {
        throw new RuntimeException("Gagal membuat backup: {$file}");
    }

    return $backup;
}

function writeSafeV11(string $file, string $content): void
{
    $dir = dirname($file);

    if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
        throw new RuntimeException("Gagal membuat folder: {$dir}");
    }

    if (file_put_contents($file, $content) === false) {
        throw new RuntimeException("Gagal menulis: {$file}");
    }
}

function lintPhpV11(string $file): void
{
    exec(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1',
        $out,
        $code
    );

    if ($code !== 0) {
        throw new RuntimeException(
            "PHP lint gagal: {$file}\n".implode(PHP_EOL, $out)
        );
    }
}

function detectReturnBladeV11(string $root): ?string
{
    $base = $root.'/packages/Webkul/Admin/src/Resources/views';

    if (! is_dir($base)) {
        return null;
    }

    $ranked = [];

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $base,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($it as $file) {
        if (
            ! $file->isFile()
            || ! str_ends_with(strtolower($file->getFilename()), '.blade.php')
        ) {
            continue;
        }

        $content = @file_get_contents($file->getPathname());

        if (! is_string($content)) {
            continue;
        }

        $score = 0;

        if (stripos($content, 'Serialized Return Inspection') !== false) {
            $score += 5;
        }

        if (stripos($content, 'Manual Quantity Return') !== false) {
            $score += 5;
        }

        if (stripos($content, 'return_condition') !== false) {
            $score += 2;
        }

        if (stripos($file->getPathname(), 'delivery') !== false) {
            $score += 1;
        }

        if ($score > 0) {
            $ranked[] = [
                'path'  => $file->getPathname(),
                'score' => $score,
            ];
        }
    }

    if ($ranked === []) {
        return null;
    }

    usort(
        $ranked,
        fn ($a, $b) => $b['score'] <=> $a['score']
    );

    return $ranked[0]['score'] >= 8
        ? $ranked[0]['path']
        : null;
}

function insertBeforeManualQuantityV11(string $blade, string $block): string
{
    $needle = 'Manual Quantity Return';
    $pos = stripos($blade, $needle);

    if ($pos === false) {
        throw new RuntimeException(
            'Anchor "Manual Quantity Return" tidak ditemukan.'
        );
    }

    $lineStart = strrpos(substr($blade, 0, $pos), "\n");
    $lineStart = $lineStart === false ? 0 : $lineStart + 1;

    return substr($blade, 0, $lineStart)
        .$block
        ."\n"
        .substr($blade, $lineStart);
}

try {
    if (! is_file($routes)) {
        throw new RuntimeException('routes/web.php tidak ditemukan.');
    }

    /*
     * If the failed V1 left a broken generated controller behind,
     * remove only that generated file before rebuilding it.
     */
    if (is_file($controller)) {
        $existingController = (string) @file_get_contents($controller);

        exec(
            escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($controller).' 2>&1',
            $lintOut,
            $lintCode
        );

        if (
            $lintCode !== 0
            && (
                str_contains($existingController, 'RETURN_MISSING_FOUND_V1')
                || str_contains($existingController, 'RETURN_MISSING_FOUND_V1_1')
            )
        ) {
            @unlink($controller);
            echo "[OK] Sisa controller V1 yang rusak dibersihkan.\n";
        }
    }

    /*
     * Determine a compatible permission name without changing ACL.
     */
    $permission = 'inventory.assets';

    if (is_file($acl)) {
        $aclSource = (string) file_get_contents($acl);

        if (str_contains($aclSource, "'inventory.assets.edit'")) {
            $permission = 'inventory.assets.edit';
        } elseif (str_contains($aclSource, "'inventory.assets'")) {
            $permission = 'inventory.assets';
        }
    }

    /*
     * ------------------------------------------------------------------
     * CONTROLLER
     * ------------------------------------------------------------------
     *
     * IMPORTANT:
     * This is a NOWDOC so PHP variables inside the generated controller
     * are NOT interpolated by this installer. This fixes the V1 bug that
     * expanded $id while generating the file.
     */
    if (! is_file($controller)) {
        $controllerSource = <<<'PHP_CONTROLLER'
<?php

namespace Webkul\Admin\Http\Controllers\DeliveryOrder;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * RETURN_MISSING_FOUND_V1_1
 *
 * Correct one serialized return from MISSING to FOUND.
 */
class MissingAssetReturnRecoveryController extends Controller
{
    public function __invoke(
        Request $request,
        int $id,
        int $allocationId
    ): RedirectResponse {
        if (
            function_exists('bouncer')
            && ! bouncer()->hasPermission('__PERMISSION__')
        ) {
            abort(403);
        }

        $validated = $request->validate([
            'found_condition' => [
                'required',
                Rule::in([
                    'good',
                    'damaged',
                ]),
            ],

            'found_notes' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        DB::transaction(
            function () use (
                $id,
                $allocationId,
                $validated
            ): void {
                $allocation = DB::table(
                    'delivery_order_inventory_allocations'
                )
                    ->where('id', $allocationId)
                    ->where('delivery_order_id', $id)
                    ->lockForUpdate()
                    ->first();

                if (! $allocation) {
                    throw ValidationException::withMessages([
                        'found_condition' =>
                            'Allocation return tidak ditemukan.',
                    ]);
                }

                if (
                    strtolower(
                        (string) $allocation->return_condition
                    ) !== 'missing'
                ) {
                    throw ValidationException::withMessages([
                        'found_condition' =>
                            'Hanya item return berstatus MISSING yang dapat ditandai FOUND.',
                    ]);
                }

                if (! $allocation->inventory_asset_id) {
                    throw ValidationException::withMessages([
                        'found_condition' =>
                            'Recovery ini hanya untuk serialized asset.',
                    ]);
                }

                $asset = DB::table('inventory_assets')
                    ->where('id', $allocation->inventory_asset_id)
                    ->lockForUpdate()
                    ->first();

                if (! $asset) {
                    throw ValidationException::withMessages([
                        'found_condition' =>
                            'Inventory asset tidak ditemukan.',
                    ]);
                }

                if (
                    strtolower((string) $asset->status)
                    !== 'missing'
                ) {
                    throw ValidationException::withMessages([
                        'found_condition' =>
                            'Asset sudah tidak berstatus MISSING. Refresh halaman.',
                    ]);
                }

                $otherActive = DB::table(
                    'delivery_order_inventory_allocations'
                )
                    ->where('inventory_asset_id', $asset->id)
                    ->where('id', '<>', $allocation->id)
                    ->whereIn('status', [
                        'allocated',
                        'picked',
                        'out',
                        'return_pending',
                    ])
                    ->exists();

                if ($otherActive) {
                    throw ValidationException::withMessages([
                        'found_condition' =>
                            'Asset masih aktif pada Surat Jalan lain. Recovery dibatalkan.',
                    ]);
                }

                $condition = (string) $validated['found_condition'];

                $toStatus = $condition === 'damaged'
                    ? 'damaged'
                    : 'available';

                $now = now();

                $userId = auth()
                    ->guard('user')
                    ->id();

                $oldNotes = trim(
                    (string) ($allocation->return_notes ?? '')
                );

                $foundNote = '[FOUND '
                    .$now->format('Y-m-d H:i:s')
                    .'] '
                    .trim((string) $validated['found_notes']);

                $returnNotes = $oldNotes !== ''
                    ? $oldNotes.PHP_EOL.$foundNote
                    : $foundNote;

                DB::table('delivery_order_inventory_allocations')
                    ->where('id', $allocation->id)
                    ->update([
                        'status'            => 'checked_in',
                        'return_condition'  => $condition,
                        'returned_quantity' => 1,
                        'checked_in_by'     => $userId ?: $allocation->checked_in_by,
                        'checked_in_at'     => $now,
                        'return_notes'      => $returnNotes,
                        'updated_at'        => $now,
                    ]);

                DB::table('inventory_assets')
                    ->where('id', $asset->id)
                    ->update([
                        'status'     => $toStatus,
                        'condition'  => $condition === 'damaged'
                            ? 'damaged'
                            : 'good',
                        'updated_at' => $now,
                    ]);

                $deliveryOrder = DB::table('delivery_orders')
                    ->where('id', $id)
                    ->first();

                $referenceNumber =
                    ($deliveryOrder->delivery_order_number ?? null)
                    ?: ($deliveryOrder->do_number ?? null)
                    ?: ($deliveryOrder->sj_number ?? null)
                    ?: ($deliveryOrder->reference_number ?? null)
                    ?: ('DO-'.$id);

                DB::table('inventory_stock_movements')
                    ->insert([
                        'inventory_item_id'      => $asset->inventory_item_id,
                        'inventory_asset_id'     => $asset->id,
                        'warehouse_id'           => $asset->warehouse_id,
                        'warehouse_location_id'  => $asset->warehouse_location_id,
                        'movement_type'          => 'missing_recovered',
                        'quantity'               => 1,
                        'from_status'            => 'missing',
                        'to_status'              => $toStatus,
                        'reference_type'         => 'delivery_order_return',
                        'reference_id'           => $id,
                        'reference_number'       => (string) $referenceNumber,
                        'performed_by'           => $userId,
                        'notes'                  => trim(
                            (string) $validated['found_notes']
                        ),
                        'occurred_at'             => $now,
                        'created_at'              => $now,
                        'updated_at'              => $now,
                    ]);
            }
        );

        session()->flash(
            'success',
            'Barang MISSING berhasil ditandai FOUND dan diterima kembali.'
        );

        return back();
    }
}
PHP_CONTROLLER;

        $controllerSource = str_replace(
            '__PERMISSION__',
            $permission,
            $controllerSource
        );

        writeSafeV11($controller, $controllerSource);
        $newControllerCreated = true;
        lintPhpV11($controller);

        echo "[OK] Controller V1.1 dibuat dan lint PASS.\n";
    } else {
        $controllerText = (string) file_get_contents($controller);

        if (! str_contains($controllerText, 'RETURN_MISSING_FOUND_V1_1')) {
            throw new RuntimeException(
                'Controller dengan nama sama sudah ada tetapi bukan milik patch V1.1.'
            );
        }

        lintPhpV11($controller);
        echo "[OK] Controller V1.1 sudah ada dan valid.\n";
    }

    /*
     * ------------------------------------------------------------------
     * ROUTE
     * ------------------------------------------------------------------
     */
    $routeSource = (string) file_get_contents($routes);

    if (! str_contains(
        $routeSource,
        'admin.delivery-orders.return.missing-found'
    )) {
        $backups[$routes] = backupFileV11(
            $routes,
            'return-missing-found-v1_1'
        );

        $routeBlock = <<<'PHP_ROUTE'


/*
|--------------------------------------------------------------------------
| RETURN_MISSING_FOUND_V1_1
|--------------------------------------------------------------------------
*/
\Illuminate\Support\Facades\Route::post(
    'admin/delivery-orders/{id}/return/{allocationId}/found',
    \Webkul\Admin\Http\Controllers\DeliveryOrder\MissingAssetReturnRecoveryController::class
)
    ->middleware([
        'web',
        'admin_locale',
        'user',
    ])
    ->name('admin.delivery-orders.return.missing-found');
PHP_ROUTE;

        if (preg_match('/\?>\s*$/', $routeSource) === 1) {
            $routeSource = preg_replace(
                '/\?>\s*$/',
                $routeBlock."\n?>",
                $routeSource,
                1
            );
        } else {
            $routeSource = rtrim($routeSource).$routeBlock."\n";
        }

        writeSafeV11($routes, $routeSource);
        lintPhpV11($routes);

        echo "[OK] Route V1.1 ditambahkan.\n";
    } else {
        echo "[OK] Route recovery sudah ada.\n";
    }

    /*
     * ------------------------------------------------------------------
     * RETURN WAREHOUSE UI
     * ------------------------------------------------------------------
     */
    $blade = detectReturnBladeV11($root);

    if (! $blade) {
        throw new RuntimeException(
            'Blade Return Warehouse tidak terdeteksi otomatis.'
        );
    }

    $bladeSource = (string) file_get_contents($blade);

    if (! str_contains(
        $bladeSource,
        'RETURN_MISSING_FOUND_V1_1_UI'
    )) {
        $backups[$blade] = backupFileV11(
            $blade,
            'return-missing-found-v1_1'
        );

        $ui = <<<'BLADE'
{{-- RETURN_MISSING_FOUND_V1_1_UI --}}
@php
    $crmReturnRouteId =
        request()->route('id')
        ?? request()->route('deliveryOrder')
        ?? request()->route('deliveryOrderId')
        ?? request()->route('delivery_order');

    $crmReturnDoId =
        is_object($crmReturnRouteId)
            ? (int) ($crmReturnRouteId->id ?? 0)
            : (int) $crmReturnRouteId;

    $crmMissingReturns = collect();

    if ($crmReturnDoId > 0) {
        $crmMissingReturns =
            \Illuminate\Support\Facades\DB::table(
                'delivery_order_inventory_allocations as allocation'
            )
                ->join(
                    'inventory_assets as asset',
                    'asset.id',
                    '=',
                    'allocation.inventory_asset_id'
                )
                ->join(
                    'inventory_items as item',
                    'item.id',
                    '=',
                    'allocation.inventory_item_id'
                )
                ->where(
                    'allocation.delivery_order_id',
                    $crmReturnDoId
                )
                ->where(
                    'allocation.return_condition',
                    'missing'
                )
                ->where(
                    'asset.status',
                    'missing'
                )
                ->select([
                    'allocation.id as allocation_id',
                    'asset.asset_code',
                    'asset.serial_number',
                    'item.name as item_name',
                ])
                ->orderBy('allocation.id')
                ->get();
    }
@endphp

@if ($crmMissingReturns->isNotEmpty())
    <div class="mb-5 rounded-lg border border-amber-300 bg-amber-50 p-5 dark:border-amber-800 dark:bg-gray-900">
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <p class="text-base font-semibold text-gray-900 dark:text-white">
                    Missing Return Exception
                </p>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Barang yang sebelumnya ditandai MISSING dapat diterima kembali di sini tanpa Stock Opname seluruh gudang.
                </p>
            </div>

            <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">
                MISSING {{ $crmMissingReturns->count() }}
            </span>
        </div>

        <div class="grid gap-4">
            @foreach ($crmMissingReturns as $crmMissingReturn)
                <div class="rounded-md border bg-white p-4 dark:border-gray-700 dark:bg-gray-950">
                    <div class="mb-3">
                        <p class="font-semibold text-gray-900 dark:text-white">
                            {{ $crmMissingReturn->item_name }}
                        </p>

                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ $crmMissingReturn->asset_code }}

                            @if ($crmMissingReturn->serial_number)
                                · Serial {{ $crmMissingReturn->serial_number }}
                            @endif
                        </p>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('admin.delivery-orders.return.missing-found', [
                            'id' => $crmReturnDoId,
                            'allocationId' => $crmMissingReturn->allocation_id,
                        ]) }}"
                        class="grid gap-3"
                    >
                        @csrf

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-800 dark:text-white">
                                Kondisi saat ditemukan *
                            </label>

                            <select
                                name="found_condition"
                                class="w-full rounded-md border px-3 py-2 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                required
                            >
                                <option value="good">
                                    GOOD → AVAILABLE
                                </option>

                                <option value="damaged">
                                    DAMAGED
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-800 dark:text-white">
                                Catatan ditemukan *
                            </label>

                            <textarea
                                name="found_notes"
                                rows="2"
                                maxlength="2000"
                                class="w-full rounded-md border px-3 py-2 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                placeholder="Contoh: ditemukan di bagasi mobil operasional."
                                required
                            ></textarea>
                        </div>

                        <div>
                            <button
                                type="submit"
                                class="primary-button"
                                onclick="return confirm('Konfirmasi barang ini sudah ditemukan dan diterima kembali?')"
                            >
                                Found / Check In
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

BLADE;

        $bladeSource = insertBeforeManualQuantityV11(
            $bladeSource,
            $ui
        );

        writeSafeV11($blade, $bladeSource);

        echo "[OK] UI Found dipasang di:\n     {$blade}\n";
    } else {
        echo "[OK] UI Found V1.1 sudah ada.\n";
    }

    /*
     * ------------------------------------------------------------------
     * CACHE + ROUTE VERIFY
     * ------------------------------------------------------------------
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
            "optimize:clear gagal:\n".implode(PHP_EOL, $clearOut)
        );
    }

    exec(
        escapeshellarg(PHP_BINARY).' '
        .escapeshellarg($root.'/artisan')
        .' route:list --name=admin.delivery-orders.return.missing-found 2>&1',
        $routeOut,
        $routeCode
    );

    if (
        $routeCode !== 0
        || ! str_contains(
            implode("\n", $routeOut),
            'admin.delivery-orders.return.missing-found'
        )
    ) {
        throw new RuntimeException(
            "Route recovery tidak aktif:\n".implode(PHP_EOL, $routeOut)
        );
    }

    echo "[OK] optimize:clear selesai.\n";
    echo "[OK] Route recovery aktif.\n\n";
    echo "HASIL: PASS\n";
    echo "Lanjutkan:\n";
    echo "  php tools\\check_return_missing_found_v1_1.php\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\n[FAIL] ".$e->getMessage()."\n");

    foreach (array_reverse($backups, true) as $original => $backup) {
        if (is_file($backup)) {
            @copy($backup, $original);
            fwrite(STDERR, "Rollback: {$original}\n");
        }
    }

    if ($newControllerCreated && is_file($controller)) {
        @unlink($controller);
        fwrite(STDERR, "Rollback: controller V1.1 dihapus.\n");
    }

    exit(1);
}
