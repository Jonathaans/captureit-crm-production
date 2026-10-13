<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Webkul\Quote\Models\Quote;
use Webkul\Quote\Models\QuoteItem;
use Webkul\User\Models\UserProxy;

class ProformaInvoice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quote_snapshot' => 'array',
        'amount' => 'decimal:2',
        'billing_percentage' => 'decimal:4',
        'issued_at' => 'date',
        'due_at' => 'date',
        'converted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function converter()
    {
        return $this->belongsTo(UserProxy::modelClass(), 'converted_by');
    }

    public function creator()
    {
        return $this->belongsTo(UserProxy::modelClass(), 'created_by');
    }

    public function billingLabel(): string
    {
        return match ($this->billing_type) {
            'down_payment' => 'DP',
            'settlement' => 'Pelunasan',
            default => 'Penuh / Tanpa DP',
        };
    }

    /** Frozen commercial data used for both DP and settlement invoices. */
    public function snapshotQuote(): Quote
    {
        $quote = new Quote;
        $quote->setRawAttributes($this->quote_snapshot['attributes'], true);
        $quote->setRelation('person', null);
        $quote->setRelation('items', new Collection(array_map(function (array $attributes) {
            $item = new QuoteItem;
            $item->setRawAttributes($attributes, true);

            return $item;
        }, $this->quote_snapshot['items'])));

        return $quote;
    }
}
