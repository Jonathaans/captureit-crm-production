<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Invoice\Contracts\DeliveryOrder as DeliveryOrderContract;
use Webkul\Quote\Models\QuoteProxy;
use Webkul\User\Models\UserProxy;

class DeliveryOrder extends Model implements DeliveryOrderContract
{
    protected $table = 'delivery_orders';

    protected $fillable = [
        'delivery_order_number',

        'invoice_id',
        'quote_id',
        'invoice_number',
        'quote_number',

        'project_code',
        'business_unit',
        'project_name',

        'person_id',
        'customer_name',

        'user_id',
        'sales_person_name',

        'recipient_name',
        'recipient_phone',

        'pic_name',
        'pic_phone',

        'event_date',
        'event_time',

        'location',

        'delivery_address',
        'delivery_date',
        'delivery_time',

        'status',

        'notes',
        'issued_at',
        'delivered_at',
        'returned_at',

        'created_by',
        'released_by',
        'released_by_name',
    ];

    protected $casts = [
        'event_date' => 'date',
        'delivery_date' => 'date',

        'issued_at' => 'datetime',
        'delivered_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(
            InvoiceProxy::modelClass()
        );
    }

    public function quote()
    {
        return $this->belongsTo(
            QuoteProxy::modelClass()
        );
    }

    public function person()
    {
        return $this->belongsTo(
            PersonProxy::modelClass()
        );
    }

    public function user()
    {
        return $this->belongsTo(
            UserProxy::modelClass()
        );
    }

    public function creator()
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'created_by'
        );
    }

    public function releaser()
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'released_by'
        );
    }

    public function items()
    {
        return $this->hasMany(
            DeliveryOrderItemProxy::modelClass(),
            'delivery_order_id'
        )->orderBy('sort_order');
    }

    /**
     * Seluruh allocation inventory milik Surat Jalan.
     */
    public function inventoryAllocations()
    {
        return $this->hasMany(
            DeliveryOrderInventoryAllocation::class,
            'delivery_order_id'
        );
    }
}
