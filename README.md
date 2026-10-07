# IRCUB — Integrated Revenue Collection & Utility Billing Platform

**Farsight Africa Technologies — Software Developer POC (take-home)**

Candidate: **Yusuf Mohamed Ahmed**

| Item | Link |
|---|---|
| Repository | https://github.com/proyousseph/farsight-ircub-poc |
| Demo (planned) | https://ircub.waagefaal.so |

> Demo subdomain DNS already points to the Contabo VPS (`161.97.90.92`). App deploy + Nginx mapping will follow once core modules are ready.

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
├── docker-compose.yml   # Postgres + Redis
└── README.md
```

---

## Current progress

### Done (Day 1 — User & Role Management)

- [x] Docker Compose: PostgreSQL + Redis
- [x] Laravel configured for Postgres / Redis
- [x] Roles & permissions schema (DB-driven)
- [x] Six system roles seeded with permissions
- [x] Sanctum auth API: login / logout / me
- [x] Permission middleware
- [x] Dompet login wired to Laravel API
- [x] Role-based sidebar menus
- [x] pgAdmin + Redis Insight for local inspection

### Done (Day 2 — Taxpayer & Customer Registry)

- [x] Payers, water accounts, and revenue obligations schema
- [x] Duplicate detection (phone / email / national ID) with force-create flag
- [x] Payer API: list, create, show (360° profile), update
- [x] Demo seed data (including intentional duplicate phone)
- [x] Frontend: payer list, register form, profile page

### Done (Day 3 — Tax Revenue Assessment & Collection)

- [x] Revenue types with GL codes
- [x] Assessments with unique control numbers
- [x] Payment capture linked to assessments
- [x] CSV bulk payment upload with accept/reject summary
- [x] Audit logs for create/update/upload actions
- [x] Filters on assessments and payments
- [x] Frontend Assessments + Payments pages
- [x] Payer 360° profile shows assessments/payments/balance

### Next

- [ ] Water utility billing
- [ ] Payment channel mocks + retries
- [ ] FMIS posting & reconciliation
- [ ] Executive dashboard + forecast
- [ ] Dockerized app deploy to `ircub.waagefaal.so`

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
| pgAdmin | **5050** | Browse Postgres — http://localhost:5050 |
| Redis Insight | **5540** | Browse Redis — http://localhost:5540 |

**pgAdmin login:** `admin@example.com` / `admin123`  

When adding a Postgres server in pgAdmin use:

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

## Auth API (available now)

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

- Banks, mobile money, SMS, FX rates, and FMIS are simulated with mock services.
- Only sandbox / test data is used — no real personal, taxpayer, or financial data.
- Optional 2FA is planned as a configurable stub, not a full production MFA product.
- Frontend still contains Dompet demo pages that will be replaced or hidden as IRCUB modules are built.
- Hosted HTTPS demo on Contabo will be enabled after core modules are deployable.

---

## AI assistance declaration

AI coding assistants (including Cursor) were used during scaffolding and implementation of this POC.

- All submitted code can be explained by the candidate line by line.
- Commit history is kept intentional and feature-based for review.

---

## License / third-party UI

- Application code in this repository is submitted for the Farsight Africa evaluation.
- The Dompet React admin UI is used under an Envato Elements license for this evaluation project and is not redistributed as a standalone commercial template.
