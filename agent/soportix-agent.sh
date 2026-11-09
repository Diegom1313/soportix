#!/usr/bin/env bash
set -euo pipefail

: "${SOPORTIX_URL:?Define SOPORTIX_URL}"
: "${SOPORTIX_KEY:?Define SOPORTIX_KEY}"

case "$SOPORTIX_URL" in
  https://*) ;;
  *) [ "${SOPORTIX_ALLOW_HTTP:-0}" = "1" ] || { echo "SOPORTIX_URL debe usar HTTPS" >&2; exit 1; } ;;
esac

json_escape() { sed -e 's/\\/\\\\/g' -e 's/"/\\"/g' | tr -d '\000-\037'; }

os=$( (. /etc/os-release 2>/dev/null && printf '%s' "${PRETTY_NAME:-}") || uname -sr)
[ -n "$os" ] || os=$(uname -sr)
cpu=$(awk -F': ' '/model name/{print $2; exit}' /proc/cpuinfo 2>/dev/null || true)
[ -n "$cpu" ] || cpu=$(uname -m)
ram_mb=$(awk '/MemTotal/{print int($2/1024)}' /proc/meminfo)
read -r disk_total_gb disk_used_pct < <(df -BG --output=size,pcent / | awk 'NR==2{gsub(/[G%]/,""); print $1, $2}')
ip=$(hostname -I 2>/dev/null | awk '{print $1}' || true)

software=""
if command -v dpkg-query >/dev/null 2>&1; then
  software=$(dpkg-query -W -f='${binary:Package} ${Version}\n' 2>/dev/null | awk 'NR<=2000')
elif command -v rpm >/dev/null 2>&1; then
  software=$(rpm -qa 2>/dev/null | awk 'NR<=2000')
fi
software_json=$(printf '%s\n' "$software" | sed '/^$/d' | cut -c1-200 | json_escape | awk 'BEGIN{ORS=""; s=""} {print s "\"" $0 "\""; s=","}')

payload=$(mktemp)
trap 'rm -f "$payload"' EXIT
cat > "$payload" <<JSON
{"os":"$(printf '%s' "$os" | json_escape)","cpu":"$(printf '%s' "$cpu" | json_escape)","ip":"$ip","ram_mb":$ram_mb,"disk_total_gb":$disk_total_gb,"disk_used_pct":$disk_used_pct,"software":[$software_json]}
JSON

printf 'header = "X-Agent-Key: %s"\n' "$SOPORTIX_KEY" | curl --config - --fail-with-body --silent --show-error --max-time 30 \
  -H 'Content-Type: application/json' \
  --data-binary "@$payload" "${SOPORTIX_URL%/}/api/agent/report"
echo
