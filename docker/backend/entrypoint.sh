#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

echo "[ircub] waiting for postgres ${DB_HOST:-postgres}:${DB_PORT:-5432}..."
for i in $(seq 1 60); do
  if php -r '
    $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST") ?: "postgres", getenv("DB_PORT") ?: "5432", getenv("DB_DATABASE") ?: "ircub");
    try { new PDO($dsn, getenv("DB_USERNAME") ?: "ircub", getenv("DB_PASSWORD") ?: "ircub_secret"); exit(0); }
    catch (Throwable $e) { exit(1); }
  '; then
    echo "[ircub] database ready"
    break
  fi
  sleep 2
  if [ "$i" -eq 60 ]; then
    echo "[ircub] database not ready" >&2
    exit 1
  fi
done

if [ -z "${APP_KEY:-}" ]; then
  echo "[ircub] APP_KEY missing — generating ephemeral key (set APP_KEY in docker/.env.app for stable cookies/sessions)"
  export APP_KEY="$(php artisan key:generate --force --show)"
fi

php artisan config:clear >/dev/null 2>&1 || true

# Only the API container should migrate/seed by default
if [ "${IRCUB_RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force
fi
if [ "${IRCUB_SEED_ON_BOOT:-false}" = "true" ]; then
  php artisan db:seed --force
fi

php artisan storage:link >/dev/null 2>&1 || true

exec "$@"
