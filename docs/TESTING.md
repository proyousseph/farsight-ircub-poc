# IRCUB testing guide

See also the **Testing** section in the root [`README.md`](../README.md).

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
| `CookieAuthAndTotpTest.php` | HttpOnly cookie auth, TOTP setup/confirm/login |
| `SecurityHardeningTest.php` | Taxpayer payment forbid, deactivated tokens, PDF owner scope, callback IDs, must-change-password |
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

Relevant test env flags:

- `CHANNEL_ALLOW_SIMULATE=true`
- `CHANNEL_CALLBACK_SECRET=ircub-mock-callback-secret`
- `IRCUB_2FA_ENABLED=true` / `IRCUB_2FA_ALLOW_STUB=true` / `IRCUB_DEMO_OTP=123456`
- `IRCUB_DOCS_ENABLED=true`

## Last known green run

- **PHPUnit:** 36 tests, 162 assertions — all passed  
- **Smokes:** Module 5 10/10 · Module 6 13/13 · Module 7 12/12 · Gaps 6/6  

## Security / auth checks to re-run after changes

```bash
cd backend
php artisan test --filter="SecurityHardeningTest|CookieAuthAndTotpTest|TotpTest"
```

HTTP smoke (API running on `:8001`):

1. Login returns `cookie_auth: true` and `Set-Cookie: ircub_token=...`
2. `/api/auth/me` with cookie only (no `Authorization`) → 200  
3. Taxpayer `POST /api/payments` → 403  
4. Response headers include `X-Content-Type-Options: nosniff` and `X-Frame-Options: DENY`

## Docker full stack

Infra only (Postgres/Redis/pgAdmin/Redis Insight):

```bash
docker compose up -d
```

Full app (API + queue + scheduler + Nginx SPA on **:8080**):

```bash
cp docker/.env.app.example docker/.env.app
# set APP_KEY in docker/.env.app
docker compose --profile app up -d --build
```

| Check | URL / command |
|---|---|
| UI | http://localhost:8080 |
| API health | `curl -s http://localhost:8080/api/auth/me` (401 without cookie) |
| Swagger | http://localhost:8080/docs/api |
| PHPUnit | still run on the host (`cd backend && php artisan test`) — not inside the container by default |

Smoke scripts need a backend `.env` pointing at host Postgres (`127.0.0.1:5433`) and Redis (`127.0.0.1:6379`), or run them against a local PHP install with that config.
