# Distora Stock — Aplikasi Stock Opname

Aplikasi **stock opname** (stock taking / physical inventory counting) untuk distributor — berbasis **Laravel 12** + **Filament 5.6** admin panel.

---

## Fitur

| Fitur | Status |
|---|---|
| Master Data Principal (supplier/brand) | ✅ Selesai |
| Master Data Item (produk) | ✅ Selesai |
| Manajemen User & Role (CRUD + assign role) | ✅ Selesai |
| Import CSV data stok dari sistem lama | ✅ Selesai |
| Generate sesi stock opname per principal | ✅ Selesai |
| Scan barcode / kode barang | ✅ Selesai |
| Catat qty aktual multi-level (CTN-PCS/PCK) | ✅ Selesai |
| Hitung selisih otomatis | ✅ Selesai |
| Koreksi qty dengan log adjustment | ✅ Selesai |
| Progress tracking per sesi | ✅ Selesai |
| Report & export CSV (harian / selisih) | ✅ Selesai |
| Mobile App (pendamping) | ❌ Belum dimulai |
| REST API | ❌ Belum dimulai |

---

## Tech Stack

| Layer | Teknologi |
|---|---|
| **Backend** | PHP 8.2, Laravel 12 |
| **Admin Panel** | Filament 5.6 |
| **Frontend** | Tailwind CSS 4, Vite 7 |
| **Database** | SQLite (default) / MySQL |
| **Testing** | PHPUnit 11 |

---

## Persyaratan Sistem

- PHP ^8.2
- Composer
- Node.js & npm (untuk Vite)
- SQLite atau MySQL/MariaDB

---

## Instalasi

```bash
# 1. Clone & masuk ke direktori backend
cd backend

# 2. Copy environment
cp .env.example .env

# 3. Install dependencies PHP
composer install

# 4. Generate app key
php artisan key:generate

# 5. Buat database SQLite (jika pakai SQLite)
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"

# 6. Jalankan migrasi
php artisan migrate

# 7. Install & build asset frontend
npm install
npm run build

# 8. Seed user default (admin + 2 stock officers)
php artisan db:seed

# Login: admin@distora.com / password
# Atau: officer1@distora.com / password
```

### Development Mode

```bash
# Di folder backend:
composer run dev
```

Ini akan menjalankan 4 proses paralel:
- `php artisan serve` — Laravel dev server
- `php artisan queue:listen` — Queue worker
- `php artisan pail` — Log viewer
- `npm run dev` — Vite HMR

---

## Struktur Direktori

```
distora-stock/
├── backend/              # Aplikasi Laravel utama
│   ├── app/
│   │   ├── DTOs/         # Data Transfer Objects
│   │   ├── Enums/        # Backed Enums (UserRole, Status, dll)
│   │   ├── Filament/     # Admin panel (Pages, Resources)
│   │   ├── Models/       # Eloquent Models
│   │   └── Services/     # Business Logic (CsvImport, StockScanning, dll)
│   ├── database/         # Migrations, Factories, Seeders
│   ├── routes/           # Web routes
│   └── tests/            # PHPUnit tests
├── docs/                 # Dokumentasi (belum diisi)
├── excel/                # Sample CSV data
├── mobile/               # Rencana mobile app (belum dimulai)
└── *.md                  # Dokumentasi proyek
```

---

## Workflow

1. **Admin upload CSV** → data stok dari sistem lama di-import
2. **Sistem buat sesi** → 1 sesi per principal, berisi daftar item
3. **Petugas scan barcode** → pilih sesi → scan → input qty aktual
4. **Sistem hitung selisih** → matched/mismatched otomatis
5. **Sesi selesai** → semua item ter-check
6. **Report** → lihat & export data selisih

---

## Testing

```bash
cd backend
composer run test
```

Atau:

```bash
cd backend
php artisan test
```

---

## Lisensi

Proyek internal Distora.
