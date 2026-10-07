# IRCUB — Integrated Revenue Collection & Utility Billing Platform

**Farsight Africa Technologies — Software Developer POC (take-home)**

Candidate: **Yusuf Mohamed Ahmed**  
Timeline: **5 working days** (not one module per day)

| Item | Link |
|---|---|
| Repository | https://github.com/proyousseph/farsight-ircub-poc |
| Demo (planned) | https://ircub.waagefaal.so |

> Demo subdomain DNS already points to the Contabo VPS (`161.97.90.92`). App deploy + Nginx mapping will follow once remaining modules are ready.

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
├── docs/                # Notes, ERD (upcoming)
├── scripts/             # Helper scripts
├── docker-compose.yml   # Postgres + Redis (+ pgAdmin / Redis Insight)
└── README.md
```

---

## 5-day take-home plan

The POC brief allows **5 working days**. Work is grouped by delivery days, not by “one document module = one day”.

| Day | Focus | Status |
|---|---|---|
| **1** | Project scaffold, Docker (Postgres/Redis), auth, roles & permissions, Dompet login + menus | Done |
| **2** | Taxpayer/customer registry + tax assessment & collection (control numbers, payments, CSV, audit, filters) | Done |
| **3** | Water utility billing (tariffs, readings, billing cycle, PDF/SMS mock, statements, abnormal holds) | Done |
| **4** | Payment channel integration (FX rates, bank/MM mocks, callbacks, retries, channel reconciliation UI) | Done |
| **5** | FMIS posting & reconciliation, executive dashboard + forecast, Contabo deploy to `ircub.waagefaal.so`, docs polish | Next |

---

## Module progress (POC checklist)

Progress below follows the **document modules**, independent of the day labels above.

### Module 1 — User & Role Management — Done

- [x] Docker Compose: PostgreSQL + Redis (+ pgAdmin / Redis Insight)
- [x] Laravel configured for Postgres / Redis
- [x] DB-driven roles & permissions
- [x] Six system roles seeded with permissions
- [x] Sanctum auth API: login / logout / me
- [x] Permission middleware
- [x] Dompet login wired to Laravel API
- [x] Role-based sidebar menus

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

### Module 6 — FMIS Posting & Reconciliation — Planned (Day 5)

- [ ] Revenue type → GL mapping (extend existing)
- [ ] Daily journal batches (Pending / Posted / Failed / Reversed)
- [ ] Mock FMIS post + FMIS reference; prevent double-posting
- [ ] IRCUB vs FMIS reconciliation screen with drill-down

### Module 7 — Dashboard with Predictive Analytics — Planned (Day 5)

- [ ] Revenue trends by type and channel
- [ ] Collections vs targets; water billed vs collected
- [ ] Next-quarter revenue forecast (linear regression or better)
- [ ] Alerts for unusual activity
- [ ] Performance-minded aggregation / async updates where practical

### Deploy & polish — Planned (Day 5)

- [ ] Dockerized / Nginx deploy to Contabo
- [ ] HTTPS demo on `ircub.waagefaal.so`
- [ ] ERD, OpenAPI/Postman notes, and README final pass

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

## Assumptions & limitations

- The take-home window is **5 working days**; modules are batched across those days.
- Banks, mobile money, SMS, FX rates, and FMIS are simulated with mock services.
- Only sandbox / test data is used — no real personal, taxpayer, or financial data.
- Optional 2FA is planned as a configurable stub, not a full production MFA product.
- Frontend still contains Dompet demo pages that will be replaced or hidden as remaining IRCUB screens are finished.
- Hosted HTTPS demo on Contabo is planned for the final day once FMIS/dashboard are in place.

---

## AI assistance declaration

AI coding assistants (including Cursor) were used during scaffolding and implementation of this POC.

- All submitted code can be explained by the candidate line by line.
- Commit history is kept intentional and feature-based for review.

---

## License / third-party UI

- Application code in this repository is submitted for the Farsight Africa evaluation.
- The Dompet React admin UI is used under an Envato Elements license for this evaluation project and is not redistributed as a standalone commercial template.
