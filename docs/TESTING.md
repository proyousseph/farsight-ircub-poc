# IRCUB testing guide

See also the **Testing** section in the root [`README.md`](../README.md).

## Quick commands

| Suite | Command | Needs |
|---|---|---|
| PHPUnit | `cd backend && php artisan test` | PHP + Composer only |
| Channel smoke | `php scripts/verify_day5.php` | Postgres + Redis (Docker) |
| FMIS smoke | `php scripts/verify_module6.php` | Postgres + Redis |
| Dashboard smoke | `php scripts/verify_module7.php` | Postgres + Redis |
| All | `.\scripts\run_all_tests.ps1` or `bash scripts/run_all_tests.sh` | Both |

## PHPUnit layout

- `tests/Feature/AuthApiTest.php`
- `tests/Feature/PayerApiTest.php`
- `tests/Feature/FmisApiTest.php`
- `tests/Feature/DashboardApiTest.php`
- `tests/Feature/ApiDocsTest.php`
- `tests/Unit/DashboardAnalyticsTest.php`
