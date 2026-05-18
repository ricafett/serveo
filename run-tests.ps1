<#
.SYNOPSIS
    Run Pest and/or Dusk tests with automatic .env swapping.

.DESCRIPTION
    This script runs the Serveo test suite. It automatically swaps .env with
    .env.dusk.local before running tests because the local dev .env uses
    PostgreSQL/Redis which may not be available in the test context.

    .env.dusk.local configures SQLite, sync queues, array cache, and
    database-backed sessions — everything needed for both Pest and Dusk.

    The original .env is always restored, even if the tests fail or the
    script is interrupted.

    IMPORTANT: Dusk tests require a manually started php -S server because
    php artisan dusk does NOT start a web server automatically on Windows.
    This script starts the server before Dusk and kills it after.

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

    $pestOutput = & php @args 2>&1
    $pestOutput | ForEach-Object { Write-Host $_ }
    [int]$pestExitCode = $LASTEXITCODE
    if ($null -eq $pestExitCode) { $pestExitCode = 0 }
    return $pestExitCode
}

function Stop-StaleTestProcesses {
    # Kill any php -S processes on port 8000 (orphaned dev servers)
    Get-NetTCPConnection -LocalPort 8000 -ErrorAction SilentlyContinue | ForEach-Object {
        try {
            $proc = Get-Process -Id $_.OwningProcess -ErrorAction SilentlyContinue
            if ($proc -and $proc.Name -eq "php") {
                Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue
                Write-Host "Killed orphaned php -S process (PID: $($_.OwningProcess))" -ForegroundColor DarkGray
            }
        } catch {}
    }

    # Kill any ChromeDriver processes on port 9515
    Get-NetTCPConnection -LocalPort 9515 -ErrorAction SilentlyContinue | ForEach-Object {
        try {
            $proc = Get-Process -Id $_.OwningProcess -ErrorAction SilentlyContinue
            if ($proc -and ($proc.Name -like "*chrome*" -or $proc.Name -like "*chromedriver*")) {
                Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue
                Write-Host "Killed orphaned ChromeDriver process (PID: $($_.OwningProcess))" -ForegroundColor DarkGray
            }
        } catch {}
    }

    # Fallback: kill any php process whose command line contains "127.0.0.1:8000"
    Get-Process -Name "php" -ErrorAction SilentlyContinue | ForEach-Object {
        try {
            # WMI query to get command line
            $cmdLine = (Get-WmiObject Win32_Process -Filter "ProcessId=$($_.Id)" -ErrorAction SilentlyContinue).CommandLine
            if ($cmdLine -and $cmdLine -like "*127.0.0.1:8000*") {
                Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
                Write-Host "Killed stale php -S process (PID: $($_.Id)) via command line scan" -ForegroundColor DarkGray
            }
        } catch {}
    }
}

function Invoke-DuskTests {
    param([string]$Filter)

    Write-Host "`n========================================" -ForegroundColor Blue
    Write-Host "Running Dusk tests..." -ForegroundColor Blue
    Write-Host "========================================" -ForegroundColor Blue

    # Always clean up stale processes before starting
    Stop-StaleTestProcesses

    $serverProcess = $null
    [int]$duskExitCode = 1

    try {
        # Start php -S server in the background
        $serverProcess = Start-Process -FilePath "php" `
            -ArgumentList "-S","127.0.0.1:8000","-t","public" `
            -WorkingDirectory "." `
            -WindowStyle Hidden `
            -PassThru
        Write-Host "Started php -S server on 127.0.0.1:8000 (PID: $($serverProcess.Id))" -ForegroundColor DarkGray

        # Wait for server to accept connections (max 10s)
        $ready = $false
        for ($i = 0; $i -lt 20; $i++) {
            Start-Sleep -Milliseconds 500
            try {
                $tcp = Get-NetTCPConnection -LocalPort 8000 -ErrorAction SilentlyContinue | Select-Object -First 1
                if ($tcp -and $tcp.State -eq "Listen") {
                    $ready = $true
                    break
                }
            } catch {
                # Port not listening yet
            }
        }

        if (-not $ready) {
            Write-Error "PHP dev server on 127.0.0.1:8000 did not start within 10 seconds."
            return 1
        }
        Write-Host "Server ready on 127.0.0.1:8000" -ForegroundColor DarkGray

        # Run Dusk tests
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
        # Kill the server process we started
        if ($serverProcess -and -not $serverProcess.HasExited) {
            Stop-Process -Id $serverProcess.Id -Force -ErrorAction SilentlyContinue
            Write-Host "Stopped php -S server (PID: $($serverProcess.Id))" -ForegroundColor DarkGray
        }

        # Aggressive cleanup: kill ALL php -S and ChromeDriver processes
        Stop-StaleTestProcesses
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

# ---------------------------------------------------------------------------
# Env management: swap to test env before any tests, restore after
# ---------------------------------------------------------------------------

$envBackup = ".env.backup.tests"
$envDusk   = ".env.dusk.local"
$envMain   = ".env"

if (-not (Test-Path $envDusk)) {
    Write-Error ".env.dusk.local not found. Tests cannot run."
    exit 1
}

# Backup current .env
if (Test-Path $envMain) {
    Copy-Item -Path $envMain -Destination $envBackup -Force
    Write-Host "Backed up .env -> $envBackup" -ForegroundColor DarkGray
}

# Swap to test env (needed for both Pest and Dusk)
Copy-Item -Path $envDusk -Destination $envMain -Force
Write-Host "Swapped .env -> .env.dusk.local for test run" -ForegroundColor DarkGray

$pestExit = 0
$duskExit = 0
$runPest  = -not $DuskOnly
$runDusk  = -not $PestOnly

try {
    if ($runPest) {
        $pestExit = Invoke-PestTests -Filter $PestFilter
    }

    if ($runDusk) {
        $duskExit = Invoke-DuskTests -Filter $DuskFilter
    }
}
finally {
    # ALWAYS restore original .env, even on failure/interrupt
    if (Test-Path $envBackup) {
        Copy-Item -Path $envBackup -Destination $envMain -Force
        Write-Host "Restored .env from backup" -ForegroundColor DarkGray
        Remove-Item -Path $envBackup -Force -ErrorAction SilentlyContinue
    }
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
