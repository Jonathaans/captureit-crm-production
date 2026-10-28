<x-admin::layouts>
    @php
        $toDp = $preview['target_type'] === 'down_payment';
        $sourceLabel = $toDp ? 'Full Payment' : 'DP';
        $targetLabel = $toDp ? 'DP' : 'Full Payment';
        $money = static fn ($value) => 'Rp '.number_format((float) $value, 2, ',', '.');
    @endphp
    <x-slot:title>Ganti Invoice: {{ $sourceLabel }} → {{ $targetLabel }}</x-slot>
    <div class="mx-auto flex max-w-3xl flex-col gap-5">
        <a class="text-brandColor underline" href="{{ route('admin.invoices.show', $preview['invoice']['id']) }}">Kembali ke invoice {{ $sourceLabel }}</a>
        <div>
            <h1 class="text-2xl font-bold dark:text-white">Ganti Invoice: {{ $sourceLabel }} → {{ $targetLabel }}</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-300">Terbitkan tagihan {{ $targetLabel }} sesuai kesepakatan. Riwayat invoice lama, status event, SPK, Surat Jalan, dan biaya yang sudah tercatat tetap disimpan.</p>
        </div>
        @if ($errors->any())
            <div role="alert" class="rounded-xl bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <div class="rounded-xl border bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-gray-500">Invoice {{ $sourceLabel }} yang diganti</dt><dd>{{ $preview['invoice']['invoice_number'] }}</dd></div>
                <div><dt class="text-gray-500">Nilai dokumen lama</dt><dd>{{ $money($preview['invoice']['grand_total']) }}</dd></div>
                <div><dt class="text-gray-500">Nilai penuh kesepakatan</dt><dd>{{ $money($preview['quote_total']) }}</dd></div>
                <div><dt class="text-gray-500">Status pengganti</dt><dd>UNPAID · uang diterima Rp0</dd></div>
                @if ($preview['amount'] !== null)
                    <div><dt class="text-gray-500">Nilai invoice {{ $targetLabel }} pengganti</dt><dd class="text-xl font-bold">{{ $money($preview['amount']) }}</dd></div>
                    <div><dt class="text-gray-500">Potongan PPh</dt><dd>{{ $money($preview['pph']) }}</dd></div>
                    <div><dt class="text-gray-500">Nilai transfer netto</dt><dd>{{ $money($preview['amount'] - $preview['pph']) }}</dd></div>
                    @if ($toDp)
                        <div><dt class="text-gray-500">Sisa kesepakatan untuk pelunasan</dt><dd>{{ $money($preview['quote_total'] - $preview['amount']) }}</dd></div>
                    @endif
                @endif
            </dl>
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">Invoice lama menjadi VOID saat invoice pengganti berhasil diterbitkan. Financial Report dan CSV menghitung nilai invoice {{ $targetLabel }} yang aktif. Riwayat dan biaya aktual proyek tetap tersimpan. Jika proses gagal, invoice lama tetap aktif.</p>
            @if ($toDp)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Sisa kesepakatan belum menjadi tagihan aktif. Setelah DP lunas, terbitkan proforma pelunasan melalui alur penagihan biasa.</p>
            @endif
        </div>
        @if ($preview['replacement'])
            <a class="primary-button" href="{{ route('admin.invoices.show', $preview['replacement']) }}">Buka invoice pengganti</a>
        @elseif ($preview['blockers'])
            <div role="alert" class="rounded-xl bg-red-50 p-5 text-red-800"><p class="font-semibold">Invoice belum dapat diganti</p><ul class="mt-2 list-disc pl-5">@foreach ($preview['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach</ul></div>
        @else
            @if ($toDp)
                <form method="GET" action="{{ route('admin.invoices.replacements.preview', $preview['invoice']['id']) }}" class="rounded-xl border bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                    <label class="block">Transfer netto DP (Rp)<input type="number" name="dp_amount" min="0.01" step="0.01" required value="{{ old('dp_amount', $preview['dp_amount']) }}" class="mt-2 block w-full rounded border p-3 dark:bg-gray-950"></label>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Isi nominal yang perlu ditransfer customer untuk DP. PPh dialokasikan otomatis sesuai kesepakatan. Hitung dan periksa nominal sebelum konfirmasi.</p>
                    @if ($preview['dp_error'])<p class="mt-2 text-red-700" role="alert">{{ $preview['dp_error'] }}</p>@endif
                    <button type="submit" class="secondary-button mt-4">Hitung / Perbarui Pratinjau DP</button>
                </form>
            @endif
            @if ($preview['amount'] !== null && ! $preview['dp_error'])
                <form method="POST" action="{{ route('admin.invoices.replacements.store', $preview['invoice']['id']) }}" class="rounded-xl border bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                    @csrf
                    <input type="hidden" name="expected" value="{{ $preview['fingerprint'] }}">
                    @if ($toDp)<input type="hidden" name="dp_amount" value="{{ $preview['dp_amount'] }}">@endif
                    <label class="block">Ketentuan pembayaran (TOP)<textarea name="payment_term" class="mt-2 w-full rounded border p-3 dark:bg-gray-950" rows="2" maxlength="2000" required>{{ old('payment_term', $preview['payment_term']) }}</textarea></label>
                    <label class="mt-4 block">Jatuh tempo invoice pengganti<input type="date" name="due_at" class="mt-2 block rounded border p-3 dark:bg-gray-950" min="{{ now()->toDateString() }}" value="{{ old('due_at', now()->addDays(7)->toDateString()) }}" required></label>
                    <label class="mt-4 block">Alasan penggantian<textarea name="reason" class="mt-2 w-full rounded border p-3 dark:bg-gray-950" rows="3" minlength="8" maxlength="2000" required placeholder="Jelaskan perubahan kesepakatan tagihan dengan pelanggan.">{{ old('reason') }}</textarea></label>
                    <label class="mt-4 flex items-start gap-3"><input type="checkbox" name="acknowledge" value="1" required @checked(old('acknowledge'))>Saya memastikan belum ada pembayaran diterima dan pelanggan menyetujui penggantian ke tagihan {{ $targetLabel }}.</label>
                    <button type="submit" class="primary-button mt-5">Konfirmasi Ganti ke {{ $targetLabel }}</button>
                </form>
            @endif
        @endif
    </div>
</x-admin::layouts>
