<?php

namespace Webkul\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Warehouse\Models\InventoryItem;

class ProductEquipmentTemplateItem extends Model
{
    protected $table = 'product_equipment_template_items';

    protected $fillable = [
        'template_id',
        'inventory_item_id',

        'name',
        'description',

        'quantity',
        'quantity_basis',
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
     * Template pemilik equipment.
     */
    public function template()
    {
        return $this->belongsTo(
            ProductEquipmentTemplate::class,
            'template_id'
        );
    }

    /**
     * Inventory master yang memenuhi requirement equipment ini.
     *
     * Relasi ini hanya menunjuk jenis barang/master inventory.
     * Untuk asset serialized aktual yang dibawa, pemilihan dilakukan
     * pada workflow allocation/picking Delivery Order.
     */
    public function inventoryItem()
    {
        return $this->belongsTo(
            InventoryItem::class,
            'inventory_item_id'
        );
    }
}
