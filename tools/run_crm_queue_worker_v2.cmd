@echo off
setlocal
cd /d "%~dp0.."
:restart
php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=120 --max-time=3600
timeout /t 5 /nobreak >nul
goto restart
