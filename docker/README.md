# IRCUB Docker

How I run IRCUB with Docker Compose (local and Contabo).

## Profiles

| Command | What starts |
|---|---|
| `docker compose up -d` | Postgres **127.0.0.1:5433**, Redis **127.0.0.1:6379** (password-protected) |
| `docker compose --profile tools up -d` | + pgAdmin **127.0.0.1:5050**, Redis Insight **127.0.0.1:5540** |
| `docker compose --profile app up -d --build` | + **api**, **queue**, **scheduler**, **web** (**127.0.0.1:8080**) |
| Contabo (behind SSI proxy) | `docker compose --profile app -f docker-compose.yml -f docker-compose.contabo.yml up -d --build` |

Contabo: use [`docker/.env.app.contabo.example`](.env.app.contabo.example), Nginx snippet [`docker/nginx/ircub.waagefaal.so.conf`](nginx/ircub.waagefaal.so.conf), deploy helper [`scripts/deploy_contabo.ps1`](../scripts/deploy_contabo.ps1).

Ports bind to **localhost only** (including web). Redis default password: `ircub_redis_local` (`REDIS_PASSWORD`). To expose the SPA on the LAN: `IRCUB_WEB_BIND=0.0.0.0` (not recommended).

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
| http://localhost:8080 | Nginx SPA (`web`) — proxies `/api`, `/docs` |
| http://localhost:8080/api | Laravel API (`api` → port 8000 inside network) |
| http://localhost:8080/docs/api | Swagger UI (when `IRCUB_DOCS_ENABLED=true`) |

Containers: `ircub-api`, `ircub-queue`, `ircub-scheduler`, `ircub-web`, `ircub-postgres`, `ircub-redis`.

### Env notes (`docker/.env.app`)

| Variable | Purpose |
|---|---|
| `APP_KEY` | **Required for stable cookies** — set in `docker/.env.app` only (compose does **not** override with empty `${APP_KEY:-}`) |
| `APP_DEBUG` | Compose default **false** |
| `CHANNEL_CALLBACK_SECRET` | HMAC ≥32 chars in `docker/.env.app`; entrypoint generates ephemeral if missing/insecure |
| `CHANNEL_ALLOW_SIMULATE` | Compose default **false** (also forced off when `APP_ENV` ∉ local/testing) |
| `CHANNEL_ALLOW_MOCK` / `FMIS_ALLOW_MOCK` | In-process mocks — **false** on Contabo |
| `IRCUB_AUTH_COOKIE_SAMESITE` | Cookie SameSite (`lax` default) |
| `IRCUB_MAX_UNLINKED_PAYMENT` | Cap for payments not linked to assessment/bill |
| `IRCUB_SEED_ON_BOOT` | Seed demo users on API boot (from `docker/.env.app`; compose does **not** override api with `false`) |
| `IRCUB_2FA_ALLOW_STUB` | Demo OTP — compose default **false** |
| `IRCUB_DOCS_ENABLED` | Swagger — compose default **false** |
| `REDIS_PASSWORD` | Redis `requirepass` (default `ircub_redis_local`) |
| `SESSION_SECURE_COOKIE` | `true` behind HTTPS |
| `PGADMIN_DEFAULT_PASSWORD` | Only for `--profile tools` |

Lab opt-in (set in **Project/.env** so compose `${}` picks them up): `APP_DEBUG=true`, `CHANNEL_ALLOW_SIMULATE=true`, `IRCUB_2FA_ALLOW_STUB=true`, `IRCUB_DOCS_ENABLED=true`.

**Contabo / staging checklist:** `APP_ENV=production`, `APP_DEBUG=false`, `CHANNEL_ALLOW_SIMULATE=false`, `IRCUB_SEED_ON_BOOT=false` (I turn seed-on-boot off after the first demo users exist), `IRCUB_2FA_ALLOW_STUB=false`, `IRCUB_DOCS_ENABLED=false`, strong unique `CHANNEL_CALLBACK_SECRET` + `APP_KEY` in `docker/.env.app` (not empty host overrides), `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES` set for the edge proxy. For this take-home demo I keep `CHANNEL_ALLOW_MOCK=true` / `FMIS_ALLOW_MOCK=true`; I would set them **false** outside mock mode.

### Nginx (`web`)

- SPA fallback + `/api` reverse proxy (forwards `Authorization` + cookies)
- Security headers: `nosniff`, `DENY` frame, `no-referrer`, SPA CSP (`script-src 'self'`, `connect-src 'self'`)
- Does **not** proxy `/mock-api` (mocks are in-process)
- `client_max_body_size 20m` on `/api` (CSV uploads)

### Workers

- **queue** — `queue:work redis` on `channels,fmis,notifications,dashboard,default`
- **scheduler** — `schedule:work` (channel retries, FMIS daily batch, dashboard rebuild)

### Stop

```bash
docker compose --profile app down          # app containers + infra
docker compose --profile app --profile tools down -v   # also wipe volumes + tools
```

## Contabo (hosted)

I host the live demo at **https://ircub.waagefaal.so** · code on the VPS at `/opt/ircub`.

```bash
# On VPS
cd /opt/ircub
docker compose --profile app -f docker-compose.yml -f docker-compose.contabo.yml up -d --build
```

- Env: copy [`docker/.env.app.contabo.example`](.env.app.contabo.example) → `.env` + `docker/.env.app`
- Edge TLS: SSI `ssiwebsite-proxy` mounts [`nginx/ircub.waagefaal.so.conf`](nginx/ircub.waagefaal.so.conf)
- From Windows: [`scripts/deploy_contabo.ps1`](../scripts/deploy_contabo.ps1)

## Layout

```text
docker/
├── README.md
├── .env.app.example
├── .env.app.contabo.example
├── nginx/                 # Contabo SSI proxy server blocks
├── backend/
│   ├── Dockerfile
│   └── entrypoint.sh
└── frontend/
    ├── Dockerfile
    └── nginx.conf
```

## Tests

I run automated tests on the **host** (see [`docs/TESTING.md`](../docs/TESTING.md)). Last green: **58 / 240** PHPUnit · security **30 / 96** · performance **4 / 15**.

```bash
cd backend
php artisan test
.\scripts\run_all_tests.ps1   # or bash scripts/run_all_tests.sh
```
