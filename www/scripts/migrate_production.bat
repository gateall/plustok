@echo off
REM ACEP Production Migration (MariaDB / Cafe24)
REM Usage: scripts\migrate_production.bat
REM Prerequisites: config\database.php configured, backup completed

setlocal
cd /d "%~dp0.."

echo [1/4] Pre-flight check...
php migrations\migrate.php --check
if errorlevel 1 exit /b 1

echo.
echo [2/4] Backup reminder — run mysqldump before continuing!
echo       Example: mysqldump -u USER -p DBNAME ^> backup_%date:~0,4%%date:~5,2%%date:~8,2%.sql
pause

echo.
echo [3/4] Running migrations...
php migrations\migrate.php
if errorlevel 1 exit /b 1

echo.
echo [4/4] Seed (optional)...
set /p RUNSEED="Run seed? (y/N): "
if /i "%RUNSEED%"=="y" php migrations\migrate.php --seed

echo.
echo [validate] php scripts\validate_production.php
php scripts\validate_production.php
endlocal
