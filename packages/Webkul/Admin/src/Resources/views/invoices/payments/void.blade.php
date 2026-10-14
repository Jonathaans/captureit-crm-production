<x-admin::layouts>
    <x-slot:title>Void Pembayaran</x-slot>
    <div class="mx-auto flex max-w-3xl flex-col gap-5">
        <a class="text-brandColor underline" href="{{ route('admin.invoices.show', $preview['invoice']['id']) }}#payment-history">Kembali ke invoice</a>
        <div>
            <h1 class="text-2xl font-bold dark:text-white">Void Pembayaran</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-300">Koreksi satu pembayaran yang salah dicatat atau duplikat. Riwayat tetap disimpan. Untuk pengembalian uang ke pelanggan, jangan gunakan aksi ini.</p>
        </div>
        @if ($errors->any())
            <div role="alert" class="rounded-xl bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <div class="rounded-xl border bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-gray-500">Invoice</dt><dd>{{ $preview['invoice']['invoice_number'] }}</dd></div>
                <div><dt class="text-gray-500">Pembayaran</dt><dd>#{{ $preview['payment']['id'] }} · {{ $preview['payment']['paid_at'] }}</dd></div>
                <div><dt class="text-gray-500">Nominal yang dibatalkan</dt><dd class="text-xl font-bold">Rp {{ number_format((float) $preview['payment']['amount'], 2, ',', '.') }}</dd></div>
                <div><dt class="text-gray-500">Referensi pembayaran</dt><dd>{{ $preview['payment']['reference_number'] ?: '-' }}</dd></div>
            </dl>
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">Cash Received berkurang pada periode tanggal pembayaran ini. Total Invoiced tetap. SPK, surat jalan, dan stok tetap menyimpan riwayat pekerjaan.</p>
        </div>
        @if ($preview['blockers'])
            <div role="alert" class="rounded-xl bg-red-50 p-5 text-red-800">
                <p class="font-semibold">Pembayaran belum dapat dibatalkan</p>
                <ul class="mt-2 list-disc pl-5">@foreach ($preview['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
            </div>
        @else
            <div class="rounded-xl border bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                <h2 class="font-bold">Posisi invoice setelah koreksi</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div><dt class="text-gray-500">Pembayaran sah</dt><dd>Rp {{ number_format($preview['after']['paid_amount'], 2, ',', '.') }}</dd></div>
                    <div><dt class="text-gray-500">Sisa tagihan</dt><dd>Rp {{ number_format($preview['after']['balance_due'], 2, ',', '.') }}</dd></div>
                    <div><dt class="text-gray-500">Status invoice</dt><dd>{{ strtoupper($preview['after']['status']) }}</dd></div>
                </dl>
            </div>
            <form method="POST" action="{{ route('admin.invoices.payments.void.store', [$preview['invoice']['id'], $preview['payment']['id']]) }}" class="rounded-xl border bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                @csrf
                <input type="hidden" name="expected" value="{{ $preview['fingerprint'] }}">
                <label class="block">Alasan koreksi
                    <textarea name="reason" class="mt-2 w-full rounded border p-3 dark:bg-gray-950" rows="3" minlength="8" maxlength="2000" required placeholder="Contoh: pembayaran tercatat dua kali untuk transfer yang sama.">{{ old('reason') }}</textarea>
                </label>
                <label class="mt-4 flex items-start gap-3"><input type="checkbox" name="acknowledge_error" value="1" required @checked(old('acknowledge_error'))>Saya memastikan pencatatan pembayaran ini salah/duplikat. Tindakan ini merupakan koreksi pencatatan, bukan pengembalian uang.</label>
                <button type="submit" class="primary-button mt-5">Void Pembayaran</button>
            </form>
        @endif
    </div>
</x-admin::layouts>
