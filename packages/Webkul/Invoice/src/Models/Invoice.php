<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Invoice\Contracts\Invoice as InvoiceContract;
use Webkul\Quote\Models\QuoteProxy;
use Webkul\User\Models\UserProxy;

class Invoice extends Model implements InvoiceContract
{
    protected $table = 'invoices';

    protected $fillable = [
        'invoice_number',
        'project_code',
        'business_unit',
        'quote_id',
        'event_date',
        'location',
        'payment_term',
        'person_id',
        'bill_to_display_mode',
        'bill_to_person_name',
        'bill_to_company_name',
        'user_id',
        'subject',
        'description',
        'billing_address',
        'shipping_address',
        'discount_percent',
        'discount_amount',
        'tax_amount',
        'adjustment_amount',
        'sub_total',
        'grand_total',
        'paid_amount',
        'balance_due',

        /**
         * Payment status:
         * unpaid / partial / paid
         */
        'status',

        /**
         * Event status:
         * prospect / confirm / cancel
         */
        'event_status',

        'issued_at',
        'due_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
    ];

    public function quote()
    {
        return $this->belongsTo(QuoteProxy::modelClass());
    }

    public function person()
    {
        return $this->belongsTo(PersonProxy::modelClass());
    }

    /**
     * Resolve the immutable invoice snapshot, with a legacy Quote fallback.
     */
    public function billToIdentity(): array
    {
        $invoicePersonName = trim((string) $this->bill_to_person_name);
        $invoiceCompanyName = trim((string) $this->bill_to_company_name);
        $hasInvoiceSnapshot = $invoicePersonName !== ''
            || $invoiceCompanyName !== '';

        if ($hasInvoiceSnapshot) {
            $mode = (string) ($this->bill_to_display_mode ?: 'person');
            $personName = $invoicePersonName;
            $companyName = $invoiceCompanyName;
        } else {
            $quote = $this->quote;
            $mode = (string) ($quote?->bill_to_display_mode ?: 'person');
            $personName = trim((string) (
                $quote?->bill_to_person_name
                ?: $quote?->person?->name
                ?: $this->person?->name
                ?: ''
            ));
            $companyName = trim((string) (
                $quote?->bill_to_company_name
                ?: $quote?->person?->organization?->name
                ?: $this->person?->organization?->name
                ?: ''
            ));
        }

        if (! in_array($mode, ['person', 'company', 'both'], true)) {
            $mode = 'person';
        }

        if ($mode !== 'person' && $companyName === '') {
            $mode = 'person';
        }

        if ($mode === 'person' && $personName === '' && $companyName !== '') {
            $mode = 'company';
        }

        return [
            'mode' => $mode,
            'person_name' => $personName ?: '-',
            'company_name' => $companyName ?: null,
        ];
    }

    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    public function items()
    {
        return $this->hasMany(InvoiceItemProxy::modelClass());
    }

    public function payments()
    {
        return $this->hasMany(PaymentProxy::modelClass());
    }

    public function expenses()
    {
        return $this->hasMany(ExpenseProxy::modelClass());
    }

    public function deliveryOrders()
    {
        return $this->hasMany(
            DeliveryOrderProxy::modelClass(),
            'invoice_id'
        );
    }
}
