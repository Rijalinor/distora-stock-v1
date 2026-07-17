# Changelog

Semua perubahan signifikan pada proyek ini akan dicatat di sini.

---

## [0.3.0] — 2026-07-16

### Added
- **User Management** — Filament Resource untuk manage pengguna
  - `UserResource` di `Master Data` navigation group (admin only)
  - Create & Edit user dengan form (name, email, password, role)
  - Role badge (Admin / Stock Officer) di tabel
  - Password hashing otomatis (hash on create, optional on edit)
  - Soft-delete prevention via hard delete with confirmation
- **Reports Page** — Halaman laporan operasional (`/admin/reports`)
  - 100% komponen bawaan Filament (StatsOverviewWidget, Table, Action, DatePicker, ActionGroup)
  - Tanpa custom Blade view — menggunakan default page layout
  - Header action modal untuk filter tanggal (`DatePicker`)
  - Summary cards via `StatsOverviewWidget` (5 stat: Sesi, Total Item, Tercek, Sesuai, Selisih)
  - Tabel item selisih via `InteractsWithTable` (dengan filter Principal)
  - ActionGroup export: Download Laporan Harian & Download Data Selisih
  - Admin-only access

### Enhanced
- **ReportService** — New methods
  - `getDailySummary()` — aggregated stats per date
  - `getAllSelisihItems()` — all mismatched items across sessions
  - `buildDailyCsv()` — CSV export for daily session summary
  - `buildSelisihCsv()` — CSV export for all selisih items

### Resolved Issues (from v0.2.0)
- ✅ Filament Resource untuk User management — **Selesai**
- ✅ ReportService UI page — **Selesai**
- ✅ Database seeder — **Sudah ada** (tidak perlu dibuat)

---

## [0.2.0] — 2026-07-16

### Added
- **Stock Scanning Page** (`/admin/stock-scanning`) — Livewire-based barcode scanning UI
  - Pilih session principal untuk hari ini
  - Scan barcode atau input kode barang
  - Input qty aktual multi-level (CTN-PCS/PCK) dengan konversi otomatis
  - Tombol "Lengkap" untuk item sesuai sistem
  - Progress bar per session
  - Daftar item selisih untuk koreksi cepat
  - Kalkulasi base quantity dari multi-level factors
- **Stock Adjustment Logging** — Riwayat perubahan qty aktual
  - `StockAdjustmentLog` model & migration
  - Catat qty before/after + reason
- **Report Service** — `ReportService` class
  - `getSessionReport()` — detail item per session
  - `getSelisihReport()` — item selisih saja
  - `getDailyReport()` — summary per hari
  - `buildSessionCsv()` — export CSV report
- **StockSessionService** — `assignOfficer()`, `completeSession()`, `recalculateProgress()`
- **Feature Test** — `StockOpnameServicesTest` mencakup:
  - Parsing display format CSV
  - Parsing conversion factors dari nama barang
  - Kalkulasi & split base quantity
  - Sync database + generate sessions dari CSV
  - Scan & record stock (matched & mismatched)
  - Update stock dengan adjustment log
  - Complete session

### Architecture
- Service layer pattern: `CsvImportService`, `StockSessionService`, `StockScanningService`, `ReportService`
- DTOs: `CsvRowData`, `CsvPreviewResult`
- Backed Enums: `UserRole`, `StockSessionStatus`, `StockSessionItemStatus`, `CsvUploadStatus`
- Filament Admin Panel dengan Amber color theme
- Database schema: 7 migrations (users, principals, item_masters, csv_uploads, stock_sessions, stock_session_items, stock_adjustment_logs)

### Known Issues
- Belum ada Filament Resource untuk User management
- ReportService sudah ada method-nya tapi belum ada UI page di Filament
- Belum ada REST API untuk mobile app
- Belum ada database seeder untuk data awal

---

## [0.1.0] — 2026-07-15

### Added
- Setup Laravel 12 + Filament 5.6
- **Master Data CRUD** — Filament Resources
  - `PrincipalResource` (supplier/brand management)
  - `ItemMasterResource` (product catalog)
- **CSV Import** — Upload & parse CSV file
  - `CsvImportService.parseAndPreview()` — parse CSV dengan validasi kolom
  - `CsvUploadResource` — Filament CRUD untuk upload
  - Auto-sync principals & item masters dari CSV
- **Stock Session Generation** — `StockSessionService.generateSessions()`
  - Group by principal, 1 session per principal
  - Item-level status tracking (pending/matched/mismatched)
- **Database Migrations** — 7 migrasi siap pakai
  - Users (dengan role admin/stock_officer)
  - Principals (kode unik, nama, status)
  - Item Masters (kode_barang, barcode, nama, principal, satuan)
  - CSV Uploads (file tracking, status, summary)
  - Stock Sessions (session_date, assigned_to, progress counters)
  - Stock Session Items (multi-level qty, selisih, status)
  - Stock Adjustment Logs (audit trail qty changes)
- **Tailwind CSS 4 + Vite 7** setup
- **Sample data** — CSV file contoh di `excel/`

### Infrastructure
- SQLite sebagai database default (MySQL juga dikonfigurasi)
- Queue worker untuk background processing
- Automated setup script (`composer run setup`)
- Dev server script (`composer run dev`) dengan 4 proses paralel
