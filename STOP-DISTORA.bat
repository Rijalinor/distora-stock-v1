@echo off
setlocal

set "XAMPP=C:\xampp"
set "APP_PORT=8010"

echo ========================================
echo  DISTORA STOCK - STOP
echo ========================================
echo.

echo Menutup Laravel...
taskkill /FI "WINDOWTITLE eq Distora Laravel*" /T /F >nul 2>nul
for /f "tokens=5" %%p in ('netstat -ano ^| findstr /R /C:":%APP_PORT% .*LISTENING"') do taskkill /PID %%p /F >nul 2>nul

echo Menutup ngrok/cloudflared jika berjalan...
taskkill /IM ngrok.exe /F >nul 2>nul
taskkill /IM cloudflared.exe /F >nul 2>nul
taskkill /FI "WINDOWTITLE eq Distora ngrok*" /T /F >nul 2>nul
taskkill /FI "WINDOWTITLE eq Distora Cloudflare Tunnel*" /T /F >nul 2>nul

if exist "%XAMPP%\mysql_stop.bat" (
    echo Menghentikan MySQL XAMPP...
    call "%XAMPP%\mysql_stop.bat"
) else (
    echo mysql_stop.bat tidak ditemukan di %XAMPP%
)

echo Menutup window MySQL jika masih terbuka...
taskkill /FI "WINDOWTITLE eq Distora MySQL*" /T /F >nul 2>nul

echo.
echo Distora dihentikan.
timeout /t 2 /nobreak >nul
exit /b 0
