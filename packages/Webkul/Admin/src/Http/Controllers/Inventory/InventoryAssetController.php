<?php

namespace Webkul\Admin\Http\Controllers\Inventory;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Inventory\InventoryAssetDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Warehouse\Models\InventoryAsset;
use Webkul\Warehouse\Models\InventoryItem;
use Webkul\Warehouse\Models\InventoryStockMovement;
use Webkul\Warehouse\Services\QrCodeService;

class InventoryAssetController extends Controller
{
    public function index()
    {
        if (request()->ajax() || request()->expectsJson()) {
            return app(InventoryAssetDataGrid::class)->toJson();
        }

        $selectedItem = null;

        if (request()->filled('inventory_item_id')) {
            $selectedItem = InventoryItem::query()
                ->where('tracking_type', 'serialized')
                ->find(request()->integer('inventory_item_id'));
        }

        return view('admin::inventory.assets.index', compact('selectedItem'));
    }

    /**
     * Printable QR asset labels.
     *
     * Optional query:
     * ?ids=1,4,5
     * ?inventory_item_id=1
     */
    public function qrLabels(Request $request): View
    {
        $query = InventoryAsset::query()
            ->with('item')
            ->orderBy('asset_code');

        if ($request->filled('inventory_item_id')) {
            $query->where(
                'inventory_item_id',
                $request->integer('inventory_item_id')
            );
        }

        if ($request->filled('ids')) {
            $ids = collect(
                explode(
                    ',',
                    (string) $request->input('ids')
                )
            )
                ->map(fn ($id) => (int) trim($id))
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            if ($ids->isNotEmpty()) {
                $query->whereIn('id', $ids->all());
            }
        }

        $assets = $query->get();

        return view(
            'admin::inventory.assets.qr-labels',
            compact('assets')
        );
    }

    /**
     * Raw SVG used by QR label pages.
     */
    public function qrSvg(
        int $id,
        QrCodeService $qrCodeService
    ): Response {
        $asset = InventoryAsset::findOrFail($id);

        $qrPayload = trim(
            (string) $asset->asset_code
        );

        return response(
            $qrCodeService->svg($qrPayload),
            200,
            [
                'Content-Type'  => 'image/svg+xml; charset=UTF-8',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]
        );
    }

    /**
     * Bulk registration for serialized physical assets.
     *
     * Example:
     * Prefix       EXT-BOX
     * Start Number 1
     * Padding      3
     * Quantity     10
     *
     * Result:
     * EXT-BOX-001 ... EXT-BOX-010
     *
     * Asset Code = Barcode Value so the database identity,
     * printed QR payload, and scanner value stay consistent.
     */
    public function bulkCreate(): View
    {
        $items = InventoryItem::query()
            ->where('tracking_type', 'serialized')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedItemId = request()->integer('inventory_item_id')
            ?: null;

        return view(
            'admin::inventory.assets.bulk-create',
            compact(
                'items',
                'selectedItemId'
            )
        );
    }

    /**
     * Create multiple serialized assets in a single transaction.
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where('tracking_type', 'serialized')
                            ->where('is_active', true)
                    ),
            ],

            'prefix' => [
                'required',
                'string',
                'max:24',
                'regex:/^[A-Za-z0-9]+(?:[._-][A-Za-z0-9]+)*$/',
            ],

            'start_number' => [
                'required',
                'integer',
                'min:0',
                'max:999999999',
            ],

            'padding' => [
                'required',
                'integer',
                Rule::in([
                    2,
                    3,
                    4,
                    5,
                    6,
                ]),
            ],

            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:200',
            ],

            'condition' => [
                'required',
                Rule::in([
                    'good',
                    'fair',
                    'damaged',
                ]),
            ],

            'purchase_date' => [
                'nullable',
                'date',
            ],

            'purchase_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'print_qr_after_create' => [
                'nullable',
                'boolean',
            ],
        ], [
            'prefix.regex' => 'Prefix hanya boleh berisi huruf, angka, titik, underscore, atau dash. Contoh: EXT-BOX.',
            'quantity.max' => 'Maksimal 200 asset per batch agar transaksi dan print QR tetap aman.',
        ]);

        $item = InventoryItem::query()
            ->where('tracking_type', 'serialized')
            ->where('is_active', true)
            ->findOrFail(
                $validated['inventory_item_id']
            );

        $prefix = strtoupper(
            trim(
                $validated['prefix']
            )
        );

        $startNumber = (int) $validated['start_number'];
        $padding = (int) $validated['padding'];
        $quantity = (int) $validated['quantity'];

        $assetCodes = [];

        for ($offset = 0; $offset < $quantity; $offset++) {
            $number = $startNumber + $offset;

            $numberText = str_pad(
                (string) $number,
                $padding,
                '0',
                STR_PAD_LEFT
            );

            $assetCode = $prefix.'-'.$numberText;

            /*
             * Internal QR generator currently supports up to
             * 32 ASCII characters.
             */
            if (strlen($assetCode) > 32) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'prefix' => sprintf(
                            'Asset Code %s terlalu panjang untuk QR internal. Maksimal 32 karakter.',
                            $assetCode
                        ),
                    ]);
            }

            $assetCodes[] = $assetCode;
        }

        $duplicateInBatch = collect($assetCodes)
            ->duplicates()
            ->unique()
            ->values();

        if ($duplicateInBatch->isNotEmpty()) {
            return back()
                ->withInput()
                ->withErrors([
                    'prefix' => 'Generator menghasilkan kode duplicate: '
                        .$duplicateInBatch->implode(', '),
                ]);
        }

        $existingCodes = InventoryAsset::query()
            ->whereIn(
                'asset_code',
                $assetCodes
            )
            ->orWhereIn(
                'barcode_value',
                $assetCodes
            )
            ->get([
                'asset_code',
                'barcode_value',
            ])
            ->flatMap(
                fn ($asset) => [
                    $asset->asset_code,
                    $asset->barcode_value,
                ]
            )
            ->filter()
            ->intersect($assetCodes)
            ->unique()
            ->values();

        if ($existingCodes->isNotEmpty()) {
            return back()
                ->withInput()
                ->withErrors([
                    'prefix' => 'Kode berikut sudah digunakan: '
                        .$existingCodes->take(15)->implode(', ')
                        .(
                            $existingCodes->count() > 15
                                ? ' ...'
                                : ''
                        ),
                ]);
        }

        $condition = $validated['condition'];
        $status = $condition === 'damaged'
            ? 'damaged'
            : 'available';

        $performedBy = auth()
            ->guard('user')
            ->id();

        $createdAssets = DB::transaction(
            function () use (
                $assetCodes,
                $item,
                $condition,
                $status,
                $performedBy,
                $validated
            ) {
                $assets = collect();

                foreach ($assetCodes as $assetCode) {
                    $asset = InventoryAsset::create([
                        'inventory_item_id' => $item->id,
                        'asset_code' => $assetCode,

                        /*
                         * New standard:
                         * Asset Code == Barcode Value == QR Payload.
                         */
                        'barcode_value' => $assetCode,

                        'serial_number' => null,
                        'warehouse_id' => $item->warehouse_id,
                        'warehouse_location_id' => $item->warehouse_location_id,
                        'status' => $status,
                        'condition' => $condition,
                        'purchase_date' => $validated['purchase_date']
                            ?? null,
                        'purchase_price' => $validated['purchase_price']
                            ?? null,
                        'notes' => ! empty($validated['notes'])
                            ? trim($validated['notes'])
                            : null,
                    ]);

                    InventoryStockMovement::create([
                        'inventory_item_id' => $item->id,
                        'inventory_asset_id' => $asset->id,
                        'warehouse_id' => $asset->warehouse_id,
                        'warehouse_location_id' => $asset->warehouse_location_id,
                        'movement_type' => 'opening',
                        'quantity' => 1,
                        'from_status' => null,
                        'to_status' => $status,
                        'reference_type' => 'bulk_asset_registration',
                        'reference_id' => $asset->id,
                        'reference_number' => $asset->asset_code,
                        'performed_by' => $performedBy,
                        'notes' => sprintf(
                            'Bulk asset registration for %s.',
                            $item->code
                        ),
                        'occurred_at' => now(),
                    ]);

                    $assets->push($asset);
                }

                return $assets;
            }
        );

        session()->flash(
            'success',
            sprintf(
                '%d asset %s berhasil dibuat: %s sampai %s.',
                $createdAssets->count(),
                $item->name,
                $createdAssets->first()?->asset_code,
                $createdAssets->last()?->asset_code
            )
        );

        if (
            (bool) (
                $validated['print_qr_after_create']
                ?? false
            )
        ) {
            return redirect()->route(
                'admin.inventory.assets.qr-labels.index',
                [
                    'ids' => $createdAssets
                        ->pluck('id')
                        ->implode(','),
                ]
            );
        }

        return redirect()->route(
            'admin.inventory.assets.index',
            [
                'inventory_item_id' => $item->id,
            ]
        );
    }

    public function create(): View
    {
        $items = InventoryItem::query()
            ->where('tracking_type', 'serialized')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedItemId = request()->integer('inventory_item_id') ?: null;

        return view(
            'admin::inventory.assets.create',
            compact('items', 'selectedItemId')
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')
                    ->where(fn ($query) => $query
                        ->where('tracking_type', 'serialized')
                        ->where('is_active', true)),
            ],
            'asset_code' => [
                'required',
                'string',
                'max:50',
                'unique:inventory_assets,asset_code',
            ],
            'barcode_value' => [
                'nullable',
                'string',
                'max:100',
                'unique:inventory_assets,barcode_value',
            ],
            'serial_number' => [
                'nullable',
                'string',
                'max:150',
                'unique:inventory_assets,serial_number',
            ],
            'condition' => [
                'required',
                Rule::in(['good', 'fair', 'damaged']),
            ],
            'purchase_date'  => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string'],
        ]);

        $item = InventoryItem::query()
            ->where('tracking_type', 'serialized')
            ->where('is_active', true)
            ->findOrFail($validated['inventory_item_id']);

        $asset = DB::transaction(function () use ($validated, $item) {
            $assetCode = trim($validated['asset_code']);

            $barcodeValue = trim((string) ($validated['barcode_value'] ?? ''));

            if ($barcodeValue === '') {
                $barcodeValue = $assetCode;
            }

            $condition = $validated['condition'];

            $status = $condition === 'damaged'
                ? 'damaged'
                : 'available';

            $asset = InventoryAsset::create([
                'inventory_item_id'     => $item->id,
                'asset_code'            => $assetCode,
                'barcode_value'         => $barcodeValue,
                'serial_number'         => ! empty($validated['serial_number'])
                    ? trim($validated['serial_number'])
                    : null,
                'warehouse_id'          => $item->warehouse_id,
                'warehouse_location_id' => $item->warehouse_location_id,
                'status'                => $status,
                'condition'             => $condition,
                'purchase_date'         => $validated['purchase_date'] ?? null,
                'purchase_price'        => $validated['purchase_price'] ?? null,
                'notes'                 => $validated['notes'] ?? null,
            ]);

            InventoryStockMovement::create([
                'inventory_item_id'     => $item->id,
                'inventory_asset_id'    => $asset->id,
                'warehouse_id'          => $asset->warehouse_id,
                'warehouse_location_id' => $asset->warehouse_location_id,
                'movement_type'         => 'opening',
                'quantity'              => 1,
                'from_status'           => null,
                'to_status'             => $status,
                'reference_type'        => 'asset_registration',
                'reference_id'          => $asset->id,
                'reference_number'      => $asset->asset_code,
                'performed_by'          => auth()->guard('user')->id(),
                'notes'                 => 'Initial asset registration.',
                'occurred_at'            => now(),
            ]);

            return $asset;
        });

        session()->flash(
            'success',
            'Asset '.$asset->asset_code.' berhasil dibuat.'
        );

        return redirect()->route(
            'admin.inventory.assets.edit',
            $asset->id
        );
    }

    public function edit(int $id): View
    {
        $asset = InventoryAsset::with([
            'item',
            'warehouse',
            'location',
            'maintenances' => fn ($query) => $query
                ->orderByDesc('id'),
            'maintenances.startedBy',
            'maintenances.completedBy',
            'maintenances.retiredBy',
        ])->findOrFail($id);

        $activeMaintenance = $asset->maintenances
            ->firstWhere('status', 'in_progress');

        return view(
            'admin::inventory.assets.edit',
            compact(
                'asset',
                'activeMaintenance'
            )
        );
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $asset = InventoryAsset::findOrFail($id);

        $validated = $request->validate([
            'asset_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('inventory_assets', 'asset_code')
                    ->ignore($asset->id),
            ],
            'barcode_value' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('inventory_assets', 'barcode_value')
                    ->ignore($asset->id),
            ],
            'serial_number' => [
                'nullable',
                'string',
                'max:150',
                Rule::unique('inventory_assets', 'serial_number')
                    ->ignore($asset->id),
            ],
            'purchase_date'  => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string'],
        ]);

        $assetCode = trim($validated['asset_code']);

        $barcodeValue = trim((string) ($validated['barcode_value'] ?? ''));

        if ($barcodeValue === '') {
            $barcodeValue = $assetCode;
        }

        $asset->update([
            'asset_code'     => $assetCode,
            'barcode_value'  => $barcodeValue,
            'serial_number'  => ! empty($validated['serial_number'])
                ? trim($validated['serial_number'])
                : null,
            'purchase_date'  => $validated['purchase_date'] ?? null,
            'purchase_price' => $validated['purchase_price'] ?? null,
            'notes'          => $validated['notes'] ?? null,
        ]);

        session()->flash(
            'success',
            'Asset '.$asset->asset_code.' berhasil diperbarui.'
        );

        return redirect()->route(
            'admin.inventory.assets.edit',
            $asset->id
        );
    }
    /**
     * MISSING_ASSET_RECOVERY_V1
     *
     * Recover one serialized asset without forcing a full stock opname.
     * A recovery is allowed only from MISSING and is recorded as an
     * inventory movement. Active Delivery Order allocations block recovery.
     */
    public function recover(
        \Illuminate\Http\Request $request,
        int $id
    ): \Illuminate\Http\RedirectResponse {
        if (
            function_exists('bouncer')
            && ! bouncer()->hasPermission('inventory.assets.edit')
        ) {
            abort(403);
        }

        $validated = $request->validate([
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
                $id,
                $validated
            ): void {
                $asset =
                    \Webkul\Warehouse\Models\InventoryAsset::query()
                        ->lockForUpdate()
                        ->findOrFail($id);

                if (
                    strtolower((string) $asset->status)
                    !== 'missing'
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'recovery_status' =>
                            'Hanya asset berstatus MISSING yang dapat dipulihkan.',
                    ]);
                }

                $hasActiveAllocation =
                    \Illuminate\Support\Facades\DB::table(
                        'delivery_order_inventory_allocations'
                    )
                        ->where(
                            'inventory_asset_id',
                            $asset->id
                        )
                        ->whereNotIn(
                            'status',
                            [
                                'checked_in',
                                'released',
                            ]
                        )
                        ->exists();

                if ($hasActiveAllocation) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'recovery_status' =>
                            'Asset masih memiliki allocation / Delivery Order aktif. '
                            .'Selesaikan transaksi tersebut sebelum recovery.',
                    ]);
                }

                $fromStatus =
                    strtolower(
                        (string) $asset->status
                    );

                $toStatus =
                    (string) $validated[
                        'recovery_status'
                    ];

                $asset->status =
                    $toStatus;

                if ($toStatus === 'damaged') {
                    $asset->condition =
                        'damaged';
                } elseif (
                    strtolower(
                        (string) $asset->condition
                    ) === 'damaged'
                ) {
                    $asset->condition =
                        'good';
                }

                $asset->save();

                \Webkul\Warehouse\Models\InventoryStockMovement::query()
                    ->create([
                        'inventory_item_id' =>
                            $asset->inventory_item_id,

                        'inventory_asset_id' =>
                            $asset->id,

                        'warehouse_id' =>
                            $asset->warehouse_id,

                        'warehouse_location_id' =>
                            $asset->warehouse_location_id,

                        'movement_type' =>
                            'missing_recovered',

                        'quantity' =>
                            1,

                        'from_status' =>
                            $fromStatus,

                        'to_status' =>
                            $toStatus,

                        'reference_type' =>
                            'asset_recovery',

                        'reference_id' =>
                            $asset->id,

                        'reference_number' =>
                            $asset->asset_code,

                        'performed_by' =>
                            auth()
                                ->guard('user')
                                ->id(),

                        'notes' =>
                            trim(
                                (string) $validated[
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
}
