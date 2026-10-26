<?php

namespace Webkul\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\User\Models\UserProxy;

class InventoryConsumableUsage extends Model
{
    protected $table = 'inventory_consumable_usages';

    protected $fillable = [
        'delivery_order_id',
        'inventory_item_id',
        'quantity',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'updated_by');
    }
}
