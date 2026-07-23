# Struktur Sistem 3 Cabang

Dokumen ini menjelaskan struktur pemakaian Distora Stock untuk 1 admin pusat
dan 3 cabang operasional.

## Gambaran Struktur

```text
Admin Pusat
|-- Cabang 1
|   |-- Admin Cabang 1
|   `-- Petugas Stock Cabang 1
|-- Cabang 2
|   |-- Admin Cabang 2
|   `-- Petugas Stock Cabang 2
`-- Cabang 3
    |-- Admin Cabang 3
    `-- Petugas Stock Cabang 3
```

## Hak Akses

| Role | Cakupan Data | Tugas Utama |
|---|---|---|
| Admin Pusat | Semua cabang | Pantau global, buat cabang, kelola user, lihat semua item, semua sesi, semua laporan |
| Admin Cabang | Cabangnya sendiri | Input item master cabang, upload stok harian cabang, buat sesi, pantau petugas, download laporan cabang |
| Petugas Stock | Cabangnya sendiri | Scan barcode dan input qty aktual pada sesi cabangnya |

## Contoh 3 Cabang

| Cabang | Admin Cabang | Petugas |
|---|---|---|
| Cabang 1 | `admin.cabang1@distora.com` | 5-7 orang |
| Cabang 2 | `admin.cabang2@distora.com` | 5-7 orang |
| Cabang 3 | `admin.cabang3@distora.com` | 5-7 orang |

Total pemakaian sekitar 20 user masih masuk akal untuk struktur ini, selama
server dan database yang dipakai stabil.

## Aturan Data Item Master

- Admin pusat bisa melihat semua item master dari semua cabang.
- Admin cabang bisa input dan edit item master untuk cabangnya sendiri.
- Item master antar cabang boleh berbeda.
- Kode barang yang sama bisa ada di cabang berbeda.
- Barcode yang sama bisa ada di cabang berbeda tanpa saling mengganggu.
- Saat scan, sistem hanya mencari barang dari sesi dan cabang yang sedang aktif.

Contoh:

```text
Cabang 1 punya item:
- Kode: G98342A
- Barcode: 8991526598373
- Nama: CN ULTRA PASTELS ASH SRP (1X1)

Cabang 2 juga boleh punya kode/barcode yang sama atau berbeda.
Saat petugas Cabang 1 scan, sistem hanya membaca item milik Cabang 1.
```

## Alur Operasional Per Cabang

1. Admin pusat membuat data cabang.
2. Admin pusat membuat user admin cabang dan memilih cabangnya.
3. Admin cabang login.
4. Admin cabang input atau restore item master cabangnya.
5. Admin cabang upload stok harian untuk cabangnya.
6. Sistem membuat sesi stock opname berdasarkan principal.
7. Petugas cabang membuka menu Scan Barcode.
8. Petugas scan barang dan input qty aktual.
9. Admin cabang review selisih dan download laporan cabang.
10. Admin pusat bisa melihat laporan semua cabang secara global.

## Membuat Admin Pusat Pertama Kali

Admin pusat adalah user dengan:

- Role: `admin`
- Cabang: kosong atau `null`

Jika memakai user bawaan seeder, jalankan:

```bash
cd backend
php artisan db:seed
php artisan user:make-central-admin admin@distora.com
```

Login default:

```text
Email: admin@distora.com
Password: password
```

Command `user:make-central-admin` akan memastikan user tersebut menjadi admin
pusat. Setelah itu user bisa melihat semua cabang, semua item master, semua
sesi, semua laporan, dan membuat admin cabang.

## Membuat Admin Cabang

1. Login sebagai admin pusat.
2. Buat cabang di menu **Cabang**.
3. Masuk ke menu **Pengguna**.
4. Buat user baru.
5. Pilih role **Admin**.
6. Pilih cabang yang akan diurus user tersebut.
7. Simpan.

User admin yang punya cabang akan menjadi admin cabang. Admin cabang hanya bisa
melihat dan mengelola data cabangnya sendiri.

## Struktur Sesi Stock

Satu cabang bisa punya banyak sesi dalam satu hari, biasanya dipisah per
principal.

```text
Cabang 1 - 23 Juli 2026
|-- Sesi Principal A
|   |-- Petugas 1
|   `-- Petugas 2
|-- Sesi Principal B
|   `-- Petugas 3
`-- Sesi Principal C
    |-- Petugas 4
    `-- Petugas 5
```

Satu sesi principal bisa dikerjakan 2 sampai 3 petugas agar opname lebih cepat.
Semua hasil scan tetap masuk ke sesi yang sama dan tercatat siapa petugasnya.

## Catatan Penting

- Admin cabang tidak dibuat untuk melihat cabang lain.
- Kalau ada item yang hanya ada di cabang tertentu, cukup input di item master cabang itu.
- Backup item master membawa info cabang agar restore tidak mencampur data.
- Laporan cabang hanya berisi data cabang tersebut, sedangkan admin pusat bisa melihat gabungan semua cabang.
