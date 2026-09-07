<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Webkul\Admin\Models\CrmNotification;

class CrmOperationalAlertService
{
    public function generate(): array
    {
        if (! Schema::hasTable('crm_notifications')) {
            return ['created_or_updated' => 0, 'resolved' => 0];
        }

        $active = [];
        $health = app(CrmOperationalHealthService::class)->summary();

        foreach ([
            CrmOperationalHealthService::BACKUP => 'Backup bermasalah',
            CrmOperationalHealthService::QUEUE => 'Queue worker berhenti',
        ] as $key => $title) {
            $item = $health[$key] ?? null;

            if (! $item || ! in_array($item['status'], ['failed', 'stale', 'warning', 'unknown'], true)) {
                continue;
            }

            $dedupe = 'ops-health:'.$key;
            $active[] = $dedupe;
            $this->upsert([
                'type' => 'operations_health',
                'severity' => in_array($item['status'], ['failed', 'stale'], true) ? 'critical' : 'warning',
                'title' => $title,
                'message' => $item['message'] ?: 'Status: '.$item['status'],
                'action_url' => Route::has('admin.operations-dashboard.index') ? route('admin.operations-dashboard.index') : null,
                'source_type' => 'operations_health',
                'source_id' => $key,
                'dedupe_key' => $dedupe,
                'due_at' => now(),
            ]);
        }

        $this->assetAlerts($active);
        $this->stockAlerts($active);

        $resolved = CrmNotification::query()
            ->whereIn('type', ['operations_health', 'inventory_asset_health', 'inventory_stock_health'])
            ->whereNull('resolved_at')
            ->when($active !== [], fn ($query) => $query->whereNotIn('dedupe_key', $active))
            ->update(['resolved_at' => now()]);

        return ['created_or_updated' => count($active), 'resolved' => $resolved];
    }

    private function assetAlerts(array &$active): void
    {
        if (! Schema::hasTable('inventory_assets')) {
            return;
        }

        $query = DB::table('inventory_assets');
        $hasStatus = Schema::hasColumn('inventory_assets', 'status');
        $hasCondition = Schema::hasColumn('inventory_assets', 'condition');

        if (! $hasStatus && ! $hasCondition) {
            return;
        }

        $query->where(function ($builder) use ($hasStatus, $hasCondition) {
            if ($hasStatus) {
                $builder->whereIn(DB::raw('LOWER(status)'), ['missing', 'damaged']);
            }
            if ($hasCondition) {
                $method = $hasStatus ? 'orWhereIn' : 'whereIn';
                $builder->{$method}(DB::raw('LOWER(`condition`)'), ['missing', 'damaged']);
            }
        });

        foreach ($query->limit(500)->get() as $asset) {
            $state = strtolower((string) (($asset->status ?? null) ?: ($asset->condition ?? 'problem')));
            $dedupe = 'inventory-asset-health:'.$asset->id.':'.$state;
            $active[] = $dedupe;
            $reason = null;
            foreach (['damage_note', 'damage_reason', 'condition_note', 'notes', 'note'] as $column) {
                if (isset($asset->{$column}) && trim((string) $asset->{$column}) !== '') {
                    $reason = trim((string) $asset->{$column});
                    break;
                }
            }

            $this->upsert([
                'type' => 'inventory_asset_health',
                'severity' => $state === 'missing' ? 'critical' : 'warning',
                'title' => ($state === 'missing' ? 'Asset hilang' : 'Asset rusak').': '.(($asset->asset_code ?? null) ?: '#'.$asset->id),
                'message' => $reason ?: 'Perlu tindak lanjut operasional.',
                'action_url' => Route::has('admin.inventory.assets.edit') ? route('admin.inventory.assets.edit', $asset->id) : null,
                'source_type' => 'inventory_asset',
                'source_id' => (string) $asset->id,
                'dedupe_key' => $dedupe,
                'due_at' => now(),
            ]);
        }
    }

    private function stockAlerts(array &$active): void
    {
        if (! Schema::hasTable('inventory_items')) {
            return;
        }

        $quantity = $this->firstColumn('inventory_items', ['quantity', 'current_stock', 'stock_quantity', 'available_quantity']);
        $minimum = $this->firstColumn('inventory_items', ['minimum_stock', 'min_stock', 'reorder_level']);

        if (! $quantity || ! $minimum) {
            return;
        }

        $items = DB::table('inventory_items')
            ->whereColumn($quantity, '<=', $minimum)
            ->limit(500)
            ->get();

        foreach ($items as $item) {
            $dedupe = 'inventory-stock-health:'.$item->id;
            $active[] = $dedupe;
            $this->upsert([
                'type' => 'inventory_stock_health',
                'severity' => (float) $item->{$quantity} <= 0 ? 'critical' : 'warning',
                'title' => 'Stok bermasalah: '.(($item->sku ?? null) ?: ($item->name ?? '#'.$item->id)),
                'message' => 'Stok '.(string) $item->{$quantity}.'; minimum '.(string) $item->{$minimum}.'.',
                'action_url' => Route::has('admin.inventory.dashboard') ? route('admin.inventory.dashboard') : null,
                'source_type' => 'inventory_item',
                'source_id' => (string) $item->id,
                'dedupe_key' => $dedupe,
                'due_at' => now(),
            ]);
        }
    }

    private function firstColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function upsert(array $payload): void
    {
        CrmNotification::query()->updateOrCreate(
            ['dedupe_key' => $payload['dedupe_key']],
            array_merge($payload, ['user_id' => null, 'read_at' => null, 'resolved_at' => null])
        );
    }
}
