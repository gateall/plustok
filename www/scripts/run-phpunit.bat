@echo off
REM PHPUnit runner — PHP 8.5 extensions for CLI
set PHP=C:\tools\php85\php.exe
set EXT=-d extension=openssl -d extension=curl -d extension=pdo_mysql -d extension=mbstring -d extension=zip

cd /d "%~dp0.."
%PHP% %EXT% vendor\bin\phpunit %*
