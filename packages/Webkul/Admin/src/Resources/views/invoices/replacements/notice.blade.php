@php
    $original = $invoice->replaced_by_invoice_id ? $invoice : $invoice->replacesInvoice;
    $replacement = $invoice->replaced_by_invoice_id ? $invoice->replacementInvoice : $invoice;
    $replacementAudit = $original->replacementRecord;
    $billingLabel = static fn ($type) => $type === 'down_payment' ? 'DP' : 'Full Payment';
@endphp
<div class="my-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-blue-900 dark:border-blue-900 dark:bg-gray-900 dark:text-blue-200">
    <p class="font-semibold">{{ $invoice->replaced_by_invoice_id ? 'Dibatalkan—Diganti Invoice Baru' : 'Invoice '.$billingLabel($replacement->billing_type).' pengganti '.$billingLabel($original->billing_type) }}</p>
    <p class="mt-2">Invoice {{ $billingLabel($original->billing_type) }} <a class="underline" href="{{ route('admin.invoices.show', $original->id) }}">{{ $original->invoice_number }}</a>
        (Rp {{ number_format($original->grand_total, 2, ',', '.') }}) → invoice {{ $billingLabel($replacement->billing_type) }} <a class="underline" href="{{ route('admin.invoices.show', $replacement->id) }}">{{ $replacement->invoice_number }}</a>
        (Rp {{ number_format($replacement->grand_total, 2, ',', '.') }}).</p>
    <p>Invoice lama bukan tagihan aktif. SPK, Surat Jalan, dan biaya historis tetap tercatat pada dokumen asal; biaya tetap dihitung dalam posisi proyek.</p>
    @if ($invoice->replaces_invoice_id && $invoice->replaced_by_invoice_id)
        <p class="mt-2">Dokumen ini sebelumnya menggantikan <a class="underline" href="{{ route('admin.invoices.show', $invoice->replaces_invoice_id) }}">{{ $invoice->replacesInvoice->invoice_number }}</a>. Buka dokumen terkait untuk riwayat perubahan sebelumnya.</p>
    @endif
    <p class="mt-2">Alasan: {{ $replacementAudit->reason }}</p>
    <p>Dicatat: {{ $replacementAudit->created_at->format('d M Y H:i') }} · {{ $replacementAudit->actor_name }}</p>
    <a class="mt-2 inline-block underline" href="{{ route('admin.proformas.show', $replacementAudit->proforma_invoice_id) }}">Buka proforma pengganti</a>
</div>
