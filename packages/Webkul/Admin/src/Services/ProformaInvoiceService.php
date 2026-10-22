<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webkul\Core\Support\PphGrossUp;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\ProformaInvoice;
use Webkul\Quote\Models\Quote;

class ProformaInvoiceService
{
    public function __construct(protected FlexibleQuoteBillingService $billing) {}

    public function create(Quote $quote, array $input, ?int $createdBy = null): ProformaInvoice
    {
        return $this->createDocument($quote, $input, $createdBy);
    }

    /** Server-owned agreement preserved by the validated invoice replacement flow. */
    public function createFromSnapshot(Quote $agreement, array $input, int $createdBy): ProformaInvoice
    {
        return $this->createDocument($agreement, $input, $createdBy, true);
    }

    private function createDocument(Quote $quote, array $input, ?int $createdBy, bool $useSnapshot = false): ProformaInvoice
    {
        return DB::transaction(function () use ($quote, $input, $createdBy, $useSnapshot) {
            $current = Quote::with(['items', 'person.organization'])->lockForUpdate()->findOrFail($quote->id);
            $quote = $useSnapshot ? $quote : $current;
            $summary = $this->billing->summarize($quote);
            $input = Validator::make($input, [
                'billing_type' => ['required', 'in:down_payment,full_payment,settlement'],
                'billing_method' => ['nullable', 'required_if:billing_type,down_payment', 'in:nominal,percentage'],
                'billing_amount' => ['nullable', 'required_if:billing_type,down_payment', 'numeric', 'gt:0'],
                'payment_term' => ['required', 'string', 'max:2000'],
                'issued_at' => ['required', 'date_format:Y-m-d'],
                'due_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:issued_at'],
            ])->validate();
            $type = $input['billing_type'];

            if (! $summary['eligibility'][$type]) {
                throw ValidationException::withMessages(['billing_type' => $summary['messages'][$type]]);
            }

            $amount = match ($type) {
                FlexibleQuoteBillingService::TYPE_DOWN_PAYMENT => (float) $input['billing_amount'],
                FlexibleQuoteBillingService::TYPE_SETTLEMENT => $summary['remaining'],
                default => $summary['quote_total'],
            };
            $position = $this->billing->calculateDownPaymentPosition($summary['quote_total'], $amount);

            if ($position['amount'] <= 0 || ($type === FlexibleQuoteBillingService::TYPE_DOWN_PAYMENT && $position['amount'] >= $summary['quote_total'])) {
                throw ValidationException::withMessages(['billing_amount' => 'Nominal DP harus lebih dari Rp 0 dan lebih kecil dari total penawaran.']);
            }

            $dpInvoiceId = null;
            if ($type === FlexibleQuoteBillingService::TYPE_SETTLEMENT) {
                $dpInvoiceId = $summary['down_payment']['id'];
                $source = ProformaInvoice::where('invoice_id', $dpInvoiceId)->first();
                $quote = $source ? $source->snapshotQuote() : $quote;
                $quote->grand_total = $summary['quote_total'];
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
                'billing_type' => $type,
                'dp_invoice_id' => $dpInvoiceId,
                'amount' => $position['amount'],
                'pph_gross_up' => (bool) $quote->pph_gross_up,
                'pph_rate' => $quote->pph_rate ?? 0,
                'pph_amount' => PphGrossUp::allocate((float) $quote->pph_amount, (float) $quote->grand_total, $position['amount'],
                    $dpInvoiceId ? (float) Invoice::findOrFail($dpInvoiceId)->pph_amount : null),
                'billing_method' => $type === FlexibleQuoteBillingService::TYPE_DOWN_PAYMENT ? $input['billing_method'] : 'nominal',
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
