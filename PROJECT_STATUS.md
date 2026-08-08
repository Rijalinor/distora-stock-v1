# Project Status

Terakhir diperbarui: 2026-08-08

## Ringkasan

Distora Stock sudah memiliki workflow inti yang bisa dipakai untuk stock
opname harian:

CSV upload -> sync master data -> generate sesi -> scan/input qty -> review
selisih -> export laporan.

Status saat ini: **production-ready**.

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
- [x] Principal nonaktif per cabang.

### Stock Opname

- [x] Upload CSV stok harian.
- [x] Preview dan sync principal/item master.
- [x] Generate sesi per principal.
- [x] Assign petugas.
- [x] Scan barcode atau kode barang.
- [x] Pencarian barcode, kode, dan nama barang.
- [x] Lookup barcode dibatasi ke item dalam sesi aktif.
- [x] Pilihan kandidat jika barcode duplikat dalam sesi.
- [x] Input qty aktual multi-level.
- [x] Barang Temuan dengan qty teks bebas.
- [x] Pencegahan Barang Temuan duplikat dalam satu sesi.
- [x] Mark matched.
- [x] Mark missing/tidak ada.
- [x] Koreksi item selisih.
- [x] Search daftar belum dicek.
- [x] Edit item dari daftar belum dicek.
- [x] Progress sesi otomatis.
- [x] Panel collapse untuk daftar kerja.
- [x] Daftar item terurut berdasarkan kode A-Z.
- [x] Mode pisah CTN/PCS untuk principal tertentu.
- [x] Laporan tetap format standar meskipun mode pisah aktif.

### Checker Barang Rusak

- [x] Header pemeriksaan barang rusak.
- [x] Multi-checker sampai 5 petugas dengan PIN.
- [x] Scan barang rusak via kamera atau input barcode.
- [x] Kamera scan cepat tetap aktif setelah scan.
- [x] Anti double-scan barcode yang sama sampai barcode hilang dari frame.
- [x] Feedback scan sukses inline, getar, dan bunyi.
- [x] Barang pending untuk barcode belum dikenal.
- [x] Tombol `+` dan `++` untuk item tercatat dan pending.
- [x] Daftar barang rusak default 5 item terakhir.
- [x] Laporan dan CSV barang rusak.

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
- [x] Perbandingan dengan stock opname terakhir.
- [x] Laporan mobile sederhana untuk petugas.
- [x] Laporan lengkap dan export tetap tersedia untuk admin.

### Testing

- [x] Parsing qty display.
- [x] Parsing conversion factor.
- [x] Hitung dan split base qty.
- [x] Sync database dan generate sesi.
- [x] Scan duplicate barcode.
- [x] Backup/restore Item Master.
- [x] Export laporan display unit.
- [x] Pencarian item tanpa barcode.
- [x] Qty teks Barang Temuan.
- [x] Pencegahan Barang Temuan duplikat.
- [x] Pemilihan tanggal opname pembanding terakhir.
- [x] Multi-checker barang rusak.
- [x] Bulk qty barang rusak dan pending.
- [x] Mode pisah CTN/PCS.

Status terakhir: 48 test pass.

## Belum Ada / Belum Prioritas

### Mobile App

- Folder `mobile/` ada, tetapi belum menjadi produk utama.
- API mobile dasar ada, tetapi workflow utama saat ini tetap Filament.
- Belum ada build mobile production.

### Hardening

- Backup database rutin.
- Monitoring log production.
- Validasi tambahan untuk edge case CSV sangat besar jika nanti dibutuhkan.
- CI/CD belum ada, deploy masih manual.

## Backlog Prioritas

| Prioritas | Item | Catatan |
|---|---|---|
| P1 | Backup database harian | Wajib untuk production |
| P1 | Monitoring error log | Cek `storage/logs/laravel.log` |
| P2 | Import validation report lebih detail | Tampilkan baris bermasalah dari CSV jika dibutuhkan |
| P3 | CI test GitHub Actions | Jalankan `php artisan test` otomatis |
| P3 | Docker/dev container | Untuk setup developer baru |

## Metrics

| Metrik | Nilai |
|---|---|
| Test cases | 48 |
| Test pass rate | 100% pada run terakhir |
| PHP | ^8.2 |
| Laravel | ^12.0 |
| Filament | ^5.6 |
| Database dev | SQLite |
| Database prod | MySQL |
