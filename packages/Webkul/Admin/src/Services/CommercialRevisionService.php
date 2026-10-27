<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webkul\Admin\Repositories\CommercialRevisionRepository;
use Webkul\Core\Support\PphGrossUp;
use Webkul\Core\Support\SalesLineItem;
use Webkul\Invoice\Support\BillingDescription;

class CommercialRevisionService
{
    private const LINE_FIELDS = ['product_id', 'sku', 'name', 'description', 'day', 'unit', 'equipment_quantity', 'quantity', 'price', 'coupon_code', 'discount_percent', 'discount_amount', 'tax_percent', 'tax_amount', 'total'];

    public function __construct(private CommercialRevisionRepository $documents, private FinancialPeriodLockService $periods) {}

    public function hasDocuments(int $quoteId): bool
    {
        return DB::table('invoices')->where('quote_id', $quoteId)->exists()
            || DB::table('proforma_invoices')->where('quote_id', $quoteId)->exists();
    }

    public function preview(string $type, int $id): array
    {
        $state = $this->documents->snapshot($this->quoteId($type, $id));
        $this->assertSource($state, $type, $id);
        [$invoices, $proformas] = $this->activeDocuments($state);
        $dp = collect($invoices)->firstWhere('billing_type', 'down_payment')
            ?? collect($proformas)->firstWhere('billing_type', 'down_payment');
        $quote = $state['quote'];
        $form = Arr::only($quote, ['subject', 'person_id', 'event_date', 'location', 'payment_term', 'description', 'bill_to_display_mode', 'adjustment_amount', 'pph_gross_up']);
        // A proforma can have its own agreed TOP, different from the original quote.
        // Start with the opened document so editing another field never restores an old TOP.
        $termSource = match ($type) {
            'proforma' => Arr::get(json_decode(collect($proformas)->firstWhere('id', $id)['quote_snapshot'] ?? '[]', true), 'attributes', []),
            'invoice' => collect($invoices)->firstWhere('id', $id),
            default => $quote,
        };
        $form['payment_term'] = Arr::get($termSource, 'payment_term', $quote['payment_term'] ?? null);
        $form['bill_to_display_mode'] = $form['bill_to_display_mode'] ?: 'person';
        $form['event_date'] = $form['event_date'] ? substr($form['event_date'], 0, 10) : null;
        foreach (['billing_address', 'shipping_address'] as $field) {
            $form[$field] = json_decode($quote[$field] ?? '[]', true) ?: [];
        }
        $form['items'] = array_map(fn ($item) => SalesLineItem::display($item), $state['quote_items']);
        $form['document_discount_amount'] = $this->money((float) $quote['discount_amount'] - array_sum(array_column($state['quote_items'], 'discount_amount')));
        $form['document_tax_amount'] = $this->money((float) $quote['tax_amount'] - array_sum(array_column($state['quote_items'], 'tax_amount')));
        $form['dp_amount'] = $dp ? $this->money(($dp['grand_total'] ?? $dp['amount']) - (float) ($dp['pph_amount'] ?? 0)) : null;
        $sourceProforma = $type === 'proforma' ? collect($proformas)->firstWhere('id', $id) : null;
        $canChangeProformaBilling = $sourceProforma && $sourceProforma['status'] === 'issued'
            && $sourceProforma['billing_type'] === 'down_payment' && empty($sourceProforma['invoice_id'])
            && $invoices === [] && count($proformas) === 1;
        $form['proforma_billing_type'] = $canChangeProformaBilling ? 'down_payment' : null;

        return compact('state', 'form', 'invoices', 'proformas') + [
            'source_proforma' => $sourceProforma,
            'can_change_proforma_billing' => $canChangeProformaBilling,
            'fingerprint' => $this->fingerprint($state),
            'history' => DB::table('commercial_document_revisions')->where('quote_id', $quote['id'])->orderByDesc('version')->limit(20)->get(),
            'has_work_orders' => DB::table('work_orders')->where('quote_id', $quote['id'])->exists(),
        ];
    }

    /** All documents and the audit entry commit together, or none of them do. */
    public function apply(string $type, int $id, array $input, int $actor): int
    {
        $data = $this->validate($input);
        $this->ensure(DB::table('users')->where('id', $actor)->exists(), 'actor', 'Pengguna revisi tidak ditemukan.');
        $quoteId = $this->quoteId($type, $id);

        return DB::transaction(function () use ($type, $id, $data, $actor, $quoteId) {
            // Same lock order as billing conversion: quote, invoices, children.
            $before = $this->documents->snapshot($quoteId, true);
            $this->assertSource($before, $type, $id);
            $this->ensure(hash_equals($this->fingerprint($before), $data['fingerprint']), 'fingerprint', 'Data berubah sejak form dibuka. Periksa data terbaru lalu ulangi revisi.');
            [$invoices, $proformas] = $this->activeDocuments($before);
            $this->assertOpenPeriods($before, $invoices);
            $proformaBillingChange = $this->proformaBillingChange($type, $id, $invoices, $proformas, $data);
            if ($proformaBillingChange !== null) {
                $proformas = array_map(fn ($proforma) => (int) $proforma['id'] === $id
                    ? array_replace($proforma, $proformaBillingChange) : $proforma, $proformas);
                // Full billing replaces the DP split; an old DP field is not a second charge.
                $data['dp_amount'] = null;
            }
            [$header, $items] = $this->commercialData($before, $data);
            [$amounts, $dpAmount] = $this->billingAmounts($before, $invoices, $proformas, $data, $header);

            $quoteItems = $this->documents->updateQuote($quoteId, $header, $items);
            foreach ($invoices as $invoice) {
                $billingType = $invoice['billing_type'] ?: 'full_payment';
                $amount = $amounts[$invoice['id']];
                $paid = $this->paid($before, $invoice['id']);
                $pph = $this->documentPph($header, $billingType, $amount, $dpAmount);
                $cash = $this->money($amount - $pph);
                $invoiceHeader = $header;
                if (! in_array($billingType, ['full_payment', 'full'], true)) {
                    $invoiceHeader = array_replace($invoiceHeader, [
                        'sub_total' => $cash, 'grand_total' => $amount, 'pph_amount' => $pph,
                        'discount_percent' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'adjustment_amount' => 0,
                    ]);
                }
                $invoiceHeader += [
                    'paid_amount' => $paid, 'balance_due' => round(max(0, $cash - $paid), 4),
                    'status' => $paid >= $cash ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                    'quote_total_snapshot' => $header['grand_total'], 'billing_amount' => $amount,
                    'billing_percentage' => round($amount / $header['grand_total'] * 100, 4),
                    'remaining_amount_snapshot' => $billingType === 'down_payment' ? $this->money($header['grand_total'] - $amount) : 0,
                ];
                if ($billingType === 'down_payment' && $this->money($invoice['grand_total']) !== $amount) {
                    $invoiceHeader['billing_method'] = 'nominal';
                }
                $this->documents->updateInvoice($invoice, $invoiceHeader, $this->invoiceItems($quoteItems, $cash, $header['grand_total'], $billingType), $before);
            }

            $quote = (array) DB::table('quotes')->where('id', $quoteId)->first();
            foreach ($proformas as $proforma) {
                $amount = $proforma['status'] === 'converted'
                    ? $amounts[$proforma['invoice_id']]
                    : match ($proforma['billing_type']) {
                        'down_payment' => $dpAmount,
                        'settlement' => $this->money($header['grand_total'] - $dpAmount),
                        default => $header['grand_total'],
                    };
                $values = [
                    'pph_gross_up' => $header['pph_gross_up'], 'pph_rate' => $header['pph_rate'],
                    'pph_amount' => $this->documentPph($header, $proforma['billing_type'], $amount, $dpAmount),
                    'amount' => $amount, 'billing_percentage' => round($amount / $header['grand_total'] * 100, 4),
                    'quote_snapshot' => ['attributes' => $quote, 'items' => $quoteItems],
                ];
                if ($proforma['billing_type'] === 'down_payment' && $this->money($proforma['amount']) !== $amount) {
                    $values['billing_method'] = 'nominal';
                }
                if ($proformaBillingChange !== null && (int) $proforma['id'] === $id) {
                    $values = array_replace($values, $proformaBillingChange);
                }
                $this->documents->update('proforma_invoices', $proforma['id'], $values);
            }

            $after = $this->documents->snapshot($quoteId);
            $after['version'] = $before['version'] + 1;

            return $this->documents->record($before, $after, $type, $id, $actor, trim($data['reason']));
        }, 3);
    }

    private function validate(array $input): array
    {
        $money = ['required', 'numeric', 'min:0', 'max:999999999999.99'];

        return Validator::make($input, [
            'pph_gross_up' => ['sometimes', 'boolean'],
            'fingerprint' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'subject' => ['required', 'string', 'max:255'],
            'person_id' => ['required', 'integer', 'exists:persons,id'],
            'bill_to_display_mode' => ['required', 'in:person,company,both'],
            'event_date' => ['nullable', 'date_format:Y-m-d'],
            'location' => ['nullable', 'string', 'max:255'],
            'payment_term' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:10000'],
            'billing_address' => ['nullable', 'array:address,city,state,country,postcode'],
            'shipping_address' => ['nullable', 'array:address,city,state,country,postcode'],
            'billing_address.*' => ['nullable', 'string'],
            'shipping_address.*' => ['nullable', 'string'],
            'billing_address.city' => ['nullable', 'string', 'max:2000'],
            'billing_address.state' => ['nullable', 'string', 'max:2000'],
            'billing_address.country' => ['nullable', 'string', 'max:2000'],
            'billing_address.postcode' => ['nullable', 'string', 'max:2000'],
            'shipping_address.city' => ['nullable', 'string', 'max:2000'],
            'shipping_address.state' => ['nullable', 'string', 'max:2000'],
            'shipping_address.country' => ['nullable', 'string', 'max:2000'],
            'shipping_address.postcode' => ['nullable', 'string', 'max:2000'],
            'adjustment_amount' => ['required', 'numeric', 'between:-999999999999.99,999999999999.99'],
            'document_discount_amount' => $money, 'document_tax_amount' => $money,
            'dp_amount' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
            'proforma_billing_type' => ['nullable', 'in:down_payment,full_payment'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:10000'],
            'items.*.unit' => ['required', 'in:pcs,day'],
            'items.*.quantity' => ['required', 'integer', 'between:1,1000000'],
            'items.*.price' => $money,
            'items.*.discount_amount' => $money, 'items.*.tax_amount' => $money,
        ])->validate();
    }

    /** An issued DP request may become a full request; issued invoices keep their own workflow. */
    private function proformaBillingChange(string $type, int $id, array $invoices, array $proformas, array $data): ?array
    {
        $requested = $data['proforma_billing_type'] ?? null;
        if ($requested === null) {
            return null;
        }

        $this->ensure($type === 'proforma', 'proforma_billing_type', 'Ubah jenis tagihan melalui Revisi Transaksi pada proforma yang dipilih.');
        $source = collect($proformas)->firstWhere('id', $id);
        $this->ensure($source !== null, 'proforma_billing_type', 'Proforma aktif tidak ditemukan.');
        if ($requested === $source['billing_type']) {
            return null;
        }

        $this->ensure($source['status'] === 'issued' && empty($source['invoice_id']), 'proforma_billing_type',
            'Proforma sudah menjadi invoice. Gunakan alur Ganti Invoice DP ke Tagihan Penuh dari invoice terkait.');
        $this->ensure($source['billing_type'] === 'down_payment' && $requested === 'full_payment', 'proforma_billing_type',
            'Perubahan jenis tagihan hanya tersedia dari Proforma DP menjadi Proforma Full.');
        $this->ensure($invoices === [] && count($proformas) === 1, 'proforma_billing_type',
            'Sudah ada invoice atau proforma aktif lain pada quotation ini. Tinjau rangkaian tagihan sebelum mengubah DP menjadi Full.');
        $this->ensure(! $this->periods->isLocked($source['issued_at']), 'period',
            'Periode proforma sudah ditutup. Jenis tagihan tidak dapat diubah.');

        return ['billing_type' => 'full_payment', 'billing_method' => 'nominal', 'dp_invoice_id' => null];
    }

    private function commercialData(array $before, array $data): array
    {
        $old = collect($before['quote_items'])->keyBy('id');
        $products = DB::table('products')->whereIn('id', array_column($data['items'], 'product_id'))->get()->keyBy('id');
        $items = [];
        foreach ($data['items'] as $index => $item) {
            $source = empty($item['id']) ? null : $old->get($item['id']);
            $this->ensure(empty($item['id']) || $source !== null, 'items', 'Item tidak berasal dari quotation ini.');
            $product = $products->get($item['product_id']);
            $line = SalesLineItem::prepare($item, $source && (int) $source['product_id'] === (int) $item['product_id'] ? $source : null);
            $base = $this->money($line['amount']);
            $discount = $this->money($item['discount_amount']);
            $tax = $this->money($item['tax_amount']);
            $this->ensure($base <= 999999999999.99, 'items.'.$index.'.price', 'Nilai item melebihi batas nominal yang didukung.');
            $this->ensure($discount <= $base, 'items.'.$index.'.discount_amount', 'Diskon item tidak boleh melebihi nilai item.');
            $taxPercent = $base > $discount ? round($tax / ($base - $discount) * 100, 4) : 0;
            $this->ensure($taxPercent <= 99999999.9999, 'items.'.$index.'.tax_amount', 'Persentase pajak melebihi batas yang didukung.');
            $items[] = Arr::only($line, self::LINE_FIELDS) + ['coupon_code' => $source['coupon_code'] ?? null];
            $items[array_key_last($items)] = array_replace($items[array_key_last($items)], [
                'id' => $source['id'] ?? null, 'sku' => $product->sku,
                'total' => $base, 'discount_amount' => $discount, 'tax_amount' => $tax,
                'discount_percent' => $base > 0 ? round($discount / $base * 100, 4) : 0,
                'tax_percent' => $taxPercent,
            ]);
        }
        $person = DB::table('persons')->leftJoin('organizations', 'organizations.id', '=', 'persons.organization_id')
            ->where('persons.id', $data['person_id'])->first(['persons.name', 'organizations.name as company']);
        $this->ensure($data['bill_to_display_mode'] === 'person' || filled($person->company), 'bill_to_display_mode', 'Contact yang dipilih belum memiliki perusahaan.');
        $header = Arr::only($data, ['subject', 'person_id', 'event_date', 'location', 'payment_term', 'description', 'bill_to_display_mode', 'billing_address', 'shipping_address']);
        $header += [
            'bill_to_person_name' => $person->name, 'bill_to_company_name' => $person->company,
            'sub_total' => $this->money(array_sum(array_column($items, 'total'))),
            'discount_percent' => 0,
            'discount_amount' => $this->money(array_sum(array_column($items, 'discount_amount')) + (float) $data['document_discount_amount']),
            'tax_amount' => $this->money(array_sum(array_column($items, 'tax_amount')) + (float) $data['document_tax_amount']),
            'adjustment_amount' => $this->money($data['adjustment_amount']),
        ];
        $enabled = (bool) ($data['pph_gross_up'] ?? $before['quote']['pph_gross_up'] ?? false);
        $base = $header['sub_total'] - $header['discount_amount'] + $header['adjustment_amount'];
        $this->ensure(! $enabled || $base > 0, 'pph_gross_up', 'Gross-up memerlukan nilai jasa bersih lebih dari nol.');
        $header = array_replace($header, PphGrossUp::calculate($base, $header['tax_amount'], $enabled));
        $this->ensure($header['grand_total'] > 0 && $header['grand_total'] <= 999999999999.99, 'items', 'Total transaksi harus lebih dari nol dan dalam batas nominal yang didukung.');

        return [$header, $items];
    }

    private function billingAmounts(array $before, array $invoices, array $proformas, array $data, array $header): array
    {
        $total = $header['grand_total'];
        $byType = collect($invoices)->groupBy(fn ($i) => in_array($i['billing_type'], [null, 'full', 'full_payment'], true) ? 'full_payment' : $i['billing_type']);
        $this->ensure($byType->every(fn ($group) => $group->count() === 1)
            && ! ($byType->has('full_payment') && count($invoices) > 1), 'billing', 'Rangkaian invoice memiliki tagihan ganda. Periksa data invoice sebelum revisi.');
        $issued = collect($proformas)->where('status', 'issued');
        $this->ensure($issued->count() <= 1, 'billing', 'Ada lebih dari satu proforma aktif. Periksa rangkaian tagihan sebelum revisi.');
        $dp = $byType->get('down_payment')?->first();
        $dpProforma = $issued->firstWhere('billing_type', 'down_payment');
        $this->ensure(! ($dp && $dpProforma), 'billing', 'Invoice DP dan proforma DP aktif terduplikasi.');
        $dpSource = $dp ?? $dpProforma;
        $previousCash = $dpSource ? $this->money(($dpSource['grand_total'] ?? $dpSource['amount']) - (float) ($dpSource['pph_amount'] ?? 0)) : 0.0;
        $dpCash = $dpSource ? $this->money($data['dp_amount'] ?? $previousCash) : 0.0;
        $dpAmount = PphGrossUp::grossFromCash($dpCash, $header['pph_amount'], $total);
        if ($dpSource) {
            $this->ensure($dpAmount > 0 && $dpAmount < $total, 'dp_amount', 'Nominal DP harus lebih dari nol dan lebih kecil dari total transaksi.');
        } else {
            $this->ensure(empty($data['dp_amount']), 'dp_amount', 'Transaksi ini tidak mempunyai tagihan DP.');
        }
        if ($dp && $this->paid($before, $dp['id']) >= $previousCash) {
            $this->ensure($dpCash === $previousCash, 'dp_amount', 'DP sudah lunas. Nilai transfer netto DP tetap; perubahan nilai transaksi dialokasikan ke pelunasan.');
        }
        foreach ($issued as $pf) {
            $this->ensure(! $byType->has('full_payment') && ! $byType->has('settlement'), 'billing', 'Proforma aktif bertabrakan dengan invoice yang sudah terbit.');
            $this->ensure($pf['billing_type'] !== 'full_payment' || $invoices === [], 'billing', 'Proforma penuh tidak dapat disatukan dengan invoice DP.');
            if ($pf['billing_type'] === 'settlement') {
                $this->ensure($dp && (int) $pf['dp_invoice_id'] === (int) $dp['id'], 'billing', 'Proforma pelunasan tidak terhubung ke invoice DP transaksi ini.');
            }
        }
        $amounts = [];
        foreach ($invoices as $invoice) {
            $kind = $invoice['billing_type'] ?: 'full_payment';
            $this->ensure(in_array($kind, ['full', 'full_payment', 'down_payment', 'settlement'], true), 'billing', 'Jenis tagihan invoice tidak didukung.');
            if ($kind === 'settlement') {
                $this->ensure($dp && (int) $invoice['dp_invoice_id'] === (int) $dp['id'], 'billing', 'Invoice pelunasan tidak terhubung ke DP transaksi ini.');
            }
            $amount = match ($kind) {
                'down_payment' => $dpAmount,
                'settlement' => $this->money($total - $dpAmount),
                default => $total,
            };
            $paid = $this->paid($before, $invoice['id']);
            $this->ensure(abs($paid - (float) $invoice['paid_amount']) < 0.0001, 'payments', 'Total riwayat pembayaran berbeda dari saldo invoice '.$invoice['invoice_number'].'. Periksa pembayaran sebelum revisi.');
            $cash = $this->money($amount - $this->documentPph($header, $kind, $amount, $dpAmount));
            $this->ensure($cash >= $paid, 'items', 'Nilai revisi '.$invoice['invoice_number'].' lebih kecil dari pembayaran yang sudah masuk. Selesaikan kelebihan pembayaran terlebih dahulu.');
            $amounts[$invoice['id']] = $amount;
        }

        if ($dpSource) {
            $this->ensure(abs($dpAmount - $this->documentPph($header, 'down_payment', $dpAmount, $dpAmount) - $dpCash) < 0.0001,
                'dp_amount', 'Pembulatan gross-up tidak cocok dengan DP netto. Periksa nominal pembagian.');
        }

        return [$amounts, $dpAmount];
    }

    private function documentPph(array $header, string $type, float $amount, float $dpAmount): float
    {
        return PphGrossUp::allocate($header['pph_amount'], $header['grand_total'], $amount,
            $type === 'settlement' ? PphGrossUp::allocate($header['pph_amount'], $header['grand_total'], $dpAmount) : null);
    }

    private function invoiceItems(array $items, float $amount, float $total, string $type): array
    {
        $full = in_array($type, ['full', 'full_payment'], true);
        $weights = array_map(fn ($i) => max(0, (float) $i['total'] - (float) $i['discount_amount'] + (float) $i['tax_amount']), $items);
        $weightTotal = array_sum($weights);
        $allocated = 0.0;
        $result = [];
        foreach ($items as $index => $item) {
            $line = Arr::only($item, self::LINE_FIELDS) + ['quote_item_id' => $item['id']];
            if (! $full) {
                $part = $index === array_key_last($items) ? $this->money($amount - $allocated)
                    : min($this->money($amount - $allocated), $this->money($amount * ($weightTotal > 0 ? $weights[$index] / $weightTotal : 1 / count($items))));
                $allocated = $this->money($allocated + $part);
                $line = array_replace($line, [
                    'description' => BillingDescription::make($type, $total, $item['description']),
                    'price' => round($part / max(1, (float) $item['quantity']), 4),
                    'total' => $part, 'discount_amount' => 0, 'discount_percent' => 0, 'tax_amount' => 0, 'tax_percent' => 0,
                ]);
            }
            $result[] = $line;
        }

        return $result;
    }

    private function activeDocuments(array $state): array
    {
        $invoices = array_values(array_filter($state['invoices'], fn ($i) => in_array($i['status'], ['unpaid', 'partial', 'paid'], true)
            && ! in_array($i['event_status'], ['cancel', 'cancelled', 'canceled'], true)));
        $ids = array_column($invoices, 'id');
        $proformas = array_values(array_filter($state['proformas'], fn ($p) => $p['status'] === 'issued'
            || ($p['status'] === 'converted' && in_array($p['invoice_id'], $ids))));

        return [$invoices, $proformas];
    }

    private function quoteId(string $type, int $id): int
    {
        $table = match ($type) {
            'quote' => 'quotes', 'invoice' => 'invoices', 'proforma' => 'proforma_invoices', default => abort(404)
        };
        $source = DB::table($table)->where('id', $id)->first();
        abort_unless($source, 404);
        $quoteId = $type === 'quote' ? $id : $source->quote_id;
        $this->ensure((bool) $quoteId, 'quote', 'Dokumen belum terhubung ke quotation.');

        return (int) $quoteId;
    }

    private function assertSource(array $state, string $type, int $id): void
    {
        [$invoices, $proformas] = $this->activeDocuments($state);
        $this->ensure($type === 'quote' || collect($type === 'invoice' ? $invoices : $proformas)->contains('id', $id), 'document', 'Dokumen VOID, dibatalkan, atau diarsipkan tidak dapat direvisi.');
    }

    private function assertOpenPeriods(array $state, array $invoices): void
    {
        $dates = [$state['quote']['document_date'] ?? $state['quote']['created_at']];
        foreach ($invoices as $invoice) {
            $dates[] = $invoice['issued_at'];
        }
        $voided = array_column($state['payment_voids'], 'payment_id');
        foreach (collect($state['payments'])->whereIn('invoice_id', array_column($invoices, 'id'))->whereNotIn('id', $voided) as $payment) {
            $dates[] = $payment['paid_at'];
        }
        foreach (array_filter(array_unique($dates)) as $date) {
            $this->ensure(! $this->periods->isLocked($date), 'period', 'Periode dokumen sudah ditutup. Revisi tidak boleh mengubah laporan pada periode terkunci.');
        }
    }

    private function paid(array $state, int $invoiceId): float
    {
        $voided = array_column($state['payment_voids'], 'payment_id');

        return round((float) collect($state['payments'])->where('invoice_id', $invoiceId)->whereNotIn('id', $voided)->sum('amount'), 4);
    }

    private function fingerprint(array $state): string
    {
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    private function money(mixed $amount): float
    {
        return round((float) $amount, 2);
    }

    private function ensure(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
