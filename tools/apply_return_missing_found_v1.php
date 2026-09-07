<?php
declare(strict_types=1);

echo "RETURN MISSING -> FOUND V1\n";
echo "==========================\n\n";

$root = dirname(__DIR__);
chdir($root);

$controller = $root.'/packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/MissingAssetReturnRecoveryController.php';
$routes     = $root.'/routes/web.php';
$acl        = $root.'/packages/Webkul/Admin/src/Config/acl.php';

$backups = [];

function backupFile(string $file, string $tag): string
{
    $backup = $file.'.bak-'.$tag.'-'.date('Ymd-His');

    if (! copy($file, $backup)) {
        throw new RuntimeException("Gagal membuat backup: {$file}");
    }

    return $backup;
}

function writeSafe(string $file, string $content): void
{
    $dir = dirname($file);

    if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
        throw new RuntimeException("Gagal membuat folder: {$dir}");
    }

    if (file_put_contents($file, $content) === false) {
        throw new RuntimeException("Gagal menulis: {$file}");
    }
}

function lintPhp(string $file): void
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

function detectReturnBlade(string $root): ?string
{
    $base =
        $root
        .'/packages/Webkul/Admin/src/Resources/views';

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
            || ! str_ends_with(
                strtolower($file->getFilename()),
                '.blade.php'
            )
        ) {
            continue;
        }

        $content =
            @file_get_contents(
                $file->getPathname()
            );

        if (! is_string($content)) {
            continue;
        }

        $score = 0;

        if (
            stripos(
                $content,
                'Serialized Return Inspection'
            ) !== false
        ) {
            $score += 5;
        }

        if (
            stripos(
                $content,
                'Manual Quantity Return'
            ) !== false
        ) {
            $score += 5;
        }

        if (
            stripos(
                $content,
                'return_condition'
            ) !== false
        ) {
            $score += 2;
        }

        if (
            stripos(
                $file->getPathname(),
                'delivery'
            ) !== false
        ) {
            $score += 1;
        }

        if ($score > 0) {
            $ranked[] = [
                'path' =>
                    $file->getPathname(),
                'score' =>
                    $score,
            ];
        }
    }

    if ($ranked === []) {
        return null;
    }

    usort(
        $ranked,
        fn ($a, $b) =>
            $b['score']
            <=>
            $a['score']
    );

    return
        $ranked[0]['score'] >= 8
            ? $ranked[0]['path']
            : null;
}

function insertBeforeManualQuantity(
    string $blade,
    string $block
): string {
    $needle =
        'Manual Quantity Return';

    $pos =
        stripos(
            $blade,
            $needle
        );

    if ($pos === false) {
        throw new RuntimeException(
            'Anchor "Manual Quantity Return" tidak ditemukan.'
        );
    }

    $lineStart =
        strrpos(
            substr(
                $blade,
                0,
                $pos
            ),
            "\n"
        );

    $lineStart =
        $lineStart === false
            ? 0
            : $lineStart + 1;

    /*
     * Prefer inserting before the nearest opening wrapper line
     * immediately above the heading. We only walk a few lines so the
     * patch cannot accidentally jump outside a large layout section.
     */
    $candidate =
        $lineStart;

    $before =
        substr(
            $blade,
            0,
            $lineStart
        );

    $lines =
        preg_split(
            '/\R/',
            $before
        ) ?: [];

    $offset =
        strlen($before);

    for (
        $i = count($lines) - 1,
        $walk = 0;
        $i >= 0 && $walk < 4;
        $i--,
        $walk++
    ) {
        $line =
            $lines[$i];

        $offset -=
            strlen($line)
            + 1;

        if (
            preg_match(
                '/<div\b|<section\b/i',
                $line
            )
        ) {
            $candidate =
                max(
                    0,
                    $offset
                );

            break;
        }
    }

    return
        substr(
            $blade,
            0,
            $candidate
        )
        .$block
        ."\n"
        .substr(
            $blade,
            $candidate
        );
}

try {
    if (! is_file($routes)) {
        throw new RuntimeException(
            'routes/web.php tidak ditemukan.'
        );
    }

    /*
     * Permission used by the existing inventory module.
     */
    $permission =
        'inventory.assets';

    if (is_file($acl)) {
        $aclSource =
            (string) file_get_contents(
                $acl
            );

        if (
            str_contains(
                $aclSource,
                "'inventory.assets.edit'"
            )
        ) {
            $permission =
                'inventory.assets.edit';
        } elseif (
            str_contains(
                $aclSource,
                "'inventory.assets'"
            )
        ) {
            $permission =
                'inventory.assets';
        }
    }

    /*
     * --------------------------------------------------------------
     * NEW CONTROLLER
     * --------------------------------------------------------------
     */
    if (! is_file($controller)) {
        $controllerSource = <<<PHP
<?php

namespace Webkul\Admin\Http\Controllers\DeliveryOrder;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * RETURN_MISSING_FOUND_V1
 *
 * Resolve one serialized MISSING return without rerunning stock opname.
 *
 * Business rule:
 * - allocation must belong to this Delivery Order
 * - return_condition must currently be missing
 * - inventory asset must currently be missing
 * - result can become GOOD/AVAILABLE or DAMAGED
 * - every correction creates an inventory_stock_movements audit record
 */
class MissingAssetReturnRecoveryController extends Controller
{
    public function __invoke(
        Request \$request,
        int \$id,
        int \$allocationId
    ): RedirectResponse {
        if (
            function_exists('bouncer')
            && ! bouncer()->hasPermission('{$permission}')
        ) {
            abort(403);
        }

        \$validated =
            \$request->validate([
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
                \$id,
                \$allocationId,
                \$validated
            ): void {
                \$allocation =
                    DB::table(
                        'delivery_order_inventory_allocations'
                    )
                        ->where(
                            'id',
                            \$allocationId
                        )
                        ->where(
                            'delivery_order_id',
                            \$id
                        )
                        ->lockForUpdate()
                        ->first();

                if (! \$allocation) {
                    throw ValidationException::withMessages([
                        'found_condition' =>
                            'Allocation return tidak ditemukan.',
                    ]);
                }

                if (
                    strtolower(
                        (string) \$allocation->return_condition
                    ) !== 'missing'
                ) {
                    throw ValidationException::withMessages([
                        'found_condition' =>
                            'Hanya item return berstatus MISSING yang dapat dikoreksi sebagai ditemukan.',
                    ]);
                }

                if (! \$allocation->inventory_asset_id) {
                    throw ValidationException::withMessages([
                        'found_condition' =>
                            'Recovery ini hanya untuk serialized asset.',
                    ]);
                }

                \$asset =
                    DB::table(
                        'inventory_assets'
                    )
                        ->where(
                            'id',
                            \$allocation->inventory_asset_id
                        )
                        ->lockForUpdate()
                        ->first();

                if (! \$asset) {
                    throw ValidationException::withMessages([
                        'found_condition' =>
                            'Inventory asset tidak ditemukan.',
                    ]);
                }

                if (
                    strtolower(
                        (string) \$asset->status
                    ) !== 'missing'
                ) {
                    throw ValidationException::withMessages([
                        'found_condition' =>
                            'Asset sudah tidak berstatus MISSING. Refresh halaman sebelum mencoba lagi.',
                    ]);
                }

                /*
                 * Prevent a recovered asset from silently colliding with a
                 * different active DO allocation.
                 */
                \$otherActive =
                    DB::table(
                        'delivery_order_inventory_allocations'
                    )
                        ->where(
                            'inventory_asset_id',
                            \$asset->id
                        )
                        ->where(
                            'id',
                            '<>',
                            \$allocation->id
                        )
                        ->whereIn(
                            'status',
                            [
                                'allocated',
                                'picked',
                                'out',
                                'return_pending',
                            ]
                        )
                        ->exists();

                if (\$otherActive) {
                    throw ValidationException::withMessages([
                        'found_condition' =>
                            'Asset memiliki allocation aktif pada Surat Jalan lain. Recovery dibatalkan.',
                    ]);
                }

                \$condition =
                    (string) \$validated[
                        'found_condition'
                    ];

                \$toStatus =
                    \$condition === 'damaged'
                        ? 'damaged'
                        : 'available';

                \$now =
                    now();

                \$userId =
                    auth()
                        ->guard('user')
                        ->id();

                \$existingNotes =
                    trim(
                        (string) (
                            \$allocation->return_notes
                            ?? ''
                        )
                    );

                \$recoveryNote =
                    '[FOUND '
                    .\$now->format('Y-m-d H:i:s')
                    .'] '
                    .trim(
                        (string) \$validated[
                            'found_notes'
                        ]
                    );

                \$returnNotes =
                    \$existingNotes !== ''
                        ? \$existingNotes
                            .PHP_EOL
                            .\$recoveryNote
                        : \$recoveryNote;

                /*
                 * A missing row is now physically checked in.
                 */
                DB::table(
                    'delivery_order_inventory_allocations'
                )
                    ->where(
                        'id',
                        \$allocation->id
                    )
                    ->update([
                        'status' =>
                            'checked_in',

                        'return_condition' =>
                            \$condition,

                        'returned_quantity' =>
                            1,

                        'checked_in_by' =>
                            \$userId
                            ?: \$allocation->checked_in_by,

                        'checked_in_at' =>
                            \$now,

                        'return_notes' =>
                            \$returnNotes,

                        'updated_at' =>
                            \$now,
                    ]);

                /*
                 * Reconcile serialized asset state.
                 */
                \$assetUpdate = [
                    'status' =>
                        \$toStatus,

                    'updated_at' =>
                        \$now,
                ];

                if (\$condition === 'damaged') {
                    \$assetUpdate[
                        'condition'
                    ] =
                        'damaged';
                } else {
                    \$assetUpdate[
                        'condition'
                    ] =
                        'good';
                }

                DB::table(
                    'inventory_assets'
                )
                    ->where(
                        'id',
                        \$asset->id
                    )
                    ->update(
                        \$assetUpdate
                    );

                \$deliveryOrder =
                    DB::table(
                        'delivery_orders'
                    )
                        ->where(
                            'id',
                            \$id
                        )
                        ->first();

                \$referenceNumber =
                    \$deliveryOrder->delivery_order_number
                    ?? \$deliveryOrder->do_number
                    ?? \$deliveryOrder->sj_number
                    ?? \$deliveryOrder->reference_number
                    ?? ('DO-'.$id);

                DB::table(
                    'inventory_stock_movements'
                )
                    ->insert([
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
                            'missing',

                        'to_status' =>
                            \$toStatus,

                        'reference_type' =>
                            'delivery_order_return',

                        'reference_id' =>
                            \$id,

                        'reference_number' =>
                            (string) \$referenceNumber,

                        'performed_by' =>
                            \$userId,

                        'notes' =>
                            trim(
                                (string) \$validated[
                                    'found_notes'
                                ]
                            ),

                        'occurred_at' =>
                            \$now,

                        'created_at' =>
                            \$now,

                        'updated_at' =>
                            \$now,
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

PHP;

        writeSafe(
            $controller,
            $controllerSource
        );

        lintPhp(
            $controller
        );

        echo "[OK] Controller Found dibuat.\n";
    } else {
        echo "[OK] Controller Found sudah ada.\n";
    }

    /*
     * --------------------------------------------------------------
     * ROUTE
     * --------------------------------------------------------------
     */
    $routeSource =
        (string) file_get_contents(
            $routes
        );

    if (
        ! str_contains(
            $routeSource,
            'admin.delivery-orders.return.missing-found'
        )
    ) {
        $backups[$routes] =
            backupFile(
                $routes,
                'return-missing-found-v1'
            );

        $routeBlock = <<<'PHP'


/*
|--------------------------------------------------------------------------
| RETURN_MISSING_FOUND_V1
|--------------------------------------------------------------------------
| Correct one serialized return from MISSING to physically FOUND.
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
    ->name(
        'admin.delivery-orders.return.missing-found'
    );
PHP;

        if (
            preg_match(
                '/\?>\s*$/',
                $routeSource
            ) === 1
        ) {
            $routeSource =
                preg_replace(
                    '/\?>\s*$/',
                    $routeBlock
                    ."\n?>",
                    $routeSource,
                    1
                );
        } else {
            $routeSource =
                rtrim(
                    $routeSource
                )
                .$routeBlock
                ."\n";
        }

        writeSafe(
            $routes,
            $routeSource
        );

        lintPhp(
            $routes
        );

        echo "[OK] Route Found ditambahkan.\n";
    } else {
        echo "[OK] Route Found sudah ada.\n";
    }

    /*
     * --------------------------------------------------------------
     * RETURN PAGE UI
     * --------------------------------------------------------------
     */
    $blade =
        detectReturnBlade(
            $root
        );

    if (! $blade) {
        throw new RuntimeException(
            'Blade Return Warehouse tidak terdeteksi otomatis.'
        );
    }

    $bladeSource =
        (string) file_get_contents(
            $blade
        );

    if (
        ! str_contains(
            $bladeSource,
            'RETURN_MISSING_FOUND_V1_UI'
        )
    ) {
        $backups[$blade] =
            backupFile(
                $blade,
                'return-missing-found-v1'
            );

        $ui = <<<'BLADE'
{{-- RETURN_MISSING_FOUND_V1_UI --}}
@php
    $crmReturnDoRouteId =
        request()->route('id')
        ?? request()->route('deliveryOrder')
        ?? request()->route('deliveryOrderId')
        ?? request()->route('delivery_order');

    $crmReturnDoId =
        is_object($crmReturnDoRouteId)
            ? (int) ($crmReturnDoRouteId->id ?? 0)
            : (int) $crmReturnDoRouteId;

    $crmMissingReturnRows =
        collect();

    if ($crmReturnDoId > 0) {
        $crmMissingReturnRows =
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
                    'asset.id as asset_id',
                    'asset.asset_code',
                    'asset.serial_number',
                    'item.code as item_code',
                    'item.name as item_name',
                    'allocation.return_notes',
                ])
                ->orderBy(
                    'allocation.id'
                )
                ->get();
    }
@endphp

@if ($crmMissingReturnRows->isNotEmpty())
    <div class="mb-5 rounded-lg border border-amber-300 bg-amber-50 p-5 dark:border-amber-800 dark:bg-gray-900">
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <p class="text-base font-semibold text-gray-900 dark:text-white">
                    Missing Return Exception
                </p>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    {{ $crmMissingReturnRows->count() }} asset masih MISSING.
                    Jika barang ditemukan, check-in langsung di sini tanpa Stock Opname seluruh gudang.
                </p>
            </div>

            <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">
                MISSING {{ $crmMissingReturnRows->count() }}
            </span>
        </div>

        <div class="grid gap-4">
            @foreach ($crmMissingReturnRows as $crmMissingReturn)
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
                                onclick="return confirm('Konfirmasi barang MISSING ini sudah ditemukan dan diterima kembali?')"
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

        $bladeSource =
            insertBeforeManualQuantity(
                $bladeSource,
                $ui
            );

        writeSafe(
            $blade,
            $bladeSource
        );

        echo "[OK] UI Found dipasang di:\n     {$blade}\n";
    } else {
        echo "[OK] UI Found sudah ada.\n";
    }

    /*
     * --------------------------------------------------------------
     * CLEAR CACHE + VERIFY ROUTE
     * --------------------------------------------------------------
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
            "optimize:clear gagal:\n"
            .implode(PHP_EOL, $clearOut)
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
            "Route Found tidak aktif:\n"
            .implode(PHP_EOL, $routeOut)
        );
    }

    echo "[OK] Cache dibersihkan.\n";
    echo "[OK] Route Found aktif.\n\n";
    echo "HASIL: PASS\n\n";
    echo "Selanjutnya jalankan:\n";
    echo "  php tools\\check_return_missing_found_v1.php\n";
} catch (Throwable $e) {
    fwrite(
        STDERR,
        "\n[FAIL] "
        .$e->getMessage()
        ."\n"
    );

    foreach (
        array_reverse(
            $backups,
            true
        )
        as $original =>
            $backup
    ) {
        if (
            is_file(
                $backup
            )
        ) {
            @copy(
                $backup,
                $original
            );

            fwrite(
                STDERR,
                "Rollback: {$original}\n"
            );
        }
    }

    if (
        is_file(
            $controller
        )
        && str_contains(
            (string) @file_get_contents(
                $controller
            ),
            'RETURN_MISSING_FOUND_V1'
        )
    ) {
        /*
         * Controller is a new file, so remove it on failed install only
         * when no previous version existed.
         */
        @unlink(
            $controller
        );
    }

    exit(1);
}
