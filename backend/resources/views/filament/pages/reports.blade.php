<x-filament-panels::page>
    @php
        $comparisonRows = $this->getSelisihComparisonRows();
        $foundItems = $this->getFoundItems();
    @endphp

    <x-filament::section>
        <x-slot name="heading">
            Perbandingan Selisih
        </x-slot>

        <x-slot name="description">
            {{ \Carbon\Carbon::parse($comparisonDate)->format('d M Y') }} dibandingkan dengan {{ \Carbon\Carbon::parse($reportDate)->format('d M Y') }}.
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
</x-filament-panels::page>
