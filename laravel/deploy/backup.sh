#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
: "${BACKUP_DIRECTORY:?Set an absolute encrypted/off-host mounted backup directory}"
case "$BACKUP_DIRECTORY" in /*) ;; *) echo 'Use an absolute backup directory' >&2; exit 1;; esac
mkdir -p "$BACKUP_DIRECTORY"
umask 077
name="meda-$(date -u +%Y%m%dT%H%M%SZ).dump"
partial="$BACKUP_DIRECTORY/$name.partial"
trap 'rm -f "$partial"' EXIT
docker compose --env-file .env.production -f compose.production.yaml exec -T db sh -c 'PGPASSWORD="$POSTGRES_PASSWORD" pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$partial"
test -s "$partial"
mv "$partial" "$BACKUP_DIRECTORY/$name"
sha256sum "$BACKUP_DIRECTORY/$name" > "$BACKUP_DIRECTORY/$name.sha256"
echo "Backup created: $name"
