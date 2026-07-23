# Distora Stock

Distora Stock adalah aplikasi stock opname untuk distributor. Aplikasi ini
dipakai untuk upload stok harian, membuat sesi opname per principal, membantu
petugas scan barang, mencatat qty aktual, menghitung selisih, dan membuat
laporan.

## Ringkasan Stack

| Komponen | Teknologi |
|---|---|
| Backend | Laravel 12 |
| Admin panel | Filament 5.6, Livewire |
| UI | Server-rendered, Tailwind CSS 4 |
| Database | SQLite untuk development, MySQL untuk produksi |
| Testing | PHPUnit 11 |
| Arsitektur | Monolith dengan service layer |

## Fitur Utama

### Master Data

- Principal CRUD.
- Item Master CRUD.
- User management untuk admin.
- Backup dan restore Item Master.
- Deteksi barcode duplikat di Item Master.
- Scanner barcode di form Item Master.

### Upload Stok Harian

- Upload CSV stok.
- Preview hasil parsing.
- Sync principal dan item master dari CSV.
- Generate sesi stock opname per principal.

### Scan Barcode

- Petugas memilih sesi principal hari ini.
- Scan dari kamera atau input manual barcode/kode barang.
- Barcode dicari hanya pada item yang ada di sesi aktif.
- Jika barcode dipakai beberapa item dalam sesi yang sama, petugas memilih kode barang yang benar.
- Daftar item belum dicek bisa dicari berdasarkan kode atau nama barang.
- Item belum dicek bisa langsung diedit atau ditandai "Tidak Ada".
- Item selisih bisa dikoreksi cepat.
- Qty aktual mendukung multi-level seperti `CTN-PCK-PCS`.
- Progress sesi dihitung otomatis.

### Laporan

- Ringkasan harian: sesi, total item, tercek, sesuai, selisih.
- Tabel item selisih.
- Filter tanggal dan principal.
- Export Laporan Harian.
- Export Data Selisih.
- Export detail Sesi Stock.
- Selisih ditampilkan dalam satuan manusia, bukan base mentah, misalnya `-5 PCS` atau `1 CTN 2 PCK 15 PCS`.
- CSV export dibuat Excel-safe untuk kode dan barcode panjang.

## Alur Operasional

1. Admin upload CSV stok harian.
2. Sistem sync principal dan item master.
3. Sistem membuat sesi stock opname per principal.
4. Petugas membuka menu **Scan Barcode**.
5. Petugas memilih principal/sesi.
6. Petugas scan barcode atau pilih item dari daftar belum dicek.
7. Petugas mencatat qty aktual, menandai lengkap, atau menandai tidak ada.
8. Admin review item selisih dan laporan.
9. Admin download laporan harian atau data selisih.

## Struktur Qty

Database menyimpan dua bentuk qty:

- `*_base`: integer dalam satuan terkecil, biasanya PCS.
- `*_display`: string untuk dibaca user, misalnya `6 CTN 37 PCS`.

Contoh struktur `CTN-PCK-PCS`:

- 1 CTN berisi beberapa PCK.
- 1 PCK berisi beberapa PCS.
- Nilai base akan dipecah lagi menjadi display saat tampil di laporan.

## Role

| Role | Akses |
|---|---|
| Admin Pusat | Semua cabang, semua master data, semua sesi, semua laporan, user management |
| Admin Cabang | Item master cabang sendiri, upload stok cabang, sesi cabang, laporan cabang |
| Stock Officer | Scan barcode dan update item pada sesi cabangnya |

## Password User

- Semua user bisa mengganti password sendiri dari menu **Ganti Password**.
- Password lama wajib diisi saat user mengganti password sendiri.
- Admin pusat bisa reset password user dari menu **Pengguna** dengan mengisi field password baru.
- Jika field password di form edit pengguna dikosongkan, password lama tidak berubah.

## Struktur Cabang

Sistem mendukung pemakaian multi cabang. Admin pusat bisa melihat semua data
secara global, sedangkan admin cabang hanya mengelola cabangnya sendiri.

Untuk contoh struktur 3 cabang, lihat
[docs/STRUKTUR-3-CABANG.md](docs/STRUKTUR-3-CABANG.md).

## Login Default

Seeder membuat user berikut:

| Email | Password | Role |
|---|---|---|
| `admin@distora.com` | `password` | Admin |
| `officer1@distora.com` | `password` | Stock Officer |
| `officer2@distora.com` | `password` | Stock Officer |

## Membuat Admin Pusat Pertama Kali

Admin pusat adalah user dengan role `admin` dan cabang kosong atau `null`.
Untuk memastikan user bawaan menjadi admin pusat, jalankan:

```bash
cd backend
php artisan db:seed
php artisan user:make-central-admin admin@distora.com
```

Setelah itu login dengan:

```text
Email: admin@distora.com
Password: password
```

Admin pusat bisa melihat semua cabang, semua item master, semua sesi, semua
laporan, dan membuat admin cabang. Detail struktur cabang ada di
[docs/STRUKTUR-3-CABANG.md](docs/STRUKTUR-3-CABANG.md).

## Quick Start Development

```bash
cd backend
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
php artisan serve --host=127.0.0.1 --port=8010
```

Buka:

```text
http://127.0.0.1:8010/admin
```

## Operasional Lokal

Untuk PC kantor/gudang, gunakan script root project:

```bat
START-DISTORA.bat
```

Untuk stop:

```bat
STOP-DISTORA.bat
```

Panduan lengkap ada di [docs/OPERASIONAL-LOKAL.md](docs/OPERASIONAL-LOKAL.md).

## Testing

```bash
cd backend
php artisan test
```

Status terakhir: 15 test pass.

## Struktur Folder

```text
distora-stock/
|-- backend/                  # Aplikasi Laravel
|   |-- app/
|   |   |-- DTOs/
|   |   |-- Enums/
|   |   |-- Filament/
|   |   |-- Models/
|   |   `-- Services/
|   |-- database/
|   |-- resources/
|   |-- routes/
|   `-- tests/
|-- docs/                     # Dokumentasi operasional
|-- excel/                    # Contoh file CSV/Excel
|-- mobile/                   # Companion app/mobile work area
|-- START-DISTORA.bat
|-- STOP-DISTORA.bat
|-- CHANGELOG.md
|-- PROJECT_STATUS.md
`-- SESSION_HANDOVER.md
```

## Dokumentasi Tambahan

- [CHANGELOG.md](CHANGELOG.md): riwayat perubahan.
- [PROJECT_STATUS.md](PROJECT_STATUS.md): status fitur dan backlog.
- [SESSION_HANDOVER.md](SESSION_HANDOVER.md): konteks teknis untuk lanjut kerja.
- [docs/OPERASIONAL-LOKAL.md](docs/OPERASIONAL-LOKAL.md): SOP start/stop lokal.
- [docs/STRUKTUR-3-CABANG.md](docs/STRUKTUR-3-CABANG.md): struktur operasional 3 cabang.
- [backend/README.md](backend/README.md): catatan teknis backend.

## Catatan Penting

- Semua workflow utama berjalan melalui Filament panel.
- Scan page hanya menampilkan sesi pada tanggal hari ini.
- Jangan edit CSV backup di aplikasi yang mengubah format cell tanpa mengecek hasilnya.
- Untuk barcode panjang, export CSV sudah dibuat Excel-safe, tapi tetap disarankan cek sample sebelum restore massal.
