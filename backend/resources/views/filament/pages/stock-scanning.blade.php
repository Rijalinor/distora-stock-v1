<x-filament-panels::page>
    @php
        $session = $this->getSelectedSession();
        $qtyLabels = $this->getQtyLabels();
    @endphp

    <div
        x-data="{
            clock: '',
            init() {
                this.updateClock();
                setInterval(() => this.updateClock(), 1000);
            },
            updateClock() {
                this.clock = new Intl.DateTimeFormat('id-ID', {
                    timeZone: 'Asia/Makassar',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                }).format(new Date()) + ' WITA';
            },
            beep(frequency = 880, duration = 120) {
                const AudioContext = window.AudioContext || window.webkitAudioContext;

                if (! AudioContext) {
                    return;
                }

                const context = new AudioContext();
                const oscillator = context.createOscillator();
                const gain = context.createGain();

                oscillator.frequency.value = frequency;
                oscillator.connect(gain);
                gain.connect(context.destination);
                gain.gain.setValueAtTime(0.08, context.currentTime);
                oscillator.start();
                oscillator.stop(context.currentTime + duration / 1000);
            },
            feedbackSuccess() {
                navigator.vibrate?.(80);
                this.beep(900, 110);
                this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
            },
            feedbackError() {
                navigator.vibrate?.([80, 60, 80]);
                this.beep(220, 180);
                this.focusBarcode();
            },
            focusBarcode() {
                this.$nextTick(() => this.$refs.barcodeInput?.focus());
            },
        }"
        x-on:stock-item-scanned.window="feedbackSuccess()"
        x-on:stock-scan-failed.window="feedbackError()"
        x-on:stock-scan-ready.window="focusBarcode()"
        class="mx-auto w-full max-w-5xl space-y-6 px-3 sm:px-0"
    >
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
                            class="flex w-full items-center gap-4 rounded-xl border border-gray-200 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary-500 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-500 dark:hover:bg-primary-950/20 sm:p-6"
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
                                    <span>{{ $availableSession->branch?->nama ?? 'Tanpa Cabang' }}</span>
                                    <span>{{ $availableSession->checked_items }}/{{ $availableSession->total_items }} item</span>
                                    @if ($availableSession->mismatched_items > 0)
                                        <span class="text-danger-600">{{ $availableSession->mismatched_items }} selisih</span>
                                    @endif
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div class="h-full rounded-full bg-gradient-to-r from-primary-500 to-emerald-500 transition-all" style="width: {{ $pct }}%"></div>
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

            <div class="flex justify-end">
                <div class="rounded-md border border-gray-200 bg-white px-2.5 py-1 font-mono text-xs font-semibold text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400" x-text="clock">
                    {{ now()->format('H:i:s') }} WITA
                </div>
            </div>

            @if (! $scannedItem)
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
                        torchOn: false,
                        torchSupported: false,
                        lastScanValue: null,
                        lastScanAt: 0,
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
                                const track = this.getVideoTrack();
                                this.torchSupported = Boolean(track?.getCapabilities?.().torch);
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
                                        const now = Date.now();

                                        if (value === this.lastScanValue && now - this.lastScanAt < 1500) {
                                            requestAnimationFrame(() => this.scanFrame());
                                            return;
                                        }

                                        this.lastScanValue = value;
                                        this.lastScanAt = now;
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
                        getVideoTrack() {
                            return this.stream?.getVideoTracks?.()[0] ?? null;
                        },
                        async toggleTorch() {
                            const track = this.getVideoTrack();

                            if (! track?.getCapabilities?.().torch) {
                                this.status = 'Lampu tidak didukung perangkat ini';
                                return;
                            }

                            try {
                                this.torchOn = ! this.torchOn;
                                await track.applyConstraints({
                                    advanced: [{ torch: this.torchOn }],
                                });
                                this.status = this.torchOn
                                    ? 'Lampu aktif, arahkan ke barcode'
                                    : 'Lampu dimatikan';
                            } catch (error) {
                                this.torchOn = false;
                                this.status = 'Gagal mengatur lampu kamera';
                            }
                        },
                        stopCamera() {
                            this.scanning = false;
                            this.torchOn = false;
                            this.torchSupported = false;

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
                    <div class="grid gap-3 sm:grid-cols-3">
                        <x-filament::button type="button" size="xl" color="primary" x-on:click="initCamera()">
                            Aktifkan Kamera
                        </x-filament::button>

                        <x-filament::button type="button" size="xl" color="warning" x-on:click="toggleTorch()">
                            Lampu
                        </x-filament::button>

                        <x-filament::button type="button" size="xl" color="gray" x-on:click="stopCamera()">
                            Matikan Kamera
                        </x-filament::button>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-950 shadow-sm ring-1 ring-black/5 dark:border-gray-700 dark:ring-white/10">
                        <video x-ref="video" class="aspect-[4/3] w-full bg-black object-cover sm:aspect-video" playsinline muted></video>
                        <div class="flex items-center gap-2 border-t border-white/10 px-4 py-3 text-sm text-gray-200 sm:text-base">
                            <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                            <span x-text="status"></span>
                        </div>
                    </div>

                    <form wire:submit="scanBarcode" class="grid gap-3 sm:grid-cols-[1fr_auto]">
                        <x-filament::input
                            type="text"
                            wire:model="barcode"
                            x-ref="barcodeInput"
                            placeholder="Ketik atau scan barcode..."
                            class="text-xl"
                        />
                        <x-filament::button type="submit" size="xl" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="scanBarcode">
                            <span wire:loading.remove wire:target="scanBarcode">Cari</span>
                            <span wire:loading wire:target="scanBarcode">Mencari...</span>
                        </x-filament::button>
                    </form>
                </div>
            </x-filament::section>
            @endif

            @if (! $scannedItem && $scanCandidates)
            <x-filament::section>
                <x-slot name="heading">
                    Pilih Kode Barang
                </x-slot>

                <x-slot name="description">
                    Barcode {{ $lastScannedBarcode }} valid untuk beberapa kode barang. Pilih kode yang sedang dihitung.
                </x-slot>

                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($scanCandidates as $candidate)
                        <button
                            type="button"
                            wire:click="chooseScanCandidate({{ $candidate['id'] }})"
                            class="flex min-h-32 w-full flex-col justify-between rounded-xl border border-gray-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary-500 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-500 dark:hover:bg-primary-950/20"
                        >
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">
                                        {{ $candidate['code'] }}
                                    </div>
                                    <x-filament::badge
                                        :color="$candidate['status'] === 'matched' ? 'success' : ($candidate['status'] === 'pending' ? 'gray' : 'warning')"
                                    >
                                        {{ $candidate['status'] }}
                                    </x-filament::badge>
                                </div>
                                <div class="mt-2 break-words text-base font-semibold text-gray-900 dark:text-white">
                                    {{ $candidate['name'] }}
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-3 text-sm text-gray-500">
                                <span>Sistem: {{ $candidate['system_qty'] }}</span>
                                <span class="font-semibold text-primary-600 dark:text-primary-400">Hitung kode ini</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            </x-filament::section>
            @endif

            @if (! $scannedItem && $recentScans)
            <x-filament::section compact>
                <x-slot name="heading">
                    Scan Terakhir
                </x-slot>

                <div class="grid gap-2">
                    @foreach ($recentScans as $scan)
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-gray-900 dark:text-white">{{ $scan['name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $scan['code'] }} &bull; {{ $scan['at'] }}</div>
                            </div>
                            <x-filament::badge
                                :color="$scan['status'] === 'matched' ? 'success' : ($scan['status'] === 'pending' ? 'gray' : 'warning')"
                            >
                                {{ $scan['status'] }}
                            </x-filament::badge>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
            @endif

            @if (! $scannedItem)
            <x-filament::section>
                <x-slot name="heading">
                    {{ $session->principal->nama }}
                </x-slot>

                <x-slot name="description">
                    {{ $session->branch?->nama ?? 'Tanpa Cabang' }}
                    &bull;
                    {{ $session->checked_items }}/{{ $session->total_items }} item
                    &bull; {{ $session->matched_items }} sesuai
                    &bull; {{ $session->mismatched_items }} selisih
                </x-slot>

                <x-slot name="headerEnd">
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
                </x-slot>

                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-full rounded-full bg-gradient-to-r from-primary-500 to-emerald-500 transition-all duration-500" style="width: {{ $pct }}%"></div>
                </div>

                <div class="mt-3 flex items-center justify-between text-sm text-gray-500">
                    <span>Progress {{ $pct }}%</span>
                    <x-filament::button
                        type="button"
                        wire:click="backToSessionList"
                        color="gray"
                        size="sm"
                        icon="heroicon-m-arrow-left"
                    >
                        Ganti Principal
                    </x-filament::button>
                </div>
            </x-filament::section>

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
                                class="flex w-full items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white px-4 py-4 text-left shadow-sm transition hover:border-danger-400 hover:bg-danger-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-danger-500 dark:hover:bg-danger-950/10"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="font-mono text-xs font-semibold text-danger-600 dark:text-danger-400">{{ $item->kode_barang }}</div>
                                    <div class="break-words text-base font-semibold text-gray-900 dark:text-white sm:text-lg">{{ $item->nama_barang }}</div>
                                    <div class="text-sm text-gray-500">Sistem: {{ $this->formatSystemQty($item) }}</div>
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
                $pendingSearch = trim($pendingSearch ?? '');
                $filteredPendingItems = $pendingSearch === ''
                    ? $pendingItems
                    : $pendingItems->filter(fn ($item) => str_contains(strtolower($item->kode_barang . ' ' . $item->nama_barang), strtolower($pendingSearch)));
            @endphp
            @if ($pendingItems->isNotEmpty())
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="flex items-center gap-2">
                            Belum Dicek
                            <x-filament::badge color="gray" size="lg">{{ $pendingItems->count() }}</x-filament::badge>
                        </span>
                    </x-slot>

                    <div class="mb-3">
                        <x-filament::input
                            type="search"
                            wire:model.live.debounce.300ms="pendingSearch"
                            placeholder="Cari kode atau nama barang..."
                        />
                    </div>

                    <div class="grid gap-2">
                        @forelse ($filteredPendingItems->take(25) as $item)
                            <div class="flex items-start gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                <x-filament::icon icon="heroicon-m-cube" class="mt-0.5 h-5 w-5 shrink-0 text-gray-400" />
                                <button
                                    type="button"
                                    wire:click="startEditItem({{ $item->id }})"
                                    class="min-w-0 flex-1 text-left"
                                >
                                    <span class="block font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $item->kode_barang }}</span>
                                    <span class="block break-words text-base text-gray-700 dark:text-gray-200">{{ $item->nama_barang }}</span>
                                    <span class="block text-sm text-gray-500">Sistem: {{ $this->formatSystemQty($item) }}</span>
                                </button>
                                <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                                    <x-filament::button
                                        type="button"
                                        size="sm"
                                        color="primary"
                                        wire:click="startEditItem({{ $item->id }})"
                                    >
                                        Edit
                                    </x-filament::button>
                                    <x-filament::button
                                        type="button"
                                        size="sm"
                                        color="gray"
                                        icon="heroicon-m-eye-slash"
                                        x-data
                                        x-on:click.prevent="if (confirm('Tandai item ini tidak ada fisik?')) { $wire.markItemMissing({{ $item->id }}) }"
                                    >
                                        Tidak Ada
                                    </x-filament::button>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700">
                                Tidak ada item yang cocok.
                            </div>
                        @endforelse
                    </div>

                    @if ($filteredPendingItems->count() > 25)
                        <div class="mt-3 text-center text-sm text-gray-400">
                            + {{ $filteredPendingItems->count() - 25 }} item lainnya
                        </div>
                    @endif
                </x-filament::section>
            @endif
            @endif

            @if ($scannedItem)
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="flex flex-wrap items-center gap-2">
                            {{ $scannedItem->nama_barang }}
                            <x-filament::badge
                                :color="match ($scannedItem->status) {
                                    \App\Enums\StockSessionItemStatus::Matched => 'success',
                                    \App\Enums\StockSessionItemStatus::Mismatched => 'danger',
                                    \App\Enums\StockSessionItemStatus::Missing => 'warning',
                                    default => 'gray',
                                }"
                                size="lg"
                            >
                                {{ $scannedItem->status === \App\Enums\StockSessionItemStatus::Missing ? 'tidak ada' : $scannedItem->status->value }}
                            </x-filament::badge>
                        </span>
                    </x-slot>

                    <x-slot name="description">
                        {{ $scannedItem->kode_barang }}
                    </x-slot>

                    <x-slot name="headerEnd">
                        <x-filament::button
                            type="button"
                            wire:click="resetScanState"
                            color="gray"
                            icon="heroicon-m-arrow-left"
                        >
                            Scan Lagi
                        </x-filament::button>
                    </x-slot>

                    <div class="space-y-5">
                        <div>
                            <x-filament::button
                                type="button"
                                wire:click="resetScanState"
                                color="gray"
                                icon="heroicon-m-arrow-left"
                            >
                                Kembali
                            </x-filament::button>
                        </div>

                        <x-filament::section compact>
                            <x-slot name="heading">Qty Sistem</x-slot>
                            <div class="text-3xl font-black text-gray-900 dark:text-white">
                                {{ $this->formatSystemQty($scannedItem) }}
                            </div>
                        </x-filament::section>

                        @if ($isEditing)
                            <x-filament::callout color="info" icon="heroicon-m-pencil-square">
                                <x-slot name="heading">Mode Edit</x-slot>
                                <x-slot name="description">Koreksi qty aktual untuk item ini.</x-slot>
                            </x-filament::callout>
                        @endif

                        <div>
                            <label class="mb-3 block text-base font-semibold text-gray-700 dark:text-gray-300">
                                Qty Aktual
                            </label>
                            <div class="grid gap-3 sm:gap-4" style="grid-template-columns: repeat({{ count($qtyLabels) }}, minmax(0, 1fr))">
                                @foreach ($qtyLabels as $index => $label)
                                    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
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
                            <div>
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
                            'grid grid-cols-1 gap-3',
                            'sm:grid-cols-3' => ! $isEditing,
                            'sm:grid-cols-1' => $isEditing,
                            'pt-2' => $isEditing,
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

                                <x-filament::button
                                    wire:click="markMissing"
                                    color="gray"
                                    size="xl"
                                    icon="heroicon-m-eye-slash"
                                    class="w-full"
                                >
                                    Tidak Ada
                                </x-filament::button>
                            @endif

                            <x-filament::button
                                wire:click="submitActualQty"
                                :color="$isEditing ? 'primary' : 'warning'"
                                size="xl"
                                :icon="$isEditing ? 'heroicon-m-check' : 'heroicon-m-exclamation-triangle'"
                                @class([
                                    'w-full',
                                    'sm:col-span-full' => $isEditing,
                                ])
                            >
                                {{ $isEditing ? 'Simpan Koreksi' : 'Catat Selisih' }}
                            </x-filament::button>
                        </div>
                    </div>
                </x-filament::section>
            @endif
        @endif

        @if ($session)
            <div
                x-data="{
                    open: false,
                    value: '',
                    keys: ['7', '8', '9', '/', '4', '5', '6', '*', '1', '2', '3', '-', '0', '.', '=', '+'],
                    press(key) {
                        if (key === '=') {
                            this.calculate();
                            return;
                        }

                        this.value += key;
                    },
                    clear() {
                        this.value = '';
                    },
                    backspace() {
                        this.value = this.value.slice(0, -1);
                    },
                    calculate() {
                        if (! /^[\d+\-*/. ()]+$/.test(this.value)) {
                            this.value = 'Error';
                            return;
                        }

                        try {
                            const result = Function(`'use strict'; return (${this.value})`)();
                            this.value = Number.isFinite(result) ? String(result) : 'Error';
                        } catch (error) {
                            this.value = 'Error';
                        }
                    },
                }"
                class="fixed bottom-5 right-4 z-40 sm:bottom-6 sm:right-6"
                wire:ignore
            >
                <button
                    x-show="! open"
                    type="button"
                    x-on:click="open = true"
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg ring-1 ring-black/10 transition hover:bg-primary-700"
                    aria-label="Buka calculator"
                >
                    <x-filament::icon icon="heroicon-m-calculator" class="h-6 w-6" />
                </button>

                <div
                    x-show="open"
                    x-transition
                    class="w-72 rounded-xl border border-gray-200 bg-white p-3 shadow-2xl ring-1 ring-black/5 dark:border-gray-700 dark:bg-gray-900 dark:ring-white/10"
                >
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                            <x-filament::icon icon="heroicon-m-calculator" class="h-5 w-5 text-primary-500" />
                            Calculator
                        </div>
                        <button
                            type="button"
                            x-on:click="open = false"
                            class="rounded-lg p-1.5 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-white/10 dark:hover:text-white"
                            aria-label="Minimize calculator"
                        >
                            <x-filament::icon icon="heroicon-m-minus" class="h-5 w-5" />
                        </button>
                    </div>

                    <input
                        type="text"
                        x-model="value"
                        inputmode="decimal"
                        class="mb-3 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-right text-2xl font-bold text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        x-on:keydown.enter.prevent="calculate()"
                    />

                    <div class="mb-2 grid grid-cols-2 gap-2">
                        <button type="button" x-on:click="clear()" class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Clear</button>
                        <button type="button" x-on:click="backspace()" class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Hapus</button>
                    </div>

                    <div class="grid grid-cols-4 gap-2">
                        <template x-for="key in keys" :key="key">
                            <button
                                type="button"
                                x-on:click="press(key)"
                                class="rounded-lg px-3 py-3 text-base font-bold shadow-sm transition"
                                :class="['/', '*', '-', '+', '='].includes(key)
                                    ? 'bg-primary-500 text-white hover:bg-primary-600'
                                    : 'bg-gray-100 text-gray-900 hover:bg-gray-200 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700'"
                                x-text="key"
                            ></button>
                        </template>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
