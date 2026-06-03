<#
.SYNOPSIS
    Refresh the local Serveo dev environment: install deps, migrate, seed, and clear caches.

.DESCRIPTION
    This script ensures the Serveo app is up to date for local development.
    It works with a Laravel Herd setup using a LAN-hosted PostgreSQL/Redis backend.

    Steps:
    1. Verify prerequisites (PHP, Composer)
    2. Install Composer dependencies
    3. Install npm dependencies
    4. Run database migrations
    5. Seed core data (CoreSeeder)
    6. Clear all caches

.PARAMETER SkipNpm
    Skip npm install (useful when frontend deps haven't changed).

.PARAMETER ForceSeed
    Force re-seeding even if migrations didn't run fresh (--force flag on db:seed).

.PARAMETER Help
    Show usage information.

.EXAMPLE
    .\scripts\refresh.ps1
    # Full refresh: deps, migrate, seed, cache clear

.EXAMPLE
    .\scripts\refresh.ps1 -SkipNpm
    # Skip npm install

.EXAMPLE
    .\scripts\refresh.ps1 -ForceSeed
    # Force the seeder to run in production-like environments
#>

[CmdletBinding()]
param(
    [switch]$SkipNpm,
    [switch]$ForceSeed,
    [switch]$Help
)

$ErrorActionPreference = "Stop"

function Show-Usage {
    Write-Host @"
Usage: .\scripts\refresh.ps1 [OPTIONS]

Refresh the local Serveo dev environment.

Options:
  -SkipNpm      Skip npm install
  -ForceSeed    Pass --force to db:seed
  -Help         Show this help message
"@ -ForegroundColor Cyan
}

function Test-CommandExists {
    param([string]$Command)
    return [bool](Get-Command $Command -ErrorAction SilentlyContinue)
}

if ($Help) {
    Show-Usage
    exit 0
}

# ---------------------------------------------------------------------------
# Validate environment
# ---------------------------------------------------------------------------

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot

if (-not (Test-Path "vendor\autoload.php") -or -not (Test-Path ".env")) {
    Write-Error "This script must be run from the project root."
    exit 1
}

if (-not (Test-CommandExists "php")) {
    Write-Error "PHP is not in PATH."
    exit 1
}

if (-not (Test-CommandExists "composer")) {
    Write-Error "Composer is not in PATH."
    exit 1
}

Write-Host "========================================" -ForegroundColor Blue
Write-Host "Serveo Dev Refresh" -ForegroundColor Blue
Write-Host "========================================" -ForegroundColor Blue

# ---------------------------------------------------------------------------
# Composer dependencies
# ---------------------------------------------------------------------------

Write-Host "`n[1/5] Installing Composer dependencies..." -ForegroundColor Cyan
$composerOutput = & composer install --no-interaction --prefer-dist 2>&1
$composerOutput | ForEach-Object { Write-Host $_ }
if ($LASTEXITCODE -ne 0) {
    Write-Error "Composer install failed."
    exit 1
}

# ---------------------------------------------------------------------------
# NPM dependencies
# ---------------------------------------------------------------------------

if (-not $SkipNpm) {
    if (-not (Test-CommandExists "npm")) {
        Write-Warning "npm not found. Skipping frontend dependencies."
    } else {
        Write-Host "`n[2/5] Installing npm dependencies..." -ForegroundColor Cyan
        $npmCiOutput = & npm ci 2>&1
        $npmCiOutput | ForEach-Object { Write-Host $_ }
        if ($LASTEXITCODE -ne 0) {
            Write-Host "  npm ci failed, trying npm install..." -ForegroundColor Yellow
            $npmInstallOutput = & npm install 2>&1
            $npmInstallOutput | ForEach-Object { Write-Host $_ }
        }

        Write-Host "`n     Building frontend assets..." -ForegroundColor Cyan
        $buildOutput = & npm run build 2>&1
        $buildOutput | ForEach-Object { Write-Host $_ }
        if ($LASTEXITCODE -ne 0) {
            Write-Error "npm run build failed."
            exit 1
        }
    }
} else {
    Write-Host "`n[2/5] Skipping npm install (-SkipNpm)" -ForegroundColor DarkGray
}

# ---------------------------------------------------------------------------
# Migrate
# ---------------------------------------------------------------------------

Write-Host "`n[3/5] Running database migrations..." -ForegroundColor Cyan
$migrateOutput = & php artisan migrate --force 2>&1
$migrateOutput | ForEach-Object { Write-Host $_ }
if ($LASTEXITCODE -ne 0) {
    Write-Error "Migrations failed."
    exit 1
}

# Verify no pending migrations remain
$pendingMigrations = & php artisan migrate:status 2>&1 | Select-String -Pattern '\s+Pending\s*$'
if ($pendingMigrations) {
    Write-Host "`n  WARNING: Some migrations are still pending:" -ForegroundColor Yellow
    $pendingMigrations | ForEach-Object { Write-Host "    $_" -ForegroundColor Yellow }
    Write-Error "Migrations did not complete. $($pendingMigrations.Count) migration(s) still pending."
    exit 1
}

# ---------------------------------------------------------------------------
# Seed
# ---------------------------------------------------------------------------

Write-Host "`n[4/5] Seeding core data..." -ForegroundColor Cyan
$seedArgs = @("artisan", "db:seed", "--class=CoreSeeder")
if ($ForceSeed) {
    $seedArgs += "--force"
}
$seedOutput = & php @seedArgs 2>&1
$seedOutput | ForEach-Object { Write-Host $_ }
if ($LASTEXITCODE -ne 0) {
    Write-Error "CoreSeeder failed."
    exit 1
}

# ---------------------------------------------------------------------------
# Clear caches
# ---------------------------------------------------------------------------

Write-Host "`n[5/5] Clearing caches..." -ForegroundColor Cyan
$cacheCommands = @(
    @("artisan", "config:clear"),
    @("artisan", "route:clear"),
    @("artisan", "view:clear"),
    @("artisan", "event:clear"),
    @("artisan", "icons:clear"),
    @("artisan", "optimize:clear")
)

foreach ($cmd in $cacheCommands) {
    $label = $cmd[1]
    Write-Host "  $label..." -ForegroundColor DarkGray
    & php @cmd 2>&1 | Out-Null
}

# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------

Write-Host "`n========================================" -ForegroundColor Green
Write-Host "Refresh complete." -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
