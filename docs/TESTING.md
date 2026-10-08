# IRCUB testing guide

How I run and verify tests for this project. See also the **Testing** section in the root [`README.md`](../README.md).

## Quick commands

| Suite | Command | Needs |
|---|---|---|
| PHPUnit (all) | `cd backend && php artisan test` | PHP + Composer only |
| Security | `php artisan test --filter="SecurityHardeningTest\|CookieAuthAndTotpTest\|TotpTest"` | PHP only |
| Performance | `php artisan test --filter=PerformanceHardeningTest` | PHP only |
| Module 5 (channels) smoke | `php scripts/verify_module5.php` | Postgres + Redis (Docker) |
| Module 6 (FMIS) smoke | `php scripts/verify_module6.php` | Postgres + Redis |
| Module 7 (dashboard) smoke | `php scripts/verify_module7.php` | Postgres + Redis |
| Module 1 polish gaps | `php scripts/verify_gaps.php` | Postgres |
| All | `.\scripts\run_all_tests.ps1` or `bash scripts/run_all_tests.sh` | Both |

## PHPUnit layout

### Feature

| File | Covers |
|---|---|
| `AuthApiTest.php` | Login / me / logout |
| `CookieAuthAndTotpTest.php` | HttpOnly cookie auth, TOTP setup/confirm/login, security headers, taxpayer seed |
| `SecurityHardeningTest.php` | Taxpayer forbid, deactivated tokens, PDF scope, callback amount/IDs, must-change-password, role token revoke, unlinked cap, audit redact, channel strip, national_id omit, admin 2FA clear password |
| `PerformanceHardeningTest.php` | Pagination cap, indexes/list queries, queue job dispatch, sync retries |
| `PayerApiTest.php` | Registry create/list/duplicates |
| `FmisApiTest.php` | Journal create/post/reverse/recon |
| `DashboardApiTest.php` | Snapshot, cache hit meta, alerts, refresh |
| `GapPolishTest.php` | SoD reversals, 2FA stub (local), users/roles, own-scope |
| `ApiDocsTest.php` | OpenAPI / Swagger routes (when `IRCUB_DOCS_ENABLED`) |

### Unit

| File | Covers |
|---|---|
| `DashboardAnalyticsTest.php` | OLS forecast helpers |
| `TotpTest.php` | TOTP secret/code verification |

## PHPUnit environment (`phpunit.xml`)

Uses in-memory SQLite + `QUEUE_CONNECTION=sync` + `CACHE_STORE=array`.

Relevant test env flags (`phpunit.xml`):

- `CHANNEL_ALLOW_SIMULATE=true` / `CHANNEL_ALLOW_MOCK=true` / `FMIS_ALLOW_MOCK=true`
- `CHANNEL_CALLBACK_SECRET=ircub-mock-callback-secret`
- `IRCUB_2FA_ENABLED=true` / `IRCUB_2FA_ALLOW_STUB=true` / `IRCUB_DEMO_OTP=123456`
- `IRCUB_DOCS_ENABLED=true`
- `IRCUB_AUTH_COOKIE_SAMESITE=lax`
- `IRCUB_MAX_UNLINKED_PAYMENT=100000`

## Last green run (my machine)

| Suite | Result |
|---|---|
| Full PHPUnit | **59 tests, 245 assertions** — passed |
| Security + cookie/TOTP | **30 tests, 96 assertions** — passed |
| Performance | **4 tests, 15 assertions** — passed |
| Smokes | Module 5 **11/11** · Module 6 13/13 · Module 7 12/12 · Gaps 6/6 |

## Security / auth checks I re-run after changes

```bash
cd backend
php artisan test --filter="SecurityHardeningTest|CookieAuthAndTotpTest|TotpTest"
php scripts/verify_gaps.php   # needs Postgres
```

What those suites cover:

| Check | Covered by |
|---|---|
| HttpOnly `ircub_token` + cookie-only `/me` | `CookieAuthAndTotpTest` |
| TOTP setup / confirm / login | `CookieAuthAndTotpTest`, `TotpTest` |
| Security headers (CSP, nosniff, frame deny) | `CookieAuthAndTotpTest` |
| Taxpayer cannot cash-capture; can `pay_own` channel | `SecurityHardeningTest`, `CookieAuthAndTotpTest` |
| Deactivated user tokens rejected | `SecurityHardeningTest` |
| Role change revokes tokens | `SecurityHardeningTest` |
| Unlinked payment amount cap | `SecurityHardeningTest` |
| Manual capture USD-only | `SecurityHardeningTest` |
| TIN normalize / case-duplicate | `SecurityHardeningTest` |
| Audit log redacts + hash chain | `SecurityHardeningTest` |
| Channel list strips provider payloads | `SecurityHardeningTest` |
| SUCCESS callback requires matching amount | `SecurityHardeningTest` |
| Payer list omits national_id | `SecurityHardeningTest` |
| Admin 2FA clear needs admin_password | `SecurityHardeningTest` |
| Active 2FA re-setup needs password+OTP | `CookieAuthAndTotpTest` |
| Channel payment own-scope | `SecurityHardeningTest` |
| Water bill PDF owner scope | `SecurityHardeningTest` |
| Callback rejects empty identifiers | `SecurityHardeningTest` |
| Must-change-password gate | `SecurityHardeningTest` |
| FMIS reverse keeps journal lines | `FmisApiTest` |
| SoD reversal request ≠ approve | `GapPolishTest` / `verify_gaps.php` |

HTTP smoke (API running on `:8001` or Docker `:8080`):

1. Login returns `cookie_auth: true` and `Set-Cookie: ircub_token=...`
2. `/api/auth/me` with cookie only (no `Authorization`) → 200  
3. Taxpayer `POST /api/payments` → 403  
4. Response headers include `X-Content-Type-Options: nosniff` and `X-Frame-Options: DENY`

## Docker full stack

```bash
docker compose up -d                              # Postgres + Redis
docker compose --profile tools up -d              # optional pgAdmin + Redis Insight
cp docker/.env.app.example docker/.env.app
# Paste real APP_KEY + CHANNEL_CALLBACK_SECRET (>=32 chars) into docker/.env.app
# Do NOT put empty APP_KEY= in Project/.env — compose no longer overrides env_file with empty ${APP_KEY:-}
# Local Swagger: IRCUB_DOCS_ENABLED=true in docker/.env.app
docker compose --profile app up -d --build        # API + queue + scheduler + web :8080
```

Details: [`docker/README.md`](../docker/README.md).

| Check | URL / command |
|---|---|
| UI | http://localhost:8080 |
| API (unauth) | `curl -s -o NUL -w "%{http_code}" http://localhost:8080/api/auth/me` → `401` |
| Swagger | http://localhost:8080/docs/api |
| PHPUnit | host: `cd backend && php artisan test` (not inside the image by default) |

Smoke scripts need backend `.env` pointing at host Postgres (`127.0.0.1:5433`) and Redis (`127.0.0.1:6379`).

## Hosted demo

**https://ircub.waagefaal.so** — my Contabo Docker stack; Swagger/docs off; seed-on-boot off. Quick check: SPA 200, `/api/auth/me` 401 without cookie, login with `admin@ircub.test`.
