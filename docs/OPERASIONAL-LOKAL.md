# Operasional Lokal Distora Stock

Panduan ini untuk menjalankan Distora Stock dari PC kantor/gudang.

## Start Aplikasi

Double-click shortcut:

```bat
START-DISTORA.bat
```

Script ini menjalankan:

- MySQL XAMPP.
- Laravel di `http://127.0.0.1:8010/admin`.
- ngrok jika tersedia.
- Browser ke URL admin.

Jika shortcut khusus ngrok tersedia:

```bat
START-DISTORA-NGROK.bat
```

## Start Tanpa Tunnel

Untuk pemakaian lokal di PC yang sama:

```bat
START-DISTORA.bat none
```

Buka:

```text
http://127.0.0.1:8010/admin
```

## Start Dengan Cloudflare Tunnel

Jika `cloudflared.exe` sudah terinstall:

```bat
START-DISTORA.bat cloudflare
```

## Stop Aplikasi

Double-click:

```bat
STOP-DISTORA.bat
```

Script stop akan:

- Menutup proses Laravel.
- Menutup proses ngrok/cloudflared.
- Menghentikan MySQL XAMPP.
- Menutup window CMD Distora yang masih terbuka.
- Menutup window stop otomatis setelah selesai.

## Catatan Untuk Petugas

- Jangan tutup window MySQL, Laravel, atau tunnel saat opname masih berjalan.
- Untuk kamera HP, akses harus melalui HTTPS seperti ngrok atau Cloudflare Tunnel.
- Jika tunnel mati, aplikasi lokal tetap bisa dibuka dari PC di `http://127.0.0.1:8010/admin`.
- Jika scan kamera tidak aktif, coba refresh halaman atau pakai input barcode manual.

## Troubleshooting

### Halaman Tidak Bisa Dibuka

1. Jalankan `STOP-DISTORA.bat`.
2. Tunggu sampai CMD tertutup.
3. Jalankan `START-DISTORA.bat` lagi.
4. Cek URL lokal `http://127.0.0.1:8010/admin`.

### MySQL Tidak Hidup

- Pastikan XAMPP ada di `C:\xampp`.
- Cek apakah `C:\xampp\mysql_start.bat` dan `C:\xampp\mysql_stop.bat` tersedia.

### URL Ngrok Tidak Bisa Dibuka

- Pastikan ngrok berjalan.
- Cek URL yang muncul di window ngrok.
- Jika domain ngrok berubah, update nilai `NGROK_URL` di `START-DISTORA.bat`.

### Port 8010 Bentrok

Jalankan stop script dulu:

```bat
STOP-DISTORA.bat
```

Jika masih bentrok, tutup proses lain yang memakai port 8010 atau ubah
`APP_PORT` di `START-DISTORA.bat` dan `STOP-DISTORA.bat`.

