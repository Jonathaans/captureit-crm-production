<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Webkul\Admin\Models\HistoricalDocumentBatch;
use Webkul\Admin\Repositories\HistoricalDocumentRepository;
use Webkul\Contact\Models\Person;
use Webkul\Core\Support\BusinessUnit;
use Webkul\Core\Support\DocumentNumbers;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\ProformaInvoice;
use Webkul\Quote\Models\Quote;

class HistoricalDocumentService
{
    public function __construct(
        private DocumentNumbers $numbers,
        private FinancialPeriodLockService $periods,
        private HistoricalDocumentRepository $documents,
    ) {}

    /** No writes: both preview and commit run the same validation. */
    public function validate(array $input): array
    {
        Validator::make(['documents' => $input], ['documents' => 'required|array|min:1|max:200', 'documents.*' => 'required|array'])->validate();
        $normalized = [];
        $seen = ['quote' => [], 'invoice' => []];
        foreach (array_keys($seen) as $type) {
            $seen[$type] = array_fill_keys(array_map(DocumentNumbers::key(...), $this->numbers->all($type)), true);
        }
        foreach ($input as $index => $raw) {
            try {
                $d = $this->normalize($raw);
                $key = DocumentNumbers::key($d['number']);
                $this->ensure(! isset($seen[$d['type']][$key]), 'number', 'Nomor sudah digunakan atau berulang: '.$d['number']);
                $seen[$d['type']][$key] = true;
                $normalized[] = $d;
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(
                    fn ($messages, $field) => ['documents.'.$index.'.'.$field => array_map(fn ($message) => 'Dokumen '.($index + 1).': '.$message, $messages)]
                )->all());
            }
        }
        // Resolve quotes before DP invoices, and DP before settlements, regardless of Excel row order.
        usort($normalized, fn ($a, $b) => $this->order($a) <=> $this->order($b));
        $quotes = Quote::query()->get()->keyBy(fn ($q) => DocumentNumbers::key($q->quote_number))->all();
        $invoices = Invoice::query()->get()->keyBy(fn ($i) => DocumentNumbers::key($i->invoice_number))->all();
        $active = Invoice::query()->notVoided()->where(fn ($q) => $q->whereNull('event_status')->orWhereNotIn('event_status', ['cancel', 'cancelled', 'canceled']))->get();
        foreach ($normalized as &$d) {
            if ($d['type'] === 'quote') {
                $quotes[DocumentNumbers::key($d['number'])] = $d;

                continue;
            }
            $quote = empty($d['quote_number']) ? null : ($quotes[DocumentNumbers::key($d['quote_number'])] ?? null);
            $this->ensure(empty($d['quote_number']) || $quote !== null, 'quote_number', $d['number'].': quotation rujukan tidak ditemukan.');
            $this->ensure($d['billing_type'] === 'full_payment' || $quote !== null, 'quote_number', $d['number'].': DP/pelunasan harus terhubung ke quotation.');
            $total = (float) ($quote['grand_total'] ?? $d['grand_total']);
            $related = collect();
            if ($quote) {
                $this->ensure((int) $quote['person_id'] === $d['person_id'], 'person_id', $d['number'].': pelanggan berbeda dari quotation rujukan.');
                $d['project_code'] = $quote['project_code'];
                $d['business_unit'] = $quote['business_unit'];
                if ($quote instanceof Quote) {
                    $this->ensure(! ProformaInvoice::where('quote_id', $quote->id)->where('status', 'issued')->exists(), 'quote_number', $d['number'].': masih ada proforma aktif untuk quotation ini.');
                    $related = $active->where('quote_id', $quote->id)->values();
                }
                $related = $related->merge(collect($normalized)->filter(fn ($row) => isset($row['_validated'])
                    && DocumentNumbers::key($row['quote_number'] ?? '') === DocumentNumbers::key($d['quote_number'])
                    && $row['event_status'] !== 'cancel'));
            }
            $used = (float) $related->sum('grand_total');
            $d['quote_total_snapshot'] = $total;
            $d['remaining_amount_snapshot'] = max(0, round($total - $used - $d['grand_total'], 2));
            if ($d['event_status'] !== 'cancel' && $quote) {
                $this->ensure($used + $d['grand_total'] <= $total + 0.005, 'grand_total', $d['number'].': total invoice melebihi nilai quotation.');
                $this->ensure(! $related->contains('billing_type', $d['billing_type']), 'billing_type', $d['number'].': jenis tagihan ini sudah ada pada quotation.');
                if ($d['billing_type'] === 'full_payment') {
                    $this->ensure($related->isEmpty() && abs($d['grand_total'] - $total) < 0.005, 'grand_total', $d['number'].': invoice penuh harus sama dengan nilai quotation.');
                } elseif ($d['billing_type'] === 'down_payment') {
                    $this->ensure($related->isEmpty() && $d['grand_total'] < $total, 'grand_total', $d['number'].': DP harus lebih kecil dari quotation dan belum memiliki tagihan lain.');
                }
            }
            if ($d['billing_type'] === 'settlement') {
                $dp = $invoices[DocumentNumbers::key($d['dp_invoice_number'] ?? '')] ?? null;
                $sameQuote = $dp instanceof Invoice
                    ? ($quote instanceof Quote && (int) $dp->quote_id === $quote->id)
                    : ($dp && DocumentNumbers::key($dp['quote_number'] ?? '') === DocumentNumbers::key($d['quote_number']));
                $this->ensure($dp && $sameQuote && $dp['billing_type'] === 'down_payment' && $dp['status'] !== 'void' && $dp['event_status'] !== 'cancel', 'dp_invoice_number', $d['number'].': invoice DP rujukan tidak sesuai.');
                $dpDate = $dp instanceof Invoice ? $dp->issued_at?->toDateString() : $dp['document_date'];
                $this->ensure($d['document_date'] >= $dpDate, 'document_date', $d['number'].': tanggal pelunasan mendahului invoice DP.');
                if ($d['event_status'] !== 'cancel') {
                    $this->ensure(abs($d['grand_total'] - ($total - $used)) < 0.005, 'grand_total', $d['number'].': pelunasan harus sesuai sisa tagihan quotation.');
                }
            }
            $d['_validated'] = true;
            $invoices[DocumentNumbers::key($d['number'])] = $d;
        }
        unset($d);

        return array_map(fn ($document) => Arr::except($document, ['_validated']), $normalized);
    }

    private function normalize(array $raw): array
    {
        $raw = array_replace([
            'discount_amount' => 0, 'tax_amount' => 0, 'adjustment_amount' => 0,
            'bill_to_display_mode' => 'both', 'billing_type' => 'full_payment', 'event_status' => 'confirm',
            'items' => [], 'payments' => [],
        ], array_filter($raw, fn ($value) => $value !== '' && $value !== null));
        $amount = ['required', 'numeric', 'decimal:0,2', 'between:0,999999999999.99'];
        $d = Validator::make($raw, [
            'type' => ['required', Rule::in(['quote', 'invoice'])],
            'number' => ['required', 'string', 'max:30', 'regex:/^[\pL\pN][\pL\pN .\/_-]*$/u'],
            'document_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'person_id' => ['required', 'integer', 'exists:persons,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'project_code' => ['nullable', 'string', 'max:50'],
            'business_unit' => ['required', Rule::in(BusinessUnit::values())],
            'event_date' => ['nullable', 'date_format:Y-m-d'],
            'location' => ['nullable', 'string', 'max:255'],
            'payment_term' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:2000'],
            'bill_to_display_mode' => ['required', Rule::in(['person', 'company', 'both'])],
            'quote_number' => ['nullable', 'string', 'max:30'],
            'dp_invoice_number' => ['nullable', 'string', 'max:30'],
            'due_at' => ['nullable', 'required_if:type,invoice', 'date_format:Y-m-d', 'after_or_equal:document_date'],
            'expired_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:document_date'],
            'billing_type' => ['required', Rule::in(['full_payment', 'down_payment', 'settlement'])],
            'event_status' => ['required', Rule::in(['confirm', 'prospect', 'cancel'])],
            'grand_total' => [...$amount, 'gt:0'], 'discount_amount' => $amount, 'tax_amount' => $amount,
            'adjustment_amount' => ['required', 'numeric', 'decimal:0,2', 'between:-999999999999.99,999999999999.99'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.quantity' => ['required', 'integer', 'between:1,1000000'],
            'items.*.day' => ['required', 'integer', 'between:1,10000'],
            'items.*.total' => [...$amount, 'gt:0'],
            'payments' => ['present', 'array', 'max:100'],
            'payments.*.amount' => [...$amount, 'gt:0'],
            'payments.*.paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'payments.*.payment_method' => ['required', 'string', 'max:255'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:255'],
            'payments.*.notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();
        $d['number'] = trim($d['number']);
        $d['person_id'] = (int) $d['person_id'];
        $d['user_id'] = (int) $d['user_id'];
        $person = Person::findOrFail($d['person_id']);
        $d['bill_to_person_name'] = $person->name;
        $d['bill_to_company_name'] = $person->organization?->name;
        $this->ensure($d['bill_to_display_mode'] === 'person' || ! empty($d['bill_to_company_name']), 'bill_to_display_mode', 'Contact yang dipilih belum memiliki Company.');
        $d['project_code'] = $d['project_code'] ?? 'LEG-'.strtoupper(substr(hash('sha256', $d['type'].':'.DocumentNumbers::key($d['number'])), 0, 16));
        foreach (['grand_total', 'discount_amount', 'tax_amount', 'adjustment_amount'] as $column) {
            $d[$column] = round((float) $d[$column], 2);
        }
        foreach ($d['items'] as &$item) {
            $this->ensure($d['type'] !== 'quote' || ! empty($item['product_id']), 'items', 'Setiap item quotation harus dipetakan ke produk CRM.');
            $item['product_id'] = empty($item['product_id']) ? null : (int) $item['product_id'];
            $item['sku'] = $item['product_id'] ? DB::table('products')->where('id', $item['product_id'])->value('sku') : null;
            $item['total'] = round((float) $item['total'], 2);
            $item['price'] = round($item['total'] / ((int) $item['quantity'] * (int) $item['day']), 4);
        }
        unset($item);
        $d['sub_total'] = round(array_sum(array_column($d['items'], 'total')), 2);
        $expected = round($d['sub_total'] - $d['discount_amount'] + $d['tax_amount'] + $d['adjustment_amount'], 2);
        $this->ensure($d['discount_amount'] <= $d['sub_total'] && abs($d['grand_total'] - $expected) < 0.005, 'grand_total', 'Total dokumen harus sama dengan jumlah item - diskon + pajak + penyesuaian.');
        $this->ensure($d['type'] === 'invoice' || $d['payments'] === [], 'payments', 'Pembayaran hanya dapat dicatat pada invoice.');
        if ($d['type'] === 'invoice') {
            $this->ensure(! $this->periods->isLocked($d['document_date']), 'document_date', 'Periode invoice telah ditutup. Buka periode melalui menu Periode Keuangan sebelum mengimpor.');
        }
        foreach ($d['payments'] as &$payment) {
            $payment['amount'] = round((float) $payment['amount'], 2);
            $this->ensure(! $this->periods->isLocked($payment['paid_at']), 'payments', 'Periode pembayaran telah ditutup.');
        }
        unset($payment);
        $d['paid_amount'] = round(array_sum(array_column($d['payments'], 'amount')), 2);
        $this->ensure($d['paid_amount'] <= $d['grand_total'], 'payments', 'Jumlah pembayaran melebihi nilai invoice.');
        $d['balance_due'] = round($d['grand_total'] - $d['paid_amount'], 2);
        $d['status'] = $d['balance_due'] == 0 ? 'paid' : ($d['paid_amount'] > 0 ? 'partial' : 'unpaid');

        return $d;
    }

    public function commit(string $batchId, int $actor): HistoricalDocumentBatch
    {
        return DB::transaction(function () use ($batchId, $actor) {
            $batch = HistoricalDocumentBatch::whereKey($batchId)->where('created_by', $actor)->lockForUpdate()->firstOrFail();
            if ($batch->status === 'imported') {
                return $batch;
            }
            $this->ensure($batch->status === 'preview' && $batch->expires_at->isFuture(), 'batch', 'Preview telah kedaluwarsa. Periksa data kembali.');
            // Prevent another billing flow from using a referenced quote during commit.
            $references = array_filter(array_column($batch->payload['documents'], 'quote_number'));
            if ($references) {
                $keys = array_map(DocumentNumbers::key(...), $references);
                $ids = Quote::all()->filter(fn ($q) => in_array(DocumentNumbers::key($q->quote_number), $keys, true))->pluck('id');
                Quote::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            }
            // Match normal billing's order: lock referenced quotes before number allocation.
            $this->numbers->lock('quote');
            $this->numbers->lock('invoice');
            $documents = $this->validate($batch->payload['documents']);
            $quotes = Quote::all()->mapWithKeys(fn ($q) => [DocumentNumbers::key($q->quote_number) => $q->id])->all();
            $invoices = Invoice::all()->mapWithKeys(fn ($i) => [DocumentNumbers::key($i->invoice_number) => $i->id])->all();
            $result = [];
            foreach ($documents as $d) {
                $this->numbers->reserve($d['type'], $d['number']);
                $record = $this->documents->create($d, $batch->id, $actor,
                    $quotes[DocumentNumbers::key($d['quote_number'] ?? '')] ?? null,
                    $invoices[DocumentNumbers::key($d['dp_invoice_number'] ?? '')] ?? null);
                if ($d['type'] === 'quote') {
                    $quotes[DocumentNumbers::key($d['number'])] = $record->id;
                } else {
                    $invoices[DocumentNumbers::key($d['number'])] = $record->id;
                }
                $result[] = ['type' => $d['type'], 'id' => $record->id, 'number' => $d['number']];
            }
            $batch->update(['status' => 'imported', 'result' => $result]);

            return $batch;
        }, 3);
    }

    private function order(array $document): int
    {
        return $document['type'] === 'quote' ? 0 : ($document['billing_type'] === 'settlement' ? 2 : 1);
    }

    private function ensure(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
