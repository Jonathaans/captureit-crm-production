<?php

namespace Webkul\Admin\Http\Controllers\Inventory;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\InventoryMissingRecoveryService;
use Webkul\Warehouse\Models\InventoryAsset;

class InventoryMissingRecoveryScanController extends Controller
{
    public function create(int $id): View|RedirectResponse
    {
        abort_unless(
            bouncer()->hasPermission('inventory.assets.edit'),
            403
        );

        $asset = InventoryAsset::query()
            ->with(['item', 'warehouse'])
            ->findOrFail($id);

        if ($asset->status !== 'missing') {
            return redirect()
                ->route('admin.inventory.assets.edit', $asset->id)
                ->with('warning', 'Recovery scan hanya tersedia untuk asset berstatus MISSING.');
        }

        $warehouses = DB::table('warehouses')
            ->orderBy('name')
            ->get(['id', 'name']);

        $recoveryToken = Str::random(64);

        session()->put(
            $this->sessionKey($asset->id),
            hash('sha256', $recoveryToken)
        );

        return view('admin::inventory.assets.recover-missing-scan', compact(
            'asset',
            'warehouses',
            'recoveryToken'
        ));
    }

    public function store(
        Request $request,
        int $id,
        InventoryMissingRecoveryService $recoveryService
    ): RedirectResponse {
        abort_unless(
            bouncer()->hasPermission('inventory.assets.edit'),
            403
        );

        $validated = $request->validate([
            'scanned_barcode' => ['required', 'string', 'max:100'],
            'condition' => ['required', Rule::in(['good', 'fair', 'damaged'])],
            'found_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'found_location' => ['required', 'string', 'max:255'],
            'found_note' => ['required', 'string', 'max:2000'],
            'damage_reason' => ['nullable', 'required_if:condition,damaged', 'string', 'max:2000'],
            'confirmed' => ['accepted'],
            'recovery_token' => ['required', 'string', 'size:64'],
        ], [
            'scanned_barcode.required' => 'Barcode/QR asset wajib dipindai.',
            'damage_reason.required_if' => 'Alasan kerusakan wajib diisi ketika kondisi DAMAGED.',
            'confirmed.accepted' => 'Konfirmasi identitas dan kondisi asset wajib dicentang.',
        ]);

        $storedTokenHash = (string) session()->pull($this->sessionKey($id), '');
        $submittedTokenHash = hash('sha256', (string) $validated['recovery_token']);

        if ($storedTokenHash === '' || ! hash_equals($storedTokenHash, $submittedTokenHash)) {
            throw ValidationException::withMessages([
                'scanned_barcode' => 'Sesi recovery sudah kedaluwarsa atau telah digunakan. Muat ulang halaman dan scan kembali.',
            ]);
        }

        $movement = $recoveryService->recover(
            $id,
            $validated,
            (int) auth()->guard('user')->id()
        );

        $message = $movement->to_status === 'damaged'
            ? 'Asset ditemukan dalam kondisi DAMAGED. Missing alert ditutup, damaged alert aktif, dan movement tercatat.'
            : 'Asset ditemukan dan kembali AVAILABLE. Recovery scan tercatat di Inventory Movement.';

        return redirect()
            ->route('admin.inventory.movements.index', [
                'inventory_asset_id' => $movement->inventory_asset_id,
            ])
            ->with('success', $message);
    }

    private function sessionKey(int $assetId): string
    {
        return 'inventory.missing_recovery_scan.'.$assetId;
    }
}
