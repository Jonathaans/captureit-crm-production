<x-admin::layouts>
    <x-slot:title>Batalkan Invoice</x-slot>
    <div class="mx-auto flex max-w-3xl flex-col gap-5">
        <a class="text-brandColor underline" href="{{ route('admin.invoices.show', $preview['invoice']['id']) }}">Kembali ke invoice</a>
        <div>
            <h1 class="text-2xl font-bold dark:text-white">Batalkan Invoice</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-300">Batalkan tagihan yang duplikat atau salah dibuat. Status event tetap mengikuti pekerjaan yang sebenarnya.</p>
        </div>
        @if ($errors->any())
            <div role="alert" class="rounded-xl bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <div class="rounded-xl border bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-gray-500">Invoice</dt><dd class="font-semibold">{{ $preview['invoice']['invoice_number'] }}</dd></div>
                <div><dt class="text-gray-500">Proyek</dt><dd>{{ $preview['invoice']['subject'] ?: '-' }}</dd></div>
                <div><dt class="text-gray-500">Nilai invoice yang dibatalkan</dt><dd class="text-xl font-bold">Rp {{ number_format((float) $preview['invoice']['grand_total'], 2, ',', '.') }}</dd></div>
                <div><dt class="text-gray-500">Sisa tagihan setelah pembatalan</dt><dd>Rp 0</dd></div>
            </dl>
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">Status menjadi VOID / DIBATALKAN. Nilainya keluar dari total tagihan, piutang, rincian invoice, dan ekspor Financial Report. Nomor dan nominal asli tetap tersimpan dalam riwayat. Biaya aktual tetap tercatat.</p>
        </div>
        @if ($preview['blockers'])
            <div role="alert" class="rounded-xl bg-red-50 p-5 text-red-800">
                <p class="font-semibold">Invoice belum dapat dibatalkan</p>
                <ul class="mt-2 list-disc pl-5">@foreach ($preview['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
            </div>
        @else
            <form method="POST" action="{{ route('admin.invoices.cancel.store', $preview['invoice']['id']) }}" class="rounded-xl border bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                @csrf
                <input type="hidden" name="expected" value="{{ $preview['fingerprint'] }}">
                <label class="block">Alasan pembatalan
                    <textarea name="reason" class="mt-2 w-full rounded border p-3 dark:bg-gray-950" rows="3" minlength="8" maxlength="2000" required placeholder="Contoh: invoice tercatat dua kali; tagihan yang benar menggunakan nomor INV ...">{{ old('reason') }}</textarea>
                </label>
                <label class="mt-4 flex items-start gap-3"><input type="checkbox" name="acknowledge" value="1" required>Saya memastikan invoice ini memang harus dibatalkan dan tidak memiliki pembayaran aktif.</label>
                <button type="submit" class="primary-button mt-5">Batalkan Invoice</button>
            </form>
        @endif
    </div>
</x-admin::layouts>
