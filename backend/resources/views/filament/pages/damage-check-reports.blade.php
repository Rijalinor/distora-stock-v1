<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $checkRows = $this->getCheckRows();
        $totalCheckRows = $this->getTotalCheckRows();
    @endphp

    <div class="mx-auto w-full max-w-7xl space-y-4 pb-16 sm:space-y-6">
        <div x-data="{ filtersOpen: window.innerWidth >= 640 }">
        <x-filament::section>
            <div class="flex items-center justify-between gap-2">
                <div>
                    <div class="font-semibold">Filter Laporan</div>
                    <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}</div>
                </div>
                <div class="flex gap-2">
                    <x-filament::button type="button" size="sm" color="gray" icon="heroicon-m-funnel" x-on:click="filtersOpen = ! filtersOpen">
                        <span x-text="filtersOpen ? 'Tutup' : 'Filter'"></span>
                    </x-filament::button>
                    <x-filament::button type="button" size="sm" color="success" icon="heroicon-m-arrow-down-tray" wire:click="exportCsv">CSV</x-filament::button>
                </div>
            </div>

            <form wire:submit="applyFilters" x-show="filtersOpen" x-cloak class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div class="grid gap-3 md:contents" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                <div>
                    <label class="mb-1 block text-xs font-semibold">Dari tanggal</label>
                    <x-filament::input type="date" wire:model="dateFrom" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold">Sampai tanggal</label>
                    <x-filament::input type="date" wire:model="dateTo" />
                </div>
                </div>
                @if (auth()->user()->isCentralAdmin())
                    <div>
                        <label class="mb-1 block text-xs font-semibold">Cabang</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="branchId">
                                <option value="">Semua cabang</option>
                                @foreach ($this->getBranches() as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->nama }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                @endif
                <div>
                    <label class="mb-1 block text-xs font-semibold">Principal</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model="principalId">
                            <option value="">Semua principal</option>
                            @foreach ($this->getPrincipals() as $principal)
                                <option value="{{ $principal->id }}">{{ $principal->nama }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold">Status</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model="status">
                            <option value="">Semua status</option>
                            <option value="open">Berjalan</option>
                            <option value="completed">Selesai</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
                <div class="xl:col-span-5">
                    <x-filament::button type="submit" icon="heroicon-m-check" class="w-full sm:w-auto">Terapkan Filter</x-filament::button>
                </div>
                @error('dateTo') <div class="text-sm text-danger-600 xl:col-span-5">{{ $message }}</div> @enderror
            </form>
        </x-filament::section>
        </div>

        <div class="grid" style="grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px;">
            <div class="rounded-xl border border-gray-200 bg-white p-3 text-center dark:border-gray-700 dark:bg-gray-900 sm:p-5">
                <div class="text-xs text-gray-500 sm:text-sm">Pemeriksaan</div><div class="mt-1 text-2xl font-bold sm:text-3xl">{{ number_format($summary['checks']) }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-3 text-center dark:border-gray-700 dark:bg-gray-900 sm:p-5">
                <div class="text-xs text-gray-500 sm:text-sm">Jenis Barang</div><div class="mt-1 text-2xl font-bold sm:text-3xl">{{ number_format($summary['items']) }}</div>
            </div>
            <div class="rounded-xl border border-danger-200 bg-danger-50 p-3 text-center dark:border-danger-900 dark:bg-danger-950/20 sm:p-5">
                <div class="text-xs text-danger-600 sm:text-sm">Total Rusak</div><div class="mt-1 text-xl font-bold text-danger-700 dark:text-danger-400 sm:text-3xl">{{ number_format($summary['pieces']) }} <span class="text-xs">PCS</span></div>
            </div>
        </div>

        <x-filament::section>
            <x-slot name="heading">Rekap per Pemeriksaan</x-slot>
            <x-slot name="description">Klik salah satu header untuk mengunduh CSV pemeriksaan tersebut.</x-slot>

            <div class="grid gap-3 lg:grid-cols-2">
                @forelse ($checkRows as $row)
                    <button type="button" wire:click="exportCheckCsv({{ $row->id }})" wire:loading.attr="disabled" class="w-full rounded-xl border border-gray-200 p-4 text-left transition hover:border-primary-500 hover:bg-primary-50/50 disabled:opacity-60 dark:border-gray-700 dark:hover:border-primary-500 dark:hover:bg-primary-950/10">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="break-words font-mono text-base font-bold text-primary-600 dark:text-primary-400">{{ $row->reference_number }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ $row->check_date->format('d M Y') }} · {{ $row->branch->nama }}</div>
                            </div>
                            <x-filament::icon icon="heroicon-m-arrow-down-tray" class="h-5 w-5 shrink-0 text-success-600" />
                        </div>

                        <div class="mt-3 grid gap-2 text-xs" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                            <div><div class="text-gray-500">Lokasi</div><div class="break-words font-semibold">{{ $row->location }}</div></div>
                            <div><div class="text-gray-500">Pembuat</div><div class="font-semibold">{{ $row->officer->name }}</div></div>
                            <div><div class="text-gray-500">Principal</div><div class="break-words font-semibold">{{ $row->principal?->nama ?? 'Semua principal' }}</div></div>
                            <div><div class="text-gray-500">Checker</div><div class="break-words font-semibold">{{ $row->checkers->pluck('name')->implode(', ') ?: '-' }}</div></div>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                            <x-filament::badge :color="$row->status === \App\Enums\DamageCheckStatus::Completed ? 'success' : 'warning'">{{ $row->status === \App\Enums\DamageCheckStatus::Completed ? 'Selesai' : 'Berjalan' }}</x-filament::badge>
                            <div class="text-right"><span class="font-bold">{{ $row->items_count }} jenis</span><span class="mx-1 text-gray-400">·</span><span class="font-bold text-danger-600">{{ $row->items_sum_qty_rusak_base ?? 0 }} PCS</span></div>
                        </div>
                    </button>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-gray-500 dark:border-gray-700 lg:col-span-2">Tidak ada pemeriksaan pada filter ini.</div>
                @endforelse
            </div>

            @if ($totalCheckRows > $checkRows->count())
                <div class="mt-4"><x-filament::button color="gray" class="w-full sm:w-auto" wire:click="loadMore">Tampilkan Pemeriksaan Lainnya</x-filament::button></div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>

@push('styles')
    <style>
        .fi-header-heading {
            width: 100%;
            text-align: center;
        }
    </style>
@endpush
