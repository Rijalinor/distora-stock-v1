# Distora Stock

Sistem **stock opname** untuk distributor berbasis **Laravel 12** dan **Filament 5.6**.

Fokus utama aplikasi ini:
- import data stok dari CSV
- buat sesi stock opname per principal
- scan barcode / kode barang
- input qty aktual bertingkat
- hitung selisih otomatis
- tutup sesi harian
- export laporan harian dan laporan selisih

## Ringkasan

- **Backend:** Laravel 12
- **Admin Panel:** Filament 5.6
- **UI:** Server-rendered, Tailwind CSS 4
- **Database:** SQLite untuk development, MySQL untuk production
- **Testing:** PHPUnit 11
- **Arsitektur:** Monolith dengan service layer

## Fitur Utama

### Master Data
- Principal CRUD
- Item Master CRUD
- User management untuk admin
- Upload dan preview CSV stok

### Stock Opname
- Generate sesi per principal
- Scan barcode atau kode barang
- Input qty aktual multi-level
- Perhitungan qty dasar otomatis
- Status item: pending, matched, mismatched
- Koreksi item dengan log adjustment
- Progress sesi realtime
- Selesai sesi dengan konfirmasi

### Laporan
- Ringkasan harian
- Daftar sesi stock opname
- Daftar item selisih
- Export CSV laporan harian
- Export CSV item selisih

### Mobile / Future
- Folder `mobile/` disiapkan untuk aplikasi Flutter
- Saat ini mobile app belum menjadi satu produk penuh
- Rencana berikutnya: scan barcode via kamera pada aplikasi mobile

## Alur Operasional

1. Admin upload CSV stok.
2. Sistem membaca data principal dan item.
3. Sistem membuat sesi stock opname per principal.
4. Petugas memilih sesi yang aktif.
5. Petugas scan barcode atau input kode barang.
6. Petugas isi qty aktual.
7. Sistem hitung selisih otomatis.
8. Admin menutup sesi harian setelah semua prinsipalnya dicek.
9. Admin download laporan harian dan laporan selisih.

## Struktur Qty

Proyek ini menyimpan qty dalam bentuk:
- `*_base` = satuan dasar / PCS
- `*_display` = tampilan manusia, misalnya `1 CTN 12 PCS`

Contoh:
- `CTN = 12 PCS`
- `PCS = 1 PCS`
- qty aktual bisa dipecah menjadi beberapa level

## Role

### Admin
- full access
- kelola master data
- kelola user
- tutup sesi harian
- lihat dan export laporan

### Stock Officer
- fokus ke sesi stock opname
- scan barcode
- input qty aktual
- update item jika perlu
- tidak fokus ke pengelolaan data master

## Login Default

Seeder tersedia untuk data awal:

- `admin@distora.com` / `password`
- `officer1@distora.com` / `password`
- `officer2@distora.com` / `password`

## Instalasi

Masuk ke folder backend:

```bash
cd backend
```

Install dependency:

```bash
composer install
npm install
```

Siapkan environment:

```bash
cp .env.example .env
php artisan key:generate
```

Jalankan migrasi:

```bash
php artisan migrate
```

Jika pakai SQLite, buat file database dulu:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

Jalankan seeder:

```bash
php artisan db:seed
```

Build asset:

```bash
npm run build
```

## Development

Jalankan semua proses dev:

```bash
composer run dev
```

Atau manual:

```bash
php artisan serve
npm run dev
php artisan queue:listen
php artisan pail
```

## Operasional Lokal Satu Klik

Untuk menjalankan aplikasi dari PC kantor/gudang:

```bat
START-DISTORA.bat
```

Untuk stop:

```bat
STOP-DISTORA.bat
```

Panduan lengkap ada di `docs/OPERASIONAL-LOKAL.md`.

## Testing

```bash
php artisan test
```

## Struktur Folder

```text
distora-stock/
├── backend/                  # Aplikasi Laravel utama
│   ├── app/
│   │   ├── DTOs/
│   │   ├── Enums/
│   │   ├── Filament/
│   │   ├── Models/
│   │   └── Services/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   └── tests/
├── mobile/                   # Rencana Flutter mobile app
├── docs/                     # Dokumentasi tambahan
├── excel/                    # Contoh file CSV / Excel
├── CHANGELOG.md
├── PROJECT_STATUS.md
└── SESSION_HANDOVER.md
```

## Catatan Teknis

- Semua interaksi utama saat ini lewat Filament panel.
- REST API belum menjadi bagian utama arsitektur.
- Barcode matching mencari `barcode` dulu, lalu fallback ke `kode_barang`.
- Sesi stock opname berjalan per tanggal aktif.
- Penutupan sesi harian dilakukan oleh admin.

## Dokumentasi Tambahan

- `CHANGELOG.md` — riwayat perubahan
- `PROJECT_STATUS.md` — status fitur
- `SESSION_HANDOVER.md` — konteks teknis cepat
- `docs/OPERASIONAL-LOKAL.md` — panduan start/stop lokal satu klik
- `backend/README.md` — ringkasan backend

## Lisensi

Proyek internal Distora.
