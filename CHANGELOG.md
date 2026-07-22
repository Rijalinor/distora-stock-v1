# Changelog

Semua perubahan signifikan pada proyek ini dicatat di sini.

## [0.4.0] - 2026-07-22

### Added

- Search pada daftar **Belum Dicek** di halaman scan.
- Kode barang pada daftar **Belum Dicek** dan **Item Selisih**.
- Tombol Edit pada item belum dicek.
- Tombol **Tidak Ada** langsung dari item belum dicek, dengan konfirmasi.
- Tombol **Ganti Principal** pada ringkasan sesi.
- Indikator jumlah barcode duplikat di Item Master.
- Filter **Barcode Duplikat** di Item Master.
- Format display untuk selisih di tabel laporan dan detail sesi.
- Test export laporan dengan qty display unit.

### Changed

- `startEditItem()` hanya masuk mode koreksi jika item sudah pernah dicek.
- Backup Item Master membuat kode/barcode sebagai teks Excel-safe.
- Restore Item Master bisa membaca nilai Excel-safe seperti `="8991234567890"`.
- Export laporan harian memakai display unit, bukan base mentah.
- Export laporan harian menghapus kolom Plus dan Minus.
- Export laporan harian, selisih, dan sesi membuat kode/barcode Excel-safe.
- `STOP-DISTORA.bat` menutup CMD otomatis setelah selesai.
- Form qty structure Item Master menjaga factor `PCS` sebagai `1`.

### Fixed

- Barcode panjang tidak lagi mudah berubah menjadi scientific notation saat CSV dibuka di Excel.
- Selisih laporan tidak lagi tampil sebagai angka base mentah.
- Barcode duplikat tetap diselesaikan dalam scope sesi aktif, bukan seluruh master.

## [0.3.0] - 2026-07-16

### Added

- User Management Filament Resource untuk admin.
- Reports Page di `/admin/reports`.
- Filter tanggal dan principal pada laporan.
- Summary cards laporan.
- Tabel item selisih.
- Export laporan harian dan data selisih.
- Database seeder untuk admin dan stock officer.

### Enhanced

- `ReportService::getDailySummary()`.
- `ReportService::getAllSelisihItems()`.
- `ReportService::buildDailyCsv()`.
- `ReportService::buildSelisihCsv()`.

## [0.2.0] - 2026-07-16

### Added

- Stock Scanning Page di `/admin/stock-scanning`.
- Pilih sesi principal hari ini.
- Scan barcode atau input kode barang.
- Input qty aktual multi-level.
- Tombol "Lengkap" untuk item sesuai sistem.
- Progress bar per session.
- Daftar item selisih untuk koreksi cepat.
- Stock adjustment logging.
- `StockAdjustmentLog` model dan migration.
- `ReportService` awal.
- `StockSessionService::assignOfficer()`.
- `StockSessionService::completeSession()`.
- `StockSessionService::recalculateProgress()`.
- Feature test untuk workflow stock opname.

### Architecture

- Service layer: `CsvImportService`, `StockSessionService`, `StockScanningService`, `ReportService`.
- DTO: `CsvRowData`, `CsvPreviewResult`.
- Backed enum: `UserRole`, `StockSessionStatus`, `StockSessionItemStatus`, `CsvUploadStatus`.
- Filament Admin Panel dengan Amber theme.

## [0.1.0] - 2026-07-15

### Added

- Setup Laravel 12 dan Filament 5.6.
- Principal CRUD.
- Item Master CRUD.
- CSV upload dan parse.
- Auto-sync principal dan item master.
- Generate stock session per principal.
- Database migrations inti.
- Tailwind CSS 4 dan Vite 7.
- Sample file di `excel/`.

### Infrastructure

- SQLite sebagai database default development.
- MySQL dikonfigurasi untuk production.
- Composer setup/dev scripts.

