# IRCUB Docker

## Profiles

| Command | What starts |
|---|---|
| `docker compose up -d` | Postgres **5433**, Redis **6379**, pgAdmin **5050**, Redis Insight **5540** |
| `docker compose --profile app up -d --build` | Above + **api**, **queue**, **scheduler**, **web** (**8080**) |

## Full app stack

```bash
# From Project/
cp docker/.env.app.example docker/.env.app
# Paste a stable key:
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

- Set a **stable `APP_KEY`** so cookies/sessions survive restarts (entrypoint generates an ephemeral key if missing).
- `IRCUB_SEED_ON_BOOT=true` (default in example) seeds demo users on first API boot.
- `IRCUB_2FA_ALLOW_STUB=true` is for local/demo only — turn off for Contabo HTTPS.

### Workers

- **queue** — `queue:work redis` on `channels,fmis,notifications,dashboard,default`
- **scheduler** — `schedule:work` (channel retries, FMIS daily batch, dashboard rebuild)

### Stop

```bash
docker compose --profile app down          # app containers + infra
docker compose --profile app down -v       # also wipe Postgres/Redis volumes
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
    └── nginx.conf         # SPA + /api proxy
```

## Tests

Automated tests run on the **host** (see [`docs/TESTING.md`](../docs/TESTING.md)):

```bash
cd backend
php artisan test
.\scripts\run_all_tests.ps1   # or bash scripts/run_all_tests.sh
```
