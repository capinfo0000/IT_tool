@echo off
REM クチコミブースト ローカル起動 (Windows)
REM 使い方: start.bat をダブルクリック → ブラウザで http://localhost:8080/
cd /d "%~dp0"
if "%PORT%"=="" set PORT=8080

where php >nul 2>nul
if errorlevel 1 (
  echo PHP が見つかりません。先に PHP 8 をインストールしてください（https://windows.php.net/download/）。
  pause
  exit /b 1
)

echo ------------------------------------------------------------
echo  クチコミブースト を起動します
echo    トップ     : http://localhost:%PORT%/
echo    管理画面   : http://localhost:%PORT%/admin
echo    停止       : Ctrl + C
echo ------------------------------------------------------------

php -S 127.0.0.1:%PORT% -t public public/index.php
