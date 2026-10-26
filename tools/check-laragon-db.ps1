<#
  HiStrangers / Laragon / Windows - SQLite driver diagnostics.
  Usage (from your Laravel project root):
    powershell -ExecutionPolicy Bypass -File .\tools\check-laragon-db.ps1
    powershell -ExecutionPolicy Bypass -File .\tools\check-laragon-db.ps1 -EnableSQLite
  -EnableSQLite modifies the active CLI php.ini after making a .bak backup.
  Never installs or downloads DLLs. You must restart `php artisan serve`.
#>
param([switch]$EnableSQLite)
$ErrorActionPreference = 'Stop'
Write-Host "`n=== HiStrangers - Laragon PHP / Database Check ===" -ForegroundColor Cyan
$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) { Write-Host 'ERROR: php.exe is not on PATH. Open Terminal from Laragon, then run this script again.' -ForegroundColor Red; exit 1 }
Write-Host "PHP CLI executable: $($php.Source)"
& php -v | Select-Object -First 2 | ForEach-Object { Write-Host $_ }
$iniPath = (& php -r 'echo php_ini_loaded_file() ?: "";').Trim()
if (-not $iniPath) { Write-Host 'ERROR: PHP CLI did not load php.ini.' -ForegroundColor Red; exit 1 }
Write-Host "Active PHP CLI ini: $iniPath"
$modules = @(& php -m)
$pSqlite = $modules -contains 'pdo_sqlite'
$sqlite = $modules -contains 'sqlite3'
$pMysql = $modules -contains 'pdo_mysql'
Write-Host "pdo_sqlite: $pSqlite | sqlite3: $sqlite | pdo_mysql: $pMysql"
Write-Host "PDO drivers: $(& php -r 'echo implode(", ", PDO::getAvailableDrivers());')"
if ($EnableSQLite -and (-not $pSqlite -or -not $sqlite)) {
    if (-not (Test-Path $iniPath)) { throw "Cannot find php.ini: $iniPath" }
    $extDir = (& php -r 'echo ini_get("extension_dir");').Trim()
    Write-Host "Extension directory: $extDir"
    $dir = if ([System.IO.Path]::IsPathRooted($extDir)) { $extDir } else { Join-Path (Split-Path $php.Source -Parent) $extDir }
    foreach ($dll in @('php_pdo_sqlite.dll', 'php_sqlite3.dll')) {
        if (-not (Test-Path (Join-Path $dir $dll))) {
            Write-Warning "Missing $dll in $dir. Change the Laragon PHP version/install matching PHP extensions instead."
            exit 2
        }
    }
    $backup = "$iniPath.histrangers.$(Get-Date -Format 'yyyyMMdd-HHmmss').bak"
    Copy-Item $iniPath $backup -ErrorAction Stop
    Write-Host "Backup php.ini: $backup" -ForegroundColor Yellow
    $lines = [System.Collections.Generic.List[string]]::new()
    Get-Content $iniPath | ForEach-Object { $lines.Add($_) }
    foreach ($ext in @('pdo_sqlite', 'sqlite3')) {
        $pattern = '^\s*;?\s*extension\s*=\s*(?:php_)?' + $ext + '(?:\.dll)?\s*$'
        $found = $false
        for ($i=0; $i -lt $lines.Count; $i++) {
            if ($lines[$i] -match $pattern) {
                $lines[$i] = "extension=$ext"
                $found = $true
            }
        }
        if (-not $found) { $lines.Add("extension=$ext") }
    }
    [System.IO.File]::WriteAllLines($iniPath, $lines, (New-Object System.Text.UTF8Encoding($false)))
    Write-Host "php.ini updated. Verifying..." -ForegroundColor Green
    & php -m | Select-String 'pdo_sqlite|sqlite3'
    Write-Host 'IMPORTANT: Stop the running php artisan serve (Ctrl+C), then start it again.' -ForegroundColor Yellow
}
if (-not $pSqlite) { Write-Host 'SQLite is unavailable. Use -EnableSQLite or configure MySQL + pdo_mysql.' -ForegroundColor Yellow }
if (Test-Path '.env') {
    Get-Content '.env' | Where-Object { $_ -match '^(DB_CONNECTION|SESSION_DRIVER|CACHE_STORE)=' } | ForEach-Object { Write-Host $_ }
} else { Write-Host 'No .env in the current directory; run from the root Laravel folder.' -ForegroundColor Yellow }
if (Test-Path 'artisan') { Write-Host 'Laravel artisan found.' -ForegroundColor Green } else { Write-Host 'artisan not found in current directory.' -ForegroundColor Yellow }
Write-Host 'Done. Never share .env credentials.'
