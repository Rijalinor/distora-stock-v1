<x-filament::section>
    <x-slot name="heading">
        Scan Barcode
    </x-slot>

    <x-slot name="description">
        Buka halaman opname dan langsung mulai scan barcode.
    </x-slot>

    <div
        x-data="{
            deferredPrompt: null,
            installStatus: 'Membuka aplikasi di browser',
            canInstall: false,
            triedAutoInstall: false,
            init() {
                if (window.matchMedia('(display-mode: standalone)').matches) {
                    this.installStatus = 'PWA sudah terpasang';
                    return;
                }

                window.addEventListener('beforeinstallprompt', (event) => {
                    event.preventDefault();
                    this.deferredPrompt = event;
                    this.canInstall = true;
                    this.installStatus = 'PWA siap dipasang';
                });

                window.addEventListener('appinstalled', () => {
                    this.deferredPrompt = null;
                    this.canInstall = false;
                    this.installStatus = 'PWA sudah dipasang';
                });
            },
            install() {
                if (!this.deferredPrompt) {
                    this.triedAutoInstall = true;
                    this.installStatus = 'Chrome belum memberi prompt install. Coba menu browser > Install app.';
                    return;
                }

                this.deferredPrompt.prompt();
                this.deferredPrompt.userChoice.finally(() => {
                    this.deferredPrompt = null;
                    this.canInstall = false;
                });
            },
        }"
        x-init="init()"
        class="space-y-4"
    >
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

        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs text-gray-500 dark:text-gray-400" x-text="installStatus"></div>

                <button
                    type="button"
                    x-on:click="install()"
                    class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition"
                    :class="canInstall
                        ? 'bg-primary-500 text-white hover:bg-primary-600'
                        : 'bg-gray-900 text-white hover:bg-gray-700 dark:bg-white/10 dark:text-white'"
                >
                    Coba Pasang PWA
                </button>
            </div>
            <div class="mt-3 text-xs text-gray-500 dark:text-gray-400" x-show="triedAutoInstall">
                Kalau browser belum memberi prompt, buka menu Chrome lalu pilih <strong>Install app</strong> atau <strong>Install Distora Stock</strong>.
            </div>
        </div>
    </div>
</x-filament::section>
