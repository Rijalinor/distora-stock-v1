<x-filament-panels::page>
    @php
        $session = $this->getSelectedSession();
        $qtyLabels = $this->getQtyLabels();
    @endphp

    <div class="mx-auto w-full max-w-4xl space-y-6 px-3 sm:px-0">
        @if (! $session)
            <x-filament::section>
                <x-slot name="heading">
                    <span class="text-2xl sm:text-3xl">Pilih Principal</span>
                </x-slot>
                <x-slot name="description">
                    <span class="text-base sm:text-lg">Pilih principal yang akan Anda kerjakan hari ini.</span>
                </x-slot>

                <div class="grid gap-4">
                    @forelse ($this->getAvailableSessions() as $availableSession)
                        @php
                            $pct = $availableSession->total_items > 0
                                ? round(($availableSession->checked_items / $availableSession->total_items) * 100)
                                : 0;
                        @endphp
                        <button
                            type="button"
                            wire:click="selectSession({{ $availableSession->id }})"
                            class="flex w-full items-center gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-5 text-left transition hover:-translate-y-0.5 hover:border-primary-500 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500 dark:hover:bg-primary-950/20 sm:p-6"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start gap-2">
                                    <span class="break-words text-lg font-semibold leading-tight text-gray-900 dark:text-white sm:text-xl">
                                        {{ $availableSession->principal->nama }}
                                    </span>
                                    @if ($availableSession->status === \App\Enums\StockSessionStatus::InProgress)
                                        <x-filament::badge color="warning" size="lg">Diproses</x-filament::badge>
                                    @endif
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-gray-500 sm:text-base">
                                    <span>{{ $availableSession->checked_items }}/{{ $availableSession->total_items }} item</span>
                                    @if ($availableSession->mismatched_items > 0)
                                        <span class="text-danger-600">{{ $availableSession->mismatched_items }} selisih</span>
                                    @endif
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div class="h-full rounded-full bg-primary-500 transition-all" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="h-6 w-6 shrink-0 text-gray-400" />
                        </button>
                    @empty
                        <x-filament::empty-state
                            heading="Tidak ada sesi"
                            description="Tidak ada sesi stock opname untuk hari ini."
                            icon="heroicon-o-clipboard-document"
                        />
                    @endforelse
                </div>
            </x-filament::section>
        @else
            @php
                $pct = $session->total_items > 0
                    ? round(($session->checked_items / $session->total_items) * 100)
                    : 0;
            @endphp

            <x-filament::section>
                <x-slot name="heading">
                    <span class="text-2xl sm:text-3xl">Scan Barcode</span>
                </x-slot>
                <x-slot name="description">
                    <span class="text-base sm:text-lg">Arahkan scanner atau ketik kode barang manual.</span>
                </x-slot>

                <div
                    x-data="{
                        stream: null,
                        detector: null,
                        scanning: false,
                        status: 'Kamera belum aktif',
                        async initCamera() {
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
                                this.status = 'Kamera aktif, arahkan ke barcode';
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
                                        this.$dispatch('barcode-detected', { value });
                                        this.stopCamera();
                                        return;
                                    }
                                }
                            } catch (error) {
                                this.status = 'Tidak bisa membaca barcode dari kamera';
                                this.stopCamera();
                                return;
                            }

                            requestAnimationFrame(() => this.scanFrame());
                        },
                        stopCamera() {
                            this.scanning = false;

                            if (this.stream) {
                                this.stream.getTracks().forEach(track => track.stop());
                                this.stream = null;
                            }

                            if (this.$refs.video) {
                                this.$refs.video.srcObject = null;
                            }

                            this.status = 'Kamera dimatikan';
                        },
                    }"
                    x-on:barcode-detected.window="$wire.scanBarcode($event.detail.value)"
                    class="space-y-5"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-filament::button type="button" size="xl" color="primary" x-on:click="initCamera()">
                            Aktifkan Kamera
                        </x-filament::button>

                        <x-filament::button type="button" size="xl" color="gray" x-on:click="stopCamera()">
                            Matikan Kamera
                        </x-filament::button>
                    </div>

                    <div class="overflow-hidden rounded-3xl border border-gray-200 bg-gray-950/90 shadow-sm dark:border-gray-700">
                        <video x-ref="video" class="aspect-[4/3] w-full bg-black object-cover sm:aspect-video" playsinline muted></video>
                        <div class="border-t border-white/10 px-4 py-3 text-sm text-gray-200 sm:text-base">
                            <span x-text="status"></span>
                        </div>
                    </div>

                    <form wire:submit="scanBarcode" class="grid gap-3 sm:grid-cols-[1fr_auto]">
                        <x-filament::input
                            type="text"
                            wire:model="barcode"
                            autofocus
                            placeholder="Ketik atau scan barcode..."
                            class="text-xl"
                        />
                        <x-filament::button type="submit" size="xl" class="w-full sm:w-auto">
                            Cari
                        </x-filament::button>
                    </form>
                </div>
            </x-filament::section>

            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Principal</div>
                        <div class="mt-1 break-words text-lg font-bold leading-tight text-gray-900 dark:text-white">
                            {{ $session->principal->nama }}
                        </div>
                        <div class="mt-1 text-sm text-gray-500">
                            {{ $session->checked_items }}/{{ $session->total_items }} item
                            &bull; {{ $session->matched_items }} sesuai
                            &bull; {{ $session->mismatched_items }} selisih
                        </div>
                    </div>

                    <x-filament::button
                        type="button"
                        color="success"
                        size="lg"
                        icon="heroicon-m-check-circle"
                        x-data
                        x-on:click.prevent="if (confirm('Yakin ingin menyelesaikan sesi ini? Item yang belum dicek tetap akan dianggap belum diperiksa.')) { $wire.completeSession() }"
                    >
                        Selesaikan Sesi
                    </x-filament::button>
                </div>

                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-full rounded-full bg-primary-500 transition-all duration-500" style="width: {{ $pct }}%"></div>
                </div>

                <div class="mt-3 flex items-center justify-between text-sm text-gray-500">
                    <span>Progress {{ $pct }}%</span>
                    <button type="button" wire:click="backToSessionList" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">
                        Ganti Principal
                    </button>
                </div>
            </div>

            @php
                $mismatchedItems = $session->items->where('status', \App\Enums\StockSessionItemStatus::Mismatched);
            @endphp
            @if ($mismatchedItems->isNotEmpty())
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="flex items-center gap-2">
                            Item Selisih
                            <x-filament::badge color="danger" size="lg">{{ $mismatchedItems->count() }}</x-filament::badge>
                        </span>
                    </x-slot>

                    <div class="grid gap-3">
                        @foreach ($mismatchedItems as $item)
                            <button
                                type="button"
                                wire:click="startEditItem({{ $item->id }})"
                                class="flex w-full items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 text-left transition hover:border-danger-400 hover:bg-danger-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-danger-500 dark:hover:bg-danger-950/10"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="break-words text-base font-semibold text-gray-900 dark:text-white sm:text-lg">{{ $item->nama_barang }}</div>
                                    <div class="text-sm text-gray-500">Sistem: {{ $item->qty_sistem_display }}</div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="font-mono text-lg font-bold text-danger-600 dark:text-danger-400">{{ $item->selisih }}</div>
                                    <div class="text-sm text-gray-500">Koreksi</div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            @php
                $pendingItems = $session->items->where('status', \App\Enums\StockSessionItemStatus::Pending);
            @endphp
            @if ($pendingItems->isNotEmpty())
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="flex items-center gap-2">
                            Belum Dicek
                            <x-filament::badge color="gray" size="lg">{{ $pendingItems->count() }}</x-filament::badge>
                        </span>
                    </x-slot>

                    <div class="grid gap-2">
                        @foreach ($pendingItems->take(15) as $item)
                            <div class="flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                                <x-filament::icon icon="heroicon-m-cube" class="h-5 w-5 shrink-0 text-gray-400" />
                                <span class="min-w-0 flex-1 break-words text-base text-gray-600 dark:text-gray-300">{{ $item->nama_barang }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if ($pendingItems->count() > 15)
                        <div class="mt-3 text-center text-sm text-gray-400">
                            + {{ $pendingItems->count() - 15 }} item lainnya
                        </div>
                    @endif
                </x-filament::section>
            @endif

            @if ($scannedItem)
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="flex flex-wrap items-center gap-2">
                            {{ $scannedItem->nama_barang }}
                            <x-filament::badge
                                :color="$scannedItem->status === \App\Enums\StockSessionItemStatus::Matched ? 'success' : ($scannedItem->status === \App\Enums\StockSessionItemStatus::Mismatched ? 'danger' : 'gray')"
                                size="lg"
                            >
                                {{ $scannedItem->status->value }}
                            </x-filament::badge>
                        </span>
                    </x-slot>
                    <x-slot name="description">{{ $scannedItem->kode_barang }}</x-slot>

                    <button
                        type="button"
                        wire:click="resetScanState"
                        class="mb-4 flex items-center gap-2 text-base font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400"
                    >
                        <x-filament::icon icon="heroicon-m-arrow-left" class="h-5 w-5" />
                        Scan barang lain
                    </button>

                    <div class="mt-2 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-4 dark:bg-amber-500/10">
                        <div class="text-sm font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-300">Qty Sistem</div>
                        <div class="mt-1 text-3xl font-black text-gray-900 dark:text-white">{{ $scannedItem->qty_sistem_display }}</div>
                    </div>

                    @if ($isEditing)
                        <div class="mt-4">
                            <x-filament::callout color="info" icon="heroicon-m-pencil-square">
                                <x-slot name="heading">Mode Edit</x-slot>
                                <x-slot name="description">Koreksi qty aktual untuk item ini.</x-slot>
                            </x-filament::callout>
                        </div>
                    @endif

                    <div class="mt-6">
                        <label class="mb-3 block text-base font-semibold text-gray-700 dark:text-gray-300">
                            Qty Aktual
                        </label>
                        <div class="grid gap-4 sm:gap-5" style="grid-template-columns: repeat({{ count($qtyLabels) }}, 1fr)">
                            @foreach ($qtyLabels as $index => $label)
                                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
                                    <label class="mb-2 block text-center text-sm font-medium text-gray-500">
                                        {{ $label }}
                                    </label>
                                    <x-filament::input
                                        type="number"
                                        min="0"
                                        wire:model="qtyLevels.{{ $index }}"
                                        class="text-center text-2xl font-bold"
                                    />
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if ($isEditing)
                        <div class="mt-4">
                            <label class="mb-2 block text-base font-semibold text-gray-700 dark:text-gray-300">
                                Alasan Koreksi
                            </label>
                            <x-filament::input
                                type="text"
                                wire:model="editReason"
                                placeholder="Alasan koreksi (opsional)"
                                class="text-lg"
                            />
                        </div>
                    @endif

                    <div @class([
                        'grid grid-cols-1 gap-3 sm:grid-cols-2',
                        'mt-4' => $isEditing,
                        'mt-6' => ! $isEditing,
                    ])>
                        @if (! $isEditing)
                            <x-filament::button
                                wire:click="markComplete"
                                color="success"
                                size="xl"
                                icon="heroicon-m-check"
                                class="w-full"
                            >
                                Lengkap
                            </x-filament::button>
                        @endif

                        <x-filament::button
                            wire:click="submitActualQty"
                            :color="$isEditing ? 'primary' : 'warning'"
                            size="xl"
                            :icon="$isEditing ? 'heroicon-m-check' : 'heroicon-m-exclamation-triangle'"
                            @class([
                                'w-full',
                                'col-span-2' => $isEditing,
                            ])
                        >
                            {{ $isEditing ? 'Simpan Koreksi' : 'Catat Selisih' }}
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
