#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
archivo=${1:-$(ls -1t "${BACKUP_DIR:-backups}"/soportix-*.dump | head -n 1)}
nombre="soportix-restore-$$"
trap 'docker rm -f "$nombre" >/dev/null 2>&1 || true' EXIT
docker run -d --name "$nombre" -e POSTGRES_PASSWORD=temporal postgres:16-alpine >/dev/null
until docker exec "$nombre" pg_isready -U postgres >/dev/null 2>&1; do sleep 1; done
sleep 2
docker exec "$nombre" createdb -U postgres soportix
docker exec "$nombre" psql -q -U postgres -c 'CREATE ROLE soportix' >/dev/null
docker exec -i "$nombre" pg_restore -U postgres -d soportix --exit-on-error < "$archivo"
for tabla in users tickets devices; do
  echo "$tabla: $(docker exec "$nombre" psql -tA -U postgres -d soportix -c "SELECT COUNT(*) FROM $tabla")"
done
echo "Restauración correcta: $archivo"
