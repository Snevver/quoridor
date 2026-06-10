@echo off
rem Quoridor dev stack — starts everything in separate windows.
cd /d "%~dp0"

rem MySQL (skips if already running, e.g. via XAMPP Control Panel)
tasklist /FI "IMAGENAME eq mysqld.exe" | find /I "mysqld.exe" >nul
if errorlevel 1 (
    echo Starting MySQL...
    start "MySQL" /MIN C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini
    timeout /t 4 /nobreak >nul
)

echo Starting Laravel web server on :8000...
start "Quoridor Web" cmd /k php artisan serve --port=8000

echo Starting Reverb websockets on :8080...
start "Quoridor Reverb" cmd /k php artisan reverb:start --host=127.0.0.1 --port=8080

echo Starting queue worker...
start "Quoridor Queue" cmd /k php artisan queue:work --tries=1

echo.
echo All services launched. Open http://localhost:8000
