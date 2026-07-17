# Project Status

> Terakhir diperbarui: 2026-07-16 (v0.3.0)

---

## Ringkasan

Proyek **Distora Stock** adalah aplikasi stock opname untuk perusahaan distribusi. Saat ini memasuki tahap **Alpha** — fitur inti sudah berfungsi, masih ada beberapa komponen yang belum terintegrasi penuh dengan UI.

---

## ✅ Selesai (100%)

### Backend Infrastructure
- [x] Laravel 12 setup
- [x] Filament 5.6 admin panel
- [x] Vite + Tailwind CSS 4 asset pipeline
- [x] Database migrations (7 tables)
- [x] SQLite & MySQL support
- [x] Queue worker configuration
- [x] Environment configuration (.env.example)

### Data Layer
- [x] Eloquent Models & Relationships
- [x] Backed Enums (UserRole, Statuses)
- [x] DTOs (CsvRowData, CsvPreviewResult)

### Master Data
- [x] Principal CRUD (Filament Resource)
- [x] Item Master CRUD (Filament Resource)
- [x] CSV Upload & Preview (Filament Resource)

### Stock Opname Core Logic
- [x] CSV Parsing (multi-level qty, conversion factors)
- [x] Database sync (Principals & Items from CSV)
- [x] Stock Session generation (group by principal)
- [x] Stock Session management (assign officer, complete)
- [x] Barcode scanning (find by barcode / kode_barang)
- [x] Multi-level quantity input (CTN-PCS-PCK)
- [x] Base quantity calculation & splitting
- [x] Auto status (matched/mismatched)
- [x] Adjustment logging with reason
- [x] Progress recalculation

### UI / Frontend
- [x] Scan Barcode page (Livewire, real-time)
- [x] Session selection (today's sessions only)
- [x] Progress bar per session
- [x] Quick-edit item selisih
- [x] Complete session button
- [x] **Reports Page** — date filter, summary cards, session list, selisih items table
- [x] **Reports Export** — download CSV harian & CSV selisih

### User Management
- [x] User CRUD (Filament Resource — Pengguna)
- [x] Role assignment (admin / stock_officer)
- [x] Restricted to admin only (canViewAny)

### Testing
- [x] Feature test: parsing, conversion factors, base qty
- [x] Feature test: full workflow (sync → generate → scan → record → adjust → complete)

----

## 🟡 Nearly Done (50-99%)

_Tidak ada._ Semua komponen inti sudah terintegrasi penuh dengan UI.

---

## ❌ Belum Dimulai

### Mobile App Companion
- `mobile/` directory masih kosong
- Belum ada REST API di backend
- Belum ada arsitektur / tech stack decided
- Target: aplikasi Flutter/React Native untuk scan pakai kamera HP

### API Layer
- Tidak ada API routes / controllers
- Semua interaksi via Filament (server-rendered)

### Deployment / Infrastructure
- Belum ada Dockerfile
- Belum ada CI/CD pipeline
- Belum ada deployment script

### Dokumentasi
- `docs/` directory masih kosong
- Belum ada user manual / SOP

---

## 🚧 Dalam Antrian (Next Up)

| Priority | Feature | Estimasi |
|---|---|---|
| P1 | Input validation (qtyLevels negatif, non-numeric) | 0.5 hari |
| P1 | Konfirmasi dialog sebelum complete session | 0.5 hari |
| P2 | Dashboard widget (daily summary, recent sessions) | 1 hari |
| P3 | REST API endpoints | 3-5 hari |
| P3 | Mobile app scanning | TBD |

---

## Metrics

| Metrik | Nilai |
|---|---|
| Total Files (PHP) | ~53 |
| Migrations | 7 |
| Test Cases | 7 (dalam 1 test class) |
| Test Pass Rate | 100% |
| PHP Version | ^8.2 |
| Laravel Version | ^12.0 |
| Filament Version | ^5.6 |
| Database | SQLite (dev), MySQL (prod-ready) |

### Changelog Rilis

- **v0.3.0** — User Management UI + Reports Page + CSV Export + Database Seeder
- **v0.2.0** — Stock Scanning Page + Adjustment Log + Report Service + Feature Tests
- **v0.1.0** — Setup Laravel + Filament + Master Data CRUD + CSV Import + Session Generation
