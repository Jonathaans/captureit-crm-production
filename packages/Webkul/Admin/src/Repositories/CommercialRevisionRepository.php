<?php

namespace Webkul\Admin\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Writes only inside the validated, audited commercial revision transaction. */
class CommercialRevisionRepository
{
    public function snapshot(int $quoteId, bool $lock = false): array
    {
        $quote = $this->rows(DB::table('quotes')->where('id', $quoteId), $lock)[0] ?? null;
        abort_unless($quote, 404);
        $invoices = $this->rows(DB::table('invoices')->where('quote_id', $quoteId), $lock);
        $invoiceIds = array_column($invoices, 'id');

        return [
            'quote' => $quote,
            'quote_items' => $this->rows(DB::table('quote_items')->where('quote_id', $quoteId), $lock),
            'invoices' => $invoices,
            'invoice_items' => $this->rows(DB::table('invoice_items')->whereIn('invoice_id', $invoiceIds), $lock),
            'proformas' => $this->rows(DB::table('proforma_invoices')->where('quote_id', $quoteId), $lock),
            'payments' => $this->rows(DB::table('payments')->whereIn('invoice_id', $invoiceIds), $lock),
            'payment_voids' => $this->rows(DB::table('payment_voids')->whereIn('invoice_id', $invoiceIds), $lock),
            'version' => (int) DB::table('commercial_document_revisions')->where('quote_id', $quoteId)->max('version'),
        ];
    }

    public function updateQuote(int $id, array $header, array $items): array
    {
        $this->update('quotes', $id, $header);
        $kept = [];
        foreach ($items as $item) {
            $idToKeep = $item['id'] ?? null;
            unset($item['id']);
            if ($idToKeep) {
                $this->update('quote_items', $idToKeep, $item);
            } else {
                $idToKeep = DB::table('quote_items')->insertGetId($item + ['quote_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
            $kept[] = $idToKeep;
        }
        DB::table('quote_items')->where('quote_id', $id)->whereNotIn('id', $kept)->delete();

        return $this->rows(DB::table('quote_items')->where('quote_id', $id), false);
    }

    public function updateInvoice(array $invoice, array $header, array $items, array $before): void
    {
        $oldQuoteItems = collect($before['quote_items'])->keyBy('id');
        $oldItems = collect($before['invoice_items'])->where('invoice_id', $invoice['id']);
        $mapped = [];
        $usedQuoteIds = [];
        foreach ($oldItems as $old) {
            $quoteItemId = $old['quote_item_id'] ?? null;
            if (! $quoteItemId) {
                // Deterministic migration of legacy lines, including repeated products.
                $source = $oldQuoteItems->first(fn ($q) => ! in_array($q['id'], $usedQuoteIds, true)
                    && (int) $q['product_id'] === (int) $old['product_id']);
                $quoteItemId = $source['id'] ?? null;
            }
            if ($quoteItemId) {
                $mapped[$quoteItemId] = $old['id'];
                $usedQuoteIds[] = $quoteItemId;
            }
        }

        $kept = [];
        foreach ($items as $item) {
            $existingId = $mapped[$item['quote_item_id']] ?? null;
            if ($existingId) {
                $this->update('invoice_items', $existingId, $item);
                $kept[] = $existingId;
            } else {
                $kept[] = DB::table('invoice_items')->insertGetId($item + [
                    'invoice_id' => $invoice['id'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        $removed = $oldItems->pluck('id')->diff($kept)->values();
        if ($removed->isNotEmpty() && Schema::hasTable('purchase_order_items')
            && DB::table('purchase_order_items')->whereIn('invoice_item_id', $removed)->exists()) {
            throw ValidationException::withMessages(['items' => 'Item yang dihapus masih terhubung ke Purchase Order. Tinjau item PO tersebut terlebih dahulu.']);
        }
        DB::table('invoice_items')->where('invoice_id', $invoice['id'])->whereNotIn('id', $kept)->delete();
        $this->update('invoices', $invoice['id'], $header);
    }

    /** Explicit revision exception; ordinary archive locks and events stay in force elsewhere. */
    public function update(string $table, int $id, array $values): void
    {
        foreach (['billing_address', 'shipping_address', 'quote_snapshot'] as $key) {
            if (array_key_exists($key, $values) && is_array($values[$key])) {
                $values[$key] = json_encode($values[$key], JSON_THROW_ON_ERROR);
            }
        }
        DB::table($table)->where('id', $id)->update($values + ['updated_at' => now()]);
    }

    public function record(array $before, array $after, string $type, int $sourceId, int $actor, string $reason): int
    {
        return DB::table('commercial_document_revisions')->insertGetId([
            'quote_id' => $before['quote']['id'], 'version' => $before['version'] + 1,
            'actor_id' => $actor, 'actor_name' => DB::table('users')->where('id', $actor)->value('name'),
            'source_type' => $type, 'source_id' => $sourceId, 'reason' => $reason,
            'before' => json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }

    private function rows(Builder $query, bool $lock): array
    {
        return ($lock ? $query->lockForUpdate() : $query)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }
}
