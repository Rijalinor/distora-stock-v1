@echo off
setlocal

set "ROOT=%~dp0"
set "BACKEND=%ROOT%backend"
set "XAMPP=C:\xampp"
set "NGROK_EXE=C:\ngrok\ngrok.exe"
set "APP_PORT=8010"
set "APP_URL=http://127.0.0.1:%APP_PORT%"
set "NGROK_URL=https://unremonstrating-inconstantly-cynthia.ngrok-free.dev"
set "TUNNEL=%~1"

if "%TUNNEL%"=="" set "TUNNEL=ngrok"

echo ========================================
echo  DISTORA STOCK - START
echo ========================================
echo.

if not exist "%BACKEND%\artisan" (
    echo Folder backend tidak ditemukan: %BACKEND%
    pause
    exit /b 1
)

if exist "%XAMPP%\mysql_start.bat" (
    tasklist /FI "IMAGENAME eq mysqld.exe" | find /I "mysqld.exe" >nul
    if errorlevel 1 (
        echo Membuka MySQL XAMPP...
        start "Distora MySQL" /min cmd /k ""%XAMPP%\mysql_start.bat""
    ) else (
        echo MySQL sudah berjalan.
    )
) else (
    echo XAMPP MySQL tidak ditemukan di %XAMPP%
    echo Lewati start MySQL. Pastikan database sudah hidup.
)

timeout /t 4 /nobreak >nul

echo Membuka Laravel...
start "Distora Laravel" /min cmd /k "cd /d ""%BACKEND%"" && php artisan serve --host=127.0.0.1 --port=%APP_PORT%"

timeout /t 4 /nobreak >nul

if /i "%TUNNEL%"=="none" goto browser

if /i "%TUNNEL%"=="cloudflare" (
    where cloudflared >nul 2>nul
    if errorlevel 1 (
        echo cloudflared belum ada di PATH. Tunnel Cloudflare dilewati.
    ) else (
        echo Membuka Cloudflare Tunnel...
        start "Distora Cloudflare Tunnel" /min cmd /k "cloudflared tunnel --url %APP_URL%"
    )
    goto browser
)

where ngrok >nul 2>nul
if errorlevel 1 (
    if exist "%NGROK_EXE%" (
        echo Membuka ngrok dari %NGROK_EXE%...
        start "Distora ngrok" /min cmd /k ""%NGROK_EXE%" http %APP_PORT%"
    ) else (
        echo ngrok belum ada di PATH dan tidak ditemukan di %NGROK_EXE%.
        echo Install ngrok atau jalankan: START-DISTORA.bat none
    )
) else (
    echo Membuka ngrok...
    start "Distora ngrok" /min cmd /k "ngrok http %APP_PORT%"
)

:browser
timeout /t 2 /nobreak >nul

echo Membuka dashboard lokal...
if /i "%TUNNEL%"=="ngrok" (
    start "" "%NGROK_URL%/admin"
) else (
    start "" "%APP_URL%/admin"
)

echo.
echo Selesai. Jangan tutup window MySQL/Laravel/Tunnel selama dipakai.
echo Untuk stop, jalankan STOP-DISTORA.bat
timeout /t 3 /nobreak >nul
