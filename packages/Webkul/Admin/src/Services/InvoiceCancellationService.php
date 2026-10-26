<?php

namespace Webkul\Admin\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Cancel duplicate billing without cancelling the event or erasing actual costs. */
class InvoiceCancellationService
{
    public function preview(int $id): array
    {
        $snapshot = $this->snapshot($id);

        return [
            'invoice' => $snapshot['invoice'],
            'blockers' => $this->blockers($snapshot),
            'fingerprint' => $this->fingerprint($snapshot),
        ];
    }

    public function cancel(int $id, array $input, int $actorId): array
    {
        $input['reason'] = trim((string) ($input['reason'] ?? ''));
        $data = Validator::make($input, [
            'expected' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
            'acknowledge' => ['required', 'accepted'],
        ])->validate();
        if (! DB::table('users')->where('id', $actorId)->exists()) {
            throw ValidationException::withMessages(['cancellation' => 'Pengguna CRM yang membatalkan invoice tidak ditemukan.']);
        }

        return DB::transaction(function () use ($id, $data, $actorId) {
            $snapshot = $this->snapshot($id, true);
            if (! hash_equals($data['expected'], $this->fingerprint($snapshot))) {
                throw ValidationException::withMessages(['expected' => 'Data berubah. Periksa kembali halaman Batalkan Invoice sebelum melanjutkan. Belum ada perubahan.']);
            }
            if ($blockers = $this->blockers($snapshot)) {
                throw ValidationException::withMessages(['cancellation' => $blockers]);
            }

            $result = [
                'invoice_id' => $id,
                'invoice_number' => $snapshot['invoice']['invoice_number'],
                'status' => 'void',
                'cancelled_amount' => (float) $snapshot['invoice']['grand_total'],
                'paid_amount' => 0,
                'balance_due' => 0,
            ];
            $auditId = DB::table('crm_data_corrections')->insertGetId([
                'kind' => 'invoice_cancel',
                'invoice_number' => $result['invoice_number'],
                'actor_id' => $actorId,
                'reason' => $data['reason'],
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'result' => json_encode($result, JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            // The invoice, its original value and all related documents remain as history.
            // This audited transition also supports invoices whose event is already Cancel.
            DB::table('invoices')->where('id', $id)->update([
                'status' => 'void', 'paid_amount' => 0, 'balance_due' => 0, 'updated_at' => now(),
            ]);

            return ['audit_id' => $auditId] + $result;
        });
    }

    private function snapshot(int $id, bool $lock = false): array
    {
        $target = DB::table('invoices')->where('id', $id)->first();
        abort_if($target === null, 404);
        // Match proforma conversion and revision lock order: quote, then invoice.
        $quote = $target->quote_id
            ? $this->rows(DB::table('quotes')->where('id', $target->quote_id), $lock) : [];
        $invoices = $this->rows(DB::table('invoices')->where('id', $id), $lock);
        abort_if($invoices === [], 404);
        $snapshot = [
            'version' => 1,
            'invoice' => $invoices[0],
            'quote' => $quote,
        ];
        foreach (['payments', 'invoice_items', 'expenses', 'purchase_orders', 'work_orders'] as $table) {
            $snapshot[$table] = $this->rows(DB::table($table)->where('invoice_id', $id), $lock);
        }
        $snapshot['payment_voids'] = $this->rows(DB::table('payment_voids')->where('invoice_id', $id)
            ->select(['id', 'payment_id', 'actor_id', 'reason', 'created_at']), $lock);
        $snapshot['delivery_orders'] = $this->rows(DB::table('delivery_orders')->where(fn (Builder $query) => $query
            ->where('invoice_id', $id)->orWhereIn('work_order_id', array_column($snapshot['work_orders'], 'id'))), $lock);
        $snapshot['allocations'] = $this->rows(DB::table('delivery_order_inventory_allocations')
            ->whereIn('delivery_order_id', array_column($snapshot['delivery_orders'], 'id')), $lock);
        $snapshot['dependent_invoices'] = $this->rows(DB::table('invoices')->where('dp_invoice_id', $id), $lock);
        $snapshot['proformas'] = $this->rows(DB::table('proforma_invoices')->where(fn (Builder $query) => $query
            ->where('invoice_id', $id)->orWhere('dp_invoice_id', $id)), $lock);
        $snapshot['period_locks'] = $this->rows(DB::table('financial_period_locks'), $lock);
        $snapshot['corrections'] = $this->rows(DB::table('crm_data_corrections')->where('invoice_number', $target->invoice_number), $lock);

        return $snapshot;
    }

    private function blockers(array $snapshot): array
    {
        $invoice = $snapshot['invoice'];
        $errors = [];
        $voidedPayments = array_column($snapshot['payment_voids'], 'payment_id');
        $activePayments = array_filter($snapshot['payments'], fn ($payment) => ! in_array($payment['id'], $voidedPayments));
        if ($invoice['status'] !== 'unpaid' || $activePayments !== [] || abs((float) $invoice['paid_amount']) > 0.0001) {
            $errors[] = 'Hanya invoice UNPAID tanpa pembayaran aktif yang dapat dibatalkan. Periksa pembayaran; gunakan Void Pembayaran jika pencatatannya salah atau duplikat.';
        }
        if ($snapshot['corrections'] !== [] || ! empty($invoice['replaced_by_invoice_id']) || ! empty($invoice['replaces_invoice_id'])) {
            $errors[] = 'Invoice sudah dibatalkan/dikoreksi atau terkait penggantian invoice. Tinjau rangkaian dokumennya terlebih dahulu.';
        }
        foreach ($snapshot['dependent_invoices'] as $dependent) {
            if ($dependent['status'] !== 'void') {
                $errors[] = 'Invoice DP masih memiliki invoice pelunasan. Tinjau dan batalkan pelunasan terlebih dahulu.';
                break;
            }
        }
        if (collect($snapshot['proformas'])->contains('status', 'issued')) {
            $errors[] = 'Masih ada proforma aktif yang bergantung pada invoice ini. Batalkan proforma tersebut terlebih dahulu.';
        }
        foreach (['purchase_orders' => ['completed', 'cancelled', 'canceled'], 'work_orders' => ['completed', 'cancelled', 'canceled'], 'delivery_orders' => ['returned', 'cancelled', 'canceled']] as $table => $finalStatuses) {
            foreach ($snapshot[$table] as $row) {
                if (! in_array($row['status'] ?? '', $finalStatuses, true)) {
                    $errors[] = 'Masih ada PO, SPK, atau surat jalan yang belum selesai/dibatalkan. Selesaikan dokumen operasional sesuai kondisi sebenarnya terlebih dahulu.';
                    break;
                }
            }
        }
        foreach ($snapshot['allocations'] as $row) {
            if (! in_array($row['status'], ['returned', 'released'], true)) {
                $errors[] = 'Masih ada alokasi alat aktif. Selesaikan Return/Check-In sebelum membatalkan invoice.';
                break;
            }
        }
        if (! $invoice['issued_at']) {
            $errors[] = 'Tanggal terbit invoice tidak tersedia. Periksa data invoice terlebih dahulu.';
        }
        foreach ($snapshot['period_locks'] as $period) {
            $date = substr((string) $invoice['issued_at'], 0, 10);
            if ($date >= $period['starts_at'] && $date <= $period['ends_at']) {
                $errors[] = 'Periode '.$period['period'].' terkunci. Pembatalan tidak dapat mengubah laporan periode yang sudah ditutup.';
            }
        }

        return array_values(array_unique($errors));
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
