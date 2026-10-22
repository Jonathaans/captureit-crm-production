<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webkul\Admin\Repositories\CommercialRevisionRepository;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\InvoiceReplacement;
use Webkul\Invoice\Models\ProformaInvoice;
use Webkul\Quote\Models\Quote;

class InvoiceReplacementService
{
    public function __construct(
        private CommercialRevisionRepository $documents,
        private FlexibleQuoteBillingService $billing,
        private ProformaInvoiceService $proformas,
        private FinancialPeriodLockService $periods
    ) {}

    public function preview(int $id): array
    {
        $state = $this->snapshot($id);
        $invoice = collect($state['invoices'])->firstWhere('id', $id);
        [$agreement, $errors] = $this->agreement($state, $invoice);

        return [
            'invoice' => $invoice,
            'amount' => (float) ($agreement?->grand_total ?? 0),
            'pph' => (float) ($agreement?->pph_amount ?? 0),
            'payment_term' => $agreement?->payment_term,
            'blockers' => array_merge($errors, $this->blockers($state, $invoice)),
            'fingerprint' => $this->fingerprint($state),
            'replacement' => $invoice['replaced_by_invoice_id'] ?? null,
        ];
    }

    /** Void, proforma conversion, links, and audit either all commit or all roll back. */
    public function replace(int $id, array $input, int $actor): Invoice
    {
        $input['reason'] = trim((string) ($input['reason'] ?? ''));
        $data = Validator::make($input, [
            'expected' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
            'payment_term' => ['required', 'string', 'max:2000'],
            'due_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ])->validate();
        $actorName = DB::table('users')->where('id', $actor)->value('name');
        $this->ensure($actorName !== null, 'actor', 'Pengguna penggantian invoice tidak ditemukan.');

        // Use the same lock order as all proforma conversions, including MySQL numbering.
        return $this->billing->withInvoiceNumberLock(fn () => DB::transaction(function () use ($id, $data, $actor, $actorName) {
            $state = $this->snapshot($id, true);
            $existing = InvoiceReplacement::where('source_invoice_id', $id)->first();
            if ($existing) {
                $this->ensure(hash_equals($existing->preview_fingerprint, $data['expected']), 'expected', 'Invoice sudah diganti. Buka invoice penggantinya.');

                return Invoice::findOrFail($existing->replacement_invoice_id);
            }
            $this->ensure(hash_equals($this->fingerprint($state), $data['expected']), 'expected', 'Data berubah sejak pratinjau. Buka pratinjau terbaru sebelum melanjutkan.');
            $source = collect($state['invoices'])->firstWhere('id', $id);
            [$agreement, $errors] = $this->agreement($state, $source);
            $errors = array_merge($errors, $this->blockers($state, $source));
            $this->ensure($errors === [], 'replacement', implode(' ', $errors));

            $this->documents->update('invoices', $id, ['status' => 'void', 'balance_due' => 0]);
            $proforma = $this->proformas->createFromSnapshot($agreement, [
                'billing_type' => 'full_payment', 'issued_at' => now()->toDateString(),
                'due_at' => $data['due_at'], 'payment_term' => $data['payment_term'],
            ], $actor);
            $replacement = $this->billing->approveProforma($proforma, $actor, 'Pengganti '.$source['invoice_number'].': '.$data['reason']);
            $this->documents->update('invoices', $id, ['replaced_by_invoice_id' => $replacement->id]);
            $this->documents->update('invoices', $replacement->id, [
                'replaces_invoice_id' => $id, 'event_status' => $source['event_status'],
            ]);
            InvoiceReplacement::create([
                'source_invoice_id' => $id, 'replacement_invoice_id' => $replacement->id,
                'proforma_invoice_id' => $proforma->id, 'actor_id' => $actor, 'actor_name' => $actorName,
                'reason' => $data['reason'], 'preview_fingerprint' => $data['expected'], 'before' => $state, 'created_at' => now(),
            ]);

            return $replacement->fresh();
        }, 3));
    }

    private function snapshot(int $id, bool $lock = false): array
    {
        $source = Invoice::findOrFail($id);
        $this->ensure((bool) $source->quote_id, 'replacement', 'Invoice harus terhubung ke quotation.');
        $state = $this->documents->snapshot($source->quote_id, $lock);
        $this->ensure(collect($state['invoices'])->contains('id', $id), 'replacement', 'Referensi invoice berubah. Buka ulang invoice.');
        $query = DB::table('financial_period_locks')->orderBy('id');
        $state['period_locks'] = ($lock ? $query->lockForUpdate() : $query)->get()->map(fn ($row) => (array) $row)->all();
        $state['replacement_date'] = now()->toDateString();

        return $state;
    }

    private function agreement(array $state, array $invoice): array
    {
        $proforma = collect($state['proformas'])->first(fn ($p) => (int) $p['invoice_id'] === (int) $invoice['id'] && $p['status'] === 'converted');
        if ($proforma) {
            $snapshot = json_decode($proforma['quote_snapshot'], true);
            if (! is_array($snapshot['attributes'] ?? null) || ! is_array($snapshot['items'] ?? null)
                || (int) ($snapshot['attributes']['id'] ?? 0) !== (int) $invoice['quote_id']
                || $proforma['billing_type'] !== 'down_payment'
                || round((float) $proforma['amount'], 2) !== round((float) $invoice['grand_total'], 2)) {
                return [null, ['Snapshot proforma DP tidak sesuai invoice dan quotation asal. Tinjau data sebelum mengganti tagihan.']];
            }
            $model = new ProformaInvoice;
            $model->setRawAttributes($proforma, true);
            $agreement = $model->snapshotQuote();
        } else {
            // Legacy DP: quotation is archive-protected; only accept a matching agreed total.
            $agreement = Quote::with(['items', 'person.organization'])->findOrFail($state['quote']['id']);
        }
        $total = round((float) $agreement->grand_total, 2);
        if ($total <= 0 || round((float) ($invoice['quote_total_snapshot'] ?? 0), 2) !== $total) {
            return [null, ['Nilai kesepakatan DP dan snapshot quotation tidak cocok. Tinjau Revisi Transaksi terlebih dahulu.']];
        }

        return [$agreement, []];
    }

    private function blockers(array $state, array $invoice): array
    {
        $errors = [];
        if ($invoice['billing_type'] !== 'down_payment' || $invoice['status'] !== 'unpaid' || $invoice['event_status'] !== 'confirm'
            || ! empty($invoice['dp_invoice_id']) || ! empty($invoice['replaced_by_invoice_id']) || ! empty($invoice['replaces_invoice_id'])) {
            $errors[] = 'Hanya invoice DP UNPAID pada event Confirm yang dapat diganti.';
        }
        $voided = array_column($state['payment_voids'], 'payment_id');
        $payments = collect($state['payments'])->where('invoice_id', $invoice['id'])->whereNotIn('id', $voided);
        if ($payments->isNotEmpty() || abs((float) $invoice['paid_amount']) > 0.0001) {
            $errors[] = 'Invoice sudah memiliki pembayaran aktif. Penggantian hanya tersedia jika belum ada uang diterima.';
        }
        $cash = max(0, (float) $invoice['grand_total'] - (float) ($invoice['pph_amount'] ?? 0));
        if ($cash <= 0 || abs($cash - (float) $invoice['balance_due']) > 0.0001) {
            $errors[] = 'Saldo invoice tidak sesuai nilai tagihan DP. Tinjau pencatatan terlebih dahulu.';
        }
        $others = collect($state['invoices'])->filter(fn ($i) => (int) $i['id'] !== (int) $invoice['id'] && $i['status'] !== 'void'
            && ! in_array($i['event_status'], ['cancel', 'cancelled', 'canceled'], true));
        if ($others->isNotEmpty() || collect($state['proformas'])->contains('status', 'issued')) {
            $errors[] = 'Ada invoice atau proforma aktif lain pada quotation ini. Tinjau rangkaian tagihannya terlebih dahulu.';
        }
        if (! $invoice['issued_at'] || $this->periods->isLocked($invoice['issued_at']) || $this->periods->isLocked(now())) {
            $errors[] = 'Tanggal terbit invoice tidak tersedia atau periode invoice lama/pengganti sudah ditutup.';
        }
        if ((float) $invoice['grand_total'] <= 0 || (float) $invoice['grand_total'] >= (float) ($invoice['quote_total_snapshot'] ?? 0)) {
            $errors[] = 'Nilai DP harus lebih kecil dari nilai penuh kesepakatan.';
        }

        return $errors;
    }

    private function fingerprint(array $state): string
    {
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    private function ensure(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
