<x-filament::section>
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

                const prepareInstall = () => {
                    this.deferredPrompt = window.distoraInstallPrompt;
                    this.canInstall = true;
                    this.installStatus = 'PWA siap dipasang';
                };

                if (window.distoraInstallPrompt) {
                    prepareInstall();
                }

                window.addEventListener('distora-pwa-installable', prepareInstall);

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
        class="distora-dashboard-hero"
    >
        <div class="distora-dashboard-hero__content">
            <div class="distora-dashboard-hero__eyebrow">
                Stock Opname
            </div>

            <h2 class="distora-dashboard-hero__title">
                Mulai scan barcode barang
            </h2>

            <p class="distora-dashboard-hero__copy">
                Pilih sesi yang ditugaskan, scan barcode, lalu input qty aktual sesuai hasil hitung fisik.
            </p>
        </div>

        <div class="distora-dashboard-hero__actions">
            <x-filament::button
                tag="a"
                href="{{ $url }}"
                size="xl"
                icon="heroicon-m-qr-code"
            >
                Buka Scan
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ url('/admin/reports') }}"
                color="gray"
                icon="heroicon-m-document-chart-bar"
            >
                Laporan
            </x-filament::button>

            @if (auth()->user()?->isAdmin())
                <x-filament::button
                    tag="a"
                    href="{{ url('/admin/stock-sessions') }}"
                    color="gray"
                    icon="heroicon-m-clipboard-document-list"
                >
                    Sesi
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    href="{{ url('/admin/csv-uploads') }}"
                    color="gray"
                    icon="heroicon-m-arrow-up-tray"
                >
                    Upload CSV
                </x-filament::button>
            @endif
        </div>

        <div class="distora-dashboard-pwa">
            <div class="distora-dashboard-pwa__status" x-text="installStatus"></div>

            <button
                type="button"
                x-on:click="install()"
                class="distora-dashboard-pwa__button"
                :class="canInstall ? 'is-ready' : ''"
            >
                Pasang PWA
            </button>

            <div class="distora-dashboard-pwa__hint" x-show="triedAutoInstall">
                Kalau browser belum memberi prompt, buka menu Chrome lalu pilih <strong>Install app</strong> atau <strong>Install Distora Stock</strong>.
            </div>
        </div>
    </div>
</x-filament::section>
