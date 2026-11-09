#!/usr/bin/env bash
set -euo pipefail
umask 077
cd "$(dirname "$0")/.."
destino=${BACKUP_DIR:-backups}
dias=${BACKUP_DAYS:-14}
mkdir -p "$destino"
archivo="$destino/soportix-$(date -u +%Y%m%dT%H%M%SZ).dump"
docker compose exec -T db pg_dump -U soportix -Fc soportix > "$archivo.tmp"
mv "$archivo.tmp" "$archivo"
find "$destino" -name 'soportix-*.dump' -mtime +"$dias" -delete
echo "$archivo"
