<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Invoice\Contracts\InvoiceItem as InvoiceItemContract;

class InvoiceItem extends Model implements InvoiceItemContract
{
    protected $table = 'invoice_items';

    protected $fillable = [
    'invoice_id',

    'product_id',
    'sku',

    'name',
    'description',
    'day',
        'unit',
        'equipment_quantity',

    'quantity',
    'price',

    'coupon_code',

    'discount_percent',
    'discount_amount',

    'tax_percent',
    'tax_amount',

    'total',
    ];

    public function invoice()
    {
        return $this->belongsTo(InvoiceProxy::modelClass());
    }
}