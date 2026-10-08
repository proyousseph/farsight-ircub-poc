# IRCUB automated test runner (Windows PowerShell)
# Usage:  cd backend; .\scripts\run_all_tests.ps1

$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

Write-Host "=== 1) PHPUnit (sqlite in-memory) — all suites ===" -ForegroundColor Cyan
php artisan test
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`n=== 1b) Security + cookie/TOTP focus ===" -ForegroundColor Cyan
php artisan test --filter="SecurityHardeningTest|CookieAuthAndTotpTest|TotpTest"
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`n=== 1c) Performance focus ===" -ForegroundColor Cyan
php artisan test --filter=PerformanceHardeningTest
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`n=== 2) Module smoke scripts (uses .env Postgres/Redis) ===" -ForegroundColor Cyan
php scripts/verify_module5.php
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php scripts/verify_module6.php
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php scripts/verify_module7.php
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php scripts/verify_gaps.php
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host @"

ALL TEST SUITES PASSED
  - PHPUnit (full)              expected ~50 tests / ~214 assertions
  - Security + cookie/TOTP      expected ~26 tests / ~88 assertions
  - Performance                 expected ~4 tests / ~15 assertions
  - Module 5 / 6 / 7 smokes + gaps (10+13+12+6)
"@ -ForegroundColor Green
exit 0
