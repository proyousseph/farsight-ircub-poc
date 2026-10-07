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

## Local quick start (frontend only)

```bash
cd frontend
npm install
npm run dev
```

Open the URL Vite prints (usually `http://localhost:5173`).

## Local quick start (backend API)

```bash
cd backend
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan serve
```

API will be at `http://127.0.0.1:8000`.

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
