<x-filament-panels::page>
    <div
        x-data="{
            stream: null,
            detector: null,
            scanning: false,
            status: 'Kamera belum aktif',
            async toggleCamera() {
                if (this.scanning) {
                    this.stopCamera();
                    return;
                }

                if (! navigator.mediaDevices?.getUserMedia || ! window.BarcodeDetector) {
                    this.status = 'Browser tidak mendukung scanner kamera';
                    return;
                }

                try {
                    this.detector = new BarcodeDetector({
                        formats: ['code_128', 'ean_13', 'ean_8', 'qr_code', 'code_39', 'code_93', 'itf', 'upc_a', 'upc_e'],
                    });
                    this.stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'environment' } },
                        audio: false,
                    });
                    this.$refs.video.srcObject = this.stream;
                    await this.$refs.video.play();
                    this.scanning = true;
                    this.status = 'Arahkan kamera ke barcode';
                    this.scanFrame();
                } catch (error) {
                    this.status = 'Gagal membuka kamera';
                }
            },
            async scanFrame() {
                if (! this.scanning) return;

                try {
                    const codes = await this.detector.detect(this.$refs.video);
                    const value = codes[0]?.rawValue?.trim();

                    if (value) {
                        this.stopCamera();
                        $wire.searchItem(value);
                        return;
                    }
                } catch (error) {
                    this.status = 'Tidak bisa membaca barcode';
                    this.stopCamera();
                    return;
                }

                requestAnimationFrame(() => this.scanFrame());
            },
            stopCamera() {
                this.scanning = false;
                this.stream?.getTracks().forEach(track => track.stop());
                this.stream = null;
                if (this.$refs.video) this.$refs.video.srcObject = null;
                this.status = 'Kamera dimatikan';
            },
        }"
        x-on:item-lookup-ready.window="$nextTick(() => $refs.barcodeInput?.focus())"
        class="mx-auto w-full max-w-4xl space-y-5 pb-16"
    >
        <x-filament::section>
            <x-slot name="heading">Cek Barang</x-slot>
            <x-slot name="description">Scan barcode atau ketik kode barang untuk melihat identitas barang.</x-slot>

            <div class="space-y-4">
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-black dark:border-gray-700">
                    <video x-ref="video" class="aspect-[4/3] w-full object-cover sm:aspect-video" playsinline muted></video>
                    <div class="border-t border-white/10 px-4 py-3 text-sm text-gray-200" x-text="status"></div>
                </div>

                <x-filament::button type="button" size="xl" icon="heroicon-m-camera" class="w-full" x-on:click="toggleCamera()">
                    <span x-text="scanning ? 'Matikan Kamera' : 'Aktifkan Kamera'"></span>
                </x-filament::button>

                <form wire:submit="searchItem" class="grid gap-3 sm:grid-cols-[1fr_auto]">
                    <x-filament::input
                        type="text"
                        wire:model="barcode"
                        x-ref="barcodeInput"
                        placeholder="Ketik barcode atau kode barang..."
                        class="text-xl"
                        autofocus
                    />
                    <x-filament::button type="submit" size="xl" wire:loading.attr="disabled" wire:target="searchItem">
                        Cek Barang
                    </x-filament::button>
                </form>
            </div>
        </x-filament::section>

        @foreach ($items as $item)
            <x-filament::section>
                <div class="space-y-4">
                    <div>
                        <div class="text-xl font-bold text-gray-950 dark:text-white">{{ $item['name'] }}</div>
                        <div class="mt-1 font-mono text-sm font-semibold text-primary-600 dark:text-primary-400">{{ $item['code'] }}</div>
                    </div>

                    <dl class="grid gap-3 border-t border-gray-200 pt-4 text-sm dark:border-gray-700 sm:grid-cols-2">
                        <div><dt class="text-gray-500">Barcode</dt><dd class="font-mono font-semibold">{{ $item['barcode'] ?: '-' }}</dd></div>
                        <div><dt class="text-gray-500">Principal</dt><dd class="font-semibold">{{ $item['principal'] }}</dd></div>
                        <div><dt class="text-gray-500">Cabang</dt><dd class="font-semibold">{{ $item['branch'] }}</dd></div>
                        <div><dt class="text-gray-500">Satuan</dt><dd class="font-semibold">{{ $item['unit'] }}</dd></div>
                        @if ($item['structure'])
                            <div class="sm:col-span-2"><dt class="text-gray-500">Struktur Isi</dt><dd class="font-semibold">{{ $item['structure'] }}</dd></div>
                        @endif
                    </dl>

                    <div class="rounded-xl border border-gray-700 bg-gray-950 p-4">
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Kalkulator PCS
                        </label>
                        <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                            <x-filament::input
                                type="number"
                                min="0"
                                wire:model.live.debounce.300ms="calculatorPcs.{{ $item['id'] }}"
                                placeholder="Input total PCS..."
                                class="text-xl"
                            />
                            <x-filament::input
                                type="text"
                                value="{{ $calculatorResults[$item['id']] ?? '0 ' . ($item['qty_labels'][array_key_last($item['qty_labels'])] ?? 'PCS') }}"
                                readonly
                                class="text-lg font-bold"
                            />
                        </div>
                    </div>
                </div>
            </x-filament::section>
        @endforeach

        @if ($searched && empty($items))
            <x-filament::empty-state
                heading="Barang tidak ditemukan"
                description="Barcode atau kode barang tidak terdaftar di Item Master cabang Anda."
                icon="heroicon-o-magnifying-glass"
            />
        @endif

        @if ($searched)
            <x-filament::button type="button" color="gray" icon="heroicon-m-arrow-path" class="w-full" wire:click="resetLookup">
                Cek Barang Lain
            </x-filament::button>
        @endif
    </div>
</x-filament-panels::page>
