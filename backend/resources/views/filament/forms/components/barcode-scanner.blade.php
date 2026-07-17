<div
    x-data="{
        stream: null,
        detector: null,
        scanning: false,
        visible: false,
        status: 'Kamera belum aktif',
        async openScanner() {
            this.visible = true;

            if (! navigator.mediaDevices?.getUserMedia) {
                this.status = 'Browser tidak mendukung kamera';
                return;
            }

            if (! window.BarcodeDetector) {
                this.status = 'BarcodeDetector tidak didukung browser ini';
                return;
            }

            try {
                this.detector = new BarcodeDetector({
                    formats: ['code_128', 'ean_13', 'ean_8', 'qr_code', 'code_39', 'code_93', 'itf', 'upc_a', 'upc_e'],
                });
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' },
                    },
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
            if (! this.scanning || ! this.detector || ! this.$refs.video) {
                return;
            }

            try {
                const codes = await this.detector.detect(this.$refs.video);

                if (codes.length > 0) {
                    const value = codes[0].rawValue?.trim();

                    if (value) {
                        $wire.set('data.barcode', value);
                        this.status = `Barcode terbaca: ${value}`;
                        this.stopScanner();
                        return;
                    }
                }
            } catch (error) {
                this.status = 'Tidak bisa membaca barcode';
                this.stopScanner();
                return;
            }

            requestAnimationFrame(() => this.scanFrame());
        },
        stopScanner() {
            this.scanning = false;

            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
                this.stream = null;
            }

            if (this.$refs.video) {
                this.$refs.video.srcObject = null;
            }
        },
    }"
    x-on:item-master-open-barcode-scanner.window="openScanner()"
    x-on:keydown.escape.window="if (visible) { stopScanner(); visible = false; }"
    class="mt-3 space-y-3"
>
    <div
        x-show="visible"
        x-cloak
        class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-950/90 shadow-sm dark:border-gray-700"
    >
        <div class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-3 text-sm text-gray-200">
            <span class="font-semibold">Scanner Barcode</span>
            <button
                type="button"
                class="rounded-lg bg-white/10 px-3 py-1 text-sm font-semibold hover:bg-white/20"
                x-on:click="stopScanner(); visible = false;"
            >
                Tutup
            </button>
        </div>

        <video x-ref="video" class="aspect-[4/3] w-full bg-black object-cover" playsinline muted></video>

        <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-200" x-text="status"></p>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500"
                    x-on:click="openScanner()"
                >
                    Buka Kamera
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-600"
                    x-on:click="stopScanner()"
                >
                    Matikan Kamera
                </button>
            </div>
        </div>
    </div>
</div>
