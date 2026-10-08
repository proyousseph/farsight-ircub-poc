#!/usr/bin/env bash
set -euo pipefail
cd /opt/ircub

for f in .env docker/.env.app; do
  sed -i 's/^IRCUB_SEED_ON_BOOT=.*/IRCUB_SEED_ON_BOOT=false/' "$f"
done

grep '^IRCUB_SEED_ON_BOOT=' .env docker/.env.app

docker compose --profile app -f docker-compose.yml -f docker-compose.contabo.yml up -d api
sleep 5
docker logs ircub-api --tail 15
curl -s -o /dev/null -w 'me=%{http_code}\n' -H 'Accept: application/json' https://ircub.waagefaal.so/api/auth/me
