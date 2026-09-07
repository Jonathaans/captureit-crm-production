<x-admin::layouts>
    <x-slot:title>Operations Dashboard</x-slot>

    <div class="flex flex-col gap-4">
        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-bold uppercase text-gray-500">Role-Based Dashboard</div>
            <h1 class="mt-1 text-2xl font-bold">Operations Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">
                Current role: <strong>{{ $role }}</strong>
            </p>
        </div>

        <div
            style="
                display:grid;
                grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
                gap:14px;
            "
        >
            @foreach ($cards as $card)
                @if ($card['url'])
                    <a href="{{ $card['url'] }}" class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                @else
                    <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                @endif
                        <div class="text-xs font-bold uppercase text-gray-500">{{ $card['label'] }}</div>
                        <div class="mt-2 text-2xl font-bold">{{ $card['value'] }}</div>
                        <div class="mt-2 text-sm text-gray-500">{{ $card['hint'] }}</div>
                @if ($card['url'])
                    </a>
                @else
                    </div>
                @endif
            @endforeach
        </div>

        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="font-bold">Quick Controls</h2>

            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($links as $link)
                    <a href="{{ $link['url'] }}" class="secondary-button">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
        {{-- CRM_FULL_QA_BACKUP_CENTER_V1 --}}
        @isset($qa)
            @php
                $qaTone = $qa['status'] === 'pass'
                    ? 'border-green-200 bg-green-50 text-green-800'
                    : ($qa['status'] === 'warning'
                        ? 'border-amber-200 bg-amber-50 text-amber-800'
                        : 'border-red-200 bg-red-50 text-red-800');
            @endphp

            <section class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-gray-500">
                            End-to-End Quality Assurance
                        </div>
                        <h2 class="mt-1 text-xl font-bold">Full QA CRM Flow</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Pemeriksaan read-only dari lead sampai audit. QA tidak mengubah transaksi.
                        </p>
                    </div>

                    <div class="rounded-lg border px-4 py-3 text-right {{ $qaTone }}">
                        <div class="text-xs font-bold uppercase">{{ strtoupper($qa['status']) }}</div>
                        <div class="text-2xl font-bold">{{ $qa['score'] }}%</div>
                        <div class="text-xs">
                            {{ $qa['counts']['pass'] }} pass ·
                            {{ $qa['counts']['warning'] }} warning ·
                            {{ $qa['counts']['fail'] }} fail
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                    <span>Checked {{ $qa['checked_at'] }} · {{ $qa['duration_ms'] }} ms</span>
                    <a href="{{ route('admin.operations-dashboard.index', ['refresh_qa' => 1]) }}" class="secondary-button">
                        Refresh Full QA
                    </a>
                </div>

                <div
                    class="mt-4"
                    style="display:grid;grid-template-columns:repeat(auto-fit,minmax(285px,1fr));gap:12px;"
                >
                    @foreach ($qa['flow'] as $stage)
                        @php
                            $stageTone = $stage['status'] === 'pass'
                                ? 'border-green-200'
                                : ($stage['status'] === 'warning'
                                    ? 'border-amber-300'
                                    : 'border-red-300');

                            $badgeTone = $stage['status'] === 'pass'
                                ? 'bg-green-100 text-green-800'
                                : ($stage['status'] === 'warning'
                                    ? 'bg-amber-100 text-amber-800'
                                    : 'bg-red-100 text-red-800');
                        @endphp

                        <details
                            class="rounded-xl border bg-white p-4 dark:bg-gray-900 {{ $stageTone }}"
                            @if ($stage['status'] !== 'pass') open @endif
                        >
                            <summary class="cursor-pointer list-none">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-700">
                                            {{ $stage['number'] }}
                                        </div>

                                        <div>
                                            <h3 class="font-bold">{{ $stage['title'] }}</h3>
                                            <p class="mt-1 text-xs leading-5 text-gray-500">
                                                {{ $stage['description'] }}
                                            </p>
                                        </div>
                                    </div>

                                    <span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase {{ $badgeTone }}">
                                        {{ $stage['status'] }}
                                    </span>
                                </div>
                            </summary>

                            <div class="mt-3 border-t pt-3 dark:border-gray-800">
                                <div class="flex flex-col gap-2">
                                    @foreach ($stage['checks'] as $check)
                                        @php
                                            $dot = $check['status'] === 'pass'
                                                ? 'bg-green-500'
                                                : ($check['status'] === 'warning'
                                                    ? 'bg-amber-500'
                                                    : 'bg-red-500');
                                        @endphp

                                        <div class="flex gap-2 text-xs">
                                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>
                                            <div>
                                                <div class="font-semibold">{{ $check['label'] }}</div>
                                                <div class="mt-0.5 leading-5 text-gray-500">
                                                    {{ $check['detail'] }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if ($stage['url'])
                                    <a href="{{ $stage['url'] }}" class="mt-3 inline-block text-xs font-semibold text-orange-600">
                                        Buka modul →
                                    </a>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endisset

        @isset($backup)
            <section class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-gray-500">
                            Disaster Recovery
                        </div>
                        <h2 class="mt-1 text-xl font-bold">Backup CRM</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                            Satu backup mencakup seluruh tabel database dan file di storage/app,
                            termasuk PDF, bukti pembayaran, NPWP, receipt, dan attachment chat.
                        </p>
                    </div>

                    @if (\Illuminate\Support\Facades\Route::has('admin.operations-dashboard.backups.store'))
                        <form
                            method="POST"
                            action="{{ route('admin.operations-dashboard.backups.store') }}"
                            onsubmit="return confirm('Buat backup database dan seluruh file sekarang? Proses dapat memerlukan beberapa menit.');"
                        >
                            @csrf
                            <button type="submit" class="primary-button">
                                Backup Semua Data
                            </button>
                        </form>
                    @endif
                </div>

                <div
                    class="mt-4"
                    style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;"
                >
                    <div class="rounded-lg border p-4 dark:border-gray-800">
                        <div class="text-xs font-bold uppercase text-gray-500">Latest Backup</div>
                        @if ($backup['latest'])
                            <div class="mt-2 font-bold">{{ $backup['latest']['created_at'] }}</div>
                            <div class="mt-1 text-sm text-gray-500">
                                {{ $backup['latest']['age_label'] }} · {{ $backup['latest']['size_label'] }}
                            </div>
                        @else
                            <div class="mt-2 font-bold text-amber-700">Belum ada backup</div>
                        @endif
                    </div>

                    <div class="rounded-lg border p-4 dark:border-gray-800">
                        <div class="text-xs font-bold uppercase text-gray-500">Verification</div>
                        @if ($backup['latest'])
                            <div class="mt-2 font-bold {{ $backup['latest']['valid'] ? 'text-green-700' : 'text-red-700' }}">
                                {{ $backup['latest']['valid'] ? 'VALID' : 'INVALID' }}
                            </div>
                            <div class="mt-1 text-sm text-gray-500">
                                {{ $backup['latest']['message'] }}
                            </div>
                        @else
                            <div class="mt-2 text-sm text-gray-500">Menunggu backup pertama.</div>
                        @endif
                    </div>

                    <div class="rounded-lg border p-4 dark:border-gray-800">
                        <div class="text-xs font-bold uppercase text-gray-500">Retention</div>
                        <div class="mt-2 font-bold">{{ $backup['count'] }} archive</div>
                        <div class="mt-1 text-sm text-gray-500">
                            File lokal lebih lama dari {{ $backup['retention_days'] }} hari dibersihkan.
                        </div>
                    </div>

                    <div class="rounded-lg border p-4 dark:border-gray-800">
                        <div class="text-xs font-bold uppercase text-gray-500">Recovery Copy</div>
                        @if ($backup['latest'] && $backup['latest']['download_url'])
                            <a href="{{ $backup['latest']['download_url'] }}" class="secondary-button mt-2 inline-flex">
                                Download Latest Backup
                            </a>
                        @else
                            <div class="mt-2 text-sm text-gray-500">Belum tersedia.</div>
                        @endif
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-900">
                    Backup di server yang sama bukan disaster recovery. Setelah download,
                    simpan salinan terenkripsi di lokasi kedua seperti object storage, NAS, atau cloud drive.
                </div>
            </section>
        @endisset
    </div>

        {{-- CRM_PRODUCTION_OPERATIONS_V2 --}}
        @if (! empty($operationsHealth))
            <div class="mt-4 rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-orange-600">System Operations</p>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Scheduler & Queue Health</h2>
                        <p class="text-sm text-gray-500">Status aktual proses yang menjaga backup, email, dan pekerjaan background.</p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        Auto refresh saat dashboard dibuka
                    </span>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($operationsHealth as $healthItem)
                        @php
                            $healthStatus = $healthItem['status'] ?? 'unknown';
                            $healthTone = match ($healthStatus) {
                                'healthy' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                                'running' => 'border-blue-200 bg-blue-50 text-blue-800',
                                'warning', 'unknown' => 'border-amber-200 bg-amber-50 text-amber-800',
                                default => 'border-red-200 bg-red-50 text-red-800',
                            };
                        @endphp

                        <div class="rounded-xl border p-4 {{ $healthTone }}">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-semibold">{{ $healthItem['label'] }}</p>
                                <span class="rounded-full bg-white/70 px-2 py-1 text-[10px] font-bold uppercase">{{ $healthStatus }}</span>
                            </div>
                            <p class="mt-3 text-sm font-semibold">Terakhir: {{ $healthItem['last_seen_human'] ?? '-' }}</p>
                            @if (! empty($healthItem['message']))
                                <p class="mt-1 line-clamp-3 text-xs opacity-80">{{ $healthItem['message'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

</x-admin::layouts>
