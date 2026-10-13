<?php

namespace Webkul\Admin\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit correction of wholly fictitious receipts, never a refund or stock reversal. */
class InvoiceVoidService
{
    public function preview(string $number): array
    {
        $snapshot = $this->snapshot($number);

        return [
            'invoice' => array_intersect_key($snapshot['invoice'], array_flip(['id', 'invoice_number', 'quote_id', 'project_code', 'grand_total', 'paid_amount', 'status', 'event_status', 'issued_at'])),
            'payments' => array_map(fn ($row) => array_intersect_key($row, array_flip(['id', 'amount', 'paid_at'])), $snapshot['payments']),
            'payment_total_to_void' => $this->paymentTotal($snapshot),
            'work_orders' => array_map(fn ($row) => array_intersect_key($row, array_flip(['id', 'work_order_number', 'status'])), $snapshot['work_orders']),
            'delivery_orders' => array_map(fn ($row) => array_intersect_key($row, array_flip(['id', 'delivery_order_number', 'status'])), $snapshot['delivery_orders']),
            'blockers' => $this->blockers($snapshot),
            'fingerprint' => $this->fingerprint($snapshot),
            'effect' => 'Status invoice VOID; paid_amount dan balance_due menjadi 0. Seluruh payment tetap tersimpan sebagai riwayat salah input dan tidak dihitung sebagai penerimaan. Nominal asli, quote, status event, SPK, DO, alokasi dan stok tidak diubah.',
        ];
    }

    public function apply(string $number, string $expected, string $reason, int $actorId, bool $noMoneyReceived): array
    {
        if (! $noMoneyReceived) {
            throw new RuntimeException('Wajib --no-money-received: seluruh payment invoice ini harus salah input, tanpa uang yang benar-benar diterima.');
        }
        if (mb_strlen(trim($reason)) < 8 || ! preg_match('/^[a-f0-9]{64}$/', $expected)) {
            throw new RuntimeException('Alasan koreksi dan fingerprint preview wajib diisi.');
        }
        if (! DB::table('users')->where('id', $actorId)->exists()) {
            throw new RuntimeException('Actor harus ID pengguna CRM yang bertanggung jawab atas koreksi.');
        }

        return DB::transaction(function () use ($number, $expected, $reason, $actorId) {
            $target = DB::table('invoices')->where('invoice_number', $number)->first();
            if (! $target) {
                throw new RuntimeException('Invoice tidak ditemukan.');
            }
            // Match billing/payment locking: quote, invoice, then its dependent rows.
            if ($target->quote_id) {
                DB::table('quotes')->where('id', $target->quote_id)->lockForUpdate()->first();
            }
            $snapshot = $this->snapshot($number, true);
            if (! hash_equals($expected, $this->fingerprint($snapshot))) {
                throw new RuntimeException('Data berubah sejak preview. Jalankan preview kembali; belum ada perubahan.');
            }
            $blockers = $this->blockers($snapshot);
            if ($blockers !== []) {
                throw new RuntimeException(implode(' ', $blockers));
            }

            $result = [
                'invoice_id' => (int) $snapshot['invoice']['id'],
                'invoice_number' => $number,
                'status' => 'void',
                'payment_count_voided' => count($snapshot['payments']),
                'payment_total_voided' => $this->paymentTotal($snapshot),
                'work_orders_preserved' => count($snapshot['work_orders']),
                'delivery_orders_preserved' => count($snapshot['delivery_orders']),
            ];
            $auditId = DB::table('crm_data_corrections')->insertGetId([
                'kind' => 'invoice_void', 'invoice_number' => $number, 'actor_id' => $actorId,
                'reason' => trim($reason), 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'result' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);

            // This reviewed maintenance operation is the sole exception to the PAID archive lock.
            // Do not delete payments, rewrite commercial values, or fire operational transitions.
            DB::table('invoices')->where('id', $target->id)->update([
                'status' => 'void', 'paid_amount' => 0, 'balance_due' => 0, 'updated_at' => now(),
            ]);

            return ['audit_id' => $auditId] + $result;
        });
    }

    private function snapshot(string $number, bool $lock = false): array
    {
        $invoices = $this->rows(DB::table('invoices')->where('invoice_number', $number), $lock);
        if ($invoices === []) {
            throw new RuntimeException('Invoice tidak ditemukan: '.$number);
        }
        $invoice = $invoices[0];
        $id = (int) $invoice['id'];
        $snapshot = ['version' => 1, 'invoice' => $invoice];
        $snapshot['quote'] = $invoice['quote_id']
            ? $this->rows(DB::table('quotes')->where('id', $invoice['quote_id']), $lock) : [];
        foreach (['payments', 'invoice_items', 'expenses', 'purchase_orders', 'work_orders'] as $table) {
            $snapshot[$table] = $this->rows(DB::table($table)->where('invoice_id', $id), $lock);
        }
        $snapshot['delivery_orders'] = $this->rows(DB::table('delivery_orders')->where(fn (Builder $query) => $query
            ->where('invoice_id', $id)->orWhereIn('work_order_id', array_column($snapshot['work_orders'], 'id'))), $lock);
        $snapshot['allocations'] = $this->rows(DB::table('delivery_order_inventory_allocations')
            ->whereIn('delivery_order_id', array_column($snapshot['delivery_orders'], 'id')), $lock);
        $snapshot['related_invoices'] = $this->rows(DB::table('invoices')->where('id', '!=', $id)->where(function (Builder $query) use ($invoice, $id) {
            $query->where('dp_invoice_id', $id);
            if ($invoice['quote_id']) {
                $query->orWhere('quote_id', $invoice['quote_id']);
            }
        }), $lock);
        $snapshot['proformas'] = $this->rows(DB::table('proforma_invoices')->where(fn (Builder $query) => $query
            ->where('invoice_id', $id)->orWhere('dp_invoice_id', $id)), $lock);
        $snapshot['period_locks'] = $this->rows(DB::table('financial_period_locks'), $lock);
        $snapshot['existing_corrections'] = $this->rows(DB::table('crm_data_corrections')->where('invoice_number', $number), $lock);

        return $snapshot;
    }

    private function blockers(array $snapshot): array
    {
        $errors = [];
        $invoice = $snapshot['invoice'];
        if (! in_array($invoice['status'], ['unpaid', 'partial', 'paid'], true) || $snapshot['existing_corrections'] !== []) {
            $errors[] = 'Invoice sudah final/dikoreksi; tidak dapat dikoreksi ulang.';
        }
        if (($invoice['billing_type'] ?? '') === 'settlement' || ! empty($invoice['dp_invoice_id'])) {
            $errors[] = 'Invoice pelunasan memerlukan peninjauan seluruh rangkaian DP/pelunasan.';
        }
        if ($snapshot['expenses'] !== [] || $snapshot['purchase_orders'] !== [] || $snapshot['related_invoices'] !== []) {
            $errors[] = 'Ada expense, PO, atau invoice lain pada penawaran ini. Koreksi membutuhkan peninjauan keuangan terkait.';
        }
        foreach ($snapshot['proformas'] as $proforma) {
            if ($proforma['status'] === 'issued' || (int) ($proforma['dp_invoice_id'] ?? 0) === (int) $invoice['id']) {
                $errors[] = 'Ada proforma aktif/pelunasan terkait. Tinjau sebelum membatalkan invoice.';
                break;
            }
        }
        foreach ($snapshot['work_orders'] as $row) {
            if (! in_array($row['status'], ['completed', 'cancelled'], true)) {
                $errors[] = 'SPK #'.$row['id'].' belum Completed/Cancelled. Catat penyelesaian pekerjaan sesuai kondisi sebenarnya.';
            }
        }
        foreach ($snapshot['delivery_orders'] as $row) {
            if (! in_array($row['status'], ['returned', 'cancelled'], true) || (int) $row['invoice_id'] !== (int) $invoice['id']) {
                $errors[] = 'DO #'.$row['id'].' belum Returned/Cancelled atau terkait invoice lain. Selesaikan Return/Check-In sesuai kondisi sebenarnya.';
            }
        }
        foreach ($snapshot['allocations'] as $row) {
            if (! in_array($row['status'], ['returned', 'released'], true)) {
                $errors[] = 'Alokasi alat #'.$row['id'].' masih aktif. Tidak ada perubahan stok yang dilakukan oleh koreksi invoice.';
            }
        }
        if (abs($this->paymentTotal($snapshot) - (float) $invoice['paid_amount']) > 0.0001) {
            $errors[] = 'Total payment tidak sesuai paid_amount invoice; perlu pemeriksaan terpisah.';
        }
        $dates = array_filter(array_merge([$invoice['issued_at'] ?? null], array_column($snapshot['payments'], 'paid_at')));
        foreach ($snapshot['period_locks'] as $period) {
            foreach ($dates as $date) {
                $date = substr($date, 0, 10);
                if ($date >= $period['starts_at'] && $date <= $period['ends_at']) {
                    $errors[] = 'Periode '.$period['period'].' terkunci. Koreksi tidak boleh mengubah angka periode yang sudah ditutup.';
                    break;
                }
            }
        }

        return array_values(array_unique($errors));
    }

    private function rows(Builder $query, bool $lock): array
    {
        $query->orderBy('id');

        return ($lock ? $query->lockForUpdate() : $query)->get()->map(fn ($row) => (array) $row)->all();
    }

    private function paymentTotal(array $snapshot): float
    {
        return round(array_sum(array_column($snapshot['payments'], 'amount')), 4);
    }

    private function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
