# IRCUB — Integrated Revenue Collection & Utility Billing Platform

Farsight Africa Technologies — Software Developer POC (take-home).

## Stack

| Layer | Technology |
|---|---|
| Frontend | React 18 + Vite (Dompet admin template) |
| Backend | Laravel (API) |
| Database | PostgreSQL |
| Cache / queues | Redis |
| Deployment | Docker Compose → Contabo VPS |

## Project layout

```
Project/
├── frontend/     # Dompet React + Vite admin UI
├── backend/      # Laravel API
├── docker/       # Nginx and other Docker configs
├── docs/         # ERD, Dompet docs, notes
├── scripts/      # Helper scripts
└── docker-compose.yml
```

## Local quick start

### 1. Infrastructure

```bash
docker compose up -d
```

Starts PostgreSQL on host port **5433** and Redis on **6379**.

### 2. Backend API

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8001
```

API: `http://127.0.0.1:8001/api`

### 3. Frontend

```bash
cd frontend
npm install
npm run dev
```

Open the URL Vite prints (usually `http://localhost:5173`).

### Demo users (password for all: `Password@123`)

| Role | Email |
|---|---|
| System Administrator | admin@ircub.test |
| Revenue Supervisor | supervisor@ircub.test |
| Revenue Officer | officer@ircub.test |
| Water Billing Officer | water@ircub.test |
| Auditor | auditor@ircub.test |
| Taxpayer / Customer | taxpayer@ircub.test |

## Docker (Postgres + Redis + app)

```bash
docker compose up -d
```

See `docker-compose.yml` for services: `postgres`, `redis`, `backend`, `frontend`, `nginx`.

## Assumptions (POC)

- External banks, mobile money, SMS, FX rates, and FMIS are mocked.
- Demo/sandbox data only — no real taxpayer or financial data.
- AI coding assistants used during development will be declared in the submission README.

## Candidate

Yusuf Mohamed Ahmed
