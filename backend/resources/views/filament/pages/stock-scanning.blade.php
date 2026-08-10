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
            candidatesFlash: false,
            foundItemFlash: false,
            focusCandidates() {
                navigator.vibrate?.(80);
                this.beep(700, 110);
                this.candidatesFlash = true;
                this.$nextTick(() => this.$refs.scanCandidates?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
                setTimeout(() => this.candidatesFlash = false, 900);
            },
            focusFoundItem() {
                navigator.vibrate?.([80, 60, 80]);
                this.beep(520, 130);
                this.foundItemFlash = true;
                this.$nextTick(() => this.$refs.foundItemPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
                setTimeout(() => this.foundItemFlash = false, 900);
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
        x-on:stock-scan-candidates.window="focusCandidates()"
        x-on:stock-found-item-ready.window="focusFoundItem()"
        x-on:stock-scan-failed.window="feedbackError()"
        x-on:stock-scan-ready.window="focusBarcode()"
        class="distora-scan-page mx-auto w-full max-w-5xl space-y-3 px-0 pb-16 sm:space-y-5 sm:px-0 sm:pb-20"
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
                                    <span>{{ $availableSession->session_date->format('d M Y') }}</span>
                                    @if ($availableSession->session_date->isToday())
                                        <x-filament::badge color='success'>Hari ini</x-filament::badge>
                                    @else
                                        <x-filament::badge color='warning'>Lanjutan</x-filament::badge>
                                    @endif
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

            @if (! $scannedItem && ! $scanCandidates && $notFoundBarcode === null)
            <x-filament::section>
                <x-slot name="heading">
                    <span class="text-2xl sm:text-3xl">Scan Barcode</span>
                </x-slot>
                <x-slot name="description">
                    <span class="text-base sm:text-lg">Arahkan scanner atau cari kode/nama barang manual.</span>
                </x-slot>

                @if ($session->principal?->separate_ctn_pcs_count)
                    <div class="mb-4 grid grid-cols-2 gap-2 rounded-xl bg-gray-100 p-1 text-sm font-semibold dark:bg-gray-800">
                        <button type="button" wire:click="setSeparateCountMode('ctn')" @class([
                            'rounded-lg px-3 py-2',
                            'bg-primary-600 text-white' => $separateCountMode === 'ctn',
                            'text-gray-600 dark:text-gray-300' => $separateCountMode !== 'ctn',
                        ])>
                            Mode CTN
                        </button>
                        <button type="button" wire:click="setSeparateCountMode('pcs')" @class([
                            'rounded-lg px-3 py-2',
                            'bg-primary-600 text-white' => $separateCountMode === 'pcs',
                            'text-gray-600 dark:text-gray-300' => $separateCountMode !== 'pcs',
                        ])>
                            Mode PCS
                        </button>
                    </div>
                @endif

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
                                    formats: ['code_128', 'ean_13', 'ean_8', 'code_39', 'code_93', 'itf', 'upc_a', 'upc_e'],
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
                        toggleCamera() {
                            if (this.scanning) {
                                this.stopCamera();
                                return;
                            }

                            this.initCamera();
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
                    x-on:barcode-detected.window="$wire.scanBarcode($event.detail.value, true)"
                    class="space-y-5"
                >
                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-950 shadow-sm ring-1 ring-black/5 dark:border-gray-700 dark:ring-white/10">
                        <video x-ref="video" class="aspect-[4/3] w-full bg-black object-cover sm:aspect-video" playsinline muted></video>
                        <div class="flex items-center gap-2 border-t border-white/10 px-4 py-3 text-sm text-gray-200 sm:text-base">
                            <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                            <span x-text="status"></span>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-filament::button
                            type="button"
                            size="xl"
                            color="primary"
                            icon="heroicon-m-camera"
                            x-on:click="toggleCamera()"
                        >
                            <span x-text="scanning ? 'Matikan Kamera' : 'Aktifkan Kamera'"></span>
                        </x-filament::button>

                        <x-filament::button type="button" size="xl" color="warning" icon="heroicon-m-light-bulb" x-on:click="toggleTorch()">
                            Lampu
                        </x-filament::button>
                    </div>

                    <form wire:submit="scanBarcode" class="grid gap-3 sm:grid-cols-[1fr_auto]">
                        <x-filament::input
                            type="text"
                            wire:model="barcode"
                            x-ref="barcodeInput"
                            placeholder="Ketik barcode, kode, atau nama barang..."
                            class="text-xl"
                        />
                        <x-filament::button type="submit" size="xl" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="scanBarcode">
                            <span wire:loading.remove wire:target="scanBarcode">Cari</span>
                            <span wire:loading wire:target="scanBarcode">Mencari...</span>
                        </x-filament::button>
                    </form>

                    <x-filament::button
                        type="button"
                        size="md"
                        color="gray"
                        icon="heroicon-m-plus-circle"
                        class="w-full"
                        wire:click="startManualFoundItem"
                    >
                        Barang Temuan
                    </x-filament::button>
                </div>
            </x-filament::section>
            @endif

            @if (! $scannedItem && $scanCandidates)
            <div
                x-ref="scanCandidates"
                x-bind:class="{ 'distora-candidate-flash': candidatesFlash }"
                class="rounded-xl"
            >
            <x-filament::section>
                <x-slot name="heading">
                    Pilih Kode Barang
                </x-slot>

                <x-slot name="description">
                    Pencarian {{ $lastScannedBarcode }} cocok dengan beberapa item. Pilih barang yang sedang dihitung.
                </x-slot>

                <x-slot name="headerEnd">
                    <div class="flex items-center gap-2">
                        <x-filament::badge color="warning">{{ count($scanCandidates) }} pilihan</x-filament::badge>
                        <x-filament::button
                            type="button"
                            wire:click="resetScanState"
                            color="gray"
                            size="sm"
                            icon="heroicon-m-arrow-left"
                        >
                            Kembali
                        </x-filament::button>
                    </div>
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
            </div>
            @endif

            @if (! $scannedItem && $notFoundBarcode !== null)
            <div
                x-ref="foundItemPanel"
                x-bind:class="{ 'distora-candidate-flash': foundItemFlash }"
                class="rounded-xl"
            >
            <x-filament::section>
                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <x-filament::button
                            type="button"
                            wire:click="resetScanState"
                            color="gray"
                            size="sm"
                            icon="heroicon-m-arrow-left"
                        >
                            Kembali
                        </x-filament::button>
                        <x-filament::badge color="warning">{{ $editingFoundItemId ? 'Edit Barang Temuan' : 'Barang Temuan' }}</x-filament::badge>
                    </div>

                    @if ($foundItemMasterId)
                        <div class="space-y-2">
                            <div class="break-words text-lg font-bold leading-snug text-gray-950 dark:text-white">
                                {{ $foundItemName }}
                            </div>
                            <div class="font-mono text-sm font-semibold text-warning-600 dark:text-warning-400">
                                {{ $notFoundBarcode }}
                            </div>
                            <div class="text-xs text-gray-500">Tidak terdaftar di sesi aktif</div>

                        </div>
                    @else
                        <div class="space-y-2">
                            <div class="text-xs text-gray-500">Tidak terdaftar di sesi aktif</div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    Kode / Barcode
                                </label>
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                    type="text"
                                    wire:model="notFoundBarcode"
                                    placeholder="Ketik kode atau barcode"
                                />
                                </x-filament::input.wrapper>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    Nama Barang
                                </label>
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                    type="text"
                                    wire:model="foundItemName"
                                    placeholder="Ketik nama barang"
                                />
                                </x-filament::input.wrapper>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Qty Fisik
                        </label>
                        @if ($foundItemMasterId)
                            <div class="grid gap-2 sm:gap-4" style="grid-template-columns: repeat({{ count($foundQtyLabels) }}, minmax(0, 1fr))">
                                @foreach ($foundQtyLabels as $index => $label)
                                    <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                                        <label class="mb-2 block text-center text-xs font-semibold uppercase text-gray-500">
                                            {{ $label }}
                                        </label>
                                        <x-filament::input.wrapper>
                                            <x-filament::input
                                            type="number"
                                            min="0"
                                            wire:model="foundQtyLevels.{{ $index }}"
                                            class="text-center text-2xl font-bold"
                                        />
                                        </x-filament::input.wrapper>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <x-filament::input.wrapper>
                                <x-filament::input
                                type="text"
                                wire:model="foundQty"
                                placeholder="Qty fisik, contoh: 1 CTN 1 PCK 1 PCS"
                            />
                            </x-filament::input.wrapper>
                        @endif
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Catatan <span class="font-normal text-gray-400">(opsional)</span>
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input
                            type="text"
                            wire:model="foundNote"
                            placeholder="Catatan / dugaan tertukar"
                        />
                        </x-filament::input.wrapper>
                    </div>

                    <x-filament::button
                        type="button"
                        color="warning"
                        size="xl"
                        :icon="$editingFoundItemId ? 'heroicon-m-check' : 'heroicon-m-plus-circle'"
                        class="w-full"
                        wire:click="recordFoundItem"
                    >
                        {{ $editingFoundItemId ? 'Simpan Perubahan' : 'Simpan Barang Temuan' }}
                    </x-filament::button>
                </div>
            </x-filament::section>
            </div>
            @endif

            @if (! $scannedItem && $recentScans)
            <div x-data="{ open: false }">
                <x-filament::section compact>
                    <x-slot name="heading">
                        <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between gap-3 text-left text-sm font-semibold">
                            <span class="flex items-center gap-2.5">
                                Scan Terakhir
                                <x-filament::badge color="gray" size="lg">{{ count($recentScans) }}</x-filament::badge>
                            </span>
                            <x-filament::icon icon="heroicon-m-chevron-down" class="h-5 w-5 shrink-0 text-gray-400 transition" x-bind:class="{ 'rotate-180': open }" />
                        </button>
                    </x-slot>

                    <div x-show="open" class="grid gap-2">
                        @foreach ($recentScans as $scan)
                            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                <div class="min-w-0">
                                    <div class="break-words font-semibold leading-snug text-gray-900 dark:text-white">{{ $scan['name'] }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $scan['code'] }} &bull; {{ $scan['at'] }}</div>
                                </div>
                                <div class="mt-3">
                                    <x-filament::badge
                                        :color="$scan['status'] === 'matched' ? 'success' : ($scan['status'] === 'pending' ? 'gray' : 'warning')"
                                    >
                                        {{ $scan['status'] }}
                                    </x-filament::badge>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            </div>
            @endif

            @if (! $scannedItem)
            <x-filament::section>
                <x-slot name="heading">
                    {{ $session->principal->nama }}
                </x-slot>

                <x-slot name="description">
                    <span class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs sm:text-sm">
                        <span>{{ $session->branch?->nama ?? 'Tanpa Cabang' }}</span>
                        <span class="text-gray-300 dark:text-gray-600">&bull;</span>
                        <span>{{ $session->checked_items }}/{{ $session->total_items }} item</span>
                        <span class="text-success-600">{{ $session->matched_items }} sesuai</span>
                        <span class="font-semibold text-danger-600">{{ $session->mismatched_items }} selisih</span>
                        <span class="font-mono" x-text="clock">{{ now()->format('H:i:s') }} WITA</span>
                    </span>
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
                        size="md"
                        icon="heroicon-m-arrow-left"
                    >
                        Ganti Principal
                    </x-filament::button>
                </div>
            </x-filament::section>

            @php
                $comparisonDate = $this->getComparisonDateForSession($session);
                $comparisonRows = $this->getComparisonRowsForSession($session)
                    ->sortBy('kode_barang', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();
                $comparisonFoundItems = $this->getFoundItemsData();
                $comparisonSearch = trim($comparisonSearch ?? '');
                $comparisonFilter = in_array($comparisonFilter, ['changed', 'unchanged', 'normal'], true)
                    ? $comparisonFilter
                    : 'changed';
                $comparisonStatusMap = [
                    'changed' => ['Berubah'],
                    'unchanged' => ['Tidak Berubah'],
                    'normal' => ['Sudah Normal'],
                ];
                $filteredComparisonRows = $comparisonRows
                    ->when(isset($comparisonStatusMap[$comparisonFilter]), fn ($rows) => $rows->filter(fn ($row) => in_array($row['status'], $comparisonStatusMap[$comparisonFilter], true)))
                    ->when($comparisonSearch !== '', fn ($rows) => $rows->filter(fn ($row) => str_contains(strtolower($row['kode_barang'] . ' ' . $row['nama_barang'] . ' ' . $row['status']), strtolower($comparisonSearch))));
                $comparisonFilterOptions = [
                    'changed' => ['label' => 'Berubah', 'count' => $comparisonRows->where('status', 'Berubah')->count()],
                    'unchanged' => ['label' => 'Tidak Berubah', 'count' => $comparisonRows->where('status', 'Tidak Berubah')->count()],
                    'normal' => ['label' => 'Sudah Normal', 'count' => $comparisonRows->where('status', 'Sudah Normal')->count()],
                ];
            @endphp
            <div x-data="{ open: false }">
                <x-filament::section>
                    <x-slot name="heading">
                        <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between gap-3 text-left text-sm font-semibold">
                            <span class="flex items-center gap-2.5">
                                Perbandingan
                                <x-filament::badge :color="$comparisonRows->isNotEmpty() ? 'warning' : 'gray'" size="lg">{{ $comparisonRows->count() }}</x-filament::badge>
                            </span>
                            <x-filament::icon icon="heroicon-m-chevron-down" class="h-5 w-5 shrink-0 text-gray-400 transition" x-bind:class="{ 'rotate-180': open }" />
                        </button>
                    </x-slot>

                    <div x-show="open">
                        <div class="mb-3 text-xs leading-relaxed text-gray-500">
                            @if ($comparisonDate)
                                Dibandingkan dengan stock opname terakhir: {{ \Carbon\Carbon::parse($comparisonDate)->format('d M Y') }}.
                            @else
                                Belum ada stock opname sebelumnya untuk principal/cabang ini.
                            @endif
                        </div>

                        @if ($comparisonDate)
                            <div class="mb-3">
                                <select
                                    wire:model.live="comparisonFilter"
                                    class="w-full rounded-lg border border-primary-500 bg-primary-50 px-4 py-3 text-base font-bold text-primary-800 shadow-sm transition focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-primary-500 dark:bg-primary-950/30 dark:text-primary-200"
                                >
                                    @foreach ($comparisonFilterOptions as $value => $option)
                                        <option value="{{ $value }}">{{ $option['label'] }} ({{ $option['count'] }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <x-filament::input
                                    type="search"
                                    wire:model.live.debounce.300ms="comparisonSearch"
                                    placeholder="Cari kode, nama barang, atau status..."
                                />
                            </div>

                            <div class="grid gap-2">
                                @forelse ($filteredComparisonRows->take($comparisonLimit) as $row)
                                    @php
                                        $sample = $row['to_item'] ?? $row['from_item'];
                                        $statusColor = match ($row['status']) {
                                            'Berubah' => 'warning',
                                            'Tidak Berubah' => 'gray',
                                            'Sudah Normal' => 'success',
                                            default => 'gray',
                                        };
                                    @endphp
                                    <div class="rounded-lg border border-gray-200 bg-white px-3.5 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                        <div class="grid gap-2.5">
                                            <div class="grid grid-cols-[1fr_auto] items-start gap-2">
                                                <div class="min-w-0">
                                                    <div class="font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $row['kode_barang'] }}</div>
                                                    <div class="mt-0.5 break-words text-sm font-semibold leading-snug text-gray-900 dark:text-white">{{ $row['nama_barang'] }}</div>
                                                </div>
                                                <x-filament::badge :color="$statusColor" size="sm">{{ $row['status'] }}</x-filament::badge>
                                            </div>

                                            <div class="grid gap-1.5 border-t border-gray-100 pt-2.5 text-[11px] dark:border-white/10">
                                                <div class="flex items-center justify-between gap-4">
                                                    <div class="text-gray-500">Terakhir</div>
                                                    <div class="text-right font-mono font-semibold text-gray-900 dark:text-white">{{ app(\App\Services\ReportService::class)->formatSignedBaseQty($row['from_selisih'], $sample) }}</div>
                                                </div>
                                                <div class="flex items-center justify-between gap-4">
                                                    <div class="text-gray-500">Sekarang</div>
                                                    <div class="text-right font-mono font-semibold text-gray-900 dark:text-white">{{ app(\App\Services\ReportService::class)->formatSignedBaseQty($row['to_selisih'], $sample) }}</div>
                                                </div>
                                                <div class="flex items-center justify-between gap-4">
                                                    <div class="text-gray-500">Perubahan</div>
                                                    <div class="text-right font-mono font-bold {{ $row['change'] === 0 ? 'text-gray-500' : ($row['change'] < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-warning-600 dark:text-warning-400') }}">
                                                        {{ app(\App\Services\ReportService::class)->formatSignedBaseQty($row['change'], $sample) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700">
                                        Tidak ada item selisih/perubahan yang cocok.
                                    </div>
                                @endforelse
                                @if ($filteredComparisonRows->count() > $comparisonLimit)
                                    <x-filament::button type="button" wire:click="loadMoreComparison" color="gray" size="lg" class="w-full">
                                        Lihat {{ min(10, $filteredComparisonRows->count() - $comparisonLimit) }} item lainnya
                                    </x-filament::button>
                                @endif
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700">
                                Perbandingan akan muncul setelah ada stock opname sebelumnya.
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            </div>

            @if ($session->found_items_count > 0)
                <div x-data="{ open: false }">
                    <x-filament::section>
                        <x-slot name="heading">
                            <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between gap-3 text-left text-sm font-semibold">
                                <span class="flex items-center gap-2.5">
                                    Barang Temuan
                                    <x-filament::badge color="warning" size="lg">{{ $session->found_items_count }}</x-filament::badge>
                                </span>
                                <x-filament::icon icon="heroicon-m-chevron-down" class="h-5 w-5 shrink-0 text-gray-400 transition" x-bind:class="{ 'rotate-180': open }" />
                            </button>
                        </x-slot>

                        <div x-show="open" class="grid gap-3">
                            @foreach ($comparisonFoundItems as $item)
                                <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="font-mono text-xs font-bold text-warning-600 dark:text-warning-400">{{ $item->kode_barang }}</div>
                                            <div class="mt-1 break-words text-sm font-semibold leading-snug text-gray-900 dark:text-white">{{ $item->nama_barang }}</div>
                                        </div>
                                        <div class="shrink-0 text-right text-[11px] leading-4 text-gray-500 dark:text-gray-400">
                                            <div>{{ $item->found_at?->translatedFormat('d M Y') ?? '-' }}</div>
                                            <div class="font-mono">{{ $item->found_at?->format('H:i') ?? '-' }} WITA</div>
                                        </div>
                                    </div>

                                    <div class="mt-3 grid gap-2 border-t border-gray-100 pt-2.5 text-xs dark:border-white/10">
                                        <div class="flex items-start justify-between gap-3">
                                            <span class="text-gray-500">Qty fisik</span>
                                            <span class="break-words text-right font-mono font-bold text-warning-700 dark:text-warning-300">{{ app(\App\Services\ReportService::class)->formatFoundQty($item) }}</span>
                                        </div>
                                        <div class="flex items-start justify-between gap-3">
                                            <span class="text-gray-500">Petugas</span>
                                            <span class="break-words text-right font-semibold text-gray-900 dark:text-white">{{ $item->foundBy?->name ?? '-' }}</span>
                                        </div>
                                        @if ($item->note)
                                            <div class="border-t border-gray-100 pt-2 text-gray-600 dark:border-white/10 dark:text-gray-300">
                                                <span class="font-semibold text-gray-500">Catatan:</span>
                                                {{ $item->note }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="mt-3 flex justify-end gap-2">
                                        <x-filament::button type="button" size="sm" color="gray" icon="heroicon-m-pencil-square" wire:click="editFoundItem({{ $item->id }})">
                                            Edit
                                        </x-filament::button>
                                        <x-filament::button type="button" size="sm" color="danger" icon="heroicon-m-trash" wire:click="deleteFoundItem({{ $item->id }})" wire:confirm="Hapus barang temuan ini?">
                                            Hapus
                                        </x-filament::button>
                                    </div>
                                </div>
                            @endforeach
                            @if ($session->found_items_count > $foundItemsLimit)
                                <x-filament::button type="button" wire:click="loadMoreFoundItems" color="gray" size="lg" class="w-full">
                                    Lihat {{ min(10, $session->found_items_count - $foundItemsLimit) }} item lainnya
                                </x-filament::button>
                            @endif
                        </div>
                    </x-filament::section>
                </div>
            @endif

            @php
                $checkedData = $this->getCheckedItemsData();
                $filteredCheckedItems = $checkedData['items'];
            @endphp
            @if ($session->checked_items > 0)
                <div x-data="{ open: false }">
                    <x-filament::section>
                        <x-slot name="heading">
                            <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between gap-3 text-left text-sm font-semibold">
                                <span class="flex items-center gap-2.5">
                                    Sudah Dicek
                                    <x-filament::badge color="success" size="lg">{{ $session->checked_items }}</x-filament::badge>
                                </span>
                                <x-filament::icon icon="heroicon-m-chevron-down" class="h-5 w-5 shrink-0 text-gray-400 transition" x-bind:class="{ 'rotate-180': open }" />
                            </button>
                        </x-slot>

                        <div x-show="open">
                            <div class="mb-3">
                                <x-filament::input
                                    type="search"
                                    wire:model.live.debounce.300ms="checkedSearch"
                                    placeholder="Cari kode, nama barang, atau petugas..."
                                />
                            </div>

                            <div class="grid gap-2">
                            @forelse ($filteredCheckedItems as $item)
                                <button
                                    type="button"
                                    wire:click="startEditItem({{ $item->id }})"
                                    class="w-full rounded-xl border border-gray-200 bg-white p-3 text-left shadow-sm transition hover:border-primary-400 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-500 dark:hover:bg-primary-950/10 sm:p-4"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $item->kode_barang }}</div>
                                            <div class="mt-1 break-words text-base font-semibold leading-snug text-gray-900 dark:text-white">{{ $item->nama_barang }}</div>
                                            <div class="mt-1 text-sm text-gray-500">
                                                Aktual: {{ $item->qty_aktual_display ?? '-' }}
                                                &bull;
                                                {{ $item->checkedBy?->name ?? '-' }}
                                                &bull;
                                                {{ $item->checked_at?->format('H:i') ?? '-' }}
                                            </div>
                                        </div>
                                        <x-filament::badge
                                            :color="match ($item->status) {
                                                \App\Enums\StockSessionItemStatus::Matched => 'success',
                                                \App\Enums\StockSessionItemStatus::Mismatched => 'danger',
                                                \App\Enums\StockSessionItemStatus::Missing => 'warning',
                                                default => 'gray',
                                            }"
                                        >
                                            {{ $item->status === \App\Enums\StockSessionItemStatus::Missing ? 'tidak ada' : $item->status->value }}
                                        </x-filament::badge>
                                    </div>
                                </button>
                            @empty
                                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700">
                                    Tidak ada item yang cocok.
                                </div>
                            @endforelse
                            </div>
                        </div>

                        @if ($checkedData['total'] > 25)
                            <div x-show="open" class="mt-3 text-center text-sm text-gray-400">
                                + {{ $checkedData['total'] - 25 }} hasil lainnya, gunakan pencarian
                            </div>
                        @endif
                    </x-filament::section>
                </div>
            @endif

            @php
                $mismatchedData = $this->getMismatchedItemsData();
                $mismatchedItems = $mismatchedData['items'];
            @endphp
            @if ($session->mismatched_items > 0)
                <div x-data="{ open: {{ $mismatchedItems->count() <= 3 ? 'true' : 'false' }} }">
                    <x-filament::section>
                        <x-slot name="heading">
                            <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between gap-3 text-left text-sm font-semibold">
                                <span class="flex items-center gap-2.5">
                                    Item Selisih
                                    <x-filament::badge color="danger" size="lg">{{ $session->mismatched_items }}</x-filament::badge>
                                </span>
                                <x-filament::icon icon="heroicon-m-chevron-down" class="h-5 w-5 shrink-0 text-gray-400 transition" x-bind:class="{ 'rotate-180': open }" />
                            </button>
                        </x-slot>

                        <div x-show="open" class="grid gap-3">
                            @foreach ($mismatchedItems as $item)
                                <button
                                    type="button"
                                    wire:click="startEditItem({{ $item->id }})"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-4 py-4 text-left shadow-sm transition hover:border-danger-400 hover:bg-danger-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-danger-500 dark:hover:bg-danger-950/10"
                                >
                                    <div class="min-w-0">
                                        <div class="font-mono text-xs font-semibold text-danger-600 dark:text-danger-400">{{ $item->kode_barang }}</div>
                                        <div class="mt-1 break-words text-base font-semibold leading-snug text-gray-900 dark:text-white sm:text-lg">{{ $item->nama_barang }}</div>
                                        <div class="mt-1 text-sm text-gray-500">Sistem: {{ $this->formatSystemQty($item) }}</div>
                                    </div>
                                    <div class="mt-3 flex items-center justify-between gap-3 border-t border-gray-100 pt-3 dark:border-white/10">
                                        <div>
                                            <div class="text-xs text-gray-500">Selisih</div>
                                            <div class="font-mono text-lg font-bold text-danger-600 dark:text-danger-400">{{ app(\App\Services\ReportService::class)->formatSignedBaseQty($item->selisih, $item) }}</div>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-danger-50 px-3 py-2 text-sm font-semibold text-danger-700 dark:bg-danger-950/30 dark:text-danger-300">
                                            Koreksi
                                            <x-filament::icon icon="heroicon-m-pencil-square" class="h-4 w-4" />
                                        </span>
                                    </div>
                                </button>
                            @endforeach
                            @if ($mismatchedData['total'] > $mismatchedItemsLimit)
                                <x-filament::button type="button" wire:click="loadMoreMismatchedItems" color="gray" size="lg" class="w-full">
                                    Lihat {{ min(10, $mismatchedData['total'] - $mismatchedItemsLimit) }} item lainnya
                                </x-filament::button>
                            @endif
                        </div>
                    </x-filament::section>
                </div>
            @endif

            @php
                $pendingData = $this->getPendingItemsData();
                $filteredPendingItems = $pendingData['items'];
                $pendingTotal = max(0, $session->total_items - $session->checked_items);
            @endphp
            @if ($pendingTotal > 0)
                <div x-data="{ open: true }">
                    <x-filament::section>
                        <x-slot name="heading">
                            <div class="flex w-full items-center text-left text-sm font-semibold">
                                <span class="flex items-center gap-2.5">
                                    Belum Dicek
                                    <x-filament::badge color="gray" size="lg">{{ $pendingTotal }}</x-filament::badge>
                                </span>
                            </div>
                        </x-slot>

                        <div x-show="open">
                            <div class="mb-3">
                                <x-filament::input
                                    type="search"
                                    wire:model.live.debounce.300ms="pendingSearch"
                                    placeholder="Cari kode atau nama barang..."
                                />
                            </div>

                            <div class="grid gap-2">
                                @forelse ($filteredPendingItems as $item)
                                    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-4">
                                        <button
                                            type="button"
                                            wire:click="startEditItem({{ $item->id }})"
                                            class="flex w-full items-start gap-3 text-left"
                                        >
                                            <x-filament::icon icon="heroicon-m-cube" class="mt-0.5 h-5 w-5 shrink-0 text-gray-400" />
                                            <span class="min-w-0 flex-1">
                                                <span class="block font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $item->kode_barang }}</span>
                                                <span class="mt-1 block break-words text-base font-semibold leading-snug text-gray-900 dark:text-white">{{ $item->nama_barang }}</span>
                                                <span class="mt-1 block text-sm text-gray-500">Sistem: {{ $this->formatSystemQty($item) }}</span>
                                            </span>
                                        </button>

                                        <div class="mt-3 grid grid-cols-2 gap-2 border-t border-gray-100 pt-3 dark:border-white/10">
                                            <x-filament::button
                                                type="button"
                                                size="lg"
                                                color="primary"
                                                icon="heroicon-m-pencil-square"
                                                class="w-full"
                                                wire:click="startEditItem({{ $item->id }})"
                                            >
                                                Edit
                                            </x-filament::button>
                                            <x-filament::button
                                                type="button"
                                                size="lg"
                                                color="gray"
                                                icon="heroicon-m-eye-slash"
                                                class="w-full"
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

                            @if ($pendingData['total'] > 25)
                                <div class="mt-3 text-center text-sm text-gray-400">
                                    + {{ $pendingData['total'] - 25 }} hasil lainnya, gunakan pencarian
                                </div>
                            @endif
                        </div>
                    </x-filament::section>
                </div>
            @endif
            @endif

            @if ($scannedItem)
                <x-filament::section>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <x-filament::button
                                type="button"
                                wire:click="resetScanState"
                                color="gray"
                                size="sm"
                                icon="heroicon-m-arrow-left"
                            >
                                Kembali
                            </x-filament::button>

                            <x-filament::badge
                                :color="match ($scannedItem->status) {
                                    \App\Enums\StockSessionItemStatus::Matched => 'success',
                                    \App\Enums\StockSessionItemStatus::Mismatched => 'danger',
                                    \App\Enums\StockSessionItemStatus::Missing => 'warning',
                                    default => 'gray',
                                }"
                            >
                                {{ $scannedItem->status === \App\Enums\StockSessionItemStatus::Missing ? 'tidak ada' : $scannedItem->status->value }}
                            </x-filament::badge>
                        </div>

                        <div class="space-y-2">
                            <div class="break-words text-lg font-bold leading-snug text-gray-950 dark:text-white">
                                {{ $scannedItem->nama_barang }}
                            </div>
                            <div class="font-mono text-sm font-semibold text-primary-600 dark:text-primary-400">
                                {{ $scannedItem->kode_barang }}
                            </div>
                        </div>

                        @if ($isEditing)
                            <x-filament::callout color="info" icon="heroicon-m-pencil-square">
                                <x-slot name="heading">Mode Edit</x-slot>
                                <x-slot name="description">
                                    Stok Sistem: {{ $this->usesSeparateCtnPcsCount() ? $this->getActiveModeSystemQty() : $this->formatSystemQty($scannedItem) }}
                                </x-slot>
                            </x-filament::callout>
                        @endif

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ $this->usesSeparateCtnPcsCount() ? 'Qty Aktual ' . strtoupper($separateCountMode) : 'Qty Aktual' }}
                            </label>
                            @if ($this->usesSeparateCtnPcsCount())
                                <div class="mb-3 rounded-xl border border-gray-300 bg-white p-3 text-center dark:border-gray-700 dark:bg-gray-900">
                                    <div class="text-xs font-semibold uppercase text-gray-700 dark:text-gray-300">Stok Sistem {{ strtoupper($separateCountMode) }}</div>
                                    <div class="text-3xl font-black text-black dark:text-white">{{ $this->getActiveModeSystemQty() }}</div>
                                </div>
                                @php($activeQtyIndexes = $this->getSeparateCountModeIndexes())
                                <div class="grid gap-2 sm:gap-4" style="grid-template-columns: repeat({{ count($activeQtyIndexes) }}, minmax(0, 1fr))">
                                    @foreach ($activeQtyIndexes as $activeQtyIndex)
                                        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                                            <label class="mb-2 block text-center text-xs font-semibold uppercase text-gray-500">
                                                {{ $qtyLabels[$activeQtyIndex] ?? strtoupper($separateCountMode) }}
                                            </label>
                                            <x-filament::input
                                                type="number"
                                                min="0"
                                                wire:model="qtyLevels.{{ $activeQtyIndex }}"
                                                class="text-center text-2xl font-bold"
                                            />
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="grid gap-2 sm:gap-4" style="grid-template-columns: repeat({{ count($qtyLabels) }}, minmax(0, 1fr))">
                                    @foreach ($qtyLabels as $index => $label)
                                        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                                            <label class="mb-2 block text-center text-xs font-semibold uppercase text-gray-500">
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
                            @endif
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

                        <div class="space-y-3 pt-2">
                            @if (! $isEditing)
                                <div class="distora-scan-actions">
                                    <x-filament::button
                                        wire:click="{{ $this->usesSeparateCtnPcsCount() ? 'markCurrentModeMatched' : 'submitActualQty' }}"
                                        color="primary"
                                        size="xl"
                                        icon="heroicon-m-check"
                                        class="w-full"
                                    >
                                        {{ $this->usesSeparateCtnPcsCount() ? 'Sesuai, Lanjut' : 'Simpan Hasil' }}
                                    </x-filament::button>
                                </div>

                                @if ($this->usesSeparateCtnPcsCount())
                                    <div class="distora-scan-actions">
                                        <x-filament::button
                                            wire:click="submitActualQty"
                                            color="gray"
                                            size="sm"
                                            icon="heroicon-m-pencil-square"
                                            class="w-full"
                                        >
                                            Simpan Angka
                                        </x-filament::button>
                                    </div>
                                @endif

                                <div class="distora-scan-actions">
                                    <x-filament::button
                                        wire:click="markMissing"
                                        color="gray"
                                        size="sm"
                                        icon="heroicon-m-eye-slash"
                                        class="w-full"
                                    >
                                        Tidak Ada
                                    </x-filament::button>
                                </div>
                            @else
                                <div class="distora-scan-actions">
                                    <x-filament::button
                                        wire:click="{{ $this->usesSeparateCtnPcsCount() ? 'markCurrentModeMatched' : 'submitActualQty' }}"
                                        color="primary"
                                        size="xl"
                                        icon="heroicon-m-check"
                                        class="w-full"
                                    >
                                        {{ $this->usesSeparateCtnPcsCount() ? 'Sesuai, Lanjut' : 'Simpan Koreksi' }}
                                    </x-filament::button>
                                </div>

                                @if ($this->usesSeparateCtnPcsCount())
                                    <div class="distora-scan-actions">
                                        <x-filament::button
                                            wire:click="submitActualQty"
                                            color="gray"
                                            size="sm"
                                            icon="heroicon-m-pencil-square"
                                            class="w-full"
                                        >
                                            Simpan Angka
                                        </x-filament::button>
                                    </div>
                                @endif
                            @endif
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
                x-on:click.outside="open = false"
                x-on:keydown.escape.window="open = false"
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

@push('styles')
    <style>
        @keyframes distora-candidate-pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
            }

            30% {
                box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.9), 0 0 24px rgba(245, 158, 11, 0.45);
            }

            65% {
                box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.55), 0 0 14px rgba(245, 158, 11, 0.3);
            }
        }

        .distora-candidate-flash {
            animation: distora-candidate-pulse 0.9s ease-out;
        }
    </style>
@endpush
