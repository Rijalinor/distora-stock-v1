@echo off
setlocal EnableExtensions EnableDelayedExpansion

title DISTORA - START ALL

REM ============================================================
REM CONFIGURATION
REM ============================================================

REM Folder tempat START-ALL.bat berada
set "ROOT=%~dp0"

REM ============================================================
REM PROJECT PATH
REM ============================================================

REM Distora Stock
set "STOCK_BACKEND=%ROOT%backend"

REM DistoraVision berada di sebelah folder distora-stock
set "VISION_BACKEND=%ROOT%..\distoravision"

REM XAMPP
set "XAMPP=C:\xampp"

REM ============================================================
REM PORT
REM ============================================================

set "STOCK_PORT=8010"
set "VISION_PORT=8020"

REM ============================================================
REM LOCAL URL
REM ============================================================

set "STOCK_URL=http://127.0.0.1:%STOCK_PORT%"
set "VISION_URL=http://127.0.0.1:%VISION_PORT%"

REM ============================================================
REM PUBLIC URL
REM ============================================================

set "STOCK_PUBLIC=https://app.oborbarumaju.id"
set "VISION_PUBLIC=https://vision.oborbarumaju.id"

REM ============================================================
REM CLOUDFLARE
REM ============================================================

set "CLOUDFLARE_TUNNEL_NAME=oborbarumaju-app"


echo.
echo ============================================================
echo              DISTORA SYSTEM - START ALL
echo ============================================================
echo.


REM ============================================================
REM CHECK DISTORA STOCK
REM ============================================================

echo [CHECK] Distora Stock...

if not exist "%STOCK_BACKEND%\artisan" (
    echo.
    echo [ERROR] Backend Distora Stock tidak ditemukan!
    echo.
    echo Path:
    echo %STOCK_BACKEND%
    echo.
    pause
    exit /b 1
)

echo [OK] %STOCK_BACKEND%
echo.


REM ============================================================
REM CHECK DISTORAVISION
REM ============================================================

echo [CHECK] DistoraVision...

if not exist "%VISION_BACKEND%\artisan" (
    echo.
    echo [ERROR] Backend DistoraVision tidak ditemukan!
    echo.
    echo Path:
    echo %VISION_BACKEND%
    echo.
    pause
    exit /b 1
)

echo [OK] %VISION_BACKEND%
echo.


REM ============================================================
REM START MYSQL
REM ============================================================

echo ============================================================
echo [1/4] MYSQL
echo ============================================================
echo.

tasklist /FI "IMAGENAME eq mysqld.exe" | find /I "mysqld.exe" >nul

if errorlevel 1 (

    if exist "%XAMPP%\mysql_start.bat" (

        echo MySQL belum berjalan.
        echo Menjalankan MySQL XAMPP...

        start "Distora MySQL" /min cmd /k ""%XAMPP%\mysql_start.bat""

        timeout /t 4 /nobreak >nul

        echo [OK] MySQL dijalankan.

    ) else (

        echo [WARNING] mysql_start.bat tidak ditemukan!
        echo Path: %XAMPP%

    )

) else (

    echo [OK] MySQL sudah berjalan.

)

echo.


REM ============================================================
REM CLEAR DISTORA STOCK CACHE
REM ============================================================

echo ============================================================
echo [2/4] DISTORA STOCK
echo ============================================================
echo.

pushd "%STOCK_BACKEND%"

echo Membersihkan cache...

php artisan optimize:clear

if errorlevel 1 (
    echo.
    echo [ERROR] Gagal menjalankan optimize:clear Distora Stock.
    popd
    pause
    exit /b 1
)

echo [OK] Cache Distora Stock dibersihkan.

popd

echo.


REM ============================================================
REM CLEAR DISTORAVISION CACHE
REM ============================================================

echo ============================================================
echo [3/4] DISTORAVISION
echo ============================================================
echo.

pushd "%VISION_BACKEND%"

echo Membersihkan cache...

php artisan optimize:clear

if errorlevel 1 (
    echo.
    echo [ERROR] Gagal menjalankan optimize:clear DistoraVision.
    popd
    pause
    exit /b 1
)

echo [OK] Cache DistoraVision dibersihkan.

popd

echo.


REM ============================================================
REM START DISTORA STOCK
REM ============================================================

echo ------------------------------------------------------------
echo Starting Distora Stock :%STOCK_PORT%
echo ------------------------------------------------------------

netstat -ano | findstr ":%STOCK_PORT%" | findstr "LISTENING" >nul

if errorlevel 1 (

    echo Membuka Laravel Distora Stock...

    start "Distora Stock - Laravel" /min cmd /k ^
    "cd /d ""%STOCK_BACKEND%"" && php artisan serve --host=127.0.0.1 --port=%STOCK_PORT%"

    echo [OK] Distora Stock dijalankan.

) else (

    echo [OK] Distora Stock sudah berjalan.

)

echo.


REM ============================================================
REM START DISTORAVISION
REM ============================================================

echo ------------------------------------------------------------
echo Starting DistoraVision :%VISION_PORT%
echo ------------------------------------------------------------

netstat -ano | findstr ":%VISION_PORT%" | findstr "LISTENING" >nul

if errorlevel 1 (

    echo Membuka Laravel DistoraVision...

    start "DistoraVision - Laravel" /min cmd /k ^
    "cd /d ""%VISION_BACKEND%"" && php artisan serve --host=127.0.0.1 --port=%VISION_PORT%"

    echo [OK] DistoraVision dijalankan.

) else (

    echo [OK] DistoraVision sudah berjalan.

)

echo.


REM ============================================================
REM WAIT LARAVEL
REM ============================================================

echo Menunggu Laravel siap...

timeout /t 5 /nobreak >nul

echo.


REM ============================================================
REM CLOUDFLARE TUNNEL
REM ============================================================

echo ============================================================
echo [4/4] CLOUDFLARE TUNNEL
echo ============================================================
echo.

tasklist /FI "IMAGENAME eq cloudflared.exe" | find /I "cloudflared.exe" >nul

if errorlevel 1 (

    where cloudflared >nul 2>nul

    if errorlevel 1 (

        echo [ERROR] cloudflared tidak ditemukan di PATH!
        echo.
        echo Aplikasi lokal tetap dijalankan.
        echo Cloudflare Tunnel tidak dijalankan.

    ) else (

        echo Menjalankan tunnel:
        echo %CLOUDFLARE_TUNNEL_NAME%
        echo.

        start "Distora - Cloudflare Tunnel" /min cmd /k ^
        "cloudflared tunnel run %CLOUDFLARE_TUNNEL_NAME%"

        timeout /t 5 /nobreak >nul

        echo [OK] Cloudflare Tunnel dijalankan.

    )

) else (

    echo [OK] Cloudflare Tunnel sudah berjalan.
    echo Menggunakan tunnel yang sama untuk Stock dan Vision.

)

echo.


REM ============================================================
REM OPEN WEBSITE
REM ============================================================

echo ============================================================
echo OPENING APPLICATION
echo ============================================================
echo.

timeout /t 3 /nobreak >nul

echo Membuka Distora Stock Admin...
start "" "https://app.oborbarumaju.id/admin"

timeout /t 2 /nobreak >nul

echo Membuka DistoraVision...
start "" "%VISION_PUBLIC%"

echo.


REM ============================================================
REM FINAL
REM ============================================================

echo ============================================================
echo                 DISTORA SYSTEM READY
echo ============================================================
echo.
echo Distora Stock
echo   Local  : %STOCK_URL%
echo   Public : %STOCK_PUBLIC%
echo   Port   : %STOCK_PORT%
echo.
echo DistoraVision
echo   Local  : %VISION_URL%
echo   Public : %VISION_PUBLIC%
echo   Port   : %VISION_PORT%
echo.
echo Cloudflare Tunnel
echo   Name   : %CLOUDFLARE_TUNNEL_NAME%
echo.
echo ============================================================
echo.
echo Jangan tutup window Laravel dan Cloudflare Tunnel.
echo.
echo Tekan tombol apa saja untuk keluar dari window ini.
pause >nul

endlocal