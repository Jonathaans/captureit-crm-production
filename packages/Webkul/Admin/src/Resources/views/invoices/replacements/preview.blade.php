<x-admin::layouts>
    <x-slot:title>Ganti Invoice: DP → Full Payment</x-slot>
    <div class="mx-auto flex max-w-3xl flex-col gap-5">
        <a class="text-brandColor underline" href="{{ route('admin.invoices.show', $preview['invoice']['id']) }}">Kembali ke invoice DP</a>
        <div>
            <h1 class="text-2xl font-bold dark:text-white">Ganti Invoice: DP → Full Payment</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-300">Terbitkan tagihan penuh sesuai kesepakatan. Riwayat invoice DP, status event, SPK, Surat Jalan, dan biaya yang sudah tercatat tetap disimpan.</p>
        </div>
        @if ($errors->any())
            <div role="alert" class="rounded-xl bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <div class="rounded-xl border bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-gray-500">Invoice DP yang diganti</dt><dd>{{ $preview['invoice']['invoice_number'] }}</dd></div>
                <div><dt class="text-gray-500">Nilai dokumen DP lama</dt><dd>Rp {{ number_format($preview['invoice']['grand_total'], 2, ',', '.') }}</dd></div>
                <div><dt class="text-gray-500">Nilai invoice full pengganti</dt><dd class="text-xl font-bold">Rp {{ number_format($preview['amount'], 2, ',', '.') }}</dd></div>
                <div><dt class="text-gray-500">Status pengganti</dt><dd>UNPAID · uang diterima Rp0</dd></div>
                @if ($preview['pph'] > 0)
                    <div><dt class="text-gray-500">Potongan PPh</dt><dd>Rp {{ number_format($preview['pph'], 2, ',', '.') }}</dd></div>
                    <div><dt class="text-gray-500">Nilai transfer netto</dt><dd>Rp {{ number_format($preview['amount'] - $preview['pph'], 2, ',', '.') }}</dd></div>
                @endif
            </dl>
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">DP yang dibatalkan tidak mengurangi tagihan penuh. Invoice DP menjadi “Dibatalkan—Diganti Invoice Baru” saat invoice pengganti berhasil diterbitkan melalui proforma. Jika proses gagal, invoice DP tetap aktif.</p>
        </div>
        @if ($preview['replacement'])
            <a class="primary-button" href="{{ route('admin.invoices.show', $preview['replacement']) }}">Buka invoice pengganti</a>
        @elseif ($preview['blockers'])
            <div role="alert" class="rounded-xl bg-red-50 p-5 text-red-800"><p class="font-semibold">Invoice belum dapat diganti</p><ul class="mt-2 list-disc pl-5">@foreach ($preview['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach</ul></div>
        @else
            <form method="POST" action="{{ route('admin.invoices.replacements.store', $preview['invoice']['id']) }}" class="rounded-xl border bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                @csrf
                <input type="hidden" name="expected" value="{{ $preview['fingerprint'] }}">
                <label class="block">Ketentuan pembayaran (TOP)<textarea name="payment_term" class="mt-2 w-full rounded border p-3 dark:bg-gray-950" rows="2" maxlength="2000" required>{{ old('payment_term', $preview['payment_term']) }}</textarea></label>
                <label class="mt-4 block">Jatuh tempo invoice pengganti<input type="date" name="due_at" class="mt-2 block rounded border p-3 dark:bg-gray-950" min="{{ now()->toDateString() }}" value="{{ old('due_at', now()->addDays(7)->toDateString()) }}" required></label>
                <label class="mt-4 block">Alasan penggantian<textarea name="reason" class="mt-2 w-full rounded border p-3 dark:bg-gray-950" rows="3" minlength="8" maxlength="2000" required placeholder="Contoh: pelanggan meminta tagihan penuh setelah event selesai; DP belum dibayar.">{{ old('reason') }}</textarea></label>
                <label class="mt-4 flex items-start gap-3"><input type="checkbox" name="acknowledge" value="1" required @checked(old('acknowledge'))>Saya memastikan belum ada pembayaran diterima dan pelanggan menyetujui penggantian ke tagihan penuh.</label>
                <button type="submit" class="primary-button mt-5">Konfirmasi Ganti ke Full Payment</button>
            </form>
        @endif
    </div>
</x-admin::layouts>
