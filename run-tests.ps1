<#
.SYNOPSIS
    Run Pest and/or Dusk tests with automatic .env swapping for Dusk.

.DESCRIPTION
    This script runs the Serveo test suite. When running Dusk browser tests,
    it automatically swaps .env with .env.dusk.local because Dusk requires
    the test environment (SQLite, database sessions) to be loaded by the
    php -S dev server.

    The original .env is always restored, even if the tests fail or the
    script is interrupted.

    IMPORTANT: The env swap is necessary because Laravel's php artisan dusk
    command starts a php -S server that reads .env from disk. The local dev
    .env uses PostgreSQL/Redis which are not available in the Dusk test
    context. .env.dusk.local configures SQLite and database-backed sessions.

.PARAMETER PestFilter
    Optional filter string for Pest tests (e.g. "MultilingualTest").

.PARAMETER DuskFilter
    Optional filter string for Dusk tests (e.g. "ThemeAndLanguageTest").

.PARAMETER PestOnly
    Run only Pest tests, skip Dusk.

.PARAMETER DuskOnly
    Run only Dusk tests, skip Pest.

.PARAMETER Help
    Show usage information.

.EXAMPLE
    .\run-tests.ps1
    # Runs both Pest and Dusk test suites

.EXAMPLE
    .\run-tests.ps1 -PestFilter "MultilingualTest"
    # Runs only Pest tests matching the filter

.EXAMPLE
    .\run-tests.ps1 -DuskFilter "ThemeAndLanguageTest" -DuskOnly
    # Runs only Dusk tests matching the filter

.EXAMPLE
    .\run-tests.ps1 -PestFilter "LanguageSwitcherTest" -DuskFilter "ThemeAndLanguageTest"
    # Runs both Pest and Dusk with filters
#>

[CmdletBinding()]
param(
    [string]$PestFilter = "",
    [string]$DuskFilter = "",
    [switch]$PestOnly,
    [switch]$DuskOnly,
    [switch]$Help
)

$ErrorActionPreference = "Stop"

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

function Show-Usage {
    Write-Host @"
Usage: .\run-tests.ps1 [OPTIONS]

Run the Serveo test suite with automatic env management.

Options:
  -PestFilter <string>   Filter Pest tests (e.g. "MultilingualTest")
  -DuskFilter <string>   Filter Dusk tests (e.g. "ThemeAndLanguageTest")
  -PestOnly              Run only Pest tests
  -DuskOnly              Run only Dusk tests
  -Help                  Show this help message

Examples:
  .\run-tests.ps1                                      # Run everything
  .\run-tests.ps1 -PestFilter "LanguageSwitcherTest"   # Filtered Pest only
  .\run-tests.ps1 -DuskFilter "ThemeAndLanguageTest"   # Filtered Dusk only
  .\run-tests.ps1 -PestOnly                            # Skip Dusk
  .\run-tests.ps1 -PestFilter "Foo" -DuskFilter "Bar"  # Both filtered
"@ -ForegroundColor Cyan
}

function Test-CommandExists {
    param([string]$Command)
    return [bool](Get-Command $Command -ErrorAction SilentlyContinue)
}

function Invoke-PestTests {
    param([string]$Filter)

    Write-Host "`n========================================" -ForegroundColor Blue
    Write-Host "Running Pest tests..." -ForegroundColor Blue
    Write-Host "========================================" -ForegroundColor Blue

    $args = @("artisan", "test", "--testsuite=Feature")
    if ($Filter) {
        $args += "--filter=$Filter"
        Write-Host "Filter: $Filter" -ForegroundColor DarkGray
    }

    # Pest tests run via phpunit.xml which already sets testing env vars
    # No .env swap needed.
    $pestOutput = & php @args 2>&1
    $pestOutput | ForEach-Object { Write-Host $_ }
    [int]$pestExitCode = $LASTEXITCODE
    if ($null -eq $pestExitCode) { $pestExitCode = 0 }
    return $pestExitCode
}

function Invoke-DuskTests {
    param([string]$Filter)

    Write-Host "`n========================================" -ForegroundColor Blue
    Write-Host "Running Dusk tests..." -ForegroundColor Blue
    Write-Host "========================================" -ForegroundColor Blue

    # Dusk starts php -S which reads .env from disk.
    # We MUST use .env.dusk.local (SQLite + database sessions).
    $envBackup = ".env.backup.tests"
    $envDusk   = ".env.dusk.local"
    $envMain   = ".env"

    if (-not (Test-Path $envDusk)) {
        Write-Error ".env.dusk.local not found. Dusk tests cannot run."
        return 1
    }

    # Backup current .env
    if (Test-Path $envMain) {
        Copy-Item -Path $envMain -Destination $envBackup -Force
        Write-Host "Backed up .env -> $envBackup" -ForegroundColor DarkGray
    }

    # Swap to Dusk env
    Copy-Item -Path $envDusk -Destination $envMain -Force
    Write-Host "Swapped .env -> .env.dusk.local for Dusk server" -ForegroundColor DarkGray

    try {
        $args = @("artisan", "dusk")
        if ($Filter) {
            $args += "--filter=$Filter"
            Write-Host "Filter: $Filter" -ForegroundColor DarkGray
        }

        $duskOutput = & php @args 2>&1
        $duskOutput | ForEach-Object { Write-Host $_ }
        [int]$duskExitCode = $LASTEXITCODE
        if ($null -eq $duskExitCode) { $duskExitCode = 0 }
    }
    finally {
        # ALWAYS restore original .env, even on failure/interrupt
        if (Test-Path $envBackup) {
            Copy-Item -Path $envBackup -Destination $envMain -Force
            Write-Host "Restored .env from backup" -ForegroundColor DarkGray
            Remove-Item -Path $envBackup -Force -ErrorAction SilentlyContinue
        }
    }

    return $duskExitCode
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

if ($Help) {
    Show-Usage
    exit 0
}

if (-not (Test-CommandExists "php")) {
    Write-Error "PHP is not in PATH. Cannot run tests."
    exit 1
}

# Validate working directory (must be project root)
if (-not (Test-Path "vendor\autoload.php") -or -not (Test-Path ".env")) {
    Write-Error "This script must be run from the project root directory."
    exit 1
}

$pestExit   = 0
$duskExit   = 0
$runPest    = -not $DuskOnly
$runDusk    = -not $PestOnly

if ($runPest) {
    $pestExit = Invoke-PestTests -Filter $PestFilter
}

if ($runDusk) {
    $duskExit = Invoke-DuskTests -Filter $DuskFilter
}

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

Write-Host "`n========================================" -ForegroundColor Blue
Write-Host "Test Run Summary" -ForegroundColor Blue
Write-Host "========================================" -ForegroundColor Blue

if ($runPest) {
    $pestStatus = if ($pestExit -eq 0) { "PASS" } else { "FAIL" }
    $pestColor  = if ($pestExit -eq 0) { "Green" } else { "Red" }
    Write-Host "Pest tests : $pestStatus (exit code $pestExit)" -ForegroundColor $pestColor
}

if ($runDusk) {
    $duskStatus = if ($duskExit -eq 0) { "PASS" } else { "FAIL" }
    $duskColor  = if ($duskExit -eq 0) { "Green" } else { "Red" }
    Write-Host "Dusk tests : $duskStatus (exit code $duskExit)" -ForegroundColor $duskColor
}

[int]$overallExit = 0
if ($runPest -and $pestExit -ne 0) { $overallExit = 1 }
if ($runDusk -and $duskExit -ne 0) { $overallExit = 1 }

Write-Host "`nOverall    : $(if ($overallExit -eq 0) { 'PASS' } else { 'FAIL' })" -ForegroundColor $(if ($overallExit -eq 0) { "Green" } else { "Red" })

exit $overallExit
