#!/usr/bin/env bash
set -euo pipefail

PASSWORD=clave-de-integracion-ci
cat > .env <<V
DOMINIO=localhost
POSTGRES_PASSWORD=$(openssl rand -hex 16)
METRICS_TOKEN=$(openssl rand -hex 16)
GRAFANA_PASSWORD=$(openssl rand -hex 16)
ADMIN_EMAIL=admin@ci.test
ADMIN_PASSWORD=$PASSWORD
V
docker compose up -d --build
sleep 8
docker compose cp caddy:/data/caddy/pki/authorities/local/root.crt /tmp/caddy.crt || { sleep 10; docker compose cp caddy:/data/caddy/pki/authorities/local/root.crt /tmp/caddy.crt; }
export CURL_CA_BUNDLE=/tmp/caddy.crt
timeout 180 sh -c 'until curl -fs https://localhost/api/health >/dev/null; do sleep 3; done'

test "$(curl -s -o /dev/null -w '%{http_code}' https://localhost/metrics)" = "404"
curl -sI https://localhost/ | grep -qi content-security-policy
ASSET=$(curl -s https://localhost/ | grep -o '/assets/[^"]*\.js' | head -n 1)
curl -sI "https://localhost$ASSET" | grep -qi x-content-type-options

TOKEN=$(curl -fs https://localhost/api/login -H 'Content-Type: application/json' -d "{\"email\":\"admin@ci.test\",\"password\":\"$PASSWORD\"}" | jq -r .token)
KEY=$(curl -fs https://localhost/api/devices -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{"hostname":"LINUX-CI"}' | jq -r .agent_key)
SOPORTIX_URL=https://localhost SOPORTIX_KEY=$KEY ./agent/soportix-agent.sh
test "$(curl -fs https://localhost/api/stats -H "Authorization: Bearer $TOKEN" | jq .devices)" = "1"

mkdir -p backups
./ops/backup.sh
test "$(stat -c '%a' backups/soportix-*.dump)" = "600"
./ops/restore-test.sh | tee /tmp/restore
grep -q 'devices: 1' /tmp/restore

for _ in $(seq 1 30); do
  VALOR=$(docker compose exec -T prometheus wget -qO- 'http://localhost:9090/api/v1/query?query=soportix_devices' | jq -r '.data.result[0].value[1] // empty')
  [ "$VALOR" = "1" ] && break
  sleep 5
done
test "$VALOR" = "1"
docker compose exec -T grafana wget -qO- http://localhost:3000/api/health | jq -e '.database == "ok"'
echo "Integración correcta"
