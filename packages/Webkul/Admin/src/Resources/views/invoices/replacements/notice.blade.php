@php
    $original = $invoice->replaced_by_invoice_id ? $invoice : $invoice->replacesInvoice;
    $replacement = $invoice->replaced_by_invoice_id ? $invoice->replacementInvoice : $invoice;
    $replacementAudit = $original->replacementRecord;
@endphp
<div class="my-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-blue-900 dark:border-blue-900 dark:bg-gray-900 dark:text-blue-200">
    <p class="font-semibold">{{ $invoice->replaced_by_invoice_id ? 'Dibatalkan—Diganti Invoice Baru' : 'Invoice Full Payment pengganti DP' }}</p>
    <p class="mt-2">Invoice DP <a class="underline" href="{{ route('admin.invoices.show', $original->id) }}">{{ $original->invoice_number }}</a>
        → invoice full <a class="underline" href="{{ route('admin.invoices.show', $replacement->id) }}">{{ $replacement->invoice_number }}</a>.</p>
    <p>Invoice DP bukan tagihan aktif. SPK, Surat Jalan, dan biaya historis tetap tercatat pada dokumen asal; biaya tetap dihitung dalam posisi proyek.</p>
    <p class="mt-2">Alasan: {{ $replacementAudit->reason }}</p>
    <p>Dicatat: {{ $replacementAudit->created_at->format('d M Y H:i') }} · {{ $replacementAudit->actor_name }}</p>
    <a class="mt-2 inline-block underline" href="{{ route('admin.proformas.show', $replacementAudit->proforma_invoice_id) }}">Buka proforma pengganti</a>
</div>
