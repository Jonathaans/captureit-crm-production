<?php

namespace Webkul\Admin\Http\Controllers\Inventory;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Inventory\InventoryConsumableUsageDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Warehouse\Models\InventoryConsumableUsage;
use Webkul\Warehouse\Models\InventoryItem;
use Webkul\Warehouse\Services\InventoryConsumableUsageService;

class InventoryConsumableUsageController extends Controller
{
    public function index()
    {
        if (request()->ajax() || request()->expectsJson()) {
            return app(InventoryConsumableUsageDataGrid::class)->toJson();
        }

        return view('admin::inventory.consumable-usage.index');
    }

    public function show(int $id, InventoryConsumableUsageService $service): View
    {
        $order = DeliveryOrder::findOrFail($id);
        $usages = InventoryConsumableUsage::query()
            ->with('item')->where('delivery_order_id', $id)->orderBy('inventory_item_id')->get();
        $revision = $service->revision($usages);
        $allocations = DeliveryOrderInventoryAllocation::query()
            ->with('inventoryItem')->where('delivery_order_id', $id)->where('tracking_type', 'quantity')
            ->whereIn('status', [...DeliveryOrderInventoryAllocation::ACTIVE_STATUSES, 'returned'])->get();
        $allocatedItemIds = $allocations->pluck('inventory_item_id')->unique();
        $reserved = DB::table('delivery_order_inventory_allocations')
            ->where('tracking_type', 'quantity')
            ->whereIn('status', DeliveryOrderInventoryAllocation::RESERVATION_STATUSES)
            ->selectRaw('inventory_item_id, SUM(quantity) as total')->groupBy('inventory_item_id')->pluck('total', 'inventory_item_id');
        $items = InventoryItem::query()->where('tracking_type', 'quantity')
            ->where(function ($query) use ($usages, $allocatedItemIds) {
                $query->where(function ($query) use ($allocatedItemIds) {
                    $query->where('is_active', true)->whereNotIn('id', $allocatedItemIds);
                })->orWhereIn('id', $usages->pluck('inventory_item_id'));
            })->orderBy('name')->get()->map(fn ($item) => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'unit' => $item->unit,
                'stock' => (float) $item->quantity_on_hand,
                'available' => max(0, (float) $item->quantity_on_hand - (float) ($reserved[$item->id] ?? 0)),
                'previous' => (float) ($usages->firstWhere('inventory_item_id', $item->id)?->quantity ?? 0),
                'active' => $item->is_active,
            ])->values();
        $initialRows = $usages->where('quantity', '>', 0)->map(fn ($usage) => [
            'inventory_item_id' => $usage->inventory_item_id,
            'quantity' => $usage->quantity,
            'notes' => $usage->notes ?? '',
        ])->values()->all();
        $canEdit = bouncer()->hasPermission('inventory.consumable-usage.edit')
            && in_array($order->status, $service::EDITABLE_STATUSES, true);

        return view('admin::inventory.consumable-usage.show', compact(
            'order', 'usages', 'revision', 'items', 'initialRows', 'allocations', 'canEdit'
        ));
    }

    public function update(Request $request, int $id, InventoryConsumableUsageService $service): RedirectResponse
    {
        $service->save($id, [
            'usage_revision' => $request->input('usage_revision'),
            'items' => $request->input('items', []),
        ], auth()->guard('user')->id());

        return redirect()->route('admin.inventory.consumable-usage.show', $id)
            ->with('success', 'Penggunaan consumable berhasil disimpan dan stok sudah disesuaikan.');
    }
}
