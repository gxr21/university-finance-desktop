@echo off
setlocal
cd /d "%~dp0"
title University Finance System
chcp 65001 >nul

set "PHP="
where php >nul 2>&1 && set "PHP=php"
if not defined PHP if exist "%ProgramFiles%\PHP\php.exe" set "PHP=%ProgramFiles%\PHP\php.exe"
if not defined PHP if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
if not defined PHP if exist "C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe" set "PHP=C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe"
if not defined PHP (
  for /d %%D in ("C:\laragon\bin\php\php-*") do if exist "%%D\php.exe" set "PHP=%%D\php.exe"
)

if not defined PHP (
  echo.
  echo PHP غير موجود. ثبّت XAMPP 8.2 أو أحدث من:
  echo https://www.apachefriends.org/
  echo ثم شغّل MySQL من لوحة XAMPP وأعد النقر على هذا الملف.
  echo.
  start https://www.apachefriends.org/
  pause
  exit /b 1
)

if exist "C:\xampp\mysql_start.bat" (
  start /MIN "" "C:\xampp\mysql_start.bat"
)

"%PHP%" "%~dp0bin\desktop-start.php"
echo.
pause
