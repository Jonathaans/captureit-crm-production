<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit, audited maintenance operation; normal document locks stay intact. */
class InvoiceCorrectionService
{
    public function preview(string $number): array
    {
        $snapshot = $this->snapshot($number);

        return [
            'invoice' => $snapshot['invoice'],
            'quote' => $snapshot['quote'],
            'payment_total' => round(array_sum(array_column($snapshot['payments'], 'amount')), 4),
            'payments' => array_map(fn ($row) => array_intersect_key($row, array_flip(['id', 'amount', 'paid_at'])), $snapshot['payments']),
            'dependencies' => $snapshot['dependencies'],
            'fingerprint' => $this->fingerprint($snapshot),
        ];
    }

    public function apply(string $number, string $expected, string $reason, int $actorId): array
    {
        if (strlen(trim($reason)) < 8 || ! preg_match('/^[a-f0-9]{64}$/', $expected)) {
            throw new RuntimeException('Alasan koreksi dan fingerprint hasil preview wajib diisi.');
        }
        if (! DB::table('users')->where('id', $actorId)->exists()) {
            throw new RuntimeException('Actor harus ID pengguna CRM yang melakukan koreksi.');
        }

        return DB::transaction(function () use ($number, $expected, $reason, $actorId) {
            $target = DB::table('invoices')->where('invoice_number', $number)->first();
            if (! $target) {
                throw new RuntimeException('Invoice tidak ditemukan atau sudah dikoreksi.');
            }

            // Same lock order as invoice generation: quote, then invoices.
            if ($target->quote_id) {
                DB::table('quotes')->where('id', $target->quote_id)->lockForUpdate()->first();
            }
            $snapshot = $this->snapshot($number, true);
            if (! hash_equals($expected, $this->fingerprint($snapshot))) {
                throw new RuntimeException('Data berubah sejak preview. Jalankan preview kembali.');
            }
            if (array_sum($snapshot['dependencies']) > 0) {
                throw new RuntimeException('Invoice masih memiliki dokumen terkait. Tinjau dependencies pada preview; tidak ada data yang dihapus.');
            }

            $id = (int) $snapshot['invoice']['id'];
            $result = [
                'invoice_id' => $id,
                'quote_id' => $snapshot['invoice']['quote_id'],
                'payment_count' => count($snapshot['payments']),
                'payment_removed_total' => round(array_sum(array_column($snapshot['payments'], 'amount')), 4),
            ];
            $auditId = DB::table('crm_data_corrections')->insertGetId([
                'kind' => 'invoice',
                'invoice_number' => $number,
                'actor_id' => $actorId,
                'reason' => trim($reason),
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'result' => json_encode($result, JSON_THROW_ON_ERROR),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Archive first, then remove only the reviewed invoice and its input.
            // Do not weaken the Eloquent guards used by everyday editing.
            DB::table('payments')->where('invoice_id', $id)->delete();
            DB::table('invoice_items')->where('invoice_id', $id)->delete();
            DB::table('invoices')->where('id', $id)->delete();

            return ['audit_id' => $auditId] + $result;
        });
    }

    private function snapshot(string $number, bool $lock = false): array
    {
        $query = DB::table('invoices')->where('invoice_number', $number);
        $invoice = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $invoice) {
            throw new RuntimeException('Invoice tidak ditemukan: '.$number);
        }

        $snapshot = ['invoice' => (array) $invoice];
        foreach (['items' => 'invoice_items', 'payments' => 'payments'] as $key => $table) {
            $query = DB::table($table)->where('invoice_id', $invoice->id)->orderBy('id');
            $snapshot[$key] = ($lock ? $query->lockForUpdate() : $query)->get()->map(fn ($row) => (array) $row)->all();
        }
        $snapshot['quote'] = $invoice->quote_id
            ? (array) DB::table('quotes')->where('id', $invoice->quote_id)->first(['id', 'quote_number', 'grand_total'])
            : null;
        $snapshot['dependencies'] = [];
        foreach (['expenses', 'work_orders', 'delivery_orders', 'purchase_orders'] as $table) {
            $query = DB::table($table)->where('invoice_id', $invoice->id)->select('id');
            $snapshot['dependencies'][$table] = ($lock ? $query->lockForUpdate() : $query)->get()->count();
        }
        $query = DB::table('invoices')->where('dp_invoice_id', $invoice->id)->select('id');
        $snapshot['dependencies']['linked_settlements'] = ($lock ? $query->lockForUpdate() : $query)->get()->count();
        $snapshot['dependencies']['other_active_invoices'] = $invoice->quote_id
            ? DB::table('invoices')->where('quote_id', $invoice->quote_id)->where('id', '<>', $invoice->id)
                ->where(fn ($q) => $q->whereNull('event_status')->orWhereNotIn('event_status', ['cancel', 'cancelled', 'canceled']))->count()
            : 0;

        return $snapshot;
    }

    private function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
