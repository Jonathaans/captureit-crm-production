<?php

namespace Webkul\Admin\Repositories;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Webkul\Invoice\Models\Invoice;
use Webkul\Quote\Models\Quote;

/** Inserts validated historical records only; no operational/email creation events. */
class HistoricalDocumentRepository
{
    public function create(array $document, string $batchId, int $actor, ?int $quoteId, ?int $dpId): Quote|Invoice
    {
        $isQuote = $document['type'] === 'quote';
        $table = $isQuote ? 'quotes' : 'invoices';
        $data = Arr::only($document, [
            'person_id', 'user_id', 'subject', 'project_code', 'business_unit', 'event_date', 'location',
            'payment_term', 'description', 'bill_to_display_mode', 'bill_to_person_name', 'bill_to_company_name',
            'grand_total', 'sub_total', 'discount_amount', 'tax_amount', 'adjustment_amount',
        ]);
        $data += [
            ($isQuote ? 'quote_number' : 'invoice_number') => $document['number'],
            'billing_address' => json_encode(['address' => $document['address'] ?? ''], JSON_THROW_ON_ERROR),
            'shipping_address' => json_encode([], JSON_THROW_ON_ERROR),
            'discount_percent' => 0, 'historical_batch_id' => $batchId,
            'created_at' => now(), 'updated_at' => now(),
        ];
        if ($isQuote) {
            $data += ['document_date' => $document['document_date'], 'expired_at' => $document['expired_at'] ?? null];
        } else {
            $data += [
                'quote_id' => $quoteId, 'issued_at' => $document['document_date'], 'due_at' => $document['due_at'],
                'event_status' => $document['event_status'], 'billing_type' => $document['billing_type'],
                'paid_amount' => $document['paid_amount'], 'balance_due' => $document['balance_due'], 'status' => $document['status'],
                'quote_total_snapshot' => $document['quote_total_snapshot'], 'billing_amount' => $document['grand_total'],
                'remaining_amount_snapshot' => $document['remaining_amount_snapshot'],
                'dp_invoice_id' => $dpId, 'billing_created_by' => $actor, 'billing_locked_at' => now(),
            ];
        }
        // These are new rows in the batch transaction, never edits to an archived document.
        $id = DB::table($table)->insertGetId($data);
        foreach ($document['items'] as $item) {
            DB::table($isQuote ? 'quote_items' : 'invoice_items')->insert(Arr::only($item, [
                'product_id', 'name', 'description', 'sku', 'quantity', 'day', 'price', 'total',
            ]) + [
                ($isQuote ? 'quote_id' : 'invoice_id') => $id,
                'discount_percent' => 0, 'discount_amount' => 0, 'tax_percent' => 0, 'tax_amount' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach ($document['payments'] as $payment) {
            DB::table('payments')->insert(Arr::only($payment, ['amount', 'paid_at', 'payment_method', 'reference_number', 'notes']) + [
                'invoice_id' => $id, 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $isQuote ? Quote::findOrFail($id) : Invoice::findOrFail($id);
    }
}
