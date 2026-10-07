# IRCUB — Integrated Revenue Collection & Utility Billing Platform

**Farsight Africa Technologies — Software Developer POC (take-home)**

Candidate: **Yusuf Mohamed Ahmed**  
Scope: **Modules 1–7** (POC brief calendar window: 5 working days)

| Item | Link |
|---|---|
| Repository | https://github.com/proyousseph/farsight-ircub-poc |
| Demo (planned) | https://ircub.waagefaal.so |

> Demo subdomain DNS already points to the Contabo VPS (`161.97.90.92`). **Next:** Dockerized app + Nginx HTTPS deploy to Contabo.

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
├── frontend/            # Dompet React + Vite admin UI
├── backend/             # Laravel API
├── docker/              # Nginx / deploy configs (to be expanded)
├── docs/                # STACK.md, ERD.md
├── scripts/             # Helper scripts
├── docker-compose.yml   # Postgres + Redis (+ pgAdmin / Redis Insight)
└── README.md
```

---

## Module progress (POC checklist)

Progress follows the **document modules** (1–7). The brief’s take-home window is five working days; delivery is tracked by module, not by calendar day.

### Module 1 — User & Role Management — Done

- [x] Docker Compose: PostgreSQL + Redis (+ pgAdmin / Redis Insight)
- [x] Laravel configured for Postgres / Redis
- [x] DB-driven roles & permissions
- [x] Six system roles seeded with permissions
- [x] Hierarchical roles (`parent_id` / `level`; child permissions ⊆ parent)
- [x] System configuration API + UI (`config.manage` — password, 2FA, water threshold, channel retries)
- [x] Sanctum auth API: login / logout / me
- [x] Password policy (min 10 + upper/lower/number/symbol) on user create/update (admin-tunable)
- [x] Optional 2FA stub (demo OTP `123456`; enabled on `auditor@ircub.test`)
- [x] Users & Roles admin API + UI (custom roles, activate/deactivate users)
- [x] Payment reversal segregation of duties (request ≠ approve)
- [x] Audit log browser API + UI
- [x] Taxpayer self-service scoped to linked `payer_id`
- [x] Permission middleware
- [x] Dompet login wired to Laravel API
- [x] Role-based sidebar menus (IRCUB routes only — unused Dompet demo pages removed from the live app)

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
- [x] Automated tests (PHPUnit + module smoke scripts) — see [Testing](#testing)
- [x] README pass for Module 1 polish gaps (SoD, 2FA stub, admin/audit/self-service)
- [ ] Dockerized / Nginx deploy to Contabo
- [ ] HTTPS demo on `ircub.waagefaal.so`

---

## Documentation

| Doc | Path |
|---|---|
| Technology stack notes | [`docs/STACK.md`](docs/STACK.md) |
| Entity Relationship Diagram | [`docs/ERD.md`](docs/ERD.md) |
| API (OpenAPI + Postman) | [`docs/API.md`](docs/API.md) · [`docs/openapi.yaml`](docs/openapi.yaml) |
| Swagger UI (local) | http://127.0.0.1:8001/docs/api |
| Testing guide | [`docs/TESTING.md`](docs/TESTING.md) · [Testing](#testing) in README |

---

## Local setup

### Prerequisites

- Docker Desktop
- PHP 8.3+ with `pdo_pgsql`
- Composer
- Node.js 20+ / npm

### 1. Start infrastructure

```bash
docker compose up -d
```

| Service | Host port | Notes |
|---|---|---|
| PostgreSQL | **5433** | Mapped away from local Postgres on 5432 |
| Redis | **6379** | Cache, queues, sessions |
| pgAdmin | **5050** | http://localhost:5050 |
| Redis Insight | **5540** | http://localhost:5540 |

**pgAdmin login:** `admin@example.com` / `admin123`

When adding a Postgres server in pgAdmin:

| Field | Value |
|---|---|
| Host | `postgres` (Docker service name) |
| Port | `5432` |
| Database | `ircub` |
| Username | `ircub` |
| Password | `ircub_secret` |

In Redis Insight, add database host `redis`, port `6379`.

### 2. Backend API

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8001
```

API base URL: `http://127.0.0.1:8001/api`

### 3. Frontend

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

Open the URL Vite prints (usually `http://localhost:5173`).

---

## Testing

Two layers of automated checks are included.

### A) PHPUnit (isolated, no Docker required)

Uses an in-memory SQLite database (`phpunit.xml`). Covers auth, payers, FMIS post/reverse/recon, dashboard forecast/alerts, and API docs routes.

```bash
cd backend
composer install
php artisan test
```

Or:

```bash
cd backend
composer test
```

**Last run:** 20 tests, 99 assertions — all passed.

### B) Module smoke scripts (needs Postgres + Redis)

These hit the real local `.env` database (start Docker first: `docker compose up -d`).

```bash
cd backend
php scripts/verify_module5.php   # channel FX / callback / retries / recon
php scripts/verify_module6.php   # FMIS posting / reverse / recon
php scripts/verify_module7.php   # dashboard aggregates / OLS / alerts
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

---

## Demo users

Password for all accounts: **`Password@123`**

| Role | Email |
|---|---|
| System Administrator | `admin@ircub.test` |
| Revenue Supervisor | `supervisor@ircub.test` |
| Revenue Officer | `officer@ircub.test` |
| Water Billing Officer | `water@ircub.test` |
| Auditor | `auditor@ircub.test` |
| Taxpayer / Customer | `taxpayer@ircub.test` |

---

## Auth API

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `POST` | `/api/auth/login` | No | Returns Bearer token + user roles/permissions |
| `GET` | `/api/auth/me` | Bearer | Current user profile |
| `POST` | `/api/auth/logout` | Bearer | Revoke current token |

Example login:

```bash
curl -X POST http://127.0.0.1:8001/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"email\":\"admin@ircub.test\",\"password\":\"Password@123\"}"
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

## Assumptions & limitations

- The brief allows a short take-home window; work is organized and named by **module**, not by calendar day.
- Banks, mobile money, SMS, FX rates, and FMIS are simulated with mock services.
- Mock FMIS journal state lives in Redis cache; reconciliation rebuilds the day index from IRCUB `POSTED` batches when needed.
- Dashboard forecast uses OLS on monthly totals (transparent POC model); not a production time-series suite.
- Dashboard “real-time” updates use short-interval polling rather than WebSockets.
- Only sandbox / test data is used — no real personal, taxpayer, or financial data.
- Optional 2FA is a **stub** (shared demo OTP), not a production TOTP/SMS product.
- Live frontend routes are IRCUB-only; unused Dompet template page sources were removed from the tree.
- Local `docker-compose.yml` currently runs Postgres + Redis. Full app containers + HTTPS on Contabo remain the last deploy item.

---

## AI assistance declaration

AI coding assistants (including Cursor) were used during scaffolding and implementation of this POC.

- All submitted code can be explained by the candidate line by line.
- Commit history is kept intentional and feature-based for review.

---

## License / third-party UI

- Application code in this repository is submitted for the Farsight Africa evaluation.
- The Dompet React admin UI is used under an Envato Elements license for this evaluation project and is not redistributed as a standalone commercial template.
