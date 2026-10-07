# IRCUB automated test runner (Windows PowerShell)
# Usage:  cd backend; .\scripts\run_all_tests.ps1

$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

Write-Host "=== 1) PHPUnit (sqlite in-memory) ===" -ForegroundColor Cyan
php artisan test
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

Write-Host "`nALL TEST SUITES PASSED" -ForegroundColor Green
exit 0
