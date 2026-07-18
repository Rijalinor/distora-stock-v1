# Operasional Lokal Distora Stock

Panduan ini untuk menjalankan Distora Stock dari PC kantor/gudang.

## Start Harian

Double-click:

```bat
START-DISTORA.bat
```

Atau yang lebih eksplisit:

```bat
START-DISTORA-NGROK.bat
```

Yang dijalankan:

- MySQL XAMPP
- Laravel di `http://127.0.0.1:8010/admin`
- ngrok, jika `ngrok.exe` ada di PATH
- Browser langsung membuka `https://unremonstrating-inconstantly-cynthia.ngrok-free.dev/admin`

## Tanpa Tunnel

Kalau hanya dipakai di PC lokal:

```bat
START-DISTORA.bat none
```

## Pakai Cloudflare Tunnel

Kalau `cloudflared.exe` sudah terinstall:

```bat
START-DISTORA.bat cloudflare
```

## Stop Harian

Double-click:

```bat
STOP-DISTORA.bat
```

## Catatan

- Jangan tutup window MySQL, Laravel, atau tunnel selama opname berjalan.
- Untuk HP petugas, gunakan URL ngrok/Cloudflare yang muncul di window tunnel.
- Kalau tunnel tidak muncul, aplikasi tetap bisa dibuka lokal di `http://127.0.0.1:8010/admin`.
- Untuk kamera dari HP, gunakan URL HTTPS dari ngrok/Cloudflare.
