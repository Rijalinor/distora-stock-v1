# Distora Stock Backend

Backend Distora Stock adalah aplikasi Laravel 12 dengan Filament 5.6. Semua
operasi utama dilakukan dari admin panel di `/admin`.

## Modul Backend

| Modul | Lokasi | Fungsi |
|---|---|---|
| CSV Import | `app/Services/CsvImportService.php` | Parse CSV stok dan sync database |
| Sesi Stock | `app/Services/StockSessionService.php` | Generate sesi, assign petugas, hitung progress |
| Scan Stock | `app/Services/StockScanningService.php` | Lookup barcode, hitung qty, record aktual |
| Laporan | `app/Services/ReportService.php` | Query laporan dan build CSV export |
| Checker Barang Rusak | `app/Services/DamageCheckService.php` | Header pemeriksaan, multi-checker, scan barang rusak, pending item |
| Laporan Barang Rusak | `app/Services/DamageCheckReportService.php` | Query laporan dan CSV barang rusak |
| Backup Item | `app/Services/ItemMasterBackupService.php` | Backup/restore Item Master Excel-safe |
| Audit Log | `app/Services/AuditLogService.php` | Catat perubahan penting |

## Filament Area

| Area | Lokasi |
|---|---|
| Scan Barcode | `app/Filament/Pages/StockScanning.php` |
| Checker Barang Rusak | `app/Filament/Pages/DamageChecker.php` |
| Laporan Barang Rusak | `app/Filament/Pages/DamageCheckReports.php` |
| Laporan | `app/Filament/Pages/Reports.php` |
| Item Master | `app/Filament/Resources/ItemMasters/` |
| Principal | `app/Filament/Resources/Principals/` |
| Upload Stok Harian | `app/Filament/Resources/CsvUploads/` |
| Sesi Stock | `app/Filament/Resources/StockSessions/` |
| Pengguna | `app/Filament/Resources/Users/` |
| Audit Log | `app/Filament/Resources/AuditLogs/` |

## Qty Rules

Backend menyimpan qty dalam dua format:

- `qty_*_base`: integer dalam satuan terkecil.
- `qty_*_display`: tampilan user, seperti `1 CTN 2 PCK 15 PCS`.

`selisih` tetap disimpan sebagai base untuk perhitungan, tetapi UI dan export
laporan menampilkan format display.

Mode pisah CTN/PCS hanya memengaruhi input dan penyimpanan internal
`qty_aktual_ctn` / `qty_aktual_pcs`. Laporan tetap memakai format display
standar seperti `16 CTN 5 PCS`.

## Barcode Rules

- Scan menerima barcode atau `kode_barang`.
- Lookup barcode dibatasi pada `stock_session_items` dari sesi aktif.
- Jika satu barcode cocok ke beberapa item dalam sesi yang sama, UI meminta petugas memilih kode barang.
- Item Master memiliki kolom/filter untuk melihat barcode duplikat.

## CSV Export Rules

Kode dan barcode pada export CSV dibuat Excel-safe dengan format teks Excel
agar tidak berubah menjadi tanggal, scientific notation, atau kehilangan nol
depan.

Contoh nilai export:

```csv
"=""8991234567890"""
```

Restore Item Master juga memahami format tersebut.

## Development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
php artisan serve --host=127.0.0.1 --port=8010
```

## Test

```bash
php artisan test
```

Status terakhir: 48 test pass.

## Production

Production mode memakai:

- `APP_ENV=production`
- `APP_DEBUG=false`
- MySQL
- database session/cache/queue
- cached config, routes, views, Filament components, dan Blade icons

Perintah cache:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
php artisan storage:link
```

## Default Login

| Email | Password | Role |
|---|---|---|
| `admin@distora.com` | `password` | Admin |
| `officer1@distora.com` | `password` | Stock Officer |
| `officer2@distora.com` | `password` | Stock Officer |

## Catatan Implementasi

- Gunakan service layer untuk business logic.
- Gunakan transaksi database untuk operasi multi-table.
- Jangan simpan qty pecahan di database. Semua base qty adalah integer.
- UI scan memakai custom Blade di `resources/views/filament/pages/stock-scanning.blade.php`.
- Reports page memakai Filament table bawaan, bukan custom Blade besar.
- Checker barang rusak memakai custom Blade di `resources/views/filament/pages/damage-checker.blade.php`.
