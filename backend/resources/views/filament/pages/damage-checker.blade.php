<x-filament-panels::page>
    @php($check = $this->getSelectedCheck())

    <div
        class="mx-auto w-full max-w-6xl space-y-4 pb-16 sm:space-y-6"
        x-data="{
            checkedRows: {},
            toggleRow(key) {
                this.checkedRows[key] = ! this.checkedRows[key];
            },
            isChecked(key) {
                return Boolean(this.checkedRows[key]);
            },
        }"
    >
        @if (! $check)
            <x-filament::section>
                <x-slot name="heading">Header Pemeriksaan Baru</x-slot>
                <x-slot name="description">Isi header sebelum mulai memindai barang rusak.</x-slot>

                <form wire:submit="createCheck" class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold">Tanggal</label>
                        <x-filament::input type="date" wire:model="checkDate" />
                        @error('checkDate') <div class="mt-1 text-sm text-danger-600">{{ $message }}</div> @enderror
                    </div>

                    @if (auth()->user()->isCentralAdmin())
                        <div>
                            <label class="mb-1 block text-sm font-semibold">Cabang</label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="branchId">
                                    <option value="">Pilih cabang</option>
                                    @foreach ($this->getBranches() as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->nama }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            @error('branchId') <div class="mt-1 text-sm text-danger-600">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="mb-1 block text-sm font-semibold">Principal (opsional)</label>
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
                        <label class="mb-1 block text-sm font-semibold">Lokasi barang rusak</label>
                        <x-filament::input wire:model="location" placeholder="Contoh: Gudang retur / Area rusak A" />
                        @error('location') <div class="mt-1 text-sm text-danger-600">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold">PIN Bergabung</label>
                        <x-filament::input type="password" inputmode="numeric" wire:model="createJoinPin" placeholder="4–6 angka" autocomplete="new-password" />
                        @error('createJoinPin') <div class="mt-1 text-sm text-danger-600">PIN harus terdiri dari 4–6 angka.</div> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-semibold">Keterangan (opsional)</label>
                        <textarea wire:model="notes" rows="2" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <x-filament::button type="submit" size="lg" icon="heroicon-m-play">Buat & Mulai Checker</x-filament::button>
                    </div>
                </form>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Pemeriksaan Terakhir</x-slot>

                @if ($pendingJoinCheckId)
                    <form wire:submit="submitJoin" class="mb-4 rounded-xl border border-warning-300 bg-warning-50 p-4 dark:border-warning-800 dark:bg-warning-950/20">
                        <div class="font-semibold">Masukkan PIN pemeriksaan</div>
                        <div class="mt-1 text-sm text-gray-500">PIN diberikan oleh pembuat header.</div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto_auto]">
                            <x-filament::input type="password" inputmode="numeric" wire:model="joinPin" placeholder="PIN 4–6 angka" autocomplete="one-time-code" />
                            <x-filament::button type="submit" icon="heroicon-m-lock-open" class="w-full sm:w-auto">Gabung</x-filament::button>
                            <x-filament::button type="button" color="gray" wire:click="cancelJoin" class="w-full sm:w-auto">Batal</x-filament::button>
                        </div>
                        @error('joinPin') <div class="mt-1 text-sm text-danger-600">PIN harus terdiri dari 4–6 angka.</div> @enderror
                    </form>
                @endif

                <div class="grid gap-3">
                    @forelse ($this->getRecentChecks() as $recent)
                        <button type="button" wire:click="selectCheck({{ $recent->id }})" class="rounded-xl border border-gray-200 p-4 text-left hover:border-primary-500 dark:border-gray-700">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="font-semibold">{{ $recent->reference_number }}</div>
                                    <div class="text-sm text-gray-500">{{ $recent->branch->nama }} · {{ $recent->location }} · {{ $recent->officer->name }}</div>
                                </div>
                                <x-filament::badge :color="$recent->status === \App\Enums\DamageCheckStatus::Open ? 'warning' : 'success'">
                                    {{ $recent->status === \App\Enums\DamageCheckStatus::Open ? 'Ketuk untuk bergabung' : 'Selesai' }} · {{ $recent->checkers_count }}/5 checker · {{ $recent->items_count }} item
                                </x-filament::badge>
                            </div>
                        </button>
                    @empty
                        <div class="text-sm text-gray-500">Belum ada pemeriksaan barang rusak.</div>
                    @endforelse
                </div>
            </x-filament::section>
        @else
            @if ($check->status === \App\Enums\DamageCheckStatus::Open)
                <x-filament::section>
                    <div
                        x-data="{
                            stream: null,
                            detector: null,
                            scanning: false,
                            scanLocked: false,
                            flashScan: false,
                            scanMode: 'santai',
                            lastScanValue: null,
                            lastScanAt: 0,
                            status: 'Kamera belum aktif',
                            cooldownMs() {
                                return 2000;
                            },
                            beep(frequency = 900, duration = 90) {
                                const AudioContext = window.AudioContext || window.webkitAudioContext;

                                if (! AudioContext) return;

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
                                        formats: ['code_128', 'ean_13', 'ean_8', 'code_39', 'code_93', 'itf', 'upc_a', 'upc_e'],
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
                                    this.status = 'Gagal membuka kamera. Periksa izin kamera browser.';
                                }
                            },
                            async scanFrame() {
                                if (! this.scanning) return;

                                if (this.scanLocked) {
                                    requestAnimationFrame(() => this.scanFrame());
                                    return;
                                }

                                try {
                                    const codes = await this.detector.detect(this.$refs.video);
                                    const value = codes[0]?.rawValue?.trim();

                                    if (! value) {
                                        requestAnimationFrame(() => this.scanFrame());
                                        return;
                                    }

                                    if (value) {
                                        const now = Date.now();

                                        if (value === this.lastScanValue && now - this.lastScanAt < this.cooldownMs()) {
                                            requestAnimationFrame(() => this.scanFrame());
                                            return;
                                        }

                                        this.lastScanValue = value;
                                        this.lastScanAt = now;
                                        this.scanLocked = true;
                                        this.flashScan = true;
                                        setTimeout(() => this.flashScan = false, 1000);
                                        setTimeout(() => this.scanLocked = false, 1000);
                                        this.status = `Terbaca: ${value}`;
                                        navigator.vibrate?.(60);
                                        this.beep();
                                        $wire.scanBarcode(value);
                                        if (this.scanMode === 'santai') {
                                            this.stopCamera();
                                            return;
                                        }
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
                                this.scanLocked = false;
                                this.flashScan = false;
                                this.stream?.getTracks().forEach(track => track.stop());
                                this.stream = null;
                                if (this.$refs.video) this.$refs.video.srcObject = null;
                                this.status = 'Kamera dimatikan';
                            },
                        }"
                        class="space-y-4"
                    >
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-black dark:border-gray-700" style="aspect-ratio: 16 / 9;">
                            <div class="relative h-full w-full bg-black">
                                <video x-ref="video" class="absolute inset-0 h-full w-full object-cover" playsinline muted></video>
                                <div x-show="flashScan" x-transition.opacity class="absolute inset-0 bg-white/80"></div>
                            </div>
                        </div>
                        <div class="h-5 truncate text-center text-sm text-gray-500" x-text="status"></div>

                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-2 rounded-xl bg-gray-100 p-1 text-sm font-semibold dark:bg-gray-800">
                                <button type="button" x-on:click="scanMode = 'santai'" class="rounded-lg px-3 py-2" x-bind:class="scanMode === 'santai' ? 'bg-primary-600 text-white' : 'text-gray-600 dark:text-gray-300'">
                                    Santai
                                </button>
                                <button type="button" x-on:click="scanMode = 'cepat'; if (! scanning) toggleCamera()" class="rounded-lg px-3 py-2" x-bind:class="scanMode === 'cepat' ? 'bg-primary-600 text-white' : 'text-gray-600 dark:text-gray-300'">
                                    Cepat
                                </button>
                            </div>

                            <x-filament::button type="button" size="lg" icon="heroicon-m-camera" x-on:click="toggleCamera()" class="w-full">
                                <span x-text="scanning ? 'Matikan Kamera' : 'Buka Kamera'"></span>
                            </x-filament::button>

                            <form wire:submit="scanBarcode" class="grid gap-2 sm:grid-cols-[1fr_auto]">
                                <x-filament::input x-ref="barcode" wire:model="barcode" placeholder="Ketik atau scan barcode" autocomplete="off" class="text-base sm:text-lg" />
                                <x-filament::button type="submit" size="lg" icon="heroicon-m-qr-code" class="w-full sm:w-auto">Scan Barcode</x-filament::button>
                            </form>

                            @if ($scanFeedback)
                                <div class="rounded-lg border border-success-200 bg-success-50 px-3 py-2 text-sm font-semibold text-success-700 dark:border-success-800 dark:bg-success-950/20 dark:text-success-300">
                                    Terakhir scan: {{ $scanFeedback }}
                                </div>
                            @endif

                            <div style="display:none !important">
                                <div x-show="manualOpen" x-transition>
                                    <x-filament::section>
                                        <x-slot name="heading">Tambah Manual</x-slot>
                                        <x-slot name="description">Cari item master, isi qty rusak, lalu klik tambah.</x-slot>

                                        <div class="grid gap-3 sm:grid-cols-[1fr_10rem]">
                                            <div>
                                                <label class="mb-1 block text-sm font-semibold">Cari Barang</label>
                                                <x-filament::input
                                                    type="search"
                                                    wire:model.live.debounce.300ms="manualSearch"
                                                    placeholder="Nama barang, kode, atau barcode"
                                                />
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-sm font-semibold">Qty Rusak</label>
                                                <x-filament::input
                                                    type="number"
                                                    min="1"
                                                    wire:model="manualQty"
                                                    placeholder="PCS"
                                                />
                                            </div>
                                        </div>

                                        @php($manualCandidates = $this->getManualItemCandidates())
                                        @if ($manualCandidates->isNotEmpty())
                                            <div class="mt-4 grid gap-2">
                                                @foreach ($manualCandidates as $item)
                                                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                            <div class="min-w-0">
                                                                <div class="break-words text-sm font-semibold">{{ $item->nama_barang }}</div>
                                                                <div class="mt-1 font-mono text-xs text-gray-500">{{ $item->kode_barang }} · {{ $item->barcode ?: 'Tanpa barcode' }} · {{ $item->principal?->nama ?? '-' }}</div>
                                                            </div>
                                                            <x-filament::button
                                                                type="button"
                                                                size="sm"
                                                                color="warning"
                                                                icon="heroicon-m-plus"
                                                                wire:click="addManualItem({{ $item->id }})"
                                                                class="w-full sm:w-auto"
                                                            >
                                                                Tambah
                                                            </x-filament::button>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @elseif (trim($manualSearch) !== '')
                                            <div class="mt-4 rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700">
                                                Tidak ada item master yang cocok.
                                            </div>
                                        @endif
                                    </x-filament::section>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($scanCandidates)
                        <div class="mt-4 space-y-2">
                            <div class="text-sm font-semibold">Barcode dipakai beberapa barang. Pilih yang sesuai:</div>
                            @foreach ($scanCandidates as $candidate)
                                <button type="button" wire:click="chooseCandidate({{ $candidate['id'] }})" class="block w-full rounded-xl border border-warning-300 p-3 text-left hover:bg-warning-50 dark:border-warning-700 dark:hover:bg-warning-950/20">
                                    <div class="font-semibold">{{ $candidate['name'] }}</div>
                                    <div class="text-sm text-gray-500">{{ $candidate['code'] }} · {{ $candidate['principal'] }} · +{{ $candidate['qty_base'] }} PCS ({{ $candidate['unit'] }})</div>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @if ($showPendingForm)
                        <form wire:submit="createPendingItem" class="mt-4 space-y-3 rounded-xl border border-warning-300 bg-warning-50 p-4 dark:border-warning-800 dark:bg-warning-950/20">
                            <div class="font-semibold">Barcode belum ada — tambah barang pending</div>
                            <div class="text-xs text-gray-500">Setelah disimpan, barcode ini dapat langsung discan checker lain di cabang yang sama.</div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div><label class="mb-1 block text-xs font-semibold">Barcode</label><x-filament::input wire:model="pendingBarcode" readonly /></div>
                                <div><label class="mb-1 block text-xs font-semibold">Nama barang</label><x-filament::input wire:model="pendingItemName" /></div>
                                <div><label class="mb-1 block text-xs font-semibold">Principal (opsional)</label><x-filament::input.wrapper><x-filament::input.select wire:model="pendingPrincipalId"><option value="">Belum diketahui</option>@foreach ($this->getPrincipals() as $principal)<option value="{{ $principal->id }}">{{ $principal->nama }}</option>@endforeach</x-filament::input.select></x-filament::input.wrapper></div>
                                <div class="grid grid-cols-2 gap-2"><div><label class="mb-1 block text-xs font-semibold">Satuan</label><x-filament::input wire:model="pendingUnitLabel" /></div><div><label class="mb-1 block text-xs font-semibold">Isi (PCS)</label><x-filament::input type="number" min="1" wire:model="pendingQtyPerScan" /></div></div>
                            </div>
                            <div><label class="mb-1 block text-xs font-semibold">Catatan (opsional)</label><x-filament::input wire:model="pendingNotes" placeholder="Contoh: kemasan lama" /></div>
                            @error('pendingItemName') <div class="text-sm text-danger-600">{{ $message }}</div> @enderror
                            <div class="flex gap-2"><x-filament::button type="submit" icon="heroicon-m-plus">Tambah & Catat</x-filament::button><x-filament::button type="button" color="gray" wire:click="cancelPendingItem">Batal</x-filament::button></div>
                        </form>
                    @endif
                </x-filament::section>
            @endif

            <x-filament::section>
                <div class="space-y-4 text-center">
                    <div>
                        <div class="break-words text-xl font-bold">{{ $check->reference_number }}</div>
                        <div class="mt-1 text-sm text-gray-500">{{ $check->check_date->format('d M Y') }} · {{ $check->branch->nama }} · {{ $check->principal?->nama ?? 'Semua principal' }}</div>
                        <div class="text-sm text-gray-500">Lokasi: {{ $check->location }}</div>
                        <div class="mt-1 text-sm text-gray-500">Checker: {{ $check->checkers->pluck('name')->implode(', ') ?: 'Belum ada yang bergabung' }}</div>
                        @if ($check->join_pin && $this->canViewSelectedPin())
                            <div class="mt-2 inline-flex rounded-lg bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-700 dark:bg-primary-950/30 dark:text-primary-300">
                                PIN Bergabung: <span class="ml-2 font-mono text-base">{{ $check->join_pin }}</span>
                            </div>
                        @endif
                    </div>
                    @if (! $check->join_pin && $this->canCompleteSelectedCheck())
                        <form wire:submit="setLegacyJoinPin" class="mx-auto w-full max-w-md rounded-lg border border-warning-300 p-3 text-left dark:border-warning-800">
                            <label class="mb-2 block text-sm font-semibold">Atur PIN untuk pemeriksaan lama</label>
                            <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
                                <x-filament::input type="password" inputmode="numeric" wire:model="legacyJoinPin" placeholder="4–6 angka" />
                                <x-filament::button type="submit">Simpan PIN</x-filament::button>
                            </div>
                            @error('legacyJoinPin') <div class="mt-1 text-sm text-danger-600">PIN harus terdiri dari 4–6 angka.</div> @enderror
                        </form>
                    @endif
                    @if (auth()->user()->isStockOfficer() && $check->status === \App\Enums\DamageCheckStatus::Open)
                        <x-filament::button color="danger" icon="heroicon-m-arrow-right-start-on-rectangle" class="w-full sm:w-auto" x-on:click.prevent="if (confirm('Keluar dari sesi? Untuk masuk lagi Anda harus memasukkan PIN.')) $wire.leaveCheck()">Keluar Sesi</x-filament::button>
                    @else
                        <x-filament::button color="gray" wire:click="backToList" icon="heroicon-m-arrow-left" class="w-full sm:w-auto">Kembali</x-filament::button>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Barang Rusak Tercatat</x-slot>
                <x-slot name="description">{{ $check->items_count + $check->pending_items_count }} jenis · {{ ($check->items_sum_qty_rusak_base ?? 0) + ($check->pending_items_sum_qty_rusak_base ?? 0) }} PCS</x-slot>

                @php($itemsData = $this->getItemsData())

                <div class="mb-3">
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.300ms="itemSearch"
                        placeholder="Cari nama, kode, atau barcode..."
                    />
                </div>

                @if ($itemsData['latestItem'])
                    @php($latest = $itemsData['latestItem'])
                    <div class="mb-3 rounded-lg border border-primary-200 bg-primary-50 p-3 dark:border-primary-800 dark:bg-primary-950/30">
                        <div class="text-xs font-semibold uppercase text-primary-700 dark:text-primary-300">Scan Terakhir</div>
                        <div class="mt-2 divide-y divide-primary-100 dark:divide-primary-900">
                                <div class="py-2 first:pt-0 last:pb-0">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="break-words text-sm font-semibold leading-snug">{{ $latest->itemMaster->nama_barang }}</div>
                                            <div class="mt-0.5 truncate font-mono text-xs text-gray-600 dark:text-gray-400">
                                                {{ $latest->itemMaster->principal?->nama ?: 'Tanpa prinsipal' }} · {{ $latest->itemMaster->kode_barang }}
                                            </div>
                                            <div class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">{{ $latest->lastScanner?->name ? 'Oleh ' . $latest->lastScanner->name : 'Scan terakhir' }}</div>
                                        </div>
                                        <div class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                                            @if ($check->status === \App\Enums\DamageCheckStatus::Open)
                                                <button type="button" wire:click="changeQuantity({{ $latest->id }}, -1)" @disabled($latest->qty_rusak_base <= 1) class="flex shrink-0 items-center justify-center rounded-lg text-xl font-bold text-white disabled:opacity-40" style="width: 40px; height: 40px; background-color: #f59e0b;">−</button>
                                            @endif
                                            <div class="min-w-16 text-center text-sm font-bold text-primary-700 dark:text-primary-300">{{ $latest->qty_rusak_display }}</div>
                                            @if ($check->status === \App\Enums\DamageCheckStatus::Open)
                                                <button type="button" wire:click="changeQuantity({{ $latest->id }}, 1)" class="flex shrink-0 items-center justify-center rounded-lg text-xl font-bold text-white" style="width: 40px; height: 40px; background-color: #16a34a;">+</button>
                                                <div x-data="{ open: false }" class="flex shrink-0 items-center gap-1">
                                                    <button type="button" x-on:click="open = ! open" class="flex items-center justify-center rounded-lg text-sm font-black text-white" style="width:40px;height:40px;background:#0d9488">++</button>
                                                    <div x-show="open" x-transition class="flex items-center gap-1">
                                                        <x-filament::input type="number" min="1" wire:model="bulkQty.{{ $latest->id }}" class="w-20 text-center font-bold" />
                                                        <button type="button" wire:click="addBulkQuantity({{ $latest->id }})" class="rounded-lg px-3 text-sm font-bold text-white" style="height:40px;background:#0d9488">Tambah</button>
                                                    </div>
                                                </div>
                                                <button type="button" aria-label="Hapus salah scan" x-on:click.prevent="if (confirm('Hapus item salah scan ini?')) $wire.deleteItem({{ $latest->id }})" class="flex shrink-0 items-center justify-center rounded-lg text-white" style="width: 40px; height: 40px; background-color: #dc2626;">
                                                    <x-filament::icon icon="heroicon-m-trash" class="h-5 w-5" />
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                        </div>
                    </div>
                @endif

                <div class="mb-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Daftar tersusun</div>
                <div class="space-y-2">
                    @forelse ($itemsData['items']->groupBy(fn ($row) => $row->itemMaster->principal?->nama ?: 'Tanpa prinsipal') as $principalName => $principalRows)
                        <div x-data="{ open: true }" class="rounded-lg border border-gray-200 dark:border-gray-700">
                            <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between gap-3 p-3 text-left">
                                <span class="min-w-0 truncate text-sm font-bold">{{ $principalName }}</span>
                                <span class="shrink-0 text-xs text-gray-500">{{ $principalRows->count() }} item</span>
                            </button>
                            <div x-show="open" class="space-y-2 border-t border-gray-100 p-3 dark:border-gray-800">
                                @foreach ($principalRows as $row)
                        <div
                            class="rounded-lg border border-gray-200 p-3 transition dark:border-gray-700"
                            x-on:click="toggleRow('item-{{ $row->id }}')"
                            x-bind:style="isChecked('item-{{ $row->id }}') ? 'background-color: rgba(37, 99, 235, 0.22); border-color: #3b82f6; box-shadow: 0 0 0 2px #3b82f6;' : ''"
                        >
                            <div class="min-w-0">
                                <div class="break-words text-sm font-semibold leading-snug">{{ $row->itemMaster->nama_barang }}</div>
                                <div class="mt-0.5 truncate font-mono text-xs text-gray-500">{{ $row->itemMaster->principal?->nama ?: 'Tanpa prinsipal' }} · {{ $row->itemMaster->kode_barang }} · {{ $row->itemMaster->barcode ?: 'Tanpa barcode' }}</div>
                                @if ($row->lastScanner)
                                    <div class="mt-0.5 text-xs text-gray-500">Scan terakhir: {{ $row->lastScanner->name }}</div>
                                @endif
                            </div>

                            <div class="mt-2 flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 pt-2 dark:border-gray-800" x-on:click.stop>
                                @if ($check->status === \App\Enums\DamageCheckStatus::Open)
                                    <button type="button" wire:click="changeQuantity({{ $row->id }}, -1)" @disabled($row->qty_rusak_base <= 1) class="flex shrink-0 items-center justify-center rounded-lg text-xl font-bold text-white disabled:opacity-40" style="width: 44px; height: 44px; background-color: #f59e0b;">−</button>
                                @endif
                                <div class="min-w-16 text-center text-base font-bold">{{ $row->qty_rusak_display }}</div>
                                @if ($check->status === \App\Enums\DamageCheckStatus::Open)
                                    <button type="button" wire:click="changeQuantity({{ $row->id }}, 1)" class="flex shrink-0 items-center justify-center rounded-lg text-xl font-bold text-white" style="width: 44px; height: 44px; background-color: #16a34a;">+</button>
                                    <div x-data="{ open: false }" class="flex shrink-0 items-center gap-1">
                                        <button type="button" x-on:click="open = ! open" class="flex items-center justify-center rounded-lg text-sm font-black text-white" style="width:44px;height:44px;background:#0d9488">++</button>
                                        <div x-show="open" x-transition class="flex items-center gap-1">
                                            <x-filament::input type="number" min="1" wire:model="bulkQty.{{ $row->id }}" class="w-20 text-center font-bold" />
                                            <button type="button" wire:click="addBulkQuantity({{ $row->id }})" class="rounded-lg px-3 text-sm font-bold text-white" style="height:44px;background:#0d9488">Tambah</button>
                                        </div>
                                    </div>
                                    <button type="button" aria-label="Hapus salah scan" x-on:click.prevent="if (confirm('Hapus item salah scan ini?')) $wire.deleteItem({{ $row->id }})" class="flex shrink-0 items-center justify-center rounded-lg text-white" style="width: 44px; height: 44px; background-color: #dc2626;">
                                        <x-filament::icon icon="heroicon-m-trash" class="h-5 w-5" />
                                    </button>
                                @endif
                            </div>
                        </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-gray-500 dark:border-gray-700">Belum ada barang. Mulai scan barcode.</div>
                    @endforelse
                </div>

                @if ($itemsData['total'] > $itemsData['items']->count())
                    <div class="mt-3">
                        <x-filament::button color="gray" class="w-full" wire:click="loadMoreItems">
                            Tampilkan 5 Lagi ({{ $itemsData['total'] - $itemsData['items']->count() }} tersisa)
                        </x-filament::button>
                    </div>
                @endif

                @php($pendingRows = $this->getPendingItemsData())
                @if ($pendingRows->isNotEmpty())
                    <div class="mt-4 space-y-2">
                        <div class="text-xs font-semibold uppercase text-warning-600">Barang Pending — menunggu pencocokan admin</div>
                        @foreach ($pendingRows as $row)
                            <div class="rounded-lg border border-warning-300 p-3 dark:border-warning-800">
                                <div class="text-sm font-semibold">{{ $row->pendingItem->item_name }}</div>
                                <div class="font-mono text-xs text-gray-500">{{ $row->pendingItem->barcode }} · {{ $row->pendingItem->temporary_code }}</div>
                                <div class="mt-2 flex items-center justify-end gap-2 border-t pt-2 dark:border-gray-800">
                                    @if ($check->status === \App\Enums\DamageCheckStatus::Open)<button type="button" wire:click="changePendingQuantity({{ $row->id }}, -1)" @disabled($row->qty_rusak_base <= 1) class="rounded-lg text-xl font-bold text-white disabled:opacity-40" style="width:44px;height:44px;background:#f59e0b">−</button>@endif
                                    <div class="min-w-16 text-center font-bold">{{ $row->qty_rusak_display }}</div>
                                    @if ($check->status === \App\Enums\DamageCheckStatus::Open)
                                        <button type="button" wire:click="changePendingQuantity({{ $row->id }}, 1)" class="rounded-lg text-xl font-bold text-white" style="width:44px;height:44px;background:#16a34a">+</button>
                                        <div x-data="{ open: false }" class="flex shrink-0 items-center gap-1">
                                            <button type="button" x-on:click="open = ! open" class="flex items-center justify-center rounded-lg text-sm font-black text-white" style="width:44px;height:44px;background:#0d9488">++</button>
                                            <div x-show="open" x-transition class="flex items-center gap-1">
                                                <x-filament::input type="number" min="1" wire:model="bulkQty.pending-{{ $row->id }}" class="w-20 text-center font-bold" />
                                                <button type="button" wire:click="addBulkPendingQuantity({{ $row->id }})" class="rounded-lg px-3 text-sm font-bold text-white" style="height:44px;background:#0d9488">Tambah</button>
                                            </div>
                                        </div>
                                        <button type="button" x-on:click.prevent="if(confirm('Hapus item ini?')) $wire.deletePendingItem({{ $row->id }})" class="inline-flex items-center justify-center rounded-lg text-white" style="width:44px;height:44px;background:#dc2626"><x-filament::icon icon="heroicon-m-trash" class="h-5 w-5" /></button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($check->status === \App\Enums\DamageCheckStatus::Open && $this->canCompleteSelectedCheck())
                    <div class="mt-5">
                        <x-filament::button color="success" size="lg" icon="heroicon-m-check-circle" class="w-full sm:w-auto" x-on:click.prevent="if (confirm('Selesaikan pemeriksaan? Data akan dikunci.')) $wire.completeCheck()">Selesaikan Pemeriksaan</x-filament::button>
                    </div>
                @endif
            </x-filament::section>
        @endif
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
