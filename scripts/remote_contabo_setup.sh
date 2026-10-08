#!/usr/bin/env bash
set -euo pipefail
cd /opt/ircub

APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
CHANNEL_CALLBACK_SECRET="$(openssl rand -hex 32)"
POSTGRES_PASSWORD="$(openssl rand -hex 24)"
REDIS_PASSWORD="$(openssl rand -hex 24)"

cp docker/.env.app.contabo.example .env
cp docker/.env.app.contabo.example docker/.env.app

fill() {
  local f="$1"
  sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" "$f"
  sed -i "s|^CHANNEL_CALLBACK_SECRET=.*|CHANNEL_CALLBACK_SECRET=${CHANNEL_CALLBACK_SECRET}|" "$f"
  sed -i "s|^POSTGRES_PASSWORD=.*|POSTGRES_PASSWORD=${POSTGRES_PASSWORD}|" "$f"
  sed -i "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=${REDIS_PASSWORD}|" "$f"
  sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${POSTGRES_PASSWORD}|" "$f"
}

fill .env
fill docker/.env.app

echo "Env keys present:"
grep -E '^(APP_ENV|APP_URL|APP_KEY|CHANNEL_CALLBACK_SECRET|POSTGRES_PASSWORD|REDIS_PASSWORD|DB_PASSWORD|IRCUB_SEED)=' .env \
  | sed -E 's/(APP_KEY|CHANNEL_CALLBACK_SECRET|POSTGRES_PASSWORD|REDIS_PASSWORD|DB_PASSWORD)=.*/\1=[set]/'

echo "Building IRCUB stack..."
docker compose --profile app -f docker-compose.yml -f docker-compose.contabo.yml up -d --build

echo "Compose status:"
docker compose --profile app -f docker-compose.yml -f docker-compose.contabo.yml ps
