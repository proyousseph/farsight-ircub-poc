# IRCUB — Integrated Revenue Collection & Utility Billing Platform

**Farsight Africa Technologies — Software Developer POC (take-home)**

Candidate: **Yusuf Mohamed Ahmed**  
Scope: **Modules 1–7** (POC brief calendar window: 5 working days)

| Item | Link |
|---|---|
| Repository | https://github.com/proyousseph/farsight-ircub-poc |
| Demo (planned) | https://ircub.waagefaal.so |

> Demo subdomain DNS already points to the Contabo VPS (`161.97.90.92`).  
> **Docker:** infra always available; full app stack via `docker compose --profile app`.  
> **Next for Contabo:** point Nginx/TLS at the `web` service (or host `:8080`) with a production `APP_KEY` + secrets.

---

## Overview

IRCUB is a proof-of-concept platform that automates:

- Tax revenue assessment and collection for a Ministry of Finance
- Water utility billing for a state water agency
- Multi-channel payments (mock bank / mobile money)
- Automated posting to a mock government FMIS
- Role-based operations and an executive dashboard

External systems (banks, mobile money, SMS, FX rates, FMIS) are **mocked**.

---

## Technology stack

| Layer | Choice |
|---|---|
| Frontend | React 18 + Vite (Dompet admin template, Envato Elements) |
| Backend | Laravel 13 API + Sanctum |
| Database | PostgreSQL 16 |
| Cache / queues / sessions | Redis 7 (Predis client) |
| Local infra | Docker Compose |
| Hosted demo | Contabo VPS + subdomain `ircub.waagefaal.so` |

---

## Project structure

```text
.
├── frontend/              # React + Vite admin UI (IRCUB routes)
├── backend/               # Laravel API + PHPUnit + smoke scripts
├── docker/
│   ├── README.md          # Compose profiles, env, URLs
│   ├── backend/           # API Dockerfile + entrypoint
│   ├── frontend/          # SPA Nginx Dockerfile + reverse-proxy conf
│   ├── .env.app.example   # Env for --profile app
│   └── .env.app           # Local docker app env (gitignored if present)
├── docs/                  # STACK, ERD, API, TESTING
├── docker-compose.yml     # Postgres/Redis (+ optional full app profile)
└── README.md
```

---

## Module progress (POC checklist)

Progress follows the **document modules** (1–7). The brief’s take-home window is five working days; delivery is tracked by module, not by calendar day.

### Module 1 — User & Role Management — Done

- [x] Docker Compose: PostgreSQL + Redis; optional `--profile tools` (pgAdmin / Redis Insight); `--profile app` full stack
- [x] Laravel configured for Postgres / Redis
- [x] DB-driven roles & permissions
- [x] Six system roles seeded with permissions
- [x] Hierarchical roles (`parent_id` / `level`; child permissions ⊆ parent)
- [x] System configuration API + UI (`config.manage` — password, 2FA, water threshold, channel retries)
- [x] Sanctum auth API: login / logout / me / change password
- [x] Encrypted HttpOnly cookie auth (`ircub_token`); Bearer via `X-IRCUB-Return-Token: 1` only
- [x] Password policy (min 10 + upper/lower/number/symbol) on user create/update (admin-tunable)
- [x] Optional 2FA: **TOTP** setup/confirm/disable; local stub OTP `123456` only when `IRCUB_2FA_ALLOW_STUB=true`
- [x] Security middleware: active-user check, must-change-password gate, trusted Origin, CSP/security headers, login throttle
- [x] Role/permission changes revoke Sanctum tokens; audit/channel APIs redact sensitive payloads
- [x] Unlinked payment amount cap (`IRCUB_MAX_UNLINKED_PAYMENT`)
- [x] Mock channel/FMIS fail-closed outside local/testing unless `CHANNEL_ALLOW_MOCK` / `FMIS_ALLOW_MOCK`
- [x] Users & Roles admin API + UI (custom roles, activate/deactivate users; 2FA enable requires confirmed TOTP)
- [x] Payment reversal segregation of duties (request ≠ approve)
- [x] Audit log browser API + UI
- [x] Taxpayer self-service scoped to linked `payer_id` (**no** `payments.capture`)
- [x] Permission middleware + frontend `RequirePermission` route guards
- [x] Dompet login wired to Laravel API
- [x] Role-based sidebar menus (IRCUB routes only)

### Module 2 — Taxpayer & Customer Registry — Done

- [x] Payers, water accounts, and revenue obligations schema
- [x] Duplicate detection (phone / email / national ID) with force-create flag
- [x] Payer API: list, create, show (360° profile), update
- [x] Demo seed data (including intentional duplicate phone)
- [x] Frontend: payer list, register form, profile page

### Module 3 — Tax Revenue Assessment & Collection — Done

- [x] Revenue types with rates and GL codes
- [x] Assessments with unique control numbers
- [x] Payment capture linked to assessments
- [x] CSV bulk payment upload with accept/reject summary + sample CSV download
- [x] Audit logs (who / when / before / after)
- [x] Advanced filters (search, revenue type, amount range, channel, status, date range)
- [x] Frontend Assessments + Payments pages
- [x] Payer 360° shows assessments, payments, and balance

### Module 4 — Water Utility Billing — Done

- [x] Tiered water tariffs (DOMESTIC / COMMERCIAL / INSTITUTIONAL)
- [x] Meter readings (individual + CSV); reject lower readings unless rollover/replacement
- [x] Monthly billing cycle (tariff + arrears + WATER payment netting)
- [x] Abnormal consumption hold (>200% of 3-month average) + exception report
- [x] Bill PDF + mock SMS/email on release
- [x] Customer statement (bills, payments, running balance)
- [x] Frontend: Meter Readings, Billing Cycles, Water Bills

### Module 5 — Payment Channel Integration — Done

- [x] Mock FX rates API (`/mock-api/rates`) — dynamic USD ↔ SOS
- [x] Mock bank / mobile money initiate + status endpoints
- [x] Flow: fetch FX → initiate → callback/status → update assessment/bill
- [x] Multi-currency with stored FX snapshot
- [x] Retry status checks up to 3 times; permanent failure + supervisor notification
- [x] Daily channel reconciliation vs mock statement file
- [x] Frontend: Channel Payments + Reconciliation pages

### Module 6 — FMIS Posting & Reconciliation — Done & verified

- [x] Configurable revenue type → GL code mapping (`gl_mappings`)
- [x] Daily journal batches with statuses Pending / Posted / Failed / Reversed
- [x] Mock FMIS post stores FMIS reference; same payment cannot be posted twice
- [x] Reverse clears IRCUB + mock FMIS so payments can be re-posted cleanly
- [x] IRCUB vs FMIS reconciliation by GL code/day with transaction drill-down
- [x] Reconcile rehydrates mock FMIS from IRCUB `POSTED` batches (survives Redis/`cache:clear`)
- [x] Full traceability: payment → journal line → FMIS reference
- [x] Frontend: **FMIS Journals** (batches, GL mappings, reconciliation tabs)
- [x] Verified with `backend/scripts/verify_module6.php` (13/13) + HTTP API checks (matched totals after reverse/repost and after `cache:clear`)

### Module 7 — Dashboard with Predictive Analytics — Done & verified

- [x] Revenue trends over time by revenue type and channel (line charts)
- [x] Collections vs month/quarter targets; water billed vs collected (efficiency %)
- [x] Next-quarter forecast via ordinary least squares (OLS) linear regression on monthly totals
- [x] Alerts for collection drops, reversal spikes, and channel failures (+ all-clear)
- [x] Pre-aggregated `dashboard_daily_aggregates` + short Redis cache for performance
- [x] Async UI updates via 30s polling (`/api/dashboard/alerts`)
- [x] Frontend **Executive Dashboard** replaces Dompet demo home (`/dashboard`)
- [x] Verified with `backend/scripts/verify_module7.php` (12/12) + HTTP API checks

### Deploy & polish — In progress

- [x] ERD — see [`docs/ERD.md`](docs/ERD.md)
- [x] OpenAPI/Swagger + Postman — see [`docs/API.md`](docs/API.md), UI at `/docs/api`
- [x] Automated tests (PHPUnit + module smoke scripts + security/TOTP/performance) — see [Testing](#testing)
- [x] Security hardening (headers, throttle, cookie auth, TOTP, taxpayer scope)
- [x] Performance hardening (indexes, queues, scheduler, cache meta, lazy UI)
- [x] Docker Compose **full app profile** (`api` + `queue` + `scheduler` + `web` on `:8080`)
- [ ] Contabo VPS deploy + HTTPS on `ircub.waagefaal.so`

---

## Documentation

| Doc | Path |
|---|---|
| Technology stack notes | [`docs/STACK.md`](docs/STACK.md) |
| Entity Relationship Diagram | [`docs/ERD.md`](docs/ERD.md) |
| API (OpenAPI + Postman) | [`docs/API.md`](docs/API.md) · [`docs/openapi.yaml`](docs/openapi.yaml) |
| Swagger UI (local) | http://127.0.0.1:8001/docs/api |
| Testing guide | [`docs/TESTING.md`](docs/TESTING.md) · [Testing](#testing) in README |
| Docker (compose profiles) | [`docker/README.md`](docker/README.md) |

---

## Local setup

### Prerequisites

- Docker Desktop
- PHP 8.3+ with `pdo_pgsql`
- Composer
- Node.js 20+ / npm

### 1. Start infrastructure (Postgres + Redis)

```bash
docker compose up -d
# Optional admin UIs (not started by default):
docker compose --profile tools up -d
```

| Service | Host port | Notes |
|---|---|---|
| PostgreSQL | **5433** | Always on with `docker compose up -d` |
| Redis | **6379** | Cache, queues, sessions |
| pgAdmin | **5050** | `--profile tools` only · http://localhost:5050 |
| Redis Insight | **5540** | `--profile tools` only · http://localhost:5540 |

**pgAdmin login:** `admin@example.com` / password from `PGADMIN_DEFAULT_PASSWORD` (default `change-me-pgadmin`).

When adding a Postgres server in pgAdmin: host `postgres`, port `5432`, db/user/pass `ircub` / `ircub` / `ircub_secret`.

### 2. Backend API (local PHP)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8001
```

API base URL: `http://127.0.0.1:8001/api`  
Also start workers for queues/scheduler (see [Performance notes](#performance-notes-poc-scale)).

### 3. Frontend (local Vite)

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

Open **http://localhost:5173** (use `localhost`, not `127.0.0.1`, so the HttpOnly auth cookie stays same-site with the API host you configure in `.env`).

### 4. Full stack in Docker (optional)

Builds API + Redis queue worker + scheduler + Nginx SPA (proxies `/api`).  
Full notes: [`docker/README.md`](docker/README.md).

```bash
# From Project/
cp docker/.env.app.example docker/.env.app
# Set APP_KEY + CHANNEL_CALLBACK_SECRET (>=32 chars, not a mock default)
docker compose --profile app up -d --build
```

| URL | Service |
|---|---|
| http://localhost:8080 | UI + `/api` proxy (`web`) |
| http://localhost:8080/api | Laravel API |
| http://localhost:8080/docs/api | Swagger (when `IRCUB_DOCS_ENABLED=true`) |

Containers: `ircub-api`, `ircub-queue`, `ircub-scheduler`, `ircub-web` (+ postgres/redis).

Stop:

```bash
docker compose --profile app down
```

---

## Testing

Details: [`docs/TESTING.md`](docs/TESTING.md).

### A) PHPUnit (isolated, no Docker required)

Uses in-memory SQLite (`phpunit.xml`). Covers auth, **cookie auth + TOTP**, **security hardening**, **performance**, payers, FMIS, dashboard, gaps, API docs.

```bash
cd backend
composer install
php artisan test
```

Focused suites (re-run after security / performance changes):

```bash
php artisan test --filter="SecurityHardeningTest|CookieAuthAndTotpTest|TotpTest"
php artisan test --filter=PerformanceHardeningTest
```

| Suite | Last green |
|---|---|
| Full PHPUnit | **44 tests, 192 assertions** |
| Security + cookie/TOTP | **20 tests, 70 assertions** |
| Performance | **4 tests, 15 assertions** |

### B) Module smoke scripts (needs Postgres + Redis)

```bash
docker compose up -d   # if not already running
cd backend
php scripts/verify_module5.php   # channel FX / callback / retries / recon
php scripts/verify_module6.php   # FMIS posting / reverse / recon
php scripts/verify_module7.php   # dashboard aggregates / OLS / alerts / cache
php scripts/verify_gaps.php      # SoD reversals, 2FA flag, taxpayer link
```

**Last run:** Module 5 10/10 · Module 6 13/13 · Module 7 12/12 · Gaps 6/6.

### Run everything

Windows (PowerShell):

```powershell
cd backend
.\scripts\run_all_tests.ps1
```

macOS / Linux:

```bash
cd backend
bash scripts/run_all_tests.sh
```

Runs full PHPUnit → security focus → performance focus → module smokes + gaps.

---

## Demo users

Initial seed password for all accounts: **`Password@123`** (forced change outside `testing`; re-seed does **not** reset changed passwords)

| Role | Email | Notes |
|---|---|---|
| System Administrator | `admin@ircub.test` | Full access |
| Revenue Supervisor | `supervisor@ircub.test` | Approvals, FMIS, dashboard |
| Revenue Officer | `officer@ircub.test` | Assessments & payments |
| Water Billing Officer | `water@ircub.test` | Metering & bills |
| Auditor | `auditor@ircub.test` | Optional 2FA (TOTP or local stub) |
| Taxpayer / Customer | `taxpayer@ircub.test` | Own bills/assessments only (no capture) |

---

## Auth API

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `POST` | `/api/auth/login` | No (throttled) | Encrypted HttpOnly `ircub_token` cookie; Bearer only if `X-IRCUB-Return-Token: 1` |
| `GET` | `/api/auth/me` | Bearer **or** cookie | Current user profile |
| `POST` | `/api/auth/logout` | Yes | Revoke token + expire cookie |
| `PUT` | `/api/auth/password` | Yes | Change password / clear must-change flag |
| `POST` | `/api/auth/2fa/setup` | Yes | Generate TOTP secret + `otpauth://` URL |
| `POST` | `/api/auth/2fa/confirm` | Yes | Confirm TOTP with a code |
| `POST` | `/api/auth/2fa/disable` | Yes | Disable 2FA |

Example login (cookie session for the SPA):

```bash
curl -X POST http://localhost:8001/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -c cookies.txt \
  -d "{\"email\":\"admin@ircub.test\",\"password\":\"Password@123\"}"
```

API client that also needs the Bearer token in JSON:

```bash
curl -X POST http://localhost:8001/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-IRCUB-Return-Token: 1" \
  -d "{\"email\":\"admin@ircub.test\",\"password\":\"Password@123\"}"
```

Cookie-only session check:

```bash
curl http://localhost:8001/api/auth/me -b cookies.txt -H "Accept: application/json"
```

---

## Module 6 — FMIS API (summary)

Requires a supervisor/admin token (`fmis.post` / `fmis.reconcile`).

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/gl-mappings` | Revenue code → GL mapping |
| `GET` | `/api/fmis/batches` | List journal batches |
| `POST` | `/api/fmis/batches` | Create daily batch (`journal_date`, optional `post_immediately`) |
| `POST` | `/api/fmis/batches/{id}/post` | Post PENDING batch to mock FMIS |
| `POST` | `/api/fmis/batches/{id}/reverse` | Reverse POSTED batch |
| `GET` | `/api/fmis/reconciliation?date=YYYY-MM-DD` | IRCUB vs FMIS totals by GL + drill-down |

Mock FMIS provider (for demos / local inspection):

| Method | Endpoint |
|---|---|
| `POST` | `/mock-api/fmis/journals` |
| `GET` | `/mock-api/fmis/journals?date=YYYY-MM-DD` |
| `POST` | `/mock-api/fmis/journals/reverse` |

Smoke check: `php scripts/verify_module6.php` (see [Testing](#testing)).

UI: login as `supervisor@ircub.test` → **FMIS Journals**.

---

## Module 7 — Dashboard API (summary)

Requires `dashboard.view` (admin, supervisor, officer, water officer, auditor).

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/dashboard` | Full snapshot: KPIs, trends, targets, water, OLS forecast, alerts |
| `GET` | `/api/dashboard/alerts` | Lightweight poll endpoint for alerts + KPIs |
| `POST` | `/api/dashboard/refresh` | Rebuild daily aggregates from payments |

Smoke check: `php scripts/verify_module7.php` (see [Testing](#testing)).

UI: login as `admin@ircub.test` or `supervisor@ircub.test` → **Dashboard**.

---

## Performance notes (POC scale)

Credible posture for the brief’s “high-volume / real-time” language:

| Area | What we did |
|---|---|
| Dashboard | Pre-aggregated `dashboard_daily_aggregates` + Redis snapshot cache; API returns `meta.cache.hit` / TTL / driver |
| Lists | Server-side pagination with hard cap (`per_page` max 100) on payments, assessments, payers, channels, FMIS, audit |
| Indexes | Composite indexes on payments / assessments / audit for common filter+sort paths |
| Queues | Redis queues: channel retries (`channels`), FMIS daily batch (`fmis`), bill SMS/email notify (`notifications`), dashboard rebuild (`dashboard`) |
| Scheduler | `routes/console.php`: channel retries every minute; FMIS daily 01:15; dashboard rebuild 01:45 |
| Frontend | Route-level `React.lazy` + ApexCharts loaded only on the dashboard |

Local workers:

```bash
# Terminal A — API
php artisan serve --host=127.0.0.1 --port=8001

# Terminal B — queue worker (Redis)
php artisan queue:work redis --queue=channels,fmis,notifications,dashboard,default

# Terminal C — scheduler (optional)
php artisan schedule:work
```

Channel retries API: `POST /api/channel/retries` queues on Redis, or processes inline when `QUEUE_CONNECTION=sync` / `?sync=1`.

---

## Security notes (POC)

| Control | Behaviour |
|---|---|
| Auth cookie | Encrypted HttpOnly `ircub_token` (legacy base64 cookies rejected) |
| Bearer opt-in | Header `X-IRCUB-Return-Token: 1` only (query/body `return_token` ignored) |
| Cookie CSRF | Cookie-auth mutating requests require trusted Origin/Referer |
| Active users | Deactivated accounts lose token access (`active` middleware) |
| Role / payer / 2FA | Role, payer link, or admin 2FA clear revokes Sanctum tokens |
| Password gate | Seeded users must change password outside `testing`; blocks business APIs |
| Taxpayer | No payment capture; own-scope on bills/assessments |
| Unlinked payments | Cap via `IRCUB_MAX_UNLINKED_PAYMENT` (channel path uses USD after FX) |
| Sensitive payloads | Channel audit rows strip provider blobs at write; list APIs omit `national_id` |
| Channel callback | HMAC + throttle; SUCCESS requires matching `amount` |
| Simulate / mocks | Simulate forced off outside local/testing; mock adapters fail closed without allow flags |
| Callback secret | ≥32 chars required outside PHPUnit; Docker entrypoint generates if missing |
| Errors | Non-debug responses hide internal exceptions (`SafeHttpError`) |
| Docker host | Postgres/Redis/admin UIs bound to `127.0.0.1`; Redis `requirepass` |
| Headers | API CSP `default-src 'none'`; Docker SPA CSP without `unsafe-eval` |
| Docs | Swagger off by default in compose; seed password not printed in OpenAPI |
| Throttle | Login, callback, password/2FA, payment/meter CSV upload, channel retries |

---

## Assumptions & limitations

- The brief allows a short take-home window; work is organized and named by **module**, not by calendar day.
- Banks, mobile money, SMS, FX rates, and FMIS are simulated with mock services.
- Mock FMIS journal state lives in Redis cache; reconciliation rebuilds the day index from IRCUB `POSTED` batches when needed.
- Dashboard forecast uses OLS on monthly totals (transparent POC model); not a production time-series suite.
- Dashboard “real-time” updates use short-interval polling rather than WebSockets.
- Only sandbox / test data is used — no real personal, taxpayer, or financial data.
- **TOTP** is the real 2FA path; stub OTP `123456` is **local/testing only** (`IRCUB_2FA_ALLOW_STUB`).
- Live frontend routes are IRCUB-only.
- Docker: infra by default; `--profile tools` for admin UIs; `--profile app` full stack on `:8080`. Contabo HTTPS is still the final deploy step.
- Mock channel/FMIS adapters are POC-only (`CHANNEL_ALLOW_MOCK` / `FMIS_ALLOW_MOCK`); fail closed outside local/testing.
- Bill notifications are queued (`NotifyWaterBillJob`); with `QUEUE_CONNECTION=sync` they still run inline for tests/demo.

---

## AI assistance declaration

AI coding assistants (including Cursor) were used during scaffolding and implementation of this POC.

- All submitted code can be explained by the candidate line by line.
- Commit history is kept intentional and feature-based for review.

---

## License / third-party UI

- Application code in this repository is submitted for the Farsight Africa evaluation.
- The Dompet React admin UI is used under an Envato Elements license for this evaluation project and is not redistributed as a standalone commercial template.
