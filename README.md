# IRCUB — Integrated Revenue Collection & Utility Billing

**Farsight Africa Technologies — Software Developer take-home POC**

| | |
|---|---|
| Candidate | Yusuf Mohamed Ahmed |
| Scope | Modules 1–7 (brief allows ~5 working days; work is tracked by **module**, not by calendar day) |
| Repository | https://github.com/proyousseph/farsight-ircub-poc |
| **Live demo** | **https://ircub.waagefaal.so** |

---

## Try the demo (2 minutes)

1. Open **https://ircub.waagefaal.so**
2. Sign in with:

| Role | Email | Password |
|---|---|---|
| Admin | `admin@ircub.test` | `Password@123` |
| Supervisor | `supervisor@ircub.test` | `Password@123` |
| Officer | `officer@ircub.test` | `Password@123` |
| Water officer | `water@ircub.test` | `Password@123` |
| Auditor | `auditor@ircub.test` | `Password@123` |
| Taxpayer | `taxpayer@ircub.test` | `Password@123` |

3. You will be asked to **change the password** on first login (required for security).

The demo runs on a Contabo VPS with HTTPS. Banks, mobile money, SMS, FX, and FMIS are **simulated** (mock services) for this POC.

---

## What is IRCUB?

A proof-of-concept platform that shows how a government ministry and a water utility can:

- Register taxpayers / customers
- Raise tax assessments and collect payments
- Bill water accounts from meter readings
- Accept multi-channel payments (mock bank / mobile money)
- Post collections to a mock government FMIS
- Monitor revenue with an executive dashboard and simple forecast

---

## What’s included (status)

| Area | Status |
|---|---|
| Module 1 — Users, roles, auth, audit, SoD reversals | Done |
| Module 2 — Taxpayer / customer registry | Done |
| Module 3 — Tax assessments & payments | Done |
| Module 4 — Water metering & billing | Done |
| Module 5 — Payment channels (FX, callback, recon) | Done |
| Module 6 — FMIS journals & reconciliation | Done & verified |
| Module 7 — Executive dashboard + OLS forecast | Done & verified |
| Docs (ERD, OpenAPI, testing) | Done |
| Security & performance hardening | Done |
| Docker (local + Contabo HTTPS) | Done — live at the URL above |

More detail on each module is in the checklists in older commits; the table above is the current delivery status.

---

## Tech stack

| Layer | Technology |
|---|---|
| UI | React 18 + Vite (Dompet admin template) |
| API | Laravel 13 + Sanctum |
| Database | PostgreSQL 16 |
| Cache / queues | Redis 7 |
| Local run | Docker Compose |
| Hosted demo | Contabo + Nginx TLS (`ircub.waagefaal.so`) |

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

## Documentation

| Topic | Where |
|---|---|
| Stack notes | [`docs/STACK.md`](docs/STACK.md) |
| Database ERD | [`docs/ERD.md`](docs/ERD.md) |
| API / OpenAPI / Postman | [`docs/API.md`](docs/API.md) |
| How to test | [`docs/TESTING.md`](docs/TESTING.md) |
| Docker details | [`docker/README.md`](docker/README.md) |
| Swagger (local only) | http://127.0.0.1:8001/docs/api — turned **off** on Contabo |

---

## Run locally

### What you need

- Docker Desktop  
- PHP 8.3+ (with `pdo_pgsql`), Composer  
- Node.js 20+ / npm  

### Option A — Fastest full stack (Docker)

```bash
# From Project/
docker compose up -d                          # Postgres + Redis
cp docker/.env.app.example docker/.env.app
# Put a real APP_KEY and CHANNEL_CALLBACK_SECRET (>= 32 characters) in docker/.env.app
docker compose --profile app up -d --build
```

Then open **http://localhost:8080**

| Service | Address |
|---|---|
| App (UI + API) | http://localhost:8080 |
| Postgres (host) | `127.0.0.1:5433` |
| Redis (host) | `127.0.0.1:6379` |

Optional admin UIs:

```bash
docker compose --profile tools up -d   # pgAdmin :5050, Redis Insight :5540
```

Stop the app stack:

```bash
docker compose --profile app down
```

### Option B — API + UI on your machine

**1. Database & Redis**

```bash
docker compose up -d
```

**2. Backend**

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8001
```

**3. Frontend**

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

Open **http://localhost:5173** (prefer `localhost` so the login cookie works with the API host in `.env`).

For background jobs (channel retries, FMIS, notifications, dashboard):

```bash
php artisan queue:work redis --queue=channels,fmis,notifications,dashboard,default
php artisan schedule:work   # optional
```

### Hosted Contabo (already running)

| | |
|---|---|
| URL | https://ircub.waagefaal.so |
| Server path | `/opt/ircub` |
| Compose | `docker compose --profile app -f docker-compose.yml -f docker-compose.contabo.yml` |
| Env template | [`docker/.env.app.contabo.example`](docker/.env.app.contabo.example) |
| TLS | Let’s Encrypt via existing `ssiwebsite-proxy` |
| Upload script | [`scripts/deploy_contabo.ps1`](scripts/deploy_contabo.ps1) |

On Contabo: debug and Swagger are off; seed-on-boot is off; secure cookies are on. Mock payment/FMIS adapters stay enabled so the demo can run without real banks.

---

## Tests

Full guide: [`docs/TESTING.md`](docs/TESTING.md).

**PHPUnit** (no Docker required — uses in-memory SQLite):

```bash
cd backend
composer install
php artisan test
```

| Suite | Last green run |
|---|---|
| All PHPUnit | **46 tests, 197 assertions** |
| Security + cookie / TOTP | **22 tests, 75 assertions** |
| Performance | **4 tests, 15 assertions** |

**Smoke scripts** (need Postgres + Redis):

```bash
cd backend
php scripts/verify_module5.php
php scripts/verify_module6.php
php scripts/verify_module7.php
php scripts/verify_gaps.php
```

Last smoke run: Module 5 **10/10** · Module 6 **13/13** · Module 7 **12/12** · Gaps **6/6**.

**Run everything:**

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
| Login | `POST /api/auth/login` | Sets HttpOnly cookie `ircub_token`. Add header `X-IRCUB-Return-Token: 1` if you also need the Bearer token in JSON. |
| Who am I | `GET /api/auth/me` | Cookie or Bearer |
| Logout | `POST /api/auth/logout` | Revokes token + clears cookie |
| Change password | `PUT /api/auth/password` | Clears “must change password” |
| 2FA | `/api/auth/2fa/*` | Real TOTP; stub OTP only in local/testing |

Example (local cookie login):

```bash
curl -X POST http://localhost:8001/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -c cookies.txt \
  -d "{\"email\":\"admin@ircub.test\",\"password\":\"Password@123\"}"

curl http://localhost:8001/api/auth/me -b cookies.txt -H "Accept: application/json"
```

---

## FMIS & dashboard (quick API map)

**FMIS** (needs `fmis.post` / `fmis.reconcile` — e.g. supervisor):

- List / create / post / reverse batches under `/api/fmis/batches`
- Reconciliation: `GET /api/fmis/reconciliation?date=YYYY-MM-DD`
- UI: **FMIS Journals**

**Dashboard** (needs `dashboard.view`):

- Full snapshot: `GET /api/dashboard`
- Alerts poll: `GET /api/dashboard/alerts`
- Rebuild aggregates: `POST /api/dashboard/refresh`
- UI: **Dashboard**

---

## Performance (what we did for the POC)

| Area | Approach |
|---|---|
| Dashboard | Daily aggregates table + short Redis cache |
| Lists | Pagination capped at 100 rows per page |
| Database | Indexes on common payment / assessment / audit filters |
| Background work | Redis queues + scheduler (retries, FMIS batch, dashboard rebuild, bill notify) |
| Frontend | Lazy-loaded pages; charts only on the dashboard |

---

## Security (plain summary)

- Login uses an **encrypted HttpOnly cookie** (SPA). API tools can opt in to a Bearer token with a special header.
- Deactivated users and password-change-required users are blocked from business APIs.
- Taxpayers cannot capture payments; they only see their own data.
- Channel callbacks use HMAC; successful settlement needs a matching amount.
- Sensitive fields are redacted in audit / channel responses.
- Mocks and Swagger stay off (or fail closed) outside local/testing unless you explicitly allow them.
- Contabo binds databases to localhost and terminates HTTPS at the edge proxy.

---

## Assumptions & limits

- This is a **POC / sandbox**, not a live payment system.
- External banks, mobile money, SMS, FX, and FMIS are **mocked**.
- Channel mocks do not auto-complete as SUCCESS; settlement needs a proper callback (or local simulate when allowed).
- Dashboard forecast is simple OLS on monthly totals — clear for a demo, not a production forecasting suite.
- “Near real-time” alerts use short polling, not WebSockets.
- No real citizen or financial data is used.

---

## AI assistance

AI coding assistants (including Cursor) helped with scaffolding and implementation. The candidate can explain all submitted code. Commits are kept intentional for review.

---

## License / UI template

Application code is submitted for the Farsight Africa evaluation. The Dompet React admin UI is used under an Envato Elements license for this project and is not redistributed as a standalone commercial template.
