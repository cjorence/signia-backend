@echo off
setlocal
where php >nul 2>nul
if errorlevel 1 (
  echo PHP was not found. Add your existing PHP installation to PATH first.
  exit /b 1
)
if not exist "%~dp0vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php" (
  echo Laravel dependencies are missing. Run composer install first.
  exit /b 1
)
powershell.exe -NoProfile -Command "try { $connection = [System.Net.Sockets.TcpClient]::new('127.0.0.1', 8000); $connection.Dispose(); exit 0 } catch { exit 1 }" >nul 2>nul
if not errorlevel 1 (
  echo Port 8000 is already in use. Stop the existing API server with Ctrl+C first.
  exit /b 1
)

rem Enable the installed PHP code cache only in this server process.
rem Recheck changed PHP files on every request for local development.
set "SIGNIA_OPCACHE_OPTION="
php -r "exit(extension_loaded('Zend OPcache') ? 0 : 1);"
if errorlevel 1 (
  php -r "exit(is_file(ini_get('extension_dir').DIRECTORY_SEPARATOR.'php_opcache.dll') ? 0 : 1);"
  if errorlevel 1 (
    echo PHP OPcache is not installed. Use php artisan serve with your existing PHP setup.
    exit /b 1
  )
  set "SIGNIA_OPCACHE_OPTION=-d zend_extension=php_opcache.dll"
)
pushd "%~dp0public"
echo SIGNIA local API: http://127.0.0.1:8000 - press Ctrl+C to stop.
echo If port 8000 is busy, stop the existing php artisan serve first.
php %SIGNIA_OPCACHE_OPTION% -d opcache.enable=1 -d opcache.enable_cli=1 -d opcache.validate_timestamps=1 -d opcache.revalidate_freq=0 -S 127.0.0.1:8000 "../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
set "SIGNIA_EXIT_CODE=%ERRORLEVEL%"
popd
exit /b %SIGNIA_EXIT_CODE%
