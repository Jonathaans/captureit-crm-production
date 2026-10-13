<x-admin::layouts>
    <x-slot:title>{{ $proforma->proforma_number }}</x-slot>
    @php($snapshot = $proforma->quote_snapshot['attributes'])
    <div class="mx-auto flex max-w-5xl flex-col gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                @if (bouncer()->hasPermission('invoices'))
                    <a class="text-brandColor underline" href="{{ route('admin.proformas.index') }}">Daftar proforma</a>
                @endif
                <h1 class="mt-3 text-2xl font-bold dark:text-white">{{ $proforma->proforma_number }}</h1>
                <p class="mt-2 text-gray-500">{{ ['issued' => 'Menunggu keputusan Admin Sales', 'converted' => 'Sudah dipindahkan ke Invoice', 'cancelled' => 'Dibatalkan'][$proforma->status] ?? $proforma->status }}</p>
            </div>
            @if (bouncer()->hasPermission('invoices.print'))
                <a class="secondary-button" target="_blank" rel="noopener" href="{{ route('admin.proformas.print', $proforma->id) }}">Cetak / Simpan PDF</a>
            @endif
        </div>
        @if ($errors->any())
            <div class="rounded-xl bg-red-50 p-4 text-red-700"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <div class="rounded-xl border bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-gray-500">Penawaran / Proyek</dt><dd>{{ $snapshot['quote_number'] ?? '-' }} · {{ $snapshot['project_code'] ?? '-' }}<br>{{ $snapshot['subject'] ?? '-' }}</dd></div>
                <div><dt class="text-gray-500">Customer / Company</dt><dd>{{ $snapshot['bill_to_person_name'] ?? '-' }}<br>{{ $snapshot['bill_to_company_name'] ?? '-' }}</dd></div>
                <div><dt class="text-gray-500">Tanggal proforma</dt><dd>{{ $proforma->issued_at->format('d/m/Y') }}</dd></div>
                <div><dt class="text-gray-500">Jatuh tempo tagihan</dt><dd>{{ $proforma->due_at->format('d/m/Y') }}</dd></div>
                <div><dt class="text-gray-500">Tanggal event</dt><dd>{{ $snapshot['event_date'] ?? '-' }}</dd></div>
                <div><dt class="text-gray-500">TOP / Ketentuan pembayaran</dt><dd class="whitespace-pre-line">{{ $snapshot['payment_term'] ?? '-' }}</dd></div>
                <div><dt class="text-gray-500">Total penawaran</dt><dd>Rp {{ number_format((float) $snapshot['grand_total'], 2, ',', '.') }}</dd></div>
                <div><dt class="text-gray-500">Proforma {{ $proforma->billingLabel() }}</dt><dd class="text-xl font-bold">Rp {{ number_format((float) $proforma->amount, 2, ',', '.') }}</dd></div>
            </dl>
            @if ($proforma->billing_type === 'down_payment')
                <p class="mt-5 text-sm text-gray-500">Sisa pelunasan: Rp {{ number_format((float) $snapshot['grand_total'] - (float) $proforma->amount, 2, ',', '.') }}. Proforma pelunasan dibuat saat event akan berjalan atau setelah event, sesuai TOP.</p>
            @endif
            <p class="mt-3 text-sm text-gray-500">Proforma belum menjadi invoice aktual. Admin Sales memindahkannya ke Invoice setelah event deal/oke atau pembayaran diterima.</p>
        </div>
        @if ($proforma->invoice)
            @if ($proforma->invoice->status === 'void')
                <p class="rounded border border-red-200 bg-red-50 p-4 text-red-800">Invoice hasil konversi sudah VOID. Catatan pembayaran sebelumnya dibatalkan sebagai salah input, tanpa uang diterima. Proforma ini disimpan sebagai riwayat.</p>
            @endif
            <div class="rounded-xl bg-green-50 p-5 text-green-800">
                Invoice {{ $proforma->billingLabel() }}: <strong>{{ $proforma->invoice->invoice_number }}</strong> · {{ strtoupper($proforma->invoice->status) }}
                @if (bouncer()->hasPermission('invoices.view'))
                    <a class="ml-3 underline" href="{{ route('admin.invoices.show', $proforma->invoice_id) }}">Buka Invoice</a>
                @endif
                @if ($proforma->billing_type === 'down_payment' && $proforma->invoice->status === 'paid' && bouncer()->hasPermission('invoices.generate'))
                    <a class="ml-3 underline" href="{{ route('admin.invoices.billing.create', ['quote_id' => $proforma->quote_id, 'billing_type' => 'settlement']) }}">Buat proforma pelunasan</a>
                @endif
                <p class="mt-3 text-sm">Alasan konversi semula: {{ $proforma->conversion_reason === 'event_confirmed' ? 'Event sudah deal / oke' : 'Pembayaran diterima' }}. Dipindahkan {{ $proforma->converted_at?->format('d/m/Y H:i') }} oleh {{ $proforma->converter?->name ?? '-' }}.</p>
                @if ($proforma->conversion_note)<p class="mt-2 text-sm">{{ $proforma->conversion_note }}</p>@endif
            </div>
        @endif
        @if ($proforma->status === 'issued' && bouncer()->hasPermission('invoices.generate'))
            <form method="POST" action="{{ route('admin.proformas.approve', $proforma->id) }}" class="rounded-xl border bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                @csrf
                <h2 class="text-lg font-bold">Pindahkan ke Invoice — event deal / oke</h2>
                <p class="mt-2 text-sm text-gray-500">Gunakan setelah event disetujui. Invoice diterbitkan dengan status UNPAID; pembayaran dicatat ketika uang diterima. SPK dapat dibuat dari invoice.</p>
                <label class="mt-4 flex items-center gap-3"><input type="checkbox" name="event_confirmed" value="1" required @checked(old('event_confirmed'))>Event sudah deal / disetujui.</label>
                <label class="mt-4 block">Catatan konfirmasi (opsional)<textarea class="mt-1 w-full rounded border p-3 dark:bg-gray-950" name="confirmation_note" maxlength="2000">{{ old('confirmation_note') }}</textarea></label>
                <button class="primary-button mt-4" type="submit">Event sudah deal — pindahkan ke Invoice</button>
            </form>
        @endif
        @if ($proforma->status === 'issued' && bouncer()->hasPermission('invoices.generate') && bouncer()->hasPermission('invoices.payment'))
            <form method="POST" action="{{ route('admin.proformas.payment', $proforma->id) }}" class="rounded-xl border bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                @csrf
                <h2 class="text-lg font-bold">Pindahkan ke Invoice — sudah dibayar</h2>
                <p class="mt-2 text-sm text-gray-500">Isi berdasarkan uang yang sudah masuk sesuai nominal proforma. Invoice PAID dan catatan pembayaran dibuat sekaligus. Untuk pembayaran bertahap, pindahkan karena event deal lalu catat penerimaan di Invoice.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label>Nominal diterima (Rp)<input class="mt-1 w-full rounded border p-3 dark:bg-gray-950" name="amount" type="number" min="0.01" step="0.01" required value="{{ old('amount', $proforma->amount) }}"></label>
                    <label>Waktu uang diterima<input class="mt-1 w-full rounded border p-3 dark:bg-gray-950" name="paid_at" type="datetime-local" required value="{{ old('paid_at', now()->format('Y-m-d\TH:i')) }}"></label>
                    <label>Metode pembayaran<input class="mt-1 w-full rounded border p-3 dark:bg-gray-950" name="payment_method" required maxlength="255" placeholder="Transfer bank" value="{{ old('payment_method') }}"></label>
                    <label>Nomor referensi transfer<input class="mt-1 w-full rounded border p-3 dark:bg-gray-950" name="reference_number" maxlength="255" value="{{ old('reference_number') }}"></label>
                </div>
                <label class="mt-4 block">Catatan<textarea class="mt-1 w-full rounded border p-3 dark:bg-gray-950" name="notes" maxlength="5000">{{ old('notes') }}</textarea></label>
                <button class="primary-button mt-4" type="submit">Pembayaran diterima — pindahkan ke Invoice</button>
            </form>
        @endif
        @if ($proforma->status === 'issued' && bouncer()->hasPermission('invoices.event-status'))
            <form method="POST" action="{{ route('admin.proformas.cancel', $proforma->id) }}" class="rounded-xl border p-5 dark:border-gray-800 dark:text-white">
                @csrf
                <label>Alasan pembatalan<textarea name="reason" required maxlength="2000" class="mt-2 w-full rounded border p-3 dark:bg-gray-950">{{ old('reason') }}</textarea></label>
                <button class="secondary-button mt-3" type="submit">Batalkan proforma</button>
            </form>
        @elseif ($proforma->status === 'cancelled')
            <p class="rounded-xl bg-gray-100 p-5 dark:bg-gray-800 dark:text-white">Dibatalkan: {{ $proforma->cancellation_reason }}</p>
        @endif
    </div>
</x-admin::layouts>
