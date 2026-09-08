<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Warehouse\Models\InventoryAsset;
use Webkul\Warehouse\Models\InventoryStockMovement;

class InventoryMissingRecoveryService
{
    /**
     * Barcode/QR printed for older assets may contain either barcode_value
     * or asset_code. Both are accepted, but an arbitrary/manual value is not.
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
     * Recover one missing serialized asset and create its immutable movement
     * entry atomically. Concurrent/double submissions cannot recover twice.
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
                    'scanned_barcode' => 'Asset ini sudah tidak berstatus MISSING. Muat ulang halaman sebelum melanjutkan.',
                ]);
            }

            if (! $this->barcodeMatches($asset, (string) $data['scanned_barcode'])) {
                throw ValidationException::withMessages([
                    'scanned_barcode' => 'Barcode/QR yang dipindai tidak cocok dengan asset '.$asset->asset_code.'.',
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
                'Missing asset recovered by barcode scan',
                'Scan: '.trim((string) $data['scanned_barcode']),
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
