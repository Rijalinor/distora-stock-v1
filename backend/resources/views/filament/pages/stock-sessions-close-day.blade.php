<div class="space-y-4">
    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="text-sm text-gray-500 dark:text-gray-400">Tanggal</div>
        <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ \Illuminate\Support\Carbon::parse($summary['date'])->format('d M Y') }}
        </div>
        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            Total sesi: {{ $summary['total_sessions'] }} |
            Aktif: {{ $summary['active_sessions'] }} |
            Selesai: {{ $summary['completed_sessions'] }}
        </div>
    </div>

    <div>
        <div class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
            Principal yang belum selesai hari ini
        </div>

        <div class="space-y-2">
            @forelse ($summary['pending_principals'] as $pending)
                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="break-words font-semibold text-gray-900 dark:text-white">
                                {{ $pending['principal'] }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Status: {{ $pending['status'] }} |
                                Dicek: {{ $pending['checked_items'] }}/{{ $pending['total_items'] }} |
                                Selisih: {{ $pending['mismatched_items'] }}
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/30 dark:text-green-300">
                    Tidak ada sesi aktif hari ini.
                </div>
            @endforelse
        </div>
    </div>
</div>
