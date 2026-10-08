#!/usr/bin/env bash
set -euo pipefail

SSI=/opt/ssiWebsite
IRCUB=/opt/ircub
CONF_SRC="$IRCUB/docker/nginx/ircub.waagefaal.so.conf"
CONF_DST="$SSI/nginx.ircub.conf"

# Ensure IRCUB nginx snippet is present on host
cp "$CONF_SRC" "$CONF_DST"

# Patch SSI compose prod overlay to mount IRCUB conf into proxy
PROD="$SSI/docker-compose.prod.yml"
if ! grep -q 'nginx.ircub.conf' "$PROD"; then
  # Insert volume mount under proxy.volumes
  python3 - <<'PY'
from pathlib import Path
p = Path("/opt/ssiWebsite/docker-compose.prod.yml")
text = p.read_text()
needle = "- ./nginx.ssl.conf:/etc/nginx/conf.d/default.conf:ro"
insert = needle + "\n      - ./nginx.ircub.conf:/etc/nginx/conf.d/ircub.conf:ro"
if "nginx.ircub.conf" not in text:
    if needle not in text:
        raise SystemExit("Could not find nginx.ssl.conf mount in docker-compose.prod.yml")
    p.write_text(text.replace(needle, insert, 1))
    print("Patched docker-compose.prod.yml")
else:
    print("docker-compose.prod.yml already patched")
PY
fi

# Recreate proxy with new mount (keeps SSI online briefly)
cd "$SSI"
export COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml
docker compose up -d proxy

# Ensure ircub-web is on SSI network
docker network connect ssiwebsite_default ircub-web 2>/dev/null || true

# Issue cert (HTTP-01 via existing ACME location on port 80)
certbot certonly --webroot -w /var/www/certbot \
  -d ircub.waagefaal.so \
  --non-interactive --agree-tos \
  -m admin@waagefaal.so \
  --keep-until-expiring

# Validate nginx config inside proxy and reload
docker compose exec -T proxy nginx -t
docker compose exec -T proxy nginx -s reload

echo "Proxy wired. Testing..."
curl -sI -o /dev/null -w "https_ircub=%{http_code}\n" --resolve ircub.waagefaal.so:443:127.0.0.1 https://ircub.waagefaal.so/ || true
curl -s -o /dev/null -w "https_api_me=%{http_code}\n" --resolve ircub.waagefaal.so:443:127.0.0.1 https://ircub.waagefaal.so/api/auth/me || true
curl -sI -o /dev/null -w "ssi_still=%{http_code}\n" https://161.97.90.92/ || true
