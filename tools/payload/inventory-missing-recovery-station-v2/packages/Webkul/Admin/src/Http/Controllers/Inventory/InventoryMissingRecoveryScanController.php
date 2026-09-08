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
    private const VERIFICATION_TTL_SECONDS = 1800;

    /**
     * Global recovery station. One native autofocus input receives the entire
     * keyboard-wedge scan and the browser submits it when the scanner sends Enter.
     */
    public function station(Request $request): View
    {
        $this->authorizeRecovery();

        $verification = session()->get($this->stationSessionKey());
        $verifiedAsset = null;
        $recoveryToken = null;
        $verificationExpired = false;

        if (is_array($verification)) {
            $verifiedAt = (int) ($verification['verified_at'] ?? 0);
            $assetId = (int) ($verification['asset_id'] ?? 0);

            if ($verifiedAt < now()->timestamp - self::VERIFICATION_TTL_SECONDS) {
                session()->forget($this->stationSessionKey());
                $verificationExpired = true;
            } else {
                $verifiedAsset = InventoryAsset::query()
                    ->with(['item', 'warehouse'])
                    ->find($assetId);

                if (! $verifiedAsset || $verifiedAsset->status !== 'missing') {
                    session()->forget($this->stationSessionKey());
                    $verifiedAsset = null;
                } else {
                    $recoveryToken = (string) ($verification['token'] ?? '');
                }
            }
        }

        $expectedAsset = null;
        $expectedAssetId = (int) $request->query('expected_asset_id', 0);

        if (! $verifiedAsset && $expectedAssetId > 0) {
            $expectedAsset = InventoryAsset::query()
                ->with(['item', 'warehouse'])
                ->where('status', 'missing')
                ->find($expectedAssetId);
        }

        $warehouses = DB::table('warehouses')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin::inventory.assets.recover-missing-station', compact(
            'verifiedAsset',
            'expectedAsset',
            'warehouses',
            'recoveryToken',
            'verificationExpired'
        ));
    }

    /**
     * Server-side scan verification. No global JavaScript listener is involved.
     */
    public function verify(
        Request $request,
        InventoryMissingRecoveryService $recoveryService
    ): RedirectResponse {
        $this->authorizeRecovery();

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
        ], [
            'barcode.required' => 'Scan barcode/QR asset terlebih dahulu.',
        ]);

        $scannedBarcode = trim((string) $validated['barcode']);
        $asset = $recoveryService->findByBarcode($scannedBarcode, 'missing');

        if (! $asset) {
            $existingAsset = $recoveryService->findByBarcode($scannedBarcode);

            $message = $existingAsset
                ? 'Asset '.$existingAsset->asset_code.' ditemukan, tetapi status saat ini '
                    .strtoupper((string) $existingAsset->status).'—bukan MISSING.'
                : 'Barcode/QR tidak dikenali sebagai asset inventory.';

            return back()
                ->withErrors(['barcode' => $message])
                ->withInput();
        }

        $token = Str::random(64);

        session()->put($this->stationSessionKey(), [
            'asset_id' => (int) $asset->id,
            'scanned_barcode' => $scannedBarcode,
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'verified_at' => now()->timestamp,
        ]);

        return redirect()
            ->route('admin.inventory.missing-recovery.station')
            ->with('success', 'Scan cocok: '.$asset->asset_code.'. Lanjutkan konfirmasi kondisi dan lokasi.');
    }

    public function complete(
        Request $request,
        int $id,
        InventoryMissingRecoveryService $recoveryService
    ): RedirectResponse {
        $this->authorizeRecovery();

        $validated = $request->validate([
            'condition' => ['required', Rule::in(['good', 'fair', 'damaged'])],
            'found_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'found_location' => ['required', 'string', 'max:255'],
            'found_note' => ['required', 'string', 'max:2000'],
            'damage_reason' => ['nullable', 'required_if:condition,damaged', 'string', 'max:2000'],
            'confirmed' => ['accepted'],
            'recovery_token' => ['required', 'string', 'size:64'],
        ], [
            'damage_reason.required_if' => 'Alasan kerusakan wajib diisi ketika kondisi DAMAGED.',
            'confirmed.accepted' => 'Konfirmasi identitas dan kondisi asset wajib dicentang.',
        ]);

        $verification = session()->get($this->stationSessionKey());

        if (! is_array($verification)) {
            throw ValidationException::withMessages([
                'barcode' => 'Sesi scan tidak ditemukan. Mulai kembali dari Recovery Station.',
            ]);
        }

        $verifiedAt = (int) ($verification['verified_at'] ?? 0);
        $verifiedAssetId = (int) ($verification['asset_id'] ?? 0);
        $storedTokenHash = (string) ($verification['token_hash'] ?? '');
        $submittedTokenHash = hash('sha256', (string) $validated['recovery_token']);

        if (
            $verifiedAt < now()->timestamp - self::VERIFICATION_TTL_SECONDS
            || $verifiedAssetId !== $id
            || $storedTokenHash === ''
            || ! hash_equals($storedTokenHash, $submittedTokenHash)
        ) {
            session()->forget($this->stationSessionKey());

            throw ValidationException::withMessages([
                'barcode' => 'Verifikasi scan kedaluwarsa atau tidak cocok. Scan ulang barang fisik.',
            ]);
        }

        $validated['scanned_barcode'] = (string) ($verification['scanned_barcode'] ?? '');

        $movement = $recoveryService->recover(
            $id,
            $validated,
            (int) auth()->guard('user')->id()
        );

        session()->forget($this->stationSessionKey());

        $message = $movement->to_status === 'damaged'
            ? 'Asset ditemukan dalam kondisi DAMAGED. Missing alert ditutup, damaged alert aktif, dan movement tercatat.'
            : 'Asset ditemukan dan kembali AVAILABLE. Recovery tercatat di Inventory Movement.';

        return redirect()
            ->route('admin.inventory.movements.index', [
                'inventory_asset_id' => $movement->inventory_asset_id,
            ])
            ->with('success', $message);
    }

    public function clear(): RedirectResponse
    {
        $this->authorizeRecovery();
        session()->forget($this->stationSessionKey());

        return redirect()
            ->route('admin.inventory.missing-recovery.station')
            ->with('success', 'Verifikasi dibatalkan. Recovery Station siap untuk scan berikutnya.');
    }

    /**
     * Backward-compatible entry from the existing Asset Edit button.
     */
    public function create(int $id): RedirectResponse
    {
        $this->authorizeRecovery();

        $asset = InventoryAsset::query()->findOrFail($id);

        if ($asset->status !== 'missing') {
            return redirect()
                ->route('admin.inventory.assets.edit', $asset->id)
                ->with('warning', 'Recovery hanya tersedia untuk asset berstatus MISSING.');
        }

        session()->forget($this->stationSessionKey());

        return redirect()->route('admin.inventory.missing-recovery.station', [
            'expected_asset_id' => $asset->id,
        ]);
    }

    /**
     * Old POST endpoint is intentionally retired to prevent stale scanner UI.
     */
    public function store(Request $request, int $id): RedirectResponse
    {
        $this->authorizeRecovery();

        return redirect()
            ->route('admin.inventory.missing-recovery.station', [
                'expected_asset_id' => $id,
            ])
            ->with('warning', 'Halaman scanner lama sudah dinonaktifkan. Scan melalui Recovery Station.');
    }

    private function authorizeRecovery(): void
    {
        abort_unless(bouncer()->hasPermission('inventory.assets.edit'), 403);
    }

    private function stationSessionKey(): string
    {
        return 'inventory.missing_recovery_station.verification';
    }
}
