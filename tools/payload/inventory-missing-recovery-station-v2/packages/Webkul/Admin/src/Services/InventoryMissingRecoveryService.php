<?php

namespace Webkul\Admin\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Warehouse\Models\InventoryAsset;
use Webkul\Warehouse\Models\InventoryStockMovement;

class InventoryMissingRecoveryService
{
    /**
     * Resolve the physical scan on the server. This deliberately does not
     * depend on a browser key-buffer: a keyboard-wedge scanner writes into a
     * normal input and the native form submit sends the complete value here.
     */
    public function findByBarcode(string $scannedBarcode, ?string $status = null): ?InventoryAsset
    {
        $scanned = $this->normalize($scannedBarcode);

        if ($scanned === '') {
            return null;
        }

        return InventoryAsset::query()
            ->with(['item', 'warehouse'])
            ->when(
                $status !== null,
                static fn (Builder $query) => $query->where('status', $status)
            )
            ->where(function (Builder $query) use ($scanned) {
                $query
                    ->whereRaw('UPPER(TRIM(asset_code)) = ?', [$scanned])
                    ->orWhereRaw('UPPER(TRIM(barcode_value)) = ?', [$scanned]);
            })
            ->first();
    }

    /**
     * Accept the printed barcode value or asset code for legacy labels.
     */
    public function barcodeMatches(InventoryAsset $asset, string $scannedBarcode): bool
    {
        $scanned = $this->normalize($scannedBarcode);

        if ($scanned === '') {
            return false;
        }

        $expectedValues = array_filter([
            $this->normalize((string) $asset->barcode_value),
            $this->normalize((string) $asset->asset_code),
        ]);

        foreach (array_unique($expectedValues) as $expected) {
            if (hash_equals($expected, $scanned)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recover one missing serialized asset and write its immutable movement
     * in the same database transaction. Row locking prevents double recovery.
     */
    public function recover(int $assetId, array $data, int $performedBy): InventoryStockMovement
    {
        return DB::transaction(function () use ($assetId, $data, $performedBy) {
            /** @var InventoryAsset $asset */
            $asset = InventoryAsset::query()
                ->with(['item', 'warehouse'])
                ->lockForUpdate()
                ->findOrFail($assetId);

            if ($asset->status !== 'missing') {
                throw ValidationException::withMessages([
                    'barcode' => 'Asset ini sudah tidak berstatus MISSING. Mulai recovery baru.',
                ]);
            }

            if (! $this->barcodeMatches($asset, (string) $data['scanned_barcode'])) {
                throw ValidationException::withMessages([
                    'barcode' => 'Barcode/QR tidak lagi cocok dengan asset '.$asset->asset_code.'.',
                ]);
            }

            $condition = strtolower((string) $data['condition']);

            if (! in_array($condition, ['good', 'fair', 'damaged'], true)) {
                throw ValidationException::withMessages([
                    'condition' => 'Kondisi saat ditemukan tidak valid.',
                ]);
            }

            if ($condition === 'damaged' && trim((string) ($data['damage_reason'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'damage_reason' => 'Alasan kerusakan wajib diisi untuk asset DAMAGED.',
                ]);
            }

            $warehouse = DB::table('warehouses')
                ->where('id', (int) $data['found_warehouse_id'])
                ->first(['id', 'name']);

            if (! $warehouse) {
                throw ValidationException::withMessages([
                    'found_warehouse_id' => 'Warehouse tempat asset ditemukan tidak valid.',
                ]);
            }

            $targetStatus = $condition === 'damaged' ? 'damaged' : 'available';
            $foundLocation = trim((string) $data['found_location']);
            $foundNote = trim((string) $data['found_note']);
            $damageReason = trim((string) ($data['damage_reason'] ?? ''));
            $recoveryNumber = 'REC-'.now()->format('Ymd-His').'-'.$asset->id;

            $auditNote = implode(' | ', array_filter([
                'Missing asset recovered at Recovery Station',
                'Physical scan: '.trim((string) $data['scanned_barcode']),
                'Condition: '.strtoupper($condition),
                'Warehouse: '.$warehouse->name,
                'Location: '.$foundLocation,
                'Note: '.$foundNote,
                $damageReason !== '' ? 'Damage reason: '.$damageReason : null,
            ]));

            $existingNotes = trim((string) $asset->notes);
            $assetNotes = trim(implode(PHP_EOL, array_filter([
                $existingNotes,
                '['.now()->format('Y-m-d H:i:s').'] '.$auditNote,
            ])));

            $warehouseChanged = (int) $asset->warehouse_id !== (int) $warehouse->id;

            $asset->forceFill([
                'warehouse_id'          => (int) $warehouse->id,
                'warehouse_location_id' => $warehouseChanged ? null : $asset->warehouse_location_id,
                'status'                => $targetStatus,
                'condition'             => $condition,
                'notes'                 => $assetNotes,
            ])->save();

            return InventoryStockMovement::create([
                'inventory_item_id'      => $asset->inventory_item_id,
                'inventory_asset_id'     => $asset->id,
                'warehouse_id'           => (int) $warehouse->id,
                'warehouse_location_id'  => $asset->warehouse_location_id,
                'movement_type'          => 'missing_recovered',
                'quantity'               => 1,
                'from_status'            => 'missing',
                'to_status'              => $targetStatus,
                'reference_type'         => 'missing_recovery',
                'reference_id'           => $asset->id,
                'reference_number'       => $recoveryNumber,
                'performed_by'           => $performedBy,
                'notes'                  => $auditNote,
                'occurred_at'            => now(),
            ]);
        }, 3);
    }

    private function normalize(string $value): string
    {
        return strtoupper(trim($value));
    }
}
