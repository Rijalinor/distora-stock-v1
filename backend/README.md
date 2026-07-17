# Distora Stock Backend

Backend aplikasi stock opname berbasis **Laravel 12** dan **Filament 5.6**.

Dokumentasi utama proyek ada di [README root](../README.md).

## Yang Ada di Backend

- Master data principal
- Master data item
- User management
- Import CSV stok
- Generate sesi stock opname
- Scan barcode
- Input qty aktual bertingkat
- Report harian
- Export CSV
- Penutupan sesi harian

## Struktur Qty

Backend menyimpan qty dalam format:

- `*_base` untuk nilai dasar
- `*_display` untuk tampilan manusia

Contoh:

- `qty_sistem_base`
- `qty_sistem_display`
- `qty_aktual_base`
- `qty_aktual_display`
- `selisih`

## Quick Start

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
```

## Development

```bash
composer run dev
```

Komponen yang berjalan:

- `php artisan serve`
- `npm run dev`
- `php artisan queue:listen`
- `php artisan pail`

## Testing

```bash
php artisan test
```

## Default Login

- `admin@distora.com` / `password`
- `officer1@distora.com` / `password`
- `officer2@distora.com` / `password`

## Folder Penting

| Path | Isi |
|---|---|
| `app/Services/` | Business logic |
| `app/Filament/` | Pages, widgets, resources |
| `app/Models/` | Model Eloquent |
| `database/migrations/` | Skema database |
| `resources/views/filament/` | Blade custom Filament |
| `tests/Feature/` | Test feature |

## Catatan Operasional

- Dashboard diarahkan ke shortcut scan barcode.
- Halaman scan barcode memakai sesi aktif.
- Sesi harian bisa ditutup oleh admin setelah review principal yang belum selesai.
- Laporan menampilkan ringkasan sesi dan item selisih.
