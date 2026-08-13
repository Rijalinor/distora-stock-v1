@if ($error)
    <div class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm font-semibold text-danger-700 dark:border-danger-700 dark:bg-danger-950/30 dark:text-danger-300">
        {{ $error }}
    </div>
@elseif ($preview)
    <div class="space-y-4">
        @if ($createOnly ?? false)
            <div class="rounded-lg border border-info-200 bg-info-50 px-3 py-2 text-xs font-semibold text-info-700 dark:border-info-800 dark:bg-info-950/30 dark:text-info-300">
                Mode tambah item baru saja: item yang sudah ada akan dilewati dan tidak ditimpa.
            </div>
        @endif

        <div class="grid grid-cols-3 gap-2">
            <div class="rounded-lg border border-success-200 bg-success-50 p-3 text-center dark:border-success-800 dark:bg-success-950/30">
                <div class="text-2xl font-bold text-success-700 dark:text-success-300">{{ $preview['created'] }}</div>
                <div class="text-xs font-semibold text-success-700 dark:text-success-300">Item baru</div>
            </div>
            <div class="rounded-lg border border-warning-200 bg-warning-50 p-3 text-center dark:border-warning-800 dark:bg-warning-950/30">
                <div class="text-2xl font-bold text-warning-700 dark:text-warning-300">{{ $preview['updated'] }}</div>
                <div class="text-xs font-semibold text-warning-700 dark:text-warning-300">Diperbarui</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-center dark:border-gray-700 dark:bg-white/5">
                <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $preview['skipped'] }}</div>
                <div class="text-xs font-semibold text-gray-600 dark:text-gray-400">Dilewati</div>
            </div>
        </div>

        @if ($preview['examples'])
            <div>
                <div class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Contoh perubahan</div>
                <div class="divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                    @foreach ($preview['examples'] as $item)
                        <div class="flex items-start justify-between gap-3 px-3 py-2.5">
                            <div class="min-w-0">
                                <div class="font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $item['branch'] }} / {{ $item['code'] }}</div>
                                <div class="mt-0.5 break-words text-sm font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</div>
                            </div>
                            <x-filament::badge :color="$item['action'] === 'Baru' ? 'success' : 'warning'">
                                {{ $item['action'] }}
                            </x-filament::badge>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="text-xs leading-relaxed text-gray-500">
            Item lain yang tidak terdapat dalam file tidak akan dihapus.
            @if ($createOnly ?? false)
                Item yang sudah ada juga tidak akan diubah.
            @endif
        </div>
    </div>
@endif
