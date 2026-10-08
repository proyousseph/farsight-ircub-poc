# IRCUB — Integrated Revenue Collection & Utility Billing

**Farsight Africa Technologies — Software Developer take-home POC**

I built this POC as Yusuf Mohamed Ahmed. Scope is Modules 1–7 (the brief allows about five working days; I tracked delivery by **module**).

| | |
|---|---|
| Author | Yusuf Mohamed Ahmed |
| Scope | Modules 1–7 |
| Repository | https://github.com/proyousseph/farsight-ircub-poc |
| **Live demo** | **https://ircub.waagefaal.so** |

---

## Try the demo

1. Open **https://ircub.waagefaal.so**
2. Sign in with one of these accounts (password for all: **`Password@123`**):

| Role | Email |
|---|---|
| Admin | `admin@ircub.test` |
| Supervisor | `supervisor@ircub.test` |
| Revenue officer | `officer@ircub.test` |
| Water officer | `water@ircub.test` |
| Auditor | `auditor@ircub.test` |
| Taxpayer | `taxpayer@ircub.test` |

3. Hosted demo (`IRCUB_DEMO_SKIP_PASSWORD_CHANGE`): seeded `@ircub.test` accounts keep `Password@123` and password/role lockouts are blocked. Local Docker without that flag still forces a password change on first login.

I host the live site on Contabo with HTTPS. Banks, mobile money, SMS, FX rates, and FMIS are **mocked** — there is no real money movement.

---

## What is IRCUB?

I built this proof-of-concept to show how a ministry of finance and a water utility can work in one system:

- Register taxpayers and customers  
- Raise tax assessments and collect payments  
- Bill water from meter readings  
- Accept multi-channel payments (mock bank / mobile money)  
- Post collections to a mock government FMIS  
- Monitor revenue on an executive dashboard (with a simple forecast)  

---

## What I delivered

| Area | Status |
|---|---|
| Module 1 — Users, roles, auth, audit, SoD reversals | Done |
| Module 2 — Taxpayer / customer registry | Done |
| Module 3 — Tax assessments & payments | Done |
| Module 4 — Water metering & billing | Done |
| Module 5 — Payment channels (FX, callback, recon) | Done |
| Module 6 — FMIS journals & reconciliation | Done & verified |
| Module 7 — Executive dashboard + OLS forecast | Done & verified |
| Docs (ERD, OpenAPI, Postman, testing guide) | Done |
| Security & performance hardening | Done |
| Docker (local) + Contabo HTTPS demo | Done — **live** |

---

## Tech stack

| Layer | Technology |
|---|---|
| UI | React 18 + Vite (Dompet admin template) |
| API | Laravel 13 + Sanctum |
| Database | PostgreSQL 16 |
| Cache / queues | Redis 7 |
| Local run | Docker Compose |
| Hosted demo | Contabo VPS · https://ircub.waagefaal.so |

---

## Project layout

```text
frontend/          React admin UI
backend/           Laravel API, tests, smoke scripts
docker/            Dockerfiles, env examples, Contabo Nginx snippet
scripts/           Contabo deploy helpers
docs/              STACK, ERD, API, TESTING
docker-compose.yml
docker-compose.contabo.yml
README.md
```

---

## More documentation

| Topic | File |
|---|---|
| Stack notes | [`docs/STACK.md`](docs/STACK.md) |
| Database diagram (ERD) | [`docs/ERD.md`](docs/ERD.md) |
| API / OpenAPI / Postman | [`docs/API.md`](docs/API.md) |
| How to run tests | [`docs/TESTING.md`](docs/TESTING.md) |
| Docker details | [`docker/README.md`](docker/README.md) |
| Swagger (local only) | http://127.0.0.1:8001/docs/api — **off** on Contabo |

---

## Run it on your machine

### You need

- Docker Desktop  
- PHP 8.3+ with `pdo_pgsql`, Composer  
- Node.js 20+ / npm  

### Option A — Full stack in Docker (simplest)

```bash
# From the Project/ folder
docker compose up -d
cp docker/.env.app.example docker/.env.app
# Edit docker/.env.app:
#   - APP_KEY (php artisan key:generate --show)
#   - CHANNEL_CALLBACK_SECRET (>=32 chars)
#   - IRCUB_SEED_ON_BOOT=true  (example default) so demo users exist on first boot
docker compose --profile app up -d --build
# If you set IRCUB_SEED_ON_BOOT=false, seed once:
#   docker compose exec api php artisan db:seed --force
```

Open **http://localhost:8080**

| Service | Where |
|---|---|
| App (UI + API) | http://localhost:8080 |
| Postgres | `127.0.0.1:5433` |
| Redis | `127.0.0.1:6379` |

Optional tools (pgAdmin / Redis Insight):

```bash
docker compose --profile tools up -d
```

Stop:

```bash
docker compose --profile app down
```

### Option B — PHP API + Vite frontend

```bash
# 1) Database + Redis
docker compose up -d

# 2) Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
# Required for channel callbacks / Module 5 smoke (do not leave empty):
php -r "file_put_contents('.env', preg_replace('/^CHANNEL_CALLBACK_SECRET=.*/m', 'CHANNEL_CALLBACK_SECRET='.bin2hex(random_bytes(32)), file_get_contents('.env')));"
php artisan migrate:fresh --seed
# Swagger: http://127.0.0.1:8001/docs/api (IRCUB_DOCS_ENABLED=true in .env.example)
php artisan serve --host=127.0.0.1 --port=8001

# 3) Frontend (new terminal)
cd frontend
cp .env.example .env
npm install
npm run dev
```

Open **http://localhost:5173** (use `localhost` so the login cookie works).

Background jobs (optional but recommended):

```bash
php artisan queue:work redis --queue=channels,fmis,notifications,dashboard,default
php artisan schedule:work
```

### Contabo (I already deployed this)

| | |
|---|---|
| Public URL | https://ircub.waagefaal.so |
| Code on server | `/opt/ircub` |
| Start command | `docker compose --profile app -f docker-compose.yml -f docker-compose.contabo.yml up -d --build` |
| Env template | [`docker/.env.app.contabo.example`](docker/.env.app.contabo.example) |
| HTTPS | Let’s Encrypt via `ssiwebsite-proxy` |
| Deploy helper | [`scripts/deploy_contabo.ps1`](scripts/deploy_contabo.ps1) |

On Contabo I run production mode, secure cookies, no Swagger, and no seed-on-boot after first setup. Mocks stay on so the demo works without real banks.

---

## Tests

Full guide: [`docs/TESTING.md`](docs/TESTING.md).

```bash
cd backend
composer install
php artisan test
```

| Suite | Latest green run |
|---|---|
| All PHPUnit | **58 tests, 240 assertions** |
| Security + cookie / TOTP | **30 tests, 96 assertions** |
| Performance | **4 tests, 15 assertions** |

Module smoke scripts (need Postgres + Redis):

```bash
cd backend
php scripts/verify_module5.php
php scripts/verify_module6.php
php scripts/verify_module7.php
php scripts/verify_gaps.php
```

Last smoke run: Module 5 **11/11** · Module 6 **13/13** · Module 7 **12/12** · Gaps **6/6**.

Run everything:

```powershell
# Windows
cd backend
.\scripts\run_all_tests.ps1
```

```bash
# macOS / Linux
cd backend
bash scripts/run_all_tests.sh
```

---

## Auth (short)

| Action | Endpoint | Notes |
|---|---|---|
| Login | `POST /api/auth/login` | Sets HttpOnly cookie `ircub_token`. Add `X-IRCUB-Return-Token: 1` if you also need the Bearer token in JSON. |
| Current user | `GET /api/auth/me` | Cookie or Bearer |
| Logout | `POST /api/auth/logout` | Revokes token and clears cookie |
| Change password | `PUT /api/auth/password` | Clears “must change password” |
| Two-factor | `/api/auth/2fa/*` | Real TOTP (authenticator app) |

---

## Main features (plain language)

**Users & security** — I implemented six roles, permissions, optional TOTP, an audit log with a tamper-evident hash chain, and payment reversals that need a different person to approve.

**Registry** — Payers / customers with duplicate checks (including normalized TIN), a 360° profile, and configurable revenue types.

**Tax** — Assessments, payments (USD capture), CSV upload, filters, and an audit trail.

**Water** — Tariffs, meter readings, monthly bills (past months only), PDFs, statements, and abnormal-use holds; water accounts can be linked to existing payers.

**Channels** — Mock FX, bank/mobile initiate + HMAC callback, retries, and daily reconciliation. Success needs a matching amount (no fake auto-success).

**FMIS** — GL mapping, daily journals, post/reverse, and IRCUB vs FMIS recon.

**Dashboard** — Trends, targets, water efficiency, an OLS next-quarter forecast, and alerts.

**Taxpayer self-service** — Taxpayers can view their own data and pay/check their own linked obligations; they cannot capture payments for other people.

---

## Performance (POC)

| Area | What I did |
|---|---|
| Dashboard | Daily aggregates + Redis cache |
| Lists | Pagination (max 100 per page) |
| Database | Indexes on common filters |
| Background work | Redis queues + scheduler |
| Frontend | Lazy-loaded pages; charts only on the dashboard |

---

## Security (plain summary)

- Encrypted HttpOnly login cookie; optional Bearer token for API tools  
- Deactivated users and “must change password” are blocked from business APIs  
- Taxpayers only see and pay their own linked obligations  
- Channel settlement uses HMAC; amount must match (callbacks and status checks)  
- Sensitive fields are hidden in audit / channel responses  
- Audit log is hash-chained (tamper-evident); optional: `php artisan ircub:audit-backfill-chain --verify`  
- Same password cannot be reused on password change  
- Mocks and Swagger stay off outside local testing unless you explicitly allow them  

---

## Assumptions & limits

- I built this as a **POC sandbox**, not a live payment system.  
- Banks, mobile money, SMS, FX, and FMIS are **mocked**.  
- Dashboard “near real-time” uses short polling, not WebSockets.  
- The forecast is simple OLS on monthly totals — clear for a demo, not a production forecasting product.  
- I do not use real citizen or financial data.  

---

## AI assistance

I used AI coding assistants (including Cursor) while scaffolding and building this project. I can explain all of the code in this repo, and I kept commits intentional so they are easy to review.

---

## License / UI template

I am submitting this application code for the Farsight Africa evaluation. I use the Dompet React admin UI under an Envato Elements license for this project; I am not redistributing it as a standalone commercial template.
