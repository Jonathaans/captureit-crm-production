<?php

namespace Webkul\Admin\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webkul\Core\Support\SalesLineItem;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Support\BillingDescription;
use Webkul\Quote\Models\Quote;

class FlexibleQuoteBillingService
{
    public const TYPE_DOWN_PAYMENT = 'down_payment';

    public const TYPE_FULL_PAYMENT = 'full_payment';

    public const TYPE_SETTLEMENT = 'settlement';

    private const SCALE = 2;

    /**
     * Pure calculation used by the form contract and unit tests. Nominal is
     * authoritative; percentage and remaining are derived from it.
     */
    public function calculateDownPaymentPosition(
        float $quoteTotal,
        float $billingAmount
    ): array {
        $total = round($quoteTotal, self::SCALE);
        $amount = round($billingAmount, self::SCALE);

        return [
            'amount' => $amount,
            'percentage' => $total > 0
                ? round(($amount / $total) * 100, 4)
                : 0.0,
            'remaining' => round(max(0, $total - $amount), self::SCALE),
        ];
    }

    public function summarize(Quote $quote): array
    {
        $quoteTotal = round((float) $quote->grand_total, self::SCALE);

        $activeInvoices = Invoice::query()
            ->where('quote_id', $quote->id)
            ->where(function ($query) {
                $query
                    ->whereNull('event_status')
                    ->orWhereNotIn('event_status', ['cancel', 'cancelled', 'canceled']);
            })
            ->orderBy('id')
            ->get();

        $downPayment = $activeInvoices
            ->firstWhere('billing_type', self::TYPE_DOWN_PAYMENT);

        $settlement = $activeInvoices
            ->firstWhere('billing_type', self::TYPE_SETTLEMENT);

        /*
         * Existing invoices are migrated as full_payment. A null value is
         * treated as full too, so legacy data can never be billed twice.
         */
        $fullPayment = $activeInvoices->first(function (Invoice $invoice) {
            return in_array(
                $invoice->billing_type ?: self::TYPE_FULL_PAYMENT,
                [self::TYPE_FULL_PAYMENT, 'full'],
                true
            );
        });

        $activeInvoiced = round(
            (float) $activeInvoices->sum('grand_total'),
            self::SCALE
        );

        $remaining = round(
            max(0, $quoteTotal - $activeInvoiced),
            self::SCALE
        );

        return [
            'quote_id' => (int) $quote->id,
            'quote_number' => $quote->quote_number ?: '#'.$quote->id,
            'project_code' => $quote->project_code ?: '-',
            'subject' => $quote->subject ?: '-',
            'quote_total' => $quoteTotal,
            'active_invoiced' => $activeInvoiced,
            'remaining' => $remaining,
            'down_payment' => $downPayment
                ? $this->invoiceSummary($downPayment)
                : null,
            'settlement' => $settlement
                ? $this->invoiceSummary($settlement)
                : null,
            'full_payment' => $fullPayment
                ? $this->invoiceSummary($fullPayment)
                : null,
            'eligibility' => [
                'down_payment' =>
                    $quoteTotal > 0
                    && ! $downPayment
                    && ! $settlement
                    && ! $fullPayment,
                'full_payment' =>
                    $quoteTotal > 0
                    && $activeInvoices->isEmpty(),
                'settlement' =>
                    $downPayment !== null
                    && $downPayment->status === 'paid'
                    && ! $settlement
                    && ! $fullPayment
                    && $remaining > 0,
            ],
            'messages' => [
                'down_payment' => $this->downPaymentMessage(
                    $quoteTotal,
                    $downPayment,
                    $settlement,
                    $fullPayment
                ),
                'full_payment' => $activeInvoices->isEmpty()
                    ? 'Full Payment tersedia untuk seluruh nilai Quote.'
                    : 'Full Payment tidak tersedia karena Quote sudah memiliki Invoice aktif.',
                'settlement' => $this->settlementMessage(
                    $downPayment,
                    $settlement,
                    $fullPayment,
                    $remaining
                ),
            ],
        ];
    }

    public function createFromQuote(
        Quote $quote,
        array $input,
        ?int $createdBy = null
    ): Invoice {
        $connection = DB::connection();
        $usesNamedLock = $connection->getDriverName() === 'mysql';

        if ($usesNamedLock) {
            $row = $connection->selectOne(
                "SELECT GET_LOCK('crm_invoice_number_v1', 10) AS acquired"
            );

            if ((int) ($row->acquired ?? 0) !== 1) {
                throw ValidationException::withMessages([
                    'invoice_number' =>
                        'Nomor invoice sedang digunakan proses lain. Silakan ulangi.',
                ]);
            }
        }

        try {
            return DB::transaction(function () use ($quote, $input, $createdBy) {
            $lockedQuote = Quote::query()
                ->with(['items', 'person.organization'])
                ->lockForUpdate()
                ->findOrFail($quote->id);

            Invoice::query()
                ->where('quote_id', $lockedQuote->id)
                ->lockForUpdate()
                ->get();

            $summary = $this->summarize($lockedQuote);
            $type = (string) ($input['billing_type'] ?? '');

            if (! in_array($type, [
                self::TYPE_DOWN_PAYMENT,
                self::TYPE_FULL_PAYMENT,
                self::TYPE_SETTLEMENT,
            ], true)) {
                throw ValidationException::withMessages([
                    'billing_type' => 'Jenis invoice tidak valid.',
                ]);
            }

            if (! ($summary['eligibility'][$type] ?? false)) {
                throw ValidationException::withMessages([
                    'billing_type' =>
                        $summary['messages'][$type]
                        ?? 'Jenis invoice ini tidak tersedia.',
                ]);
            }

            $quoteTotal = (float) $summary['quote_total'];
            $method = null;
            $percentage = null;
            $dpInvoiceId = null;

            if ($type === self::TYPE_DOWN_PAYMENT) {
                $method = (string) ($input['billing_method'] ?? '');

                if (! in_array($method, ['percentage', 'nominal'], true)) {
                    throw ValidationException::withMessages([
                        'billing_method' => 'Pilih metode Persentase atau Nominal.',
                    ]);
                }

                /*
                 * Nominal is authoritative. Percentage is derived metadata.
                 */
                $amount = round(
                    (float) ($input['billing_amount'] ?? 0),
                    self::SCALE
                );

                if ($amount <= 0 || $amount >= $quoteTotal) {
                    throw ValidationException::withMessages([
                        'billing_amount' =>
                            'Nominal DP harus lebih dari Rp 0 dan lebih kecil dari Grand Total Quote.',
                    ]);
                }

                $position = $this->calculateDownPaymentPosition(
                    $quoteTotal,
                    $amount
                );

                $percentage = $position['percentage'];
            } elseif ($type === self::TYPE_SETTLEMENT) {
                $amount = round(
                    (float) $summary['remaining'],
                    self::SCALE
                );
                $dpInvoiceId = (int) $summary['down_payment']['id'];
            } else {
                $amount = $quoteTotal;
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'billing_amount' => 'Nilai invoice harus lebih dari Rp 0.',
                ]);
            }

            $remainingAfterInvoice = round(
                max(0, (float) $summary['remaining'] - $amount),
                self::SCALE
            );

            $billToIdentity = $this->resolveQuoteBillToIdentity($lockedQuote);

            $invoice = Invoice::create([
                'invoice_number' => 'TMP-'.Str::uuid(),
                'project_code' => $lockedQuote->project_code,
                'business_unit' => $lockedQuote->business_unit,
                'event_date' => $lockedQuote->event_date,
                'location' => $lockedQuote->location,
                'payment_term' => $lockedQuote->payment_term,
                'quote_id' => $lockedQuote->id,
                'person_id' => $lockedQuote->person_id,
                'bill_to_display_mode' => $billToIdentity['mode'],
                'bill_to_person_name' => $billToIdentity['person_name'],
                'bill_to_company_name' => $billToIdentity['company_name'],
                'user_id' => $lockedQuote->user_id,
                'subject' => $lockedQuote->subject,
                'description' => $lockedQuote->description,
                'billing_address' => $lockedQuote->billing_address,
                'shipping_address' => $lockedQuote->shipping_address,
                'discount_percent' => $type === self::TYPE_FULL_PAYMENT
                    ? ($lockedQuote->discount_percent ?? 0)
                    : 0,
                'discount_amount' => $type === self::TYPE_FULL_PAYMENT
                    ? ($lockedQuote->discount_amount ?? 0)
                    : 0,
                'tax_amount' => $type === self::TYPE_FULL_PAYMENT
                    ? ($lockedQuote->tax_amount ?? 0)
                    : 0,
                'adjustment_amount' => $type === self::TYPE_FULL_PAYMENT
                    ? ($lockedQuote->adjustment_amount ?? 0)
                    : 0,
                'sub_total' => $type === self::TYPE_FULL_PAYMENT
                    ? ($lockedQuote->sub_total ?? $amount)
                    : $amount,
                'grand_total' => $amount,
                'paid_amount' => 0,
                'balance_due' => $amount,
                'status' => 'unpaid',
                'event_status' => 'confirm',
                'issued_at' => $input['issued_at'] ?? now(),
                'due_at' => $input['due_at'] ?? now()->addDays(7),
            ]);

            /*
             * Build immutable item snapshots before billing_locked_at is set.
             */
            if ($type === self::TYPE_FULL_PAYMENT) {
                $this->copyFullItems($invoice, $lockedQuote);
            } else {
                $this->copyAllocatedItems(
                    $invoice,
                    $lockedQuote,
                    $amount,
                    $type
                );
            }

            $invoice->forceFill([
                'billing_type' => $type,
                'billing_method' => $method,
                'billing_percentage' => $percentage,
                'quote_total_snapshot' => $quoteTotal,
                'billing_amount' => $amount,
                'remaining_amount_snapshot' => $remainingAfterInvoice,
                'dp_invoice_id' => $dpInvoiceId,
                'billing_created_by' => $createdBy,
                'billing_locked_at' => now(),
            ])->saveQuietly();

            $invoice->invoice_number = $this->nextInvoiceNumber();
            $invoice->saveQuietly();

            return $invoice->fresh([
                'items',
                'payments',
                'quote',
                'person',
                'user',
            ]);
            }, 3);
        } finally {
            if ($usesNamedLock) {
                $connection->selectOne(
                    "SELECT RELEASE_LOCK('crm_invoice_number_v1')"
                );
            }
        }
    }

    /**
     * Allow payment/status metadata, and optionally allow commercial edits
     * while an invoice is still unpaid/partial.
     */
    public function assertMutable(Model $model, string $operation): void
    {
        $table = $model->getTable();
        $allowLockedEdits = (bool) config(
            'crm-hardening.allow_edit_locked_invoices'
        );

        if ($table === 'invoices') {
            if (! $model->getRawOriginal('billing_locked_at')) {
                return;
            }

            $status = (string) $model->getRawOriginal('status');
            $eventStatus = (string) $model->getRawOriginal('event_status');

            if (
                $operation !== 'delete'
                && $allowLockedEdits
                && in_array($status, ['unpaid', 'partial'], true)
                && ! in_array(
                    $eventStatus,
                    ['cancel', 'cancelled', 'canceled'],
                    true
                )
            ) {
                return;
            }

            if ($operation === 'delete') {
                $this->throwLocked();
            }

            $protected = [
                'quote_id',
                'person_id',
                'bill_to_display_mode',
                'bill_to_person_name',
                'bill_to_company_name',
                'discount_percent',
                'discount_amount',
                'tax_amount',
                'adjustment_amount',
                'sub_total',
                'grand_total',
                'billing_type',
                'billing_method',
                'billing_percentage',
                'quote_total_snapshot',
                'billing_amount',
                'remaining_amount_snapshot',
                'dp_invoice_id',
            ];

            if (array_intersect(array_keys($model->getDirty()), $protected)) {
                $this->throwLocked();
            }

            return;
        }

        if ($table !== 'invoice_items') {
            return;
        }

        $invoiceId = (int) (
            $model->getRawOriginal('invoice_id')
            ?: $model->getAttribute('invoice_id')
        );

        if ($invoiceId <= 0) {
            return;
        }

        $invoice = DB::table('invoices')
            ->select(['status', 'event_status'])
            ->where('id', $invoiceId)
            ->whereNotNull('billing_locked_at')
            ->first();

        if (! $invoice) {
            return;
        }

        if (
            $allowLockedEdits
            && in_array($invoice->status, ['unpaid', 'partial'], true)
            && ! in_array(
                $invoice->event_status,
                ['cancel', 'cancelled', 'canceled'],
                true
            )
        ) {
            return;
        }

        $this->throwLocked();
    }

    /**
     * Keep billing snapshots consistent after a manual invoice edit.
     */
    public function syncAfterManualUpdate(Invoice $invoice): void
    {
        if (! $invoice->billing_locked_at || ! $invoice->quote_id) {
            return;
        }

        $quoteTotal = round(
            (float) ($invoice->quote_total_snapshot ?? 0),
            self::SCALE
        );

        if ($quoteTotal <= 0) {
            $quoteTotal = round(
                (float) Quote::query()
                    ->whereKey($invoice->quote_id)
                    ->value('grand_total'),
                self::SCALE
            );
        }

        if ($quoteTotal <= 0) {
            return;
        }

        $otherInvoicesTotal = Invoice::query()
            ->where('quote_id', $invoice->quote_id)
            ->where('id', '<>', $invoice->id)
            ->where(function ($query) {
                $query
                    ->whereNull('event_status')
                    ->orWhereNotIn('event_status', [
                        'cancel',
                        'cancelled',
                        'canceled',
                    ]);
            })
            ->sum('grand_total');

        $grandTotal = round(
            (float) $invoice->grand_total,
            self::SCALE
        );

        $billingPercentage = $invoice->billing_type
            === self::TYPE_DOWN_PAYMENT
            ? round(($grandTotal / $quoteTotal) * 100, 4)
            : $invoice->billing_percentage;

        $invoice->forceFill([
            'quote_total_snapshot' => $quoteTotal,
            'billing_amount' => $grandTotal,
            'billing_percentage' => $billingPercentage,
            'remaining_amount_snapshot' => round(
                max(
                    0,
                    $quoteTotal
                    - (float) $otherInvoicesTotal
                    - $grandTotal
                ),
                self::SCALE
            ),
        ])->saveQuietly();
    }

    public function sumUnbilledForQuoteIds(iterable $quoteIds): float
    {
        $ids = collect($quoteIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return 0.0;
        }

        $quoteTotals = Quote::query()
            ->whereIn('id', $ids)
            ->pluck('grand_total', 'id');

        $invoiceTotals = Invoice::query()
            ->whereIn('quote_id', $ids)
            ->where(function ($query) {
                $query
                    ->whereNull('event_status')
                    ->orWhereNotIn('event_status', ['cancel', 'cancelled', 'canceled']);
            })
            ->selectRaw('quote_id, SUM(grand_total) as billed')
            ->groupBy('quote_id')
            ->pluck('billed', 'quote_id');

        /* CRM_FINANCIAL_REPORT_EXPENSE_HOTFIX_V1_3 */
        $unbilled = 0.0;

        foreach ($quoteTotals as $quoteId => $total) {
            $unbilled += max(
                0,
                (float) $total - (float) $invoiceTotals->get($quoteId, 0)
            );
        }

        return round($unbilled, self::SCALE);
    }

    private function nextInvoiceNumber(): string
    {
        $prefix = 'INV '.now()->format('ym').'-';

        $sequence = app(\Webkul\Invoice\Services\InvoiceNumberHistory::class)
            ->lastSequence($prefix) + 1;

        return $prefix.str_pad(
            (string) $sequence,
            4,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Copy the selected Quote Bill To presentation into the Invoice.
     */
    private function resolveQuoteBillToIdentity(Quote $quote): array
    {
        $personName = trim((string) (
            $quote->bill_to_person_name
            ?: $quote->person?->name
            ?: ''
        ));
        $companyName = trim((string) (
            $quote->bill_to_company_name
            ?: $quote->person?->organization?->name
            ?: ''
        ));
        $mode = (string) ($quote->bill_to_display_mode ?: 'person');

        if (! in_array($mode, ['person', 'company', 'both'], true)) {
            $mode = 'person';
        }

        if ($mode !== 'person' && $companyName === '') {
            $mode = 'person';
        }

        return [
            'mode' => $mode,
            'person_name' => $personName ?: null,
            'company_name' => $companyName ?: null,
        ];
    }

    private function copyFullItems(Invoice $invoice, Quote $quote): void
    {
        foreach ($quote->items as $item) {
            $invoice->items()->create([
                'product_id' => $item->product_id,
                'sku' => $item->sku,
                'name' => $item->name,
                'description' => $item->description ?? null,
                'day' => $item->day ?? 1,
                'unit' => $item->unit,
                'equipment_quantity' => $item->equipment_quantity,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'coupon_code' => $item->coupon_code,
                'discount_percent' => $item->discount_percent ?? 0,
                'discount_amount' => $item->discount_amount ?? 0,
                'tax_percent' => $item->tax_percent ?? 0,
                'tax_amount' => $item->tax_amount ?? 0,
                'total' => $item->total,
            ]);
        }
    }

    private function copyAllocatedItems(
        Invoice $invoice,
        Quote $quote,
        float $amount,
        string $type
    ): void {
        if ($quote->items->isEmpty()) {
            $invoice->items()->create([
                'product_id' => null,
                'sku' => $type === self::TYPE_DOWN_PAYMENT ? 'DP' : 'PELUNASAN',
                'name' => $this->typeLabel($type),
                'description' => BillingDescription::make(
                    $type,
                    (float) $quote->grand_total,
                    'Referensi penawaran '.$quote->quote_number.'.'
                ),
                'day' => 1,
                'unit' => 'pcs',
                'equipment_quantity' => 0,
                'quantity' => 1,
                'price' => $amount,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'tax_percent' => 0,
                'tax_amount' => 0,
                'total' => $amount,
            ]);

            return;
        }

        $weights = $quote->items->map(function ($item) {
            return max(
                0,
                (float) $item->total
                    + (float) ($item->tax_amount ?? 0)
                    - (float) ($item->discount_amount ?? 0)
            );
        });

        $weightTotal = (float) $weights->sum();

        if ($weightTotal <= 0) {
            $weights = $quote->items->map(fn () => 1.0);
            $weightTotal = (float) $weights->sum();
        }

        $allocated = 0.0;
        $lastIndex = $quote->items->keys()->last();

        foreach ($quote->items as $index => $item) {
            $lineAmount = $index === $lastIndex
                ? round($amount - $allocated, self::SCALE)
                : round(
                    $amount * ((float) $weights->get($index) / $weightTotal),
                    self::SCALE
                );

            $allocated = round($allocated + $lineAmount, self::SCALE);
            $billing = SalesLineItem::display($item->getAttributes());
            $quantity = max(1, (float) $billing['quantity']);

            $invoice->items()->create([
                'product_id' => $item->product_id,
                'sku' => $item->sku,
                'name' => $item->name,
                'description' => BillingDescription::make(
                    $type,
                    (float) $quote->grand_total,
                    $item->description
                ),
                'day' => 1,
                'unit' => $billing['unit'],
                'equipment_quantity' => SalesLineItem::physicalQuantity($item->getAttributes()),
                'quantity' => $quantity,
                'price' => round($lineAmount / $quantity, self::SCALE),
                'coupon_code' => null,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'tax_percent' => 0,
                'tax_amount' => 0,
                'total' => $lineAmount,
            ]);
        }
    }

    private function invoiceSummary(Invoice $invoice): array
    {
        return [
            'id' => (int) $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'amount' => round((float) $invoice->grand_total, self::SCALE),
            'status' => $invoice->status,
            'event_status' => $invoice->event_status,
        ];
    }

    private function downPaymentMessage(
        float $quoteTotal,
        ?Invoice $downPayment,
        ?Invoice $settlement,
        ?Invoice $fullPayment
    ): string {
        if ($quoteTotal <= 0) {
            return 'Grand Total Quote harus lebih dari Rp 0.';
        }

        if ($fullPayment) {
            return 'Quote sudah memiliki Invoice Full Payment aktif.';
        }

        if ($settlement) {
            return 'Quote sudah memiliki Invoice Pelunasan aktif.';
        }

        if ($downPayment) {
            return 'Quote sudah memiliki Invoice DP aktif: '
                .$downPayment->invoice_number.'.';
        }

        return 'DP tersedia. Nominal dapat diubah sebelum Invoice dibuat.';
    }

    private function settlementMessage(
        ?Invoice $downPayment,
        ?Invoice $settlement,
        ?Invoice $fullPayment,
        float $remaining
    ): string {
        if ($fullPayment) {
            return 'Quote sudah memakai Full Payment.';
        }

        if (! $downPayment) {
            return 'Buat Invoice DP terlebih dahulu.';
        }

        if ($downPayment->status !== 'paid') {
            return 'Pelunasan baru dapat dibuat setelah Invoice DP berstatus PAID.';
        }

        if ($settlement) {
            return 'Invoice Pelunasan sudah ada: '
                .$settlement->invoice_number.'.';
        }

        if ($remaining <= 0) {
            return 'Tidak ada sisa nilai Quote yang perlu ditagihkan.';
        }

        return 'Pelunasan tersedia sebesar sisa nilai Quote.';
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_DOWN_PAYMENT => 'Down Payment',
            self::TYPE_SETTLEMENT => 'Pelunasan',
            default => 'Full Payment',
        };
    }

    private function throwLocked(): never
    {
        throw ValidationException::withMessages([
            'billing' =>
                'Nilai komersial Invoice DP/Full Payment/Pelunasan sudah dikunci. '
                .'Jika salah, batalkan Invoice lalu generate ulang agar audit trail tetap utuh.',
        ]);
    }
}
