# IRCUB Docker

## Profiles

| Command | What starts |
|---|---|
| `docker compose up -d` | Postgres **5433**, Redis **6379** |
| `docker compose --profile tools up -d` | + pgAdmin **5050**, Redis Insight **5540** |
| `docker compose --profile app up -d --build` | + **api**, **queue**, **scheduler**, **web** (**8080**) |

Combine profiles when needed:

```bash
docker compose --profile tools --profile app up -d --build
```

## Full app stack

```bash
# From Project/
cp docker/.env.app.example docker/.env.app
# Paste a stable APP_KEY and a unique CHANNEL_CALLBACK_SECRET (>=32 chars):
#   cd backend && php artisan key:generate --show
docker compose --profile app up -d --build
```

| URL | Service |
|---|---|
| http://localhost:8080 | Nginx SPA (`web`) — proxies `/api`, `/docs`, `/mock-api` |
| http://localhost:8080/api | Laravel API (`api` → port 8000 inside network) |
| http://localhost:8080/docs/api | Swagger UI (when `IRCUB_DOCS_ENABLED=true`) |

Containers: `ircub-api`, `ircub-queue`, `ircub-scheduler`, `ircub-web`, `ircub-postgres`, `ircub-redis`.

### Env notes (`docker/.env.app`)

| Variable | Purpose |
|---|---|
| `APP_KEY` | **Required for stable cookies** — entrypoint generates ephemeral key if missing |
| `CHANNEL_CALLBACK_SECRET` | HMAC secret (≥32 chars); mock/default values rejected outside local/testing |
| `CHANNEL_ALLOW_SIMULATE` | Client simulate — forced off when `APP_ENV` ∉ local/testing |
| `CHANNEL_ALLOW_MOCK` / `FMIS_ALLOW_MOCK` | POC mock adapters — **set false** on Contabo unless intentional |
| `IRCUB_AUTH_COOKIE_SAMESITE` | Cookie SameSite (`lax` default) |
| `IRCUB_MAX_UNLINKED_PAYMENT` | Cap for payments not linked to assessment/bill |
| `IRCUB_SEED_ON_BOOT` | Seed demo users on API boot (default `true` in example) |
| `IRCUB_2FA_ALLOW_STUB` | Demo OTP — **set `false` on Contabo** |
| `IRCUB_DOCS_ENABLED` | Swagger — **set `false` on Contabo** |
| `SESSION_SECURE_COOKIE` | `true` behind HTTPS |
| `PGADMIN_DEFAULT_PASSWORD` | Only for `--profile tools` (default `change-me-pgadmin`) |

**Contabo / staging checklist:** `APP_ENV=production`, `APP_DEBUG=false`, `CHANNEL_ALLOW_SIMULATE=false`, `CHANNEL_ALLOW_MOCK=false`, `FMIS_ALLOW_MOCK=false`, `IRCUB_2FA_ALLOW_STUB=false`, `IRCUB_DOCS_ENABLED=false`, strong `CHANNEL_CALLBACK_SECRET`, `SESSION_SECURE_COOKIE=true`.

### Nginx (`web`)

- SPA fallback + `/api` reverse proxy (forwards `Authorization` + cookies)
- Security headers: `nosniff`, `DENY` frame, `no-referrer`, SPA CSP (`script-src 'self'`, `connect-src 'self'`)
- `client_max_body_size 20m` on `/api` (CSV uploads)

### Workers

- **queue** — `queue:work redis` on `channels,fmis,notifications,dashboard,default`
- **scheduler** — `schedule:work` (channel retries, FMIS daily batch, dashboard rebuild)

### Stop

```bash
docker compose --profile app down          # app containers + infra
docker compose --profile app --profile tools down -v   # also wipe volumes + tools
```

## Layout

```text
docker/
├── README.md
├── .env.app.example
├── backend/
│   ├── Dockerfile
│   └── entrypoint.sh      # wait for DB → migrate/seed → exec
└── frontend/
    ├── Dockerfile         # Vite build → Nginx
    └── nginx.conf         # SPA + /api proxy + CSP headers
```

## Tests

Automated tests run on the **host** (see [`docs/TESTING.md`](../docs/TESTING.md)).

Last green (host):

| Suite | Result |
|---|---|
| Full PHPUnit | **43 tests, 189 assertions** |
| Security + cookie/TOTP | **19 tests, 67 assertions** |
| Performance | **4 tests, 15 assertions** |
| Smokes | Module 5 10/10 · 6 13/13 · 7 12/12 · Gaps 6/6 |

```bash
cd backend
php artisan test
.\scripts\run_all_tests.ps1   # or bash scripts/run_all_tests.sh
```
