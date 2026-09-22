<?php

namespace Webkul\Quote\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Attribute\Traits\CustomAttribute;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Lead\Models\LeadProxy;
use Webkul\Quote\Contracts\Quote as QuoteContract;
use Webkul\User\Models\UserProxy;

class Quote extends Model implements QuoteContract
{
    use CustomAttribute;

    protected $table = 'quotes';

    protected $casts = [
        'event_date' => 'date',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'expired_at' => 'datetime',
    ];

    protected $fillable = [
        'quote_number',
        'subject',
        'event_date',
        'location',
        'payment_term',
        'project_code',
        'business_unit',
        'description',
        'billing_address',
        'shipping_address',
        'discount_percent',
        'discount_amount',
        'tax_amount',
        'adjustment_amount',
        'sub_total',
        'grand_total',
        'expired_at',
        'user_id',
        'person_id',
        'bill_to_display_mode',
        'bill_to_person_name',
        'bill_to_company_name',
        'client_signer_name',
        'client_signer_company',
    ];

    public function items()
    {
        return $this->hasMany(QuoteItemProxy::modelClass());
    }

    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    public function person()
    {
        return $this->belongsTo(PersonProxy::modelClass());
    }

    public function leads()
    {
        return $this->belongsToMany(
            LeadProxy::modelClass(),
            'lead_quotes'
        );
    }
}
