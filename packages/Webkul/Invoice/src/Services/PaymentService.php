<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\Payment;

class PaymentService
{
    /**
     * Add payment to an invoice and recalculate
     * paid amount, balance due, and payment status.
     */
    public function addPayment(
        Invoice $invoice,
        array $data
    ): Payment {
        return DB::transaction(function () use ($invoice, $data) {
            /**
             * Reload invoice and lock the row.
             *
             * Tujuannya supaya dua pembayaran yang masuk
             * bersamaan tidak merusak perhitungan balance.
             */
            $invoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($invoice->status === 'void') {
                throw ValidationException::withMessages(['invoice' => 'Invoice VOID tidak dapat menerima pembayaran.']);
            }

            /**
             * Validate payment amount.
             */
            $amount = (float) (
                $data['amount'] ?? 0
            );

            if ($amount <= 0) {
                throw new InvalidArgumentException(
                    'Payment amount must be greater than zero.'
                );
            }

            /**
             * Payment tidak boleh lebih besar
             * dari sisa tagihan.
             */
            if ($amount > (float) $invoice->balance_due) {
                throw new InvalidArgumentException(
                    'Payment amount cannot exceed invoice balance.'
                );
            }

            /**
             * =====================================================
             * CREATE PAYMENT HISTORY
             * =====================================================
             */
            $payment = Payment::create([
                'invoice_id' => $invoice->id,

                'amount' => $amount,

                'payment_method' => $data['payment_method'] ?? null,

                'reference_number' => $data['reference_number'] ?? null,

                'notes' => $data['notes'] ?? null,

                'paid_at' => $data['paid_at'] ?? now(),

                'created_by' => $data['created_by'] ?? null,
            ]);

            /**
             * =====================================================
             * RECALCULATE TOTAL PAYMENT
             * =====================================================
             *
             * Kita hitung berdasarkan payment history,
             * bukan sekadar:
             *
             * old paid_amount + payment baru
             *
             * supaya data tetap konsisten.
             */
            $paidAmount = (float) Payment::query()->notVoided()
                ->where(
                    'invoice_id',
                    $invoice->id
                )
                ->sum('amount');

            /**
             * Grand total invoice.
             */
            $grandTotal =
                $invoice->cashPayable();

            /**
             * Remaining balance.
             */
            $balanceDue = max(
                0,
                $grandTotal - $paidAmount
            );

            /**
             * =====================================================
             * PAYMENT STATUS
             * =====================================================
             *
             * Kolom:
             *
             * invoices.status
             *
             * digunakan khusus untuk PAYMENT STATUS.
             */
            if ($paidAmount <= 0) {
                $paymentStatus = 'unpaid';

            } elseif ($paidAmount < $grandTotal) {
                $paymentStatus = 'partial';

            } else {
                $paymentStatus = 'paid';
            }

            /**
             * =====================================================
             * UPDATE INVOICE
             * =====================================================
             *
             * PENTING:
             *
             * event_status TIDAK diubah di PaymentService.
             */
            $invoice->update([
                'paid_amount' => $paidAmount,

                'balance_due' => $balanceDue,

                'status' => $paymentStatus,
            ]);

            /**
             * Return payment with relationships.
             */
            return $payment->fresh([
                'invoice',
                'creator',
            ]);
        });
    }
}
