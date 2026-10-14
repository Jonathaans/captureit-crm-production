<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Webkul\Invoice\Contracts\Payment as PaymentContract;
use Webkul\User\Models\UserProxy;

class Payment extends Model implements PaymentContract
{
    protected $table = 'payments';

    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
        'paid_at',
        'created_by',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(InvoiceProxy::modelClass());
    }

    /** Wrong entries remain visible in history but are not actual receipts. */
    public function scopeNotVoided(Builder $query): Builder
    {
        return $query->whereDoesntHave('voidRecord')
            ->whereHas('invoice', fn (Builder $invoice) => $invoice->notVoided());
    }

    public function voidRecord()
    {
        return $this->hasOne(PaymentVoidRecord::class, 'payment_id');
    }

    public function creator()
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'created_by'
        );
    }
}
