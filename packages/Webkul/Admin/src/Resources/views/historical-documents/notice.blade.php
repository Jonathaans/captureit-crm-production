@if ($record->historical_batch_id)
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <strong>Data lama</strong> · Tanggal dokumen {{ ($record->document_date ?? $record->issued_at)?->format('d M Y') }} · Diinput ke CRM {{ $record->created_at?->format('d M Y H:i') }}
    </div>
@endif
