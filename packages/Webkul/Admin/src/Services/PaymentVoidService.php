<?php

namespace Webkul\Admin\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Correct one erroneous receipt; keep the invoice and operational history. */
class PaymentVoidService
{
    public function preview(int $invoiceId, int $paymentId): array
    {
        $snapshot = $this->snapshot($invoiceId, $paymentId);

        return [
            'invoice' => $snapshot['invoice'],
            'payment' => $snapshot['payment'],
            'after' => $this->result($snapshot),
            'blockers' => $this->blockers($snapshot),
            'fingerprint' => $this->fingerprint($snapshot),
        ];
    }

    public function apply(int $invoiceId, int $paymentId, string $expected, string $reason, int $actorId, bool $isCorrection): array
    {
        if (! $isCorrection || mb_strlen(trim($reason)) < 8 || mb_strlen($reason) > 2000 || ! preg_match('/^[a-f0-9]{64}$/', $expected)) {
            throw ValidationException::withMessages(['correction' => 'Alasan, konfirmasi salah pencatatan, dan preview terbaru wajib diisi.']);
        }
        if (! DB::table('users')->where('id', $actorId)->exists()) {
            throw ValidationException::withMessages(['correction' => 'Pengguna CRM yang melakukan koreksi tidak ditemukan.']);
        }

        return DB::transaction(function () use ($invoiceId, $paymentId, $expected, $reason, $actorId) {
            // The invoice lock also serializes normal addPayment and invoice void.
            $snapshot = $this->snapshot($invoiceId, $paymentId, true);
            if (! hash_equals($expected, $this->fingerprint($snapshot))) {
                throw ValidationException::withMessages(['correction' => 'Data berubah. Muat ulang halaman Void Pembayaran dan periksa angka terbaru. Belum ada perubahan.']);
            }
            if ($blockers = $this->blockers($snapshot)) {
                throw ValidationException::withMessages(['correction' => $blockers]);
            }

            $result = $this->result($snapshot);
            $auditId = DB::table('payment_voids')->insertGetId([
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'invoice_number' => $snapshot['invoice']['invoice_number'],
                'actor_id' => $actorId,
                'reason' => trim($reason),
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'result' => json_encode($result, JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            // Audited exception to the PAID archive lock; never edit/delete receipts
            // or trigger event, SPK, DO, proforma, or inventory transitions.
            DB::table('invoices')->where('id', $invoiceId)->update([
                'paid_amount' => $result['paid_amount'],
                'balance_due' => $result['balance_due'],
                'status' => $result['status'],
                'updated_at' => now(),
            ]);

            return ['audit_id' => $auditId] + $result;
        });
    }

    private function snapshot(int $invoiceId, int $paymentId, bool $lock = false): array
    {
        $invoices = $this->rows(DB::table('invoices')->where('id', $invoiceId), $lock);
        abort_if($invoices === [], 404);
        $payments = $this->rows(DB::table('payments')->where('invoice_id', $invoiceId), $lock);
        $payment = collect($payments)->firstWhere('id', $paymentId);
        abort_if($payment === null, 404);

        return [
            'version' => 1,
            'invoice' => $invoices[0],
            'payment' => $payment,
            'payments' => $payments,
            // References, not recursively nested audit snapshots.
            'payment_voids' => $this->rows(DB::table('payment_voids')->where('invoice_id', $invoiceId)
                ->select(['id', 'payment_id', 'actor_id', 'reason', 'created_at']), $lock),
            'period_locks' => $this->rows(DB::table('financial_period_locks'), $lock),
        ];
    }

    private function blockers(array $snapshot): array
    {
        $errors = [];
        $invoice = $snapshot['invoice'];
        $payment = $snapshot['payment'];
        if (! in_array($invoice['status'], ['unpaid', 'partial', 'paid'], true)) {
            $errors[] = 'Invoice sudah dibatalkan/diarsipkan. Pembayarannya tidak dapat dikoreksi lagi.';
        }
        if (in_array($payment['id'], array_column($snapshot['payment_voids'], 'payment_id'))) {
            $errors[] = 'Pembayaran ini sudah VOID. Tidak ada pembatalan ulang.';
        }
        if ((float) $payment['amount'] <= 0 || (float) $invoice['grand_total'] <= 0
            || abs($this->activeTotal($snapshot) - (float) $invoice['paid_amount']) > 0.0001) {
            $errors[] = 'Nominal atau total pembayaran tidak cocok dengan invoice. Perlu pemeriksaan data terlebih dahulu.';
        }
        foreach ($snapshot['period_locks'] as $period) {
            foreach (array_filter([$invoice['issued_at'] ?? null, $payment['paid_at'] ?? null]) as $date) {
                $date = substr($date, 0, 10);
                if ($date >= $period['starts_at'] && $date <= $period['ends_at']) {
                    $errors[] = 'Periode '.$period['period'].' terkunci. Koreksi tidak dapat mengubah laporan periode yang sudah ditutup.';
                    break;
                }
            }
        }

        return array_values(array_unique($errors));
    }

    private function result(array $snapshot): array
    {
        $paid = round(max(0, $this->activeTotal($snapshot) - (float) $snapshot['payment']['amount']), 4);
        $balance = round(max(0, (float) $snapshot['invoice']['grand_total'] - $paid), 4);

        return [
            'invoice_id' => (int) $snapshot['invoice']['id'],
            'payment_id' => (int) $snapshot['payment']['id'],
            'amount_voided' => (float) $snapshot['payment']['amount'],
            'paid_amount' => $paid,
            'balance_due' => $balance,
            'status' => $paid <= 0 ? 'unpaid' : ($balance > 0 ? 'partial' : 'paid'),
        ];
    }

    private function activeTotal(array $snapshot): float
    {
        $voidedIds = array_column($snapshot['payment_voids'], 'payment_id');

        return round(array_sum(array_map(
            fn ($payment) => in_array($payment['id'], $voidedIds) ? 0 : (float) $payment['amount'],
            $snapshot['payments']
        )), 4);
    }

    private function rows(Builder $query, bool $lock): array
    {
        $query->orderBy('id');

        return ($lock ? $query->lockForUpdate() : $query)->get()->map(fn ($row) => (array) $row)->all();
    }

    private function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
