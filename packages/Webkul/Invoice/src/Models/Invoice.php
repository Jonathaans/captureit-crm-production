<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'pph_gross_up',
        'pph_rate',
        'pph_amount',
        'paid_amount',
        'balance_due',

        /**
         * Payment status:
         * unpaid / partial / paid / void
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
        'pph_gross_up' => 'boolean',
        'event_date' => 'date',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
    ];

    /** Net cash agreed after PPh deduction; not evidence of tax remittance. */
    public function cashPayable(): float
    {
        return round(max(0, (float) $this->grand_total - (float) $this->pph_amount), 4);
    }

    public function quote()
    {
        return $this->belongsTo(QuoteProxy::modelClass());
    }

    public function person()
    {
        return $this->belongsTo(PersonProxy::modelClass());
    }

    /**
     * Keep the selected recipient/mode, while reflecting name corrections in Contacts.
     * Stored names remain the fallback when a contact is no longer available.
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

        $person = $this->person_id ? $this->person : (! $hasInvoiceSnapshot ? $this->quote?->person : null);
        if ($person) {
            $personName = trim((string) $person->name) ?: $personName;
            $companyName = trim((string) $person->organization?->name) ?: $companyName;
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

    public function activePayments()
    {
        return $this->payments()->whereDoesntHave('voidRecord');
    }

    /** Financial queries exclude voids; document lookups keep the original history. */
    public function scopeNotVoided(Builder $query): Builder
    {
        $column = $this->qualifyColumn('status');

        return $query->where(fn (Builder $query) => $query->whereNull($column)->orWhere($column, '!=', 'void'));
    }

    public function voidCorrection()
    {
        return $this->hasOne(InvoiceVoidRecord::class, 'invoice_number', 'invoice_number')
            ->whereIn('kind', ['invoice_void', 'invoice_cancel']);
    }

    public function expenses()
    {
        return $this->hasMany(ExpenseProxy::modelClass());
    }

    public function replacementInvoice()
    {
        return $this->belongsTo(self::class, 'replaced_by_invoice_id');
    }

    public function replacesInvoice()
    {
        return $this->belongsTo(self::class, 'replaces_invoice_id');
    }

    public function replacementRecord()
    {
        return $this->hasOne(InvoiceReplacement::class, 'source_invoice_id');
    }

    public function deliveryOrders()
    {
        return $this->hasMany(
            DeliveryOrderProxy::modelClass(),
            'invoice_id'
        );
    }

    /** DP and its settlement have one operational parent. */
    public function operationalInvoiceId(): int
    {
        return $this->billing_type === 'settlement' && $this->dp_invoice_id
            ? (int) $this->dp_invoice_id
            : (int) $this->id;
    }

    public function projectInvoiceIds(): array
    {
        $parentId = (int) ($this->replaces_invoice_id ?: $this->operationalInvoiceId());

        return static::where('dp_invoice_id', $parentId)->where('billing_type', 'settlement')->where('quote_id', $this->quote_id)
            ->pluck('id')->push($parentId)->push($this->id)->push($this->replaced_by_invoice_id)->filter()->unique()->all();
    }
}
