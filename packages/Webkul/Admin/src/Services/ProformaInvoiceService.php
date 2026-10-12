<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\ProformaInvoice;
use Webkul\Quote\Models\Quote;

class ProformaInvoiceService
{
    public function __construct(protected FlexibleQuoteBillingService $billing) {}

    public function create(Quote $quote, array $input, ?int $createdBy = null): ProformaInvoice
    {
        return DB::transaction(function () use ($quote, $input, $createdBy) {
            $quote = Quote::with(['items', 'person.organization'])->lockForUpdate()->findOrFail($quote->id);
            $summary = $this->billing->summarize($quote);

            if (! $summary['eligibility']['down_payment']) {
                throw ValidationException::withMessages(['billing_type' => $summary['messages']['down_payment']]);
            }

            $input = Validator::make($input, [
                'billing_method' => ['required', 'in:nominal,percentage'],
                'billing_amount' => ['required', 'numeric', 'gt:0'],
                'payment_term' => ['required', 'string', 'max:2000'],
                'issued_at' => ['required', 'date_format:Y-m-d'],
                'due_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:issued_at'],
            ])->validate();
            $position = $this->billing->calculateDownPaymentPosition($summary['quote_total'], (float) $input['billing_amount']);

            if ($position['amount'] <= 0 || $position['amount'] >= $summary['quote_total']) {
                throw ValidationException::withMessages(['billing_amount' => 'Nominal DP harus lebih dari Rp 0 dan lebih kecil dari total penawaran.']);
            }

            // Freeze the selected Bill To identity, even if the contact changes.
            $identity = (new Invoice)->setRelation('quote', $quote)->billToIdentity();
            $attributes = $quote->getAttributes();
            $attributes['payment_term'] = $input['payment_term'];
            $attributes['bill_to_display_mode'] = $identity['mode'];
            $attributes['bill_to_person_name'] = $identity['person_name'];
            $attributes['bill_to_company_name'] = $identity['company_name'];

            $proforma = ProformaInvoice::create([
                'proforma_number' => 'TMP-'.Str::uuid(),
                'quote_id' => $quote->id,
                'status' => 'issued',
                'amount' => $position['amount'],
                'billing_method' => $input['billing_method'],
                'billing_percentage' => $position['percentage'],
                'quote_snapshot' => [
                    'attributes' => $attributes,
                    'items' => $quote->items->map(fn ($item) => $item->getAttributes())->values()->all(),
                ],
                'issued_at' => $input['issued_at'],
                'due_at' => $input['due_at'],
                'created_by' => $createdBy,
            ]);
            // The primary key makes numbering safe across concurrent quotes.
            $proforma->update(['proforma_number' => 'PF '.now()->format('ym').'-'.str_pad((string) $proforma->id, 5, '0', STR_PAD_LEFT)]);

            return $proforma->fresh();
        }, 3);
    }

    public function cancel(ProformaInvoice $proforma, string $reason, ?int $actor = null): void
    {
        Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'max:2000']])->validate();

        DB::transaction(function () use ($proforma, $reason, $actor) {
            Quote::whereKey($proforma->quote_id)->lockForUpdate()->firstOrFail();
            $proforma = ProformaInvoice::lockForUpdate()->findOrFail($proforma->id);

            if ($proforma->status !== 'issued') {
                throw ValidationException::withMessages(['proforma' => 'Hanya proforma yang belum dikonversi atau dibatalkan yang dapat dibatalkan.']);
            }

            $proforma->update([
                'status' => 'cancelled',
                'cancellation_reason' => trim($reason),
                'cancelled_at' => now(),
                'cancelled_by' => $actor,
            ]);
        }, 3);
    }
}
