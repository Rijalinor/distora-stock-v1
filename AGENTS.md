# AGENTS.md — Petunjuk untuk AI Agent

## Project Overview

Aplikasi **Distora Stock** — Stock Opname (physical inventory counting) untuk distributor.
- **Stack:** Laravel 12 + Filament 5.6 admin panel
- **Architecture:** Monolith dengan Service Layer pattern
- **UI:** Filament (server-rendered, Livewire), Tailwind CSS 4
- **Database:** SQLite (dev), MySQL (prod)
- **Testing:** PHPUnit 11

## Directory Structure

```
backend/                         # Laravel application
├── app/
│   ├── DTOs/                    # Data Transfer Objects (CsvRowData, CsvPreviewResult)
│   ├── Enums/                   # Backed enums (UserRole, statuses)
│   ├── Filament/
│   │   ├── Pages/               # Custom pages (StockScanning, Reports)
│   │   └── Resources/           # CRUD resources (Users, Principals, ItemMasters, StockSessions, CsvUploads)
│   ├── Models/                  # Eloquent models
│   └── Services/                # Business logic layer
│       ├── CsvImportService.php
│       ├── StockSessionService.php
│       ├── StockScanningService.php
│       └── ReportService.php
├── database/
│   ├── migrations/              # 7 migration files
│   └── seeders/                 # DatabaseSeeder (admin + 2 stock officers)
├── routes/
│   ├── web.php                  # Minimal (welcome route only)
│   └── console.php
└── tests/
    └── Feature/
        └── StockOpnameServicesTest.php
```

## Key Conventions

### Naming
- **Controllers:** Minimal usage; business logic in Services
- **Services:** PascalCase, `*Service.php`
- **DTOs:** PascalCase, in `App\DTOs`
- **Enums:** Backed string enums in `App\Enums`
- **Models:** Singular, PascalCase
- **Filament Resources:** Grouped by domain in `App\Filament\Resources\{Domain}\`
- **Filament Tables/Schemas:** Separated into `Tables/` and `Schemas/` subdirectories

### Code Style
- PHP 8.2+ features (constructor promotion, match, readonly properties)
- No `@property` docblocks for Eloquent models
- No docblocks for simple methods (use type hints only)
- Transactions in Service layer, not controllers
- All quantities stored as integer `*_base` fields with display string `*_display`

### Database Schema Conventions
- `*_base` fields: integer, represents quantity in smallest unit (PCS)
- `*_display` fields: string, human-readable (e.g., "5 CTN 12 PCS")
- `selisih` = `qty_aktual_base - qty_sistem_base`
- Table names: snake_case, plural
- Composite indexes for frequent lookup patterns

### Workflow
1. CSV Upload → Parse & Preview → Sync Database
2. Generate Sessions (grouped by principal)
3. Assign Officer → Session status: `open` → `in_progress`
4. Scan Barcode → Input Aktual Qty → Matched? Mismatched?
5. Optional: Update Stock (with adjustment log)
6. Complete Session → `completed`

## Important Notes

- **No REST API** — semua interaksi via Filament panel
- **Seeder sudah ada** — jalankan `php artisan db:seed` untuk membuat admin & 2 stock officers
  - Admin: `admin@distora.com` / `password`
  - Officer 1: `officer1@distora.com` / `password`
  - Officer 2: `officer2@distora.com` / `password`
- **Reports Page** — custom Blade view (`reports.blade.php`) dengan date filter, summary cards, tables, dan CSV export
- **StockScanning** page menggunakan custom Blade view (`stock-scanning.blade.php`), bukan form builder
- **Auth:** Filament built-in auth, route prefix `/admin`
- **Roles:** `admin` (full access) | `stock_officer` (limited to assigned sessions)
- **User Management Resource** — hanya bisa diakses oleh admin (`canViewAny()`)

## Common Tasks

### Menambah resource baru
1. Buat model + migration
2. Buat Filament Resource dengan struktur `Resources/{Domain}/{Name}Resource.php`
3. Pisahkan form schema ke `Schemas/{Name}Form.php`
4. Pisahkan table config ke `Tables/{Name}Table.php`
5. Buat Pages: `List{Name}`, `Create{Name}`, `Edit{Name}` (jika perlu)

### Menambah service baru
1. Buat class di `app/Services/`
2. Inject dependencies via constructor
3. Gunakan `DB::transaction()` untuk operasi multi-tabel
4. Buat unit/feature test di `tests/Feature/`

### Test
```bash
cd backend
php artisan test
# atau
composer run test


