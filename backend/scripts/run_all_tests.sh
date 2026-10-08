#!/usr/bin/env bash
# IRCUB automated test runner
# Usage:  cd backend && bash scripts/run_all_tests.sh
set -euo pipefail
cd "$(dirname "$0")/.."

echo "=== 1) PHPUnit (sqlite in-memory) — all suites ==="
php artisan test

echo
echo "=== 1b) Security + cookie/TOTP focus ==="
php artisan test --filter='SecurityHardeningTest|CookieAuthAndTotpTest|TotpTest'

echo
echo "=== 1c) Performance focus ==="
php artisan test --filter=PerformanceHardeningTest

echo
echo "=== 2) Module smoke scripts (uses .env Postgres/Redis) ==="
php scripts/verify_module5.php
php scripts/verify_module6.php
php scripts/verify_module7.php
php scripts/verify_gaps.php

cat <<'EOF'

ALL TEST SUITES PASSED
  - PHPUnit (full)              expected ~46 tests / ~197 assertions
  - Security + cookie/TOTP      expected ~22 tests / ~75 assertions
  - Performance                 expected ~4 tests / ~15 assertions
  - Module 5 / 6 / 7 smokes + gaps (10+13+12+6)
EOF
