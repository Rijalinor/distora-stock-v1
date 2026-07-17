<x-filament::section>
    <x-slot name="heading">
        Scan Barcode
    </x-slot>

    <x-slot name="description">
        Buka halaman opname dan langsung mulai scan barcode.
    </x-slot>

    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Menu utama untuk petugas stock opname.
            </div>
        </div>

        <x-filament::button
            tag="a"
            href="{{ $url }}"
            size="lg"
            icon="heroicon-m-qr-code"
        >
            Buka Scan
        </x-filament::button>
    </div>
</x-filament::section>
