<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Invoice\Contracts\DeliveryOrderItem as DeliveryOrderItemContract;
use Webkul\Warehouse\Models\InventoryItem;

class DeliveryOrderItem extends Model implements DeliveryOrderItemContract
{
    protected $table = 'delivery_order_items';

    protected $fillable = [
        'delivery_order_id',

        'product_id',
        'inventory_item_id',
        'sku',

        'name',
        'description',

        'quantity',
        'requires_inventory',
        'unit',

        'notes',

        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'sort_order' => 'integer',
        'inventory_item_id' => 'integer',
        'requires_inventory' => 'boolean',
    ];

    /**
     * Surat Jalan pemilik item.
     */
    public function deliveryOrder()
    {
        return $this->belongsTo(
            DeliveryOrderProxy::modelClass(),
            'delivery_order_id'
        );
    }

    /**
     * Inventory master yang menjadi requirement item Surat Jalan.
     *
     * Ini bukan actual serialized asset. Asset aktual akan dipilih
     * pada Phase 3B melalui allocation/picking.
     */
    public function inventoryItem()
    {
        return $this->belongsTo(
            InventoryItem::class,
            'inventory_item_id'
        );
    }

    /**
     * Seluruh riwayat allocation item Surat Jalan.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(
            DeliveryOrderInventoryAllocation::class,
            'delivery_order_item_id'
        );
    }

    /**
     * Allocation yang masih mengikat stok / asset.
     */
    public function activeAllocations(): HasMany
    {
        return $this->allocations()
            ->whereIn(
                'status',
                DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
            );
    }
}
