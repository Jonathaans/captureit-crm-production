<?php

namespace Webkul\Admin\Http\Controllers\DeliveryOrder;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Invoice\Services\DeliveryOrderReturnService;
use Webkul\Warehouse\Models\InventoryAsset;

class DeliveryOrderReturnController extends Controller
{
    public function __construct(
        protected DeliveryOrderReturnService $returnService
    ) {
    }

    public function show(int $id): View
    {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $allocations = DeliveryOrderInventoryAllocation::query()
            ->with([
                'deliveryOrderItem',
                'inventoryItem',
                'inventoryAsset',
                'checkedInBy',
            ])
            ->where('delivery_order_id', $deliveryOrder->id)
            ->whereIn('status', [
                'out',
                'return_pending',
                'returned',
            ])
            ->orderBy('delivery_order_item_id')
            ->orderBy('id')
            ->get();

        $summary = [
            'out' => $allocations
                ->where('status', 'out')
                ->count(),

            'return_pending' => $allocations
                ->where('status', 'return_pending')
                ->count(),

            'returned' => $allocations
                ->where('status', 'returned')
                ->count(),
        ];

        $canOperate = strtolower(
            $deliveryOrder->status ?: 'draft'
        ) === 'delivered';

        $allReturned = $this->returnService
            ->allReturned($deliveryOrder);

        return view(
            'admin::delivery-orders.return',
            compact(
                'deliveryOrder',
                'allocations',
                'summary',
                'canOperate',
                'allReturned'
            )
        );
    }

    /**
     * Scanner or manual Check-In for serialized assets.
     *
     * Default condition from UI is GOOD. For damaged/fair, choose the
     * condition before scanning. Missing assets still use the row form
     * because naturally there is nothing physical to scan.
     */
    public function scanCheckIn(
        Request $request,
        int $id
    ): JsonResponse|RedirectResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $validated = $request->validate([
            'barcode' => [
                'required',
                'string',
                'max:150',
            ],
        ]);

        $barcode = trim(
            $validated['barcode']
        );

        $asset = $this->findAssetByReturnIdentifier(
            $barcode
        );

        if (! $asset) {
            throw ValidationException::withMessages([
                'barcode' => 'QR / Barcode / Asset Code / Serial Number tidak ditemukan: '.$barcode,
            ]);
        }

        $allocation = DeliveryOrderInventoryAllocation::query()
            ->with([
                'deliveryOrderItem',
                'inventoryItem',
                'inventoryAsset',
            ])
            ->where('delivery_order_id', $deliveryOrder->id)
            ->where('tracking_type', 'serialized')
            ->where('inventory_asset_id', $asset->id)
            ->whereIn(
                'status',
                [
                    'out',
                    'return_pending',
                    'returned',
                ]
            )
            ->orderByDesc('id')
            ->first();

        if (! $allocation) {
            throw ValidationException::withMessages([
                'barcode' => sprintf(
                    'Asset %s bukan barang milik %s yang sedang Return.',
                    $asset->asset_code,
                    $deliveryOrder->delivery_order_number
                ),
            ]);
        }

        $previousStatus = $allocation->status;

        if ($allocation->status === 'out') {
            $allocation = $this->returnService
                ->scanReturnSerialized(
                    $deliveryOrder,
                    $allocation,
                    auth()->guard('user')->id()
                );

            $allocation->load([
                'deliveryOrderItem',
                'inventoryAsset',
            ]);
        }

        $alreadyReceived = in_array(
            $previousStatus,
            [
                'return_pending',
                'returned',
            ],
            true
        );

        $message = match ($previousStatus) {
            'returned' => $asset->asset_code.' sudah selesai Return.',
            'return_pending' => $asset->asset_code.' sudah discan masuk warehouse.',
            default => sprintf(
                'RECEIVED: %s -> menunggu inspection.',
                $asset->asset_code
            ),
        };

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'duplicate' => $alreadyReceived,
                'message' => $message,
                'allocation' => [
                    'id' => $allocation->id,
                    'asset_code' => $asset->asset_code,
                    'item_name' => $allocation->deliveryOrderItem?->name,
                    'status' => $allocation->status,
                    'received_at' => optional(
                        $allocation->return_pending_at
                    )->format('d M Y H:i:s'),
                ],
            ]);
        }

        return redirect()
            ->route(
                'admin.delivery-orders.return.show',
                $deliveryOrder->id
            )
            ->with('success', $message);
    }

    /**
     * Save all quantity returns in one submit.
     */
        /**
     * RETURN DAMAGED NOTE V1
     *
     * Finalize inspection and quantity return in one submit. A damage reason
     * is mandatory for every serialized asset marked DAMAGED.
     */
    public function finalize(
        Request $request,
        int $id
    ): RedirectResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $validated = $request->validate([
            'conditions' => [
                'nullable',
                'array',
            ],

            'conditions.*' => [
                'required',
                Rule::in([
                    'good',
                    'fair',
                    'damaged',
                ]),
            ],

            'return_notes' => [
                'nullable',
                'array',
            ],

            'return_notes.*' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'quantities' => [
                'nullable',
                'array',
            ],

            'quantities.*' => [
                'required',
                'numeric',
                'min:0',
            ],
        ]);

        $conditions = $validated['conditions'] ?? [];
        $returnNotes = $validated['return_notes'] ?? [];
        $damageErrors = [];

        foreach ($conditions as $allocationId => $condition) {
            if (strtolower((string) $condition) !== 'damaged') {
                continue;
            }

            $damageNote = trim((string) ($returnNotes[$allocationId] ?? ''));

            if ($damageNote === '') {
                $damageErrors['return_notes.'.$allocationId] =
                    'Alasan kerusakan wajib diisi untuk barang DAMAGED.';
            }
        }

        if ($damageErrors !== []) {
            throw ValidationException::withMessages($damageErrors);
        }

        $this->returnService->finalizeReturnBatch(
            $deliveryOrder,
            $conditions,
            $validated['quantities'] ?? [],
            auth()->guard('user')->id(),
            $returnNotes
        );

        return redirect()
            ->route(
                'admin.delivery-orders.return.show',
                $deliveryOrder->id
            )
            ->with(
                'success',
                'Return berhasil difinalisasi. Condition, alasan kerusakan, dan quantity return sudah disimpan.'
            );
    }


    public function checkIn(
        Request $request,
        int $id,
        int $allocationId
    ): RedirectResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $allocation = DeliveryOrderInventoryAllocation::query()
            ->where('delivery_order_id', $deliveryOrder->id)
            ->findOrFail($allocationId);

        if ($allocation->tracking_type === 'serialized') {
            $validated = $request->validate([
                'condition' => [
                    'required',
                    Rule::in([
                        'good',
                        'fair',
                        'damaged',
                        'missing',
                    ]),
                ],
                'notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

            $this->returnService->checkInSerialized(
                $deliveryOrder,
                $allocation,
                $validated['condition'],
                $validated['notes'] ?? null,
                auth()->guard('user')->id()
            );
        } elseif ($allocation->tracking_type === 'quantity') {
            $validated = $request->validate([
                'returned_quantity' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:'.(float) $allocation->quantity,
                ],
                'notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

            $this->returnService->checkInQuantity(
                $deliveryOrder,
                $allocation,
                (float) $validated['returned_quantity'],
                $validated['notes'] ?? null,
                auth()->guard('user')->id()
            );
        } else {
            throw ValidationException::withMessages([
                'inventory' => 'Tracking type allocation tidak dikenali.',
            ]);
        }

        session()->flash(
            'success',
            'Inventory berhasil di-Check-In.'
        );

        return redirect()->route(
            'admin.delivery-orders.return.show',
            $deliveryOrder->id
        );
    }

    /**
     * Resolve input from the scanner and manual fallback through one path.
     *
     * Asset Code and Barcode are unique. Serial Number is allowed only when
     * it resolves to exactly one asset, preventing an ambiguous manual return.
     */
    private function findAssetByReturnIdentifier(
        string $identifier
    ): ?InventoryAsset {
        $asset = InventoryAsset::query()
            ->where(function ($query) use ($identifier) {
                $query
                    ->where('barcode_value', $identifier)
                    ->orWhere('asset_code', $identifier);
            })
            ->first();

        if ($asset) {
            return $asset;
        }

        $serialMatches = InventoryAsset::query()
            ->where('serial_number', $identifier)
            ->limit(2)
            ->get();

        if ($serialMatches->count() > 1) {
            throw ValidationException::withMessages([
                'barcode' => 'Serial Number digunakan oleh lebih dari satu asset. Gunakan Asset Code agar tidak salah return.',
            ]);
        }

        return $serialMatches->first();
    }

    private function findDeliveryOrder(
        int $id
    ): DeliveryOrder {
        return DeliveryOrder::with([
            'items.inventoryItem',
            'items.allocations.inventoryAsset',
        ])->findOrFail($id);
    }
}
