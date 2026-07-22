# Project Status

Terakhir diperbarui: 2026-07-22

## Ringkasan

Distora Stock sudah memiliki workflow inti yang bisa dipakai untuk stock
opname harian:

CSV upload -> sync master data -> generate sesi -> scan/input qty -> review
selisih -> export laporan.

Status saat ini: **operasional internal / alpha stabil**.

## Selesai

### Infrastruktur

- [x] Laravel 12.
- [x] Filament 5.6 admin panel.
- [x] Tailwind CSS 4 dan Vite.
- [x] SQLite development dan MySQL production-ready.
- [x] Seeder user default.
- [x] Script start/stop lokal Windows.

### Master Data

- [x] Principal CRUD.
- [x] Item Master CRUD.
- [x] Barcode scanner di form Item Master.
- [x] Backup Item Master.
- [x] Restore Item Master.
- [x] CSV backup Excel-safe untuk kode dan barcode.
- [x] Indikator dan filter barcode duplikat.
- [x] User management admin-only.
- [x] Audit log.

### Stock Opname

- [x] Upload CSV stok harian.
- [x] Preview dan sync principal/item master.
- [x] Generate sesi per principal.
- [x] Assign petugas.
- [x] Scan barcode atau kode barang.
- [x] Lookup barcode dibatasi ke item dalam sesi aktif.
- [x] Pilihan kandidat jika barcode duplikat dalam sesi.
- [x] Input qty aktual multi-level.
- [x] Mark matched.
- [x] Mark missing/tidak ada.
- [x] Koreksi item selisih.
- [x] Search daftar belum dicek.
- [x] Edit item dari daftar belum dicek.
- [x] Progress sesi otomatis.

### Laporan

- [x] Laporan harian.
- [x] Tabel item selisih.
- [x] Filter tanggal dan principal.
- [x] Export laporan harian.
- [x] Export data selisih.
- [x] Export detail sesi.
- [x] Selisih tampil dalam display unit, bukan base mentah.
- [x] Kolom Plus/Minus dihapus dari laporan harian.
- [x] Kode/barcode export Excel-safe.

### Testing

- [x] Parsing qty display.
- [x] Parsing conversion factor.
- [x] Hitung dan split base qty.
- [x] Sync database dan generate sesi.
- [x] Scan duplicate barcode.
- [x] Backup/restore Item Master.
- [x] Export laporan display unit.

Status terakhir: 12 test pass.

## Belum Ada / Belum Prioritas

### Mobile App

- Folder `mobile/` ada, tetapi belum menjadi produk utama.
- API mobile dasar ada, tetapi workflow utama saat ini tetap Filament.
- Belum ada build mobile production.

### Deployment

- Belum ada Dockerfile.
- Belum ada CI/CD.
- Belum ada deployment automation.

### Hardening

- Validasi tambahan untuk edge case CSV besar.
- Pembatasan complete session jika masih banyak pending, jika dibutuhkan operasional.
- Audit lebih detail untuk restore massal.
- Manual QA di perangkat gudang.

## Backlog Prioritas

| Prioritas | Item | Catatan |
|---|---|---|
| P1 | Manual QA end-to-end di PC gudang dan HP scanner | Pastikan kamera, tunnel, dan layout mobile nyaman |
| P1 | Review complete session policy | Putuskan boleh tutup dengan pending atau harus blok |
| P2 | Export pending items | Berguna untuk follow-up barang belum dicek |
| P2 | Import validation report lebih detail | Tampilkan baris bermasalah dari CSV |
| P3 | CI test GitHub Actions | Jalankan `php artisan test` otomatis |
| P3 | Docker/dev container | Untuk setup developer baru |

## Metrics

| Metrik | Nilai |
|---|---|
| Test cases | 12 |
| Test pass rate | 100% pada run terakhir |
| PHP | ^8.2 |
| Laravel | ^12.0 |
| Filament | ^5.6 |
| Database dev | SQLite |
| Database prod | MySQL |

