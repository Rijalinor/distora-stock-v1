<x-filament-panels::page>
    @php
        $comparisonRows = $this->getSelisihComparisonRows();
        $foundItems = $this->getFoundItems();
        $isStockOfficer = auth()->user()?->isStockOfficer();
        $reportSearch = trim($reportSearch ?? '');
        $officerComparisonRows = $comparisonRows
            ->when($reportSearch !== '', fn ($rows) => $rows->filter(fn ($row) => str_contains(
                strtolower($row['kode_barang'] . ' ' . $row['nama_barang'] . ' ' . $row['status']),
                strtolower($reportSearch)
            )))
            ->sortBy('kode_barang', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $officerFoundItems = $foundItems
            ->when($reportSearch !== '', fn ($rows) => $rows->filter(fn ($item) => str_contains(
                strtolower($item->kode_barang . ' ' . $item->nama_barang),
                strtolower($reportSearch)
            )))
            ->sortBy('kode_barang', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    @endphp

    @if ($isStockOfficer)
        <div class="space-y-3">
            <div class="text-sm text-gray-500">
                @if ($comparisonDate)
                    Dibandingkan dengan opname terakhir
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ \Carbon\Carbon::parse($comparisonDate)->format('d M Y') }}</span>.
                @else
                    Belum ada stock opname sebelumnya untuk dibandingkan.
                @endif
            </div>

            <x-filament::input
                type="search"
                wire:model.live.debounce.300ms="reportSearch"
                placeholder="Cari kode atau nama barang..."
            />

            <div class="grid grid-cols-3 gap-2">
                <div class="rounded-lg border border-danger-200 bg-danger-50 p-3 dark:border-danger-800 dark:bg-danger-950/20">
                    <div class="text-xs text-danger-700 dark:text-danger-300">Baru / Memburuk</div>
                    <div class="mt-1 text-xl font-bold text-danger-700 dark:text-danger-300">
                        {{ $comparisonRows->whereIn('status', ['Baru Selisih', 'Selisih Memburuk'])->count() }}
                    </div>
                </div>
                <div class="rounded-lg border border-success-200 bg-success-50 p-3 dark:border-success-800 dark:bg-success-950/20">
                    <div class="text-xs text-success-700 dark:text-success-300">Membaik</div>
                    <div class="mt-1 text-xl font-bold text-success-700 dark:text-success-300">
                        {{ $comparisonRows->where('status', 'Selisih Membaik')->count() }}
                    </div>
                </div>
                <div class="rounded-lg border border-warning-200 bg-warning-50 p-3 dark:border-warning-800 dark:bg-warning-950/20">
                    <div class="text-xs text-warning-700 dark:text-warning-300">Temuan</div>
                    <div class="mt-1 text-xl font-bold text-warning-700 dark:text-warning-300">{{ $foundItems->count() }}</div>
                </div>
            </div>

            <div class="grid gap-2">
                @forelse ($officerComparisonRows as $row)
                    @php
                        $sample = $row['to_item'] ?? $row['from_item'];
                        $statusColor = match ($row['status']) {
                            'Selisih Memburuk', 'Baru Selisih' => 'danger',
                            'Selisih Membaik', 'Sudah Normal' => 'success',
                            default => 'gray',
                        };
                    @endphp
                    <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $row['kode_barang'] }}</div>
                                <div class="mt-1 break-words text-sm font-semibold leading-snug text-gray-900 dark:text-white">{{ $row['nama_barang'] }}</div>
                            </div>
                            <x-filament::badge :color="$statusColor" size="sm">{{ $row['status'] }}</x-filament::badge>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 border-t border-gray-100 pt-2.5 text-center text-[11px] dark:border-white/10">
                            <div>
                                <div class="text-gray-500">Terakhir</div>
                                <div class="mt-1 font-mono font-bold text-gray-900 dark:text-white">{{ app(\App\Services\ReportService::class)->formatBaseQty($row['from_selisih'], $sample) }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Sekarang</div>
                                <div class="mt-1 font-mono font-bold text-gray-900 dark:text-white">{{ app(\App\Services\ReportService::class)->formatBaseQty($row['to_selisih'], $sample) }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Perubahan</div>
                                <div class="mt-1 font-mono font-bold {{ $row['change'] < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">
                                    {{ app(\App\Services\ReportService::class)->formatBaseQty($row['change'], $sample) }}
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700">
                        Tidak ada perbandingan yang cocok.
                    </div>
                @endforelse
            </div>

            @if ($officerFoundItems->isNotEmpty())
                <div class="pt-2 text-sm font-bold text-gray-900 dark:text-white">Barang Temuan</div>
                <div class="grid gap-2">
                    @foreach ($officerFoundItems as $item)
                        <div class="rounded-lg border border-warning-200 bg-white p-3 dark:border-warning-800 dark:bg-gray-900">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-mono text-xs font-semibold text-warning-600 dark:text-warning-400">{{ $item->kode_barang }}</div>
                                    <div class="mt-1 break-words text-sm font-semibold text-gray-900 dark:text-white">{{ $item->nama_barang }}</div>
                                </div>
                                <div class="shrink-0 text-right font-mono text-sm font-bold text-warning-700 dark:text-warning-300">{{ $item->qty_aktual_display }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @else
    <x-filament::section>
        <x-slot name="heading">
            Perbandingan Selisih
        </x-slot>

        <x-slot name="description">
            @if ($comparisonDate)
                {{ \Carbon\Carbon::parse($comparisonDate)->format('d M Y') }} dibandingkan dengan {{ \Carbon\Carbon::parse($reportDate)->format('d M Y') }}.
            @else
                Belum ada sesi stock opname sebelumnya untuk filter ini.
            @endif
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase text-gray-500 dark:border-white/10">
                        <th class="px-3 py-2">Cabang</th>
                        <th class="px-3 py-2">Principal</th>
                        <th class="px-3 py-2">Kode</th>
                        <th class="px-3 py-2">Barang</th>
                        <th class="px-3 py-2 text-right">Selisih Pembanding</th>
                        <th class="px-3 py-2 text-right">Selisih Target</th>
                        <th class="px-3 py-2 text-right">Perubahan</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($comparisonRows->take(25) as $row)
                        @php
                            $sample = $row['to_item'] ?? $row['from_item'];
                            $statusColor = match ($row['status']) {
                                'Selisih Memburuk', 'Baru Selisih' => 'text-danger-600 dark:text-danger-400',
                                'Selisih Membaik', 'Sudah Normal' => 'text-success-600 dark:text-success-400',
                                default => 'text-gray-600 dark:text-gray-300',
                            };
                        @endphp
                        <tr>
                            <td class="px-3 py-3 text-gray-700 dark:text-gray-200">{{ $row['branch'] }}</td>
                            <td class="px-3 py-3 text-gray-700 dark:text-gray-200">{{ $row['principal'] }}</td>
                            <td class="px-3 py-3 font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $row['kode_barang'] }}</td>
                            <td class="px-3 py-3 font-medium text-gray-900 dark:text-white">{{ $row['nama_barang'] }}</td>
                            <td class="px-3 py-3 text-right font-mono">{{ app(\App\Services\ReportService::class)->formatBaseQty($row['from_selisih'], $sample) }}</td>
                            <td class="px-3 py-3 text-right font-mono">{{ app(\App\Services\ReportService::class)->formatBaseQty($row['to_selisih'], $sample) }}</td>
                            <td class="px-3 py-3 text-right font-mono font-bold {{ $row['change'] === 0 ? 'text-gray-500' : ($row['change'] < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-warning-600 dark:text-warning-400') }}">
                                {{ app(\App\Services\ReportService::class)->formatBaseQty($row['change'], $sample) }}
                            </td>
                            <td class="px-3 py-3 font-semibold {{ $statusColor }}">{{ $row['status'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-gray-500">
                                Tidak ada selisih untuk dibandingkan pada filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($comparisonRows->count() > 25)
            <div class="mt-3 text-sm text-gray-500">
                Menampilkan 25 dari {{ $comparisonRows->count() }} item. Gunakan export CSV untuk data lengkap.
            </div>
        @endif
    </x-filament::section>

    @if ($foundItems->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">
                Barang Temuan
            </x-slot>

            <x-slot name="description">
                Barang fisik yang tidak ada di sistem pada tanggal target.
            </x-slot>

            <div class="grid gap-3">
                @foreach ($foundItems->take(25) as $item)
                    <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 dark:border-warning-700 dark:bg-warning-950/20">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="font-mono text-xs font-semibold text-warning-700 dark:text-warning-300">{{ $item->kode_barang }}</div>
                                <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $item->nama_barang }}</div>
                                <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $item->stockSession?->branch?->nama ?? '-' }} &bull;
                                    {{ $item->stockSession?->principal?->nama ?? '-' }} &bull;
                                    {{ $item->foundBy?->name ?? '-' }}
                                </div>
                                @if ($item->note)
                                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $item->note }}</div>
                                @endif
                            </div>
                            <div class="font-mono text-lg font-bold text-warning-700 dark:text-warning-300">
                                +{{ $item->qty_aktual_display }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($foundItems->count() > 25)
                <div class="mt-3 text-sm text-gray-500">
                    Menampilkan 25 dari {{ $foundItems->count() }} barang temuan. Gunakan export CSV untuk data lengkap.
                </div>
            @endif
        </x-filament::section>
    @endif

    {{ $this->table }}
    @endif
</x-filament-panels::page>
