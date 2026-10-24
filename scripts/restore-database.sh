#!/bin/sh
set -eu

BACKUP_FILE="${1:-}"
test -n "$BACKUP_FILE" || { echo "Usage: $0 backups/file.sql"; exit 1; }
test -f "$BACKUP_FILE" || { echo "Backup not found: $BACKUP_FILE"; exit 1; }
test "${CONFIRM_RESTORE:-}" = "YES" || { echo "Set CONFIRM_RESTORE=YES to overwrite the current database."; exit 1; }
COMPOSE_FILE="${COMPOSE_FILE:-compose.yaml}"
docker compose -f "$COMPOSE_FILE" exec -T mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < "$BACKUP_FILE"
echo "Database restored from: $BACKUP_FILE"
