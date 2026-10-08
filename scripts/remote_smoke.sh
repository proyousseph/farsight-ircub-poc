#!/usr/bin/env bash
set -euo pipefail

echo "SPA: $(curl -s -o /dev/null -w '%{http_code}' https://ircub.waagefaal.so/)"
echo "ME:  $(curl -s -o /dev/null -w '%{http_code}' -H 'Accept: application/json' https://ircub.waagefaal.so/api/auth/me)"

LOGIN=$(curl -s -c /tmp/ircub.ck -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -X POST https://ircub.waagefaal.so/api/auth/login \
  -d '{"email":"admin@ircub.test","password":"Password@123"}')
echo "LOGIN keys: $(echo "$LOGIN" | python3 -c 'import sys,json; d=json.load(sys.stdin); print(sorted(d.keys()))')"

ME=$(curl -s -b /tmp/ircub.ck -H 'Accept: application/json' https://ircub.waagefaal.so/api/auth/me)
echo "ME email: $(echo "$ME" | python3 -c 'import sys,json; d=json.load(sys.stdin); print(d.get("user",d).get("email", d))')"

echo "SSI: $(curl -s -o /dev/null -w '%{http_code}' https://161.97.90.92/)"
docker ps --format 'table {{.Names}}\t{{.Status}}' | head -20
