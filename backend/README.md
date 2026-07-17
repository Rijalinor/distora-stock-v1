# Distora Stock — Backend

Aplikasi Laravel 12 + Filament 5.6 untuk stock opname (stock taking).

> Dokumentasi lengkap: lihat `../README.md` di root proyek.

## Quick Start

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install && npm run build
```

## Development

```bash
composer run dev
```

Atau jalankan per komponen:

```bash
php artisan serve           # Server di localhost:8000
npm run dev                 # Vite HMR
php artisan queue:listen    # Queue worker
php artisan pail            # Log viewer
```

## Testing

```bash
php artisan test
```

## Structure

| Path | Description |
|---|---|
| `app/Services/` | Business logic layer |
| `app/Filament/` | Admin panel (Pages & Resources) |
| `app/Models/` | Eloquent models |
| `app/DTOs/` | Data transfer objects |
| `app/Enums/` | Backed enums |
| `database/migrations/` | Schema definitions |
| `tests/Feature/` | Feature tests |
