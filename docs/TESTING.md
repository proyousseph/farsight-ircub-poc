# IRCUB testing guide

See also the **Testing** section in the root [`README.md`](../README.md).

## Quick commands

| Suite | Command | Needs |
|---|---|---|
| PHPUnit | `cd backend && php artisan test` | PHP + Composer only |
| Module 5 (channels) smoke | `php scripts/verify_module5.php` | Postgres + Redis (Docker) |
| Module 6 (FMIS) smoke | `php scripts/verify_module6.php` | Postgres + Redis |
| Module 7 (dashboard) smoke | `php scripts/verify_module7.php` | Postgres + Redis |
| Module 1 polish gaps | `php scripts/verify_gaps.php` | Postgres |
| All | `.\scripts\run_all_tests.ps1` or `bash scripts/run_all_tests.sh` | Both |

## PHPUnit layout

- `tests/Feature/AuthApiTest.php`
- `tests/Feature/PayerApiTest.php`
- `tests/Feature/FmisApiTest.php`
- `tests/Feature/DashboardApiTest.php`
- `tests/Feature/ApiDocsTest.php`
- `tests/Unit/DashboardAnalyticsTest.php`
- `tests/Feature/GapPolishTest.php` (SoD, 2FA stub, users/roles, own-scope)
